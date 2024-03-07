require 'HTTParty'
require 'json'
require "fast_polylines"
require 'cgi'

PTV_USERNAME = ENV["PTV_USERNAME"]  # Username for PTV
PTV_PASSWORD = ENV["PTV_PASSWORD"]  # API password for PTV
PTV_API_URL = "https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rs/XRoute/experimental/calculateRoute"
PTV_GEOCODE_API_URL = (
    "https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rest/XLocate/locations"
)

TOLLGURU_API_KEY = os.environ.get("TOLLGURU_API_KEY")
TOLLGURU_API_URL = "https://apis.tollguru.com/toll/v2"
POLYLINE_ENDPOINT = "complete-polyline-from-mapping-service"

# Explore https://tollguru.com/toll-api-docs to get the best of all the parameters that tollguru has to offer
request_parameters = {
  "vehicle": {
    "type": "2AxlesAuto",
  },
  # Visit https://en.wikipedia.org/wiki/Unix_time to know the time format
  "departure_time": "2021-01-05T09:46:08Z",
}

def get_toll_rate(source,destination)

    b64 = Base64.strict_encode64("#{PTV_USERNAME}:#{PTV_PASSWORD}")

    $ptv_headers = {'Content-Type' => 'application/json', 'Authorization' => "Basic #{b64}"}

    def get_geocode(loc)
        ptv_geocoding_url = "#{PTV_GEOCODE_API_URL}/#{CGI::escape(loc)}"
        geocoding_response = HTTParty.get(ptv_geocoding_url,:headers => $ptv_headers)
        geocode_parsed = (JSON.parse(geocoding_response.body)['results'])[0]['location']['referenceCoordinate']
        return geocode_parsed
    end
    source = get_geocode(source)
    destination = get_geocode(destination)

    ptv_url = PTV_API_URL
    ptv_body = {"storedProfile" => "car.xml", "waypoints" => [ { "$type" => "OffRoadWaypoint", "location" => { "offRoadCoordinate" => source } }, { "$type" => "OffRoadWaypoint", "location" => { "offRoadCoordinate" => destination } } ], "resultFields" => { "nodes" => true, "polyline" => true, "segments" => { "enabled" => true, "polyline" => true }, "legs" => { "enabled" => true, "polyline" => true, "tollSummary" => true }, "toll" => { "enabled" => true, "sections" => true, "systems" => true }, "eventTypes" => [ "TOLL_EVENT" ] }, "routeOptions" => { "timeConsideration" => { "$type" => "ExactTimeConsiderationAtStart", "referenceTime" => "2021-01-15T13:46:17" }, "tollOptions" => { "useDetailedToll" => true } }, "requestProfile" => { "routingProfile" => { "course" => { "toll" => { "tollPenalty" => 0 } } } } }
   
    response = HTTParty.post(ptv_url, :body => ptv_body.to_json ,:headers => $ptv_headers)
    json_parsed = JSON.parse(response.body)

    ptv_tolls = json_parsed["toll"]["summary"]["costs"].map{|x| x["amount"]}.pop
    ptv_year = json_parsed["toll"]["systems"].map {|x| x['tariffVersion']}

    # Extracting PTV polyline from JSON. HERE coordinates are encoded to google polyline
    ptv_coordinates_array = json_parsed['legs'].map { |x| x['polyline']['plain']['polyline'] }.pop.map {|item| [item["y"],item["x"]]}
    google_encoded_polyline = FastPolylines.encode(ptv_coordinates_array)

    # Sending POST request to TollGuru
    tollguru_url = "#{TOLLGURU_API_URL}/#{POLYLINE_ENDPOINT}" 
    headers = {'content-type' => 'application/json', 'x-api-key' => TOLLGURU_API_KEY}
    body = {'source': "here", 'polyline': google_encoded_polyline, **request_parameters}
    tollguru_response = HTTParty.post(tollguru_url,:body => body.to_json, :headers => headers)
    begin
        toll_body = JSON.parse(tollguru_response.body)    
        if toll_body["route"]["hasTolls"] == true
            return google_encoded_polyline,toll_body["route"]["costs"]["tag"], toll_body["route"]["costs"]["cash"]
        else
            raise "No tolls encountered in this route"
        end
    rescue Exception => e
        puts e.message 
    end
    
end
