# [PTV AG For Developer](https://www.ptvgroup.com/en/solutions/products/ptv-xserver/)

### Get token to access PTV APIs (if you have an API key skip this)
  
### Getting Geocodes for Source and Destination from PTV API
* Use the following code to call ArcGIS API to fetch the geocode of the locations
```php

function getCord($address){

$url = 'https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rest/XLocate/locations/'.rawurlencode($address).'';

//connecting to ptv...
$ptv = curl_init();

curl_setopt($ptv, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ptv, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ptv, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Accept-Charset: utf-8',
    'Authorization: Basic <Authorization key>'
  ));
curl_setopt($ptv, CURLOPT_URL, $url);
curl_setopt($ptv, CURLOPT_RETURNTRANSFER, true);

//getting response from ptvapi...
$response = curl_exec($ptv);
$err = curl_error($ptv);

curl_close($ptv);

if ($err) {
	  echo "cURL Error #:" . $err;
} else {
	  echo "200 : OK\n";
}

//extracting the JSON response..
$data = json_decode($response, true);

$location = array(
	'x' => $data['results']['0']['location']['referenceCoordinate']['y'], //lat
    'y' => $data['results']['0']['location']['referenceCoordinate']['x'] //long
);
// print_r($location);
return $location;
 }

```
With this in place, make a POST request: https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rs/XRoute/experimental/calculateRoute

#### Step 4: Extracting polyline from PTV using Source-Destination Geocodes

With `CURLOPT_POSTFIELDS` send the following body

```php

//connecting to ptv...
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rs/XRoute/experimental/calculateRoute',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>'{
  "waypoints": [
    {
      "$type": "OffRoadWaypoint",
      "location": {
        "offRoadCoordinate": {
          "x": -75.16819953918458,
          "y": 39.940310674179216
        }
      }
    },
    {
      "$type": "OffRoadWaypoint",
      "location": {
        "offRoadCoordinate": {
          "x": -87.64683365821838,
          "y": 41.86265761100228
        }
      }
    }
  ],
  "resultFields": {
    "nodes": true,
    "polyline": true,
    "segments": {
      "enabled": true,
      "polyline": true
    },
    "legs": {
      "enabled": true,
      "polyline": true,
      "tollSummary": true
    },
    "toll": {
      "enabled": true,
      "sections": true,
      "systems": true
    },
    "eventTypes": [
      "TOLL_EVENT"
    ]
  },
  "routeOptions": {
    "tollOptions": {
      "useDetailedToll": true
    }
  },
  "requestProfile": {
    "routingProfile": {
      "course": {
        "toll": {
          "tollPenalty": 0
        }
      }
    },
    "vehicleProfile": {
      "electronicTollCollectionSubscriptions": "US_OHIO_EZPASS",
      "axle":{
        "numberOfAxles": 2
      }
    }
  }
}',
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Accept-Charset: utf-8',
    'Authorization: Basic <Authorization key>'
  ),
));

$response = curl_exec($curl);

curl_close($curl);

```

### Note:
* You should see full path as series of coordinates which we are storing in `$poly`, we convert it to
`polyline`
* Code to get the `polyline` can be found at https://github.com/emcconville/google-map-polyline-encoding-tool


```php

$poly = $data['legs']['0']['polyline']['plain']['polyline'];

$revPoly = array();
foreach ($poly as $i) {
  array_push($revPoly, $i['y']);
  array_push($revPoly, $i['x']);
}

//creating polyline...
require_once(__DIR__.'/Polyline.php');
$p_ptv = Polyline::encode($revPoly);

```

Note:

We extracted the polyline for a route from PTV AG Routing API.

We need to send this route polyline to TollGuru API to receive toll information

## [TollGuru API](https://tollguru.com/developers/docs/)

### Get key to access TollGuru polyline API
* create a dev account to receive a [free key from TollGuru](https://tollguru.com/developers/get-api-key)
* suggest adding `vehicleType` parameter. Tolls for cars are different than trucks and therefore if `vehicleType` is not specified, may not receive accurate tolls. For example, tolls are generally higher for trucks than cars. If `vehicleType` is not specified, by default tolls are returned for 2-axle cars. 
* Similarly, `departure_time` is important for locations where tolls change based on time-of-the-day.
* Use the following code to get rates from TollGuru.

```php

//using tollguru API...
$curl = curl_init();

curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);


$postdata = array(
  "source" => "here",
  "polyline" => $p_ptv
);

//json encoding source and polyline to send as postfields..
$encode_postData = json_encode($postdata);

curl_setopt_array($curl, array(
CURLOPT_URL => "https://dev.tollguru.com/v1/calc/route",
CURLOPT_RETURNTRANSFER => true,
CURLOPT_ENCODING => "",
CURLOPT_MAXREDIRS => 10,
CURLOPT_TIMEOUT => 0,
CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
CURLOPT_CUSTOMREQUEST => "POST",


//sending ptv polyline to tollguru
CURLOPT_POSTFIELDS => $encode_postData,
CURLOPT_HTTPHEADER => array(
              "content-type: application/json",
              "x-api-key: tollguru_api_key"),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
    echo "200 : OK\n";
}

//response from tollguru..
$data = json_decode($response, true);

```

The working code can be found in `php_curl_ptv.php` file.

## License
ISC License (ISC). Copyright 2020 &copy;TollGuru. https://tollguru.com/

Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby granted, provided that the above copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
