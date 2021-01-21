<?php
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
?>