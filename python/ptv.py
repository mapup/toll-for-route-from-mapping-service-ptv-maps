# Importing modules
import json
import requests
import os
import base64
import polyline as poly

PTV_USERNAME = os.environ.get("PTV_USERNAME")  # Username for PTV
PTV_PASSWORD = os.environ.get("PTV_PASSWORD")  # API password for PTV
PTV_API_URL = "https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rs/XRoute/experimental/calculateRoute"
PTV_GEOCODE_API_URL = (
    "https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rest/XLocate/locations"
)

TOLLGURU_API_KEY = os.environ.get("TOLLGURU_API_KEY")
TOLLGURU_API_URL = "https://apis.tollguru.com/toll/v2"
POLYLINE_ENDPOINT = "complete-polyline-from-mapping-service"

#'Authorization' parameter takes "Basic " followed by base64 encodes form of username:password
# Sample :  'Authorization' : 'Basic eHRvazofSZGrghde1344545TRkZGEtYrDGFREGTgvbeQxZGI0Njg='
autho = base64.standard_b64encode(
    bytes(f"{PTV_USERNAME}:{PTV_PASSWORD}", "utf-8")
).decode("utf-8")
header = {"Content-Type": "application/json", "Authorization": f"Basic {autho}"}

"""Fetching geocodes from PTV"""


def get_geocode_from_ptv(address):
    address_actual = address  # storing the actual address before CGI encoding
    address = address.replace(" ", "%20").replace(",", "%2C")
    ptv_geocoding_url = f"{PTV_GEOCODE_API_URL}/{address}"
    res = requests.get(ptv_geocoding_url, headers=header).json()
    return res["results"][0]["location"][
        "referenceCoordinate"
    ]  # Returns a dictionary {'x':long,'y':lat} eg:{'x': -72.470237792, 'y': 42.174369817}


"""Fetching Polyline from PTV"""


def get_polyline_from_ptv(source_geocode_dict, destination_geocode_dict):
    ptv_url = PTV_API_URL
    payload = {
        "storedProfile": "car.xml",
        "waypoints": [
            {
                "$type": "OffRoadWaypoint",
                "location": {"offRoadCoordinate": source_geocode_dict},
            },
            {
                "$type": "OffRoadWaypoint",
                "location": {"offRoadCoordinate": destination_geocode_dict},
            },
        ],
        "resultFields": {
            "nodes": True,
            "polyline": True,
            "segments": {"enabled": True, "polyline": True},
            "legs": {"enabled": True, "polyline": True, "tollSummary": True},
            "toll": {"enabled": True, "sections": True, "systems": True},
            "eventTypes": ["TOLL_EVENT"],
        },
        "routeOptions": {
            "timeConsideration": {
                "$type": "ExactTimeConsiderationAtStart",
                "referenceTime": "2021-01-15T13:46:17",
            },
            "tollOptions": {"useDetailedToll": True},
        },
        "requestProfile": {
            "routingProfile": {"course": {"toll": {"tollPenalty": 0}}},
            "vehicleProfile": {
                "electronicTollCollectionSubscriptions": "US_OHIO_EZPASS",
                "axle": {"numberOfAxles": 2},
            },
        },
    }
    res_ = requests.post(ptv_url, json=payload, headers=header).json()
    # extracting all the coordinates of nodes in lat-long pair to get route , note that PTV provides in {'x':"long",'y':"lat"} dictionary format
    coordinate_list = [
        (node["y"], node["x"])
        for node in res_["legs"][0]["polyline"]["plain"]["polyline"]
    ]
    # Encoding this list of coordinates into "Encoded" polyline , note that encoded polyline requires coordinates in lat-long pair
    polyline_from_ptv = poly.encode(coordinate_list)
    return polyline_from_ptv


"""Fetching Rates from TollGuru"""


def get_rates_from_tollguru(polyline):
    # Tollguru querry url
    Tolls_URL = f"{TOLLGURU_API_URL}/{POLYLINE_ENDPOINT}"
    # Tollguru resquest parameters
    headers = {"Content-type": "application/json", "x-api-key": TOLLGURU_API_KEY}
    params = {
        # Explore https://tollguru.com/developers/docs/ to get best of all the parameter that tollguru has to offer
        "source": "ptv",
        "polyline": polyline,  # this is the encoded polyline that we made
        "vehicleType": "2AxlesAuto",  #'''Visit https://github.com/mapup/toll-tomtom/wiki/Supported-vehicle-type-list-for-TollGuru-for-respective-continents to know more options'''
        "departure_time": "2021-01-05T09:46:08Z",  #'''Visit https://en.wikipedia.org/wiki/Unix_time to know the time format'''
    }
    # Requesting Tollguru with parameters
    response_tollguru = requests.post(
        Tolls_URL, json=params, headers=headers, timeout=200
    ).json()
    # checking for errors or printing rates
    if str(response_tollguru).find("message") == -1:
        return response_tollguru["route"]["costs"]
    else:
        raise Exception(response_tollguru["message"])


"""Program Starts"""
# Step 1 :Provide Source and Destination and get geocodes from PTV
source_geocode_dict = get_geocode_from_ptv(
    "Central Square, NY, United States"
)  # returns in {'x':long,'y':lat} structure
destination_geocode_dict = get_geocode_from_ptv("Ludlow, MA 01056, United States")

# Step 2 : Get Polyline from PTV
polyline_from_ptv = get_polyline_from_ptv(source_geocode_dict, destination_geocode_dict)

# Step 3 : Get rates from Tollguru
rates_from_tollguru = get_rates_from_tollguru(polyline_from_ptv)

# Print the rates of all the available modes of payment
if rates_from_tollguru == {}:
    print("The route doesn't have tolls")
else:
    print(f"The rates are \n {rates_from_tollguru}")

"""Program Ends"""
