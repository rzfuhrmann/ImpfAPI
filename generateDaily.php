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

    require_once __DIR__ . '/vendor/autoload.php';

    $data = array();

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
        
        if (!isset($data[$date])) $data[$date] = array(); 
        if (!isset($data[$date][$bundesland])) $data[$date][$bundesland] = array(); 

        $metric = str_replace("rkixlsx_", "", $row["name"]);
        $impfstoff = "~~ total ~~";
        if (preg_match("~_(biontech|moderna|astrazeneca)$~", $metric, $matches)){
            $impfstoff = $matches[1];
            $metric = preg_replace("~_".$impfstoff."$~", "", $metric);

        }
        
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
                    "SH" => "Schleswig-Holtstein",
                    "SN" => "Sachsen",
                    "ST" => "Sachsen-Anhalt",
                    "TH" => "Thüringen",
                );
                $bundesland = $bl_short_to_long[$bl_short]; 
                if (!isset($data[$date])) $data[$date] = array(); 
                if (!isset($data[$date][$bundesland])) $data[$date][$bundesland] = array(); 
                if (!isset($data[$date][$bundesland][$cols[1]])) $data[$date][$bundesland][$cols[1]] = array(); 
                $data[$date][$bundesland][$cols[1]]["dosen_geliefert"] = (int)$cols[3];
            }
            
        }
    }
    // echo json_encode($data, JSON_PRETTY_PRINT);
    $data = array(
        "meta" => array(
            "name" => "Dataset: Impffortschritt Deutschland",
            "description" => "Please share your feedback and inconsistencies with us so that we can improve this dataset - thank you!\n\nWe try our best, but we provide this data \"proxy\" without any warranty in any kind.",
            "version" => "2021-03-05-".str_replace("\n", "", `git rev-parse --short HEAD`),
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
            "ts" => date("Y-m-d H:i:s"),
            "contact" => array(
                "email" => "impfapi@rz-fuhrmann.de",
                "github" => "https://github.com/rzfuhrmann/ImpfAPI"
            )
        ),
        "data" => $data
    );
    file_put_contents(__DIR__.'/data/all_object.json', json_encode($data, JSON_PRETTY_PRINT));

    // upload
    $ul = `scp data/all*.json impfapi.rz-fuhrmann.de:/var/www/impfapi.rz-fuhrmann.de/v0/`;
?>