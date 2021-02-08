# [PTV AG For Developer](https://www.ptvgroup.com/en/solutions/products/ptv-xserver/)

### Get token to access PTV APIs (if you have an API token skip this)
#### Step 1: Login/Signup
* go to signup/login link https://www.ptvgroup.com/en/solutions/products/ptv-xserver/customer-area/ptv-xserver-api-version-2/ and create an account
#### Step 2: Creating a token
* You will be presented with a default token.

To get the route polyline make a POST request on https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rs/XRoute/experimental/calculateRoute with encoded username and password as header parameters

### Note:
* PTV accepts source and destination, as a Hash. We will use geocoding API to convert location to latitude-longitude pair

```python

#Importing modules
import json
import requests
import os
import base64
import polyline as poly

#Username for PTV
username=os.environ.get("PTV_username")
#API password for PTV
password=os.environ.get("PTV_password")

#'Authorization' parameter takes "Basic " followed by base64 encodes form of username:password
#Sample :  'Authorization' : 'Basic eHRvazofSZGrghde1344545TRkZGEtYrDGFREGTgvbeQxZGI0Njg='
autho = base64.standard_b64encode(bytes(f"{username}:{password}",'utf-8')).decode('utf-8')
header={'Content-Type' : 'application/json','Authorization' : f"Basic {autho}"}

'''Fetching geocodes from PTV'''  
def get_geocode_from_ptv(address):
    address_actual=address                                                  #storing the actual address before CGI encoding
    address=address.replace(" ", "%20").replace(",","%2C")
    ptv_geocoding_url = f"https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rest/XLocate/locations/{address}"              
    res=requests.get(ptv_geocoding_url,headers=header).json()
    return(res['results'][0]['location']['referenceCoordinate'])        # Returns a dictionary {'x':long,'y':lat} eg:{'x': -72.470237792, 'y': 42.174369817}


'''Fetching Polyline from PTV'''   
def get_polyline_from_ptv(source_geocode_dict,destination_geocode_dict):
    ptv_url="https://xserver2-europe-eu-test.cloud.ptvgroup.com/services/rs/XRoute/experimental/calculateRoute"
    payload= {
        "storedProfile" : "car.xml",
        "waypoints": [
            {
                "$type": "OffRoadWaypoint",
                "location": {
                    "offRoadCoordinate": source_geocode_dict
                    }
                },
            {
                "$type": "OffRoadWaypoint",
                "location": {
                    "offRoadCoordinate": destination_geocode_dict
                    }
                }
            ],
        "resultFields": {
            "nodes": True,
            "polyline": True,
            "segments": {
                "enabled": True,
                "polyline": True
                },
            "legs": {
                "enabled": True,
                "polyline": True,
                "tollSummary": True
                },
            "toll": {
                "enabled": True,
                "sections": True,
                "systems": True
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
                "useDetailedToll": True
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
                "axle" : {"numberOfAxles" : 2}
                }
            }
        }
    res_= requests.post(ptv_url,json=payload,headers=header).json()
    #extracting all the coordinates of nodes in lat-long pair to get route , note that PTV provides in {'x':"long",'y':"lat"} dictionary format
    coordinate_list=[(node['y'],node['x']) for node in res_['legs'][0]['polyline']['plain']['polyline']]
    #Encoding this list of coordinates into "Encoded" polyline , note that encoded polyline requires coordinates in lat-long pair
    polyline_from_ptv=poly.encode(coordinate_list)
    return(polyline_from_ptv)

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
```python
#API key for Tollguru
Tolls_Key = os.environ.get("TOLLGURU_API_KEY")

def get_rates_from_tollguru(polyline):        
    #Tollguru querry url
    Tolls_URL = 'https://dev.tollguru.com/v1/calc/route'
    #Tollguru resquest parameters
    headers = {
                'Content-type': 'application/json',
                'x-api-key': Tolls_Key
                }
    params = {
                #Explore https://tollguru.com/developers/docs/ to get best of all the parameter that tollguru has to offer 
                'source': "ptv",
                'polyline': polyline,                       # this is the encoded polyline that we made     
                'vehicleType': '2AxlesAuto',                #'''Visit https://github.com/mapup/toll-ptv-maps/wiki/1.-Vehicle-types-supported-by-TollGuru to know more options'''
                'departure_time' : "2021-01-05T09:46:08Z"   #'''Visit https://en.wikipedia.org/wiki/Unix_time to know the time format'''
                }
    #Requesting Tollguru with parameters
    response_tollguru= requests.post(Tolls_URL, json=params, headers=headers,timeout=200).json()
    #checking for errors or printing rates
    if str(response_tollguru).find('message')==-1:
        return(response_tollguru['route']['costs'])
    else:
        raise Exception(response_tollguru['message'])
```


Whole working code can be found in [**ptv.py**](https://github.com/mapup/toll-ptv-maps/blob/main/python/ptv.py) file.
