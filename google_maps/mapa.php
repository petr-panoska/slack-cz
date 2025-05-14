<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
        <title>Google Maps JavaScript API Example:     Reverse Geocoder</title>
        <script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=ABQIAAAAYnMQ0vrvEnLnf0UB2kzxGBTL567PZnX9QHXnPwRTzMb3H51xLRSBOZWcVKYVTrBL6WF9ujhcGUlSXw" 
            type="text/javascript"></script>
        <script type="text/javascript">


            var map;
            var geocoder;
            var address;

            function initialize() {
                map = new GMap2(document.getElementById("map_canvas"));
                map.setCenter(new GLatLng(49.887557,15.216064), 7);   
                map.setUIToDefault();
                GEvent.addListener(map, "click", getAddress);
                geocoder = new GClientGeocoder();
            }

            function getAddress(overlay, latlng) {
                if (latlng != null) {
                    address = latlng;
                    geocoder.getLocations(latlng, showAddress);
                }
            }

            function showAddress(response) {
                map.clearOverlays();
                if (!response || response.Status.code != 200) {
                    alert("Status Code:" + response.Status.code);
                } else {
                    place = response.Placemark[0];
                    point = new GLatLng(place.Point.coordinates[1],
                    place.Point.coordinates[0]);
                    //marker = new GMarker(point);
                    marker = new GMarker(point);
                    map.addOverlay(marker);
                    marker.openInfoWindowHtml(
                    '<b>GPS:</b>' + response.name + '<br />' +
                    '<b>Stát:</b>' + place.AddressDetails.Country.CountryName + '<br />' + 
                    '<b>Kraj:</b>' + place.AddressDetails.Country.AdministrativeArea.AdministrativeAreaName + '<br />' +
                    '<b>Zkratka:</b>' + place.AddressDetails.Country.CountryNameCode + '<br />');
                    gps(response.name);
                }
            }


        </script>
    </head>
    
    <script type="text/javascript">
            function gps(text){
                document.formular.souradnice.value=text;
            }
        </script>

    <body onload="initialize()">
        <div id="map_canvas" style="width: 500px; height: 400px"></div>
            <form name="formular">
                <input type="text" name="souradnice" readonly="readonly">
                <input type="button" onclick="stisk()">
            </form>
    </body>
</html>