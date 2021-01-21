<?php
error_reporting(0);

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
$data = json_decode($response, true);

$poly = $data['legs']['0']['polyline']['plain']['polyline'];

$revPoly = array();
foreach ($poly as $i) {
  array_push($revPoly, $i['y']);
  array_push($revPoly, $i['x']);
}

//creating polyline...
require_once(__DIR__.'/Polyline.php');
$p_ptv = Polyline::encode($revPoly);

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
print_r($data['route']['costs']);
?>