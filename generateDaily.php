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
     */

    $data = array(); 


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
    echo json_encode($data, JSON_PRETTY_PRINT);
    file_put_contents(__DIR__.'/all.json', json_encode($data, JSON_PRETTY_PRINT));
?>