# ImpfAPI
Script to provide COVID-19 vaccination data and everything around it in one single big dataset for Germany on a daily basis (or even faster if data sources supports that). 

## Access latest data

Data is automatically refreshed at least once per day, typically each 1-2 hours. 
| Endpoint | Format | Description | URL |
| ------- | ------ | ----------- | --- |
| v0      | JSON Objects | All data in a JSON structure according to the hierarchy: Day/Date => State => Vaccine  | https://impfapi.rz-fuhrmann.de/v0/all_object.json |
| v0      | JSON List | All data in a JSON structure, but one-dimensional, thus easy convertible in everything table-related. | https://impfapi.rz-fuhrmann.de/v0/all_list.json |
| v0      | InfluxDB Export | :warning: Different measurement names, but easy to import into an own InfluxDB instance | https://impfapi.rz-fuhrmann.de/v0/all_influx.7z |
| v0      | TSV Export | All data as a combined TSV list to be used in Excel etc. | https://impfapi.rz-fuhrmann.de/v0/all_list.tsv |

We'll add live data refresh and more parameters soon in v1. Please let us know your feedback and wishes that should be added to the datasets at [impfapi@rz-fuhrmann.de](mailto:impfapi@rz-fuhrmann.de).

## Roadmap
* [x] cleanup namings (e.g. "Schleswig-Holtstein"/"Schleswig-Holstein", "astra"/"astrazeneca", "Bayern*")
* [ ] cleanup value names
* [ ] align cumulative/non-cumulative values
* [ ] add live requests / "real" API, not just static files, to request specific data
* [ ] add caching
* [ ] align different data sources to have similar naming on different breakdowns...
* [ ] add time ranges to data source definition (not each data source covers the same data)
* [X] different format: _object + list!

... more Ideas? Let me know: [impfapi@rz-fuhrmann.de](mailto:impfapi@rz-fuhrmann.de)

## Datasources
We're using various data sources to generate that combined dataset. Some values are extrapolated, others are aligned with the rest (e.g. have a cumulative field for each normal value and vice-versa) to have a consistent set of values that are comparable. 

| Datasource | Description | Provider | Earliest available data | URL |
| --- | --- | --- | --- | --- |
| Impfquotenmonitoring (XLSX) | Daily report provided by the RKI, unfortunately without historic data. | Robert-Koch-Institut, rki.de | 2020-01-23 | https://www.rki.de/DE/Content/InfAZ/N/Neuartiges_Coronavirus/Daten/Impfquotenmonitoring.xlsx |
| Vaccine deliveries (TSV) | time series of vaccine deliveries by BMG | Bundesministerium f??r Gesundheit (BMG), impfdashboard.de | 2020-12-27 | https://impfdashboard.de/static/data/germany_deliveries_timeseries_v2.tsv |
| Vaccinations (TSV) | time series of vaccinations by BMG | Bundesministerium f??r Gesundheit (BMG), impfdashboard.de | 2020-12-27 | https://impfdashboard.de/static/data/germany_vaccinations_timeseries_v2.tsv |
| Appointment Availability | :warning: Only includes appointment data for _some_ vaccination centers, therefore not included in the JSONs, but in InfluxDB export. | impfterminservice.de / [monitoring script](https://github.com/rzfuhrmann/PHPImpftermine) | 2020-02-01 | https://github.com/rzfuhrmann/PHPImpftermine |
| RKI COVID19 | | Hub RKI | 2020-01-27 | https://npgeo-corona-npgeo-de.hub.arcgis.com/datasets/dd4580c810204019a7b8eb3e0b329dd6_0/ |