<?php
error_reporting(0);

$PTV_API_KEY = getenv('PTV_API_KEY');
$PTV_API_URL = "https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rs/XRoute/experimental/calculateRoute";

$TOLLGURU_API_KEY = getenv('TOLLGURU_API_KEY');
$TOLLGURU_API_URL = "https://apis.tollguru.com/toll/v2";
$POLYLINE_ENDPOINT = "complete-polyline-from-mapping-service";

//calling helper files...
require_once(__DIR__.'/test_location.php');
require_once(__DIR__.'/get_lat_long.php');
foreach ($locdata as $item) {
echo "QUERY: {FROM: ".$item['from'].'}{TO: '.$item['to'].'}';
echo "\n";
$source = getCord($item['from']);
$source_longitude = $source['y'];
$source_latitude = $source['x'];
$destination = getCord($item['to']);
$destination_longitude = $destination['y'];
$destination_latitude = $destination['x'];

// Explore https://tollguru.com/toll-api-docs to get the best of all the parameters that Tollguru has to offer
$request_parameters = array(
  "vehicle" => array(
      "type" => "2AxlesAuto"
  ),
  // Visit https://en.wikipedia.org/wiki/Unix_time to know the time format
  "departure_time" => "2021-01-05T09:46:08Z"
);

//connecting to ptv...
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => $PTV_API_URL,
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
          "x": '.$source_longitude.',
          "y": '.$source_latitude.'
        }
      }
    },
    {
      "$type": "OffRoadWaypoint",
      "location": {
        "offRoadCoordinate": {
          "x": '.$destination_longitude.',
          "y": '.$destination_latitude.'
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
    "timeConsideration": {
      "$type": "ExactTimeConsiderationAtStart",
      "referenceTime": "2021-01-15T13:46:17"
    },
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
    'Authorization: Basic ' . $PTV_API_KEY
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


echo "*************RATES FROM PTV***************\n";
print_r($data['toll']['summary']['costs']);
$ptvCost = $data['toll']['summary']['costs']['0']['amount'];
$ptvCur = $data['toll']['summary']['costs']['0']['currency'];

//creating polyline...
require_once(__DIR__.'/Polyline.php');
$p_ptv = Polyline::encode($revPoly);

// echo $p_ptv;

//using tollguru API..
$curl = curl_init();

curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$postdata = array(
  "source" => "here",
  "polyline" => $p_ptv,
  ...$request_parameters,
);

//json encoding source and polyline to send as postfields...
$encode_postData = json_encode($postdata);

curl_setopt_array($curl, array(
  CURLOPT_URL => $TOLLGURU_API_URL . "/" . $POLYLINE_ENDPOINT,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 300,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",


  //sending ptv polyline to tollguru..
  CURLOPT_POSTFIELDS => $encode_postData,
  CURLOPT_HTTPHEADER => array(
    "content-type: application/json",
    "x-api-key: " . $TOLLGURU_API_KEY),
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
echo "*************RATES FROM TOLLGURU USING PTV POLYLINE***************\n";
print_r($data['route']['costs']);
echo "**************************************************************************\n";

$tag = $data['route']['costs']['tag'];
$cash = $data['route']['costs']['cash'];

//dumping into text file along with polyline...
$dumpFile = fopen("dump.txt", "a") or die("unable to open file!");
fwrite($dumpFile, "from =>");
fwrite($dumpFile, $item['from'].PHP_EOL);
fwrite($dumpFile, "to =>");
fwrite($dumpFile, $item['to'].PHP_EOL);
fwrite($dumpFile, "*************POLYLINE FROM PTV***************".PHP_EOL);
fwrite($dumpFile, "polyline =>".PHP_EOL);
fwrite($dumpFile, $p_ptv.PHP_EOL);
fwrite($dumpFile, "*************RATES FROM PTV***************".PHP_EOL);
fwrite($dumpFile, "cost =>");
fwrite($dumpFile, $ptvCost.PHP_EOL);
fwrite($dumpFile, "currency =>");
fwrite($dumpFile, $ptvCur.PHP_EOL);
fwrite($dumpFile, "*************RATES FROM TOLLGURU USING PTV POLYLINE***************".PHP_EOL);
fwrite($dumpFile, "tag =>");
fwrite($dumpFile, $tag.PHP_EOL);
fwrite($dumpFile, "cash =>");
fwrite($dumpFile, $cash.PHP_EOL);
fwrite($dumpFile, "*************************************************************************".PHP_EOL);


//dumping in csv file...
$dumpFile = fopen("final.csv", "a") or die("unable to open file!");
fwrite($dumpFile, $item['from']);
fwrite($dumpFile, ",");
fwrite($dumpFile, $item['to']);
fwrite($dumpFile, ",");
fwrite($dumpFile, $ptvCur);
fwrite($dumpFile, ",");
fwrite($dumpFile, $ptvCost);
fwrite($dumpFile, ",");
fwrite($dumpFile, $tag);
fwrite($dumpFile, ",");
fwrite($dumpFile, $cash.PHP_EOL);
}
?>
