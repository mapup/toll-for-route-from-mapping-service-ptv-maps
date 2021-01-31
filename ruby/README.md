# [PTV AG For Developer](https://www.ptvgroup.com/en/solutions/products/ptv-xserver/)

### Get token to access PTV APIs (if you have an API token skip this)
#### Step 1: Login/Signup
* go to signup/login link https://www.ptvgroup.com/en/solutions/products/ptv-xserver/customer-area/ptv-xserver-api-version-2/ and create an account
#### Step 2: Creating a token
* You will be presented with a default token.

To get the route polyline make a POST request on https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rs/XRoute/experimental/calculateRoute with encoded username and password as header parameters

### Note:
* PTV accepts source and destination, as a Hash. We will use geocoding API to convert location to latitude-longitude pair

```ruby
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

```

Note:

We extracted the polyline for a route from Mapbox API

We need to send this route polyline to TollGuru API to receive toll information

## [TollGuru API](https://tollguru.com/developers/docs/)

### Get key to access TollGuru polyline API
* create a dev account to receive a free key from TollGuru https://tollguru.com/developers/get-api-key
* suggest adding `vehicleType` parameter. Tolls for cars are different than trucks and therefore if `vehicleType` is not specified, may not receive accurate tolls. For example, tolls are generally higher for trucks than cars. If `vehicleType` is not specified, by default tolls are returned for 2-axle cars. 
* Similarly, `departure_time` is important for locations where tolls change based on time-of-the-day.

the last line can be changed to following
```ruby

TOLLGURU_URL = 'https://dev.tollguru.com/v1/calc/route'
TOLLGURU_KEY = ENV['TOLLGURU_KEY']
headers = {'content-type' => 'application/json', 'x-api-key' => TOLLGURU_KEY}
body = {'source' => "mapbox", 'polyline' => mapbox_polyline, 'vehicleType' => "2AxlesAuto", 'departure_time' => "2021-01-05T09:46:08Z"}
tollguru_response = HTTParty.post(TOLLGURU_URL,:body => body.to_json, :headers => headers)
```


Whole working code can be found in main.rb file.
