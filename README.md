# ImpfAPI
Script to provide vaccine data and everything around it in one single dataset on a daily basis (or even faster if data sources supports that ;)). 

## Access latest data

Data is automatically refreshed at least once per day, typically each 1-2 hours. 
| Endpoint | Format | Description | URL |
| ------- | ------ | ----------- | --- |
| v0      | JSON Objects | All data in a JSON structure according to the hierarchy: Day/Date => State => Vaccine  | https://impfapi.rz-fuhrmann.de/v0/all_object.json |
| v0      | JSON List | All data in a JSON structure, but one-dimensional, thus easy convertible in everything table-related. | https://impfapi.rz-fuhrmann.de/v0/all_list.json |
| v0      | InfluxDB Export | :warning: Different measurement names, but easy to import into an own InfluxDB instance | https://impfapi.rz-fuhrmann.de/v0/all_influx.7z |
| v0      | TSV Export | All data as a combined TSV list to be used in Excel etc. | https://impfapi.rz-fuhrmann.de/v0/all_list.tsv |

We'll add live data refresh and more parameters soon in v1. Please let us know your feedback and wishes that should be added to the datasets at [impfapi@rz-fuhrmann.de](mailto:impfapi@rz-fuhrmann.de).