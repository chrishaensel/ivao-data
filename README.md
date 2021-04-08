# IVAO-Data

The IVAO data library is a very simple PHP class to download IVAO whazzup data.

## IMPORTANT
This is **work in progress**! This is not the best code you will ever see. But it might help you. 
Good luck.

## IVAO API Information

You can find more information on the IVAO API and the retrievval of the whazzup data in the IVAO wiki:  [IVAO WIKI](https://wiki.ivao.aero) auf.

## Usage

1. Clone this repo or composer-require it `git clone https://...` or `composer require chrishaensel/ivao-data`
2. Create a new instance of the IVAO class ` $ivao = new \chrishaensel\Ivao("My app name v.1.1");`
3. Download the data `$ivao->downloadIvaoWhazzupData();` 

## Options

When instantiating the `Ivao` class, you **must** pass your application's name as the first parameter.
`$ivao = new \chrishaensel\Ivao("My app name v1.1")`, otherwise it will fail with an exception. IVAO requires you to pass your application name as `User Agent` string when downloading the data.

As second parameter, you can pass an `array` with other options.
Currently, the only supported option is `create_json`. When set to `1`, the library will create a `JSON` file of the ATC and PILOT data contained in the whazzupt.txt file. 

When calling the `downloadIvaoWhazzupData` method, you can pass a parameter (`string`) with the target path / filename for the whazzup data.
`$ivao->downloadIvaoWhazzupData("my_whazzup.txt"")` will cause the script to save the data in the file `my_whazzup.txt`.

## IVAO Rules

IVAO does have some rules regarding the download of whazzup data.

Most importantly: 
####  These files may only be obtained after an official clearance from the IVAO Development Operations Department! ###

- Always set your user agent to the name of your application, including the version. 
- The whazzup file may only be downloaded once **every 5 minutes**.
- The status.txt file may only be downloaded **once a day**
  
**IVAO-data** does take care of the timing - you need to ask for permission :)

### Some sample code

This way, you can use the Ivao class. We're using naspaces, so there won't be conflicts with other libraries you might use.

```php
require_once 'vendor/autoload.php';
use chrishaensel\Ivao;

$options = [
    "create_json" => 1,
    "json_file" => "whazzup.json"
];

$ivao = new Ivao("My best app ever v1.1", $options);
$ivao->downloadIvaoWhazzupData("my_whazzup.txt");
```

### Sample output of the JSON file

The whazzup data will be split in two parts within the JSON. 
"PILOT" and "ATC" - both are arrays of the connected parties.

```JSON 
{
  "PILOT": [
    {
      "callsign": "5RAED",
      "user": {
        "vid": "652248",
        "name": "652248",
        "rating": 3,
        "rating_decoded": "Flight Student (FS2)"
      },
      "client_type": "PILOT",
      "freq": "",
      "position": {
        "latitude": "-22.7559",
        "longitude": "47.8591",
        "altitude": "1929"
      },
      "flight_data": {
        "groundspeed": "179",
        "heading": "319",
        "on_ground": "0"
      },
      "flightplan": {
        "aircraft": "1\/M20P\/L-SDFGR\/C",
        "cruising_speed": "N0130",
        "origin": "FMSG",
        "cruising_level": "F080",
        "destination": "FMMI",
        "revision": "0",
        "flight_rules": "I",
        "dep_time": "1431",
        "actual_dep_time": "1431",
        "eet_hours": "2",
        "eet_minutes": "18",
        "endurance_hours": "4",
        "endurance_minutes": "25",
        "alternate_aerodrome": "FMMT",
        "remarks": "PBN\/D2 DOF\/210408 REG\/5RAED PER\/A RMK\/TCAS",
        "route": "FMSG FMSF FMME FMMA FMMI",
        "alternate_aerodrome2": "",
        "type_of_flight": "S",
        "persons_on_board": "2"
      },
      "server": "SHARD1",
      "protocol": "B",
      "combined_rating": "3",
      "transponder_code": "2000",
      "facility_type": "0",
      "visual_range": "50",
      "unused1": "",
      "unused2": "",
      "ATIS": "",
      "ATIS_time": "",
      "connection_time": "20210408140418",
      "connection_duration": "2:25:21",
      "software": {
        "name": "Altitude\/win",
        "version": "1.10.2b"
      },
      "plane": "1\/M20P\/L-SDFGR\/C"
    },
   },
   "ATC": [
   {
      "callsign": "EDDF_A_GND",
      "user": {
        "vid": "544029",
        "name": "544029",
        "rating": 4,
        "rating_decoded": "Advanced ATC Trainee - AS3"
      },
      "client_type": "ATC",
      "freq": "121.855",
      "position": {
        "latitude": "50.0333",
        "longitude": "8.57046",
        "altitude": "0"
      },
      "flight_data": {
        "groundspeed": "0",
        "heading": "",
        "on_ground": ""
      },
      "flightplan": {
        "aircraft": "",
        "cruising_speed": "",
        "origin": "",
        "cruising_level": "",
        "destination": "",
        "revision": "",
        "flight_rules": "",
        "dep_time": "",
        "actual_dep_time": "",
        "eet_hours": "",
        "eet_minutes": "",
        "endurance_hours": "",
        "endurance_minutes": "",
        "alternate_aerodrome": "",
        "remarks": "",
        "route": "",
        "alternate_aerodrome2": "",
        "type_of_flight": "",
        "persons_on_board": ""
      },
      "server": "SHARD3",
      "protocol": "B",
      "combined_rating": "4",
      "transponder_code": "0",
      "facility_type": "3",
      "visual_range": "10",
      "unused1": "",
      "unused2": "",
      "ATIS": "",
      "ATIS_time": "",
      "connection_time": "20210408141942",
      "connection_duration": "2:9:57",
      "software": {
        "name": "Aurora\/win",
        "version": "1.2.12b"
      },
      "plane": ""
    }
   ] 
```

## Questions? Remarks? 

- Contact me at [chris@haensel.pro](mailto:chris@haensel.pro). 
- Find my website at [https://haensel.pro](https://haensel.pro).
