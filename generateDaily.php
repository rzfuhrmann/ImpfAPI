<?php
    /**
     * quick-n-dirty script to provide vaccine data asap on a daily basis
     * Request latest data at https://impfapi.rz-fuhrmann.de/v0/all.json
     * 
     * Data Sources: 
     * - Vaccine deliveries: https://impfdashboard.de/static/data/germany_deliveries_timeseries_v2.tsv
     * - Vaccinations: https://impfdashboard.de/static/data/germany_vaccinations_timeseries_v2.tsv
     * 
     * @author      Sebastian Fuhrmann <sebastian.fuhrmann@rz-fuhrmann.de>
     * @copyright   (C) 2020-2021 Rechenzentrum Fuhrmann Inh. Sebastian Fuhrmann
     * @license     MIT
     * 
     * TODOs:
     * - cleanup, especially the lookup tables... 
     * - add caching
     * - add live requests
     * - align different data sources to have similar naming on different breakdowns...
     * - add time ranges to data source definition (not each data source covers the same data)
     * - different format: _object + list!
     */    

    $data = array();

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // MAPPINGS /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    $vaccineMappings = array(
        "astra" => "astrazeneca",   // use long name everywhere
        "comirnaty" => "biontech"   // they use company name for all other vacciones, why not for Biontech?
    ); 

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // CASES RKI ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    $offset = 0; 
    $recordsPerQuery = 5000; 
    do {
        $json = json_decode(file_get_contents("https://services7.arcgis.com/mOBPykOjAyBO2ZKk/arcgis/rest/services/RKI_COVID19/FeatureServer/0/query?where=1%3D1&outFields=*&returnDistinctValues=true&outSR=4326&f=json&resultRecordCount=".$recordsPerQuery."&resultOffset=".$offset), true);
        foreach ($json["features"] as $feature){
            $counts = $feature["attributes"]; 

            $date = date("Y-m-d", floor($counts["Meldedatum"]/1000));
            $bundesland = $counts["Bundesland"];

            if (!isset($data[$date])) $data[$date] = array(); 
            if (!isset($data[$date][$bundesland])) $data[$date][$bundesland] = array(); 
            $impfstoff = "~~ total ~~";
            if (!isset($data[$date][$bundesland][$impfstoff])) $data[$date][$bundesland][$impfstoff] = array(); 

            $metrics = array(
                "cases_count" => "AnzahlFall",
                "cases_recovered" => "AnzahlGenesen",
                "cases_dead" => "AnzahlTodesfall",

                "cases_count_new" => "NeuerFall",
                "cases_recovered_new" => "NeuGenesen",
                "cases_dead_new" => "NeuerTodesfall",
            );

            foreach ($metrics as $metric => $rkimetric){
                if (!isset($data[$date][$bundesland][$impfstoff][$metric])) $data[$date][$bundesland][$impfstoff][$metric] = 0; 

                $data[$date][$bundesland][$impfstoff][$metric] += $counts[$rkimetric];
            }
        }
        $offset += $recordsPerQuery;
    } while (isset($json["exceededTransferLimit"]) && $json["exceededTransferLimit"]); 


    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // IMPFQUOTENMONITORING XLSX ////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // RKI doesn't provide their Excel on a historic basis, therefore getting the data from our InfluxDB...
    $csv = `influx -database 'covid_impfungen' -host 'localhost' -port '8086' -execute 'select * FROM /^rkixlsx/;' -format csv`;
    //$csv = str_replace("\n","\r\n", $csv);
    $csv = str_getcsv($csv, "\n");
    foreach ($csv as &$row) $row = str_getcsv($row); 
    array_walk($csv, function(&$a) use ($csv) {
        $a = array_combine($csv[0], $a);
    });
    array_shift($csv);

    foreach ($csv as $r => $row){
        if (!preg_match("~^rkixlsx_~", $row["name"])) continue; 
        
        $date = date("Y-m-d", floor($row["time"]/1000000000));

        $bundesland = $row["bundesland"];

        // They had things like "*" in the data.. 
        if (preg_match("~\*~", $bundesland)) continue; 
        
        if (!isset($data[$date])) $data[$date] = array(); 
        if (!isset($data[$date][$bundesland])) $data[$date][$bundesland] = array(); 

        $metric = str_replace("rkixlsx_", "", $row["name"]);
        $impfstoff = "~~ total ~~";
        if (preg_match("~_(biontech|moderna|astrazeneca)$~", $metric, $matches)){
            $impfstoff = $matches[1];
            $metric = preg_replace("~_".$impfstoff."$~", "", $metric);

        }

        // clean up naming
        if (isset($vaccineMappings[strtolower($impfstoff)])) $impfstoff = $vaccineMappings[strtolower($impfstoff)];
        
        if (!isset($data[$date][$bundesland][$impfstoff])) $data[$date][$bundesland][$impfstoff] = array(); 

        
        $data[$date][$bundesland][$impfstoff][$metric] = (double)$row["value"];
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // VACCINCATIONS ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $tsv = file_get_contents("https://impfdashboard.de/static/data/germany_vaccinations_timeseries_v2.tsv");
    $lines = explode("\n", $tsv); 

    $headers = array(); 
    $points = array(); 
    foreach ($lines as $l => $line){
        $cols = explode("\t", $line);
        if ($l == 0){
            $headers = $cols; 
        } else {
            if (sizeof($cols) == sizeof($headers)){
                $date = $cols[0];
                $bundesland = "~~ total ~~";
                for ($c = 1; $c < sizeof($cols); $c++){
                    $impfstoff = "~~ total ~~";
                    if (preg_match("~_(biontech|moderna|astrazeneca)_~", $headers[$c], $matches)){
                        $impfstoff = $matches[1];
                    }
                    // clean up naming
                    if (isset($vaccineMappings[strtolower($impfstoff)])) $impfstoff = $vaccineMappings[strtolower($impfstoff)];

                    if (!isset($data[$date])) $data[$date] = array(); 
                    if (!isset($data[$date][$bundesland])) $data[$date][$bundesland] = array(); 
                    if (!isset($data[$date][$bundesland][$impfstoff])) $data[$date][$bundesland][$impfstoff] = array(); 
                    $metric = str_replace("_".$impfstoff."_", "_", $headers[$c]);
                    $data[$date][$bundesland][$impfstoff][$metric] = (double)$cols[$c];
                }
            }
            
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Deliveries ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $tsv = file_get_contents("https://impfdashboard.de/static/data/germany_deliveries_timeseries_v2.tsv");
    $lines = explode("\n", $tsv); 

    $headers = array(); 
    $points = array(); 
    foreach ($lines as $l => $line){
        $cols = explode("\t", $line);
        if ($l == 0){
            $headers = $cols; 
        } else {
            if (sizeof($cols) == sizeof($headers)){
                $date = $cols[0];
                $bl = explode("-", $cols[2]);
                $bl_short = $bl[1];
                $bl_short_to_long = array(
                    "BW" => "Baden-Württemberg",
                    "BY" => "Bayern",
                    "BE" => "Berlin",
                    "BB" => "Brandenburg",
                    "HB" => "Bremen",
                    "HH" => "Hamburg",
                    "HE" => "Hessen",
                    "MV" => "Mecklenburg-Vorpommern",
                    "NI" => "Niedersachsen",
                    "NW" => "Nordrhein-Westfalen",
                    "RP" => "Rheinland-Pfalz",
                    "SL" => "Saarland",
                    "SH" => "Schleswig-Holstein",
                    "SN" => "Sachsen",
                    "ST" => "Sachsen-Anhalt",
                    "TH" => "Thüringen",
                );
                $bundesland = $bl_short_to_long[$bl_short]; 
                if (!isset($data[$date])) $data[$date] = array(); 
                if (!isset($data[$date][$bundesland])) $data[$date][$bundesland] = array(); 

                $impfstoff = $cols[1];
                // clean up naming
                if (isset($vaccineMappings[strtolower($impfstoff)])) $impfstoff = $vaccineMappings[strtolower($impfstoff)];

                if (!isset($data[$date][$bundesland][$impfstoff])) $data[$date][$bundesland][$impfstoff] = array(); 
                $data[$date][$bundesland][$impfstoff]["dosen_geliefert"] = (int)$cols[3];
            }
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // CLEANUP //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // sort by date, just in case the data is mixed-up
    ksort($data);

    $propertynamesByDepth = array(); 
    function getPossibleValues($arr, $depth = 0){
        global $propertynamesByDepth; 
        if (!isset($propertynamesByDepth[$depth])) $propertynamesByDepth[$depth] = []; 

        foreach ($arr as $index => $value){
            if (!in_array($index, $propertynamesByDepth[$depth])){
                $propertynamesByDepth[$depth][] = $index; 
            }
            if (is_array($value)){
                getPossibleValues($value, $depth+1);
            }
        }
    }

    getPossibleValues($data); 

    $newdata = array();
    $depth_with_data = sizeof($propertynamesByDepth)-1; 

    function getValueByPath($data, $path){
        $value = array_shift($path); 

        if (isset($data[$value])){
            if (sizeof($path)){
                return getValueByPath($data[$value], $path);
            } else {
                return $data[$value];
            }
        }
        return false; 
    }
    $listdata = array(); 
    function addNeededValues (&$newdata, $path = []){
        global $propertynamesByDepth, $data, $listdata;

        $depth = sizeof($path); 
        $values = $propertynamesByDepth[$depth]; 
        sort($values);
        
        $newLine = []; 
        foreach ($values as $value){
            $newpath = $path; 
            $newpath[] = $value; 
            if ($depth == sizeof($propertynamesByDepth)-1){
                $newdata[$value] = null; 

                $val = getValueByPath($data, $newpath); 
                if ($val){
                    $newdata[$value] = $val; 
                }
                $newLine[] = $newdata[$value]; 
            } else {
                $newdata[$value] = array(); 
                addNeededValues($newdata[$value], $newpath);
            }
        }
        if ($newLine){
            $listdata[] = array_merge($path, $newLine);
        }
    }

    addNeededValues($newdata);


    /*$listdata = array(); 
    function fillListRow(&$listdata, $path = []){
        global $propertynamesByDepth;

        if (sizeof($path) == sizeof($path)){
            // values
        } else {
            fillListRow($listdata, )
        }
    }
    fillListRow($listdata);*/

    file_put_contents(__DIR__.'/cleaneddata.json', json_encode($newdata, JSON_PRETTY_PRINT));

    var_dump($propertynamesByDepth); 

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // OUTPUT ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $objectdata = array(
        "meta" => array(
            "name" => "Dataset: Impffortschritt Deutschland",
            "description" => "Please share your feedback and inconsistencies with us so that we can improve this dataset. No one is perfect, also not this dataset. So let us know anything we can improve / what doesn't make sense. We'll try to consider that feedback for v1. Thank you!\n\nWe try our best, but we provide this data \"proxy\" without any warranty of any kind.",
            "version" => "2021-03-09-".str_replace("\n", "", `git rev-parse --short HEAD`),
            "ts" => date("Y-m-d H:i:s"),
            "contact" => array(
                "email" => "impfapi@rz-fuhrmann.de",
                "github" => "https://github.com/rzfuhrmann/ImpfAPI"
            ),
            "changelog" => array(
                array(
                    "date" => "2021-03-05",
                    "description" => "Initial version."
                ),
                array(
                    "date" => "2021-03-09",
                    "description" => 
                        '- Cleanup: Fixed typos, therefore summarized a few values (e.g. "Schleswig-Holtstein"/"Schleswig-Holstein", "astra"/"astrazeneca")'."\n".
                        '- Cleanup: Aligned vaccine names, cleaned-up the mix of company names and vaccine names'
                ),
                array(
                    "date" => "2021-03-12",
                    "description" => "Add COVID19 RKI data."
                )
            ),
            "datasources" => array(
                array(
                    "name" => "Impfquotenmonitoring",
                    "description" => "",
                    "url" => "https://www.rki.de/DE/Content/InfAZ/N/Neuartiges_Coronavirus/Daten/Impfquotenmonitoring.xlsx",
                    "copyright" => "Robert-Koch-Institut, rki.de",
                    "lastpull" => date("Y-m-d H:i:s")
                ),
                array(
                    "name" => "Vaccine deliveries",
                    "description" => "",
                    "url" => "https://impfdashboard.de/static/data/germany_deliveries_timeseries_v2.tsv",
                    "copyright" => "Bundesministerium für Gesundheit (BMG)",
                    "lastpull" => date("Y-m-d H:i:s")
                ),
                array(
                    "name" => "Vaccinations",
                    "description" => "",
                    "url" => "https://impfdashboard.de/static/data/germany_vaccinations_timeseries_v2.tsv",
                    "copyright" => "Bundesministerium für Gesundheit (BMG)",
                    "lastpull" => date("Y-m-d H:i:s")
                )
            ),
        ),
        "data" => $data
    );
    file_put_contents(__DIR__.'/data/all_object.json', json_encode($objectdata, JSON_PRETTY_PRINT));

    $listoutput = $objectdata; 
    $listoutput["data"] = $listdata;
    file_put_contents(__DIR__.'/data/all_list.json', json_encode($listoutput, JSON_PRETTY_PRINT));

    $tsv = implode("\n", array_map(function ($entry) {
        return implode("\t", $entry);
    }, $listdata));
    file_put_contents(__DIR__.'/data/all_list.tsv', $tsv);

    // upload
    $ul = `scp data/all* impfapi.rz-fuhrmann.de:/var/www/impfapi.rz-fuhrmann.de/v0/`;

    // influxDB export
    $ul2 = `influxd backup -portable -retention autogen -database covid_impfungen all_influx.db && 7z a all_influx.7z all_influx.db && scp all_influx.7z impfapi.rz-fuhrmann.de:/var/www/impfapi.rz-fuhrmann.de/v0/ && rm -r all_influx.db && rm all_influx.7z`;
?>