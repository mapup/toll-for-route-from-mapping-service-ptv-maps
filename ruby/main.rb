require 'HTTParty'
require 'json'
require "fast_polylines"
require 'cgi'


username = ENV['username']
password = ENV['password']
b64 = Base64.strict_encode64("#{username}:#{password}")

$ptv_headers = {'Content-Type' => 'application/json', 'Authorization' => "Basic #{b64}"}

# GET Source and Destination Coordinates from geocoding API
def get_geocode(loc)
	ptv_geocoding_url = "https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rest/XLocate/locations/#{CGI::escape(loc)}"
	geocoding_response = HTTParty.get(ptv_geocoding_url,:headers => $ptv_headers)
	geocode_parsed = (JSON.parse(geocoding_response.body)['results'])[0]['location']['referenceCoordinate']
	return geocode_parsed
end
source = get_geocode(source)
destination = get_geocode(destination)

# POST Request to PTV Server
ptv_url = "https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rs/XRoute/experimental/calculateRoute"
ptv_body = {"storedProfile" => "car.xml", "waypoints" => [ { "$type" => "OffRoadWaypoint", "location" => { "offRoadCoordinate" => source } }, { "$type" => "OffRoadWaypoint", "location" => { "offRoadCoordinate" => destination } } ], "resultFields" => { "nodes" => true, "polyline" => true, "segments" => { "enabled" => true, "polyline" => true }, "legs" => { "enabled" => true, "polyline" => true, "tollSummary" => true }, "toll" => { "enabled" => true, "sections" => true, "systems" => true }, "eventTypes" => [ "TOLL_EVENT" ] }, "routeOptions" => { "timeConsideration" => { "$type" => "ExactTimeConsiderationAtStart", "referenceTime" => "2021-01-15T13:46:17" }, "tollOptions" => { "useDetailedToll" => true } }, "requestProfile" => { "routingProfile" => { "course" => { "toll" => { "tollPenalty" => 0 } } } } }
response = HTTParty.post(ptv_url, :body => ptv_body.to_json ,:headers => $ptv_headers)
json_parsed = JSON.parse(response.body)

# Extracting PTV polyline from JSON. HERE coordinates are encoded to google polyline
ptv_coordinates_array = json_parsed['legs'].map { |x| x['polyline']['plain']['polyline'] }.pop.map {|item| [item["y"],item["x"]]}
google_encoded_polyline = FastPolylines.encode(ptv_coordinates_array)

# Sending POST request to TollGuru
tollguru_url = 'https://dev.tollguru.com/v1/calc/route'
tollguru_key = ENV['TOLLGURU_KEY']
headers = {'content-type' => 'application/json', 'x-api-key' => tollguru_key}
body = {'source' => "mapbox", 'polyline' => google_encoded_polyline, 'vehicleType' => "2AxlesAuto", 'departure_time' => "2021-01-05T09:46:08Z"}
tollguru_response = HTTParty.post(tollguru_url,:body => body.to_json, :headers => headers)


