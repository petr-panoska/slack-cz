<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title>Ukázka Google Maps</title>
    
<script type="text/javascript" src="http://www.google.com/jsapi?key=ABQIAAAAYnMQ0vrvEnLnf0UB2kzxGBSnhZUvHNAhP8d2n22Jk-WlHieWUBSomSVEAUKZjtoCeHo3DWS8kmmahQ"></script>
<script type="text/javascript">
  google.load("maps", "2.x");
  // Call this function when the page has been loaded   
  var global_markers = new Array();  // pole všech značek
  var map;
   
  // Vytvoří značku, parametry: souřadnice značky, html kód okna, popiska značky 
  function createMarker(point, content, cap) {
    // objekt obsahující vlastnosti značky
    var opt = new Object();
    opt.title = cap;
    var marker = new GMarker(point, opt);  
    // obsloužení kliknutí na značku
    GEvent.addListener(marker, "click", function() {
      map.setCenter(point, 15); // vycentruje a zazoomuje      
      marker.openInfoWindowHtml(content);  
    });  
    return marker;
  }  
  
  // Inicializace mapy
  function initialize() {
    // určení DIVu, který obsahuje mapu
    map = new google.maps.Map2(document.getElementById("map"));
    // přidání ovládátek na mapu (zoomovadlo, přepínač a náhled)
    map.addControl(new GLargeMapControl());
    map.addControl(new GMapTypeControl());
    map.addControl(new GOverviewMapControl());
    // určení výchozí polohy a měřítka mapy
    map.setCenter(new GLatLng(50.08380233140971, 14.431915283203125), 7);   
    //javascript:void(prompt('Aktualni souradnice mapy:',map.getCenter()));
    //javascript:void(prompt('',gApplication.getMap().getCenter()));  

    //GEvent.addListener(map, "click", function() {
    //      alert("You clicked the map");
    //     });
    
    var myEventListener = GEvent.bind(this.map, "click", this, function(overlay, latlng) {
         if (this.counter == 0) {
           if (latlng) {
             this.map.addOverlay(new GMarker(latlng))
             this.counter++;
           } else if (overlay instanceof GMarker) {
             // This code is never executed as the event listener is 
             // removed the second time this event is triggered
             this.removeOverlay(marker)
           }
         } else {
           GEvent.removeListener(myEventListener);
         }
      });
    
    // Načtení a zpracování dat z XML souboru
    GDownloadUrl("xml.php", function(data, responseCode) {  
      var xml = GXml.parse(data);                                  
      var s;
      var highlines = xml.documentElement.getElementsByTagName("highline");
      
      // smyčka přes všechny hotely v XML souboru  
      for (var i = 0; i < highlines.length; i++) {
        // souřadnice hotelu    
        var point = new GLatLng(parseFloat(highlines[i].getAttribute("lat")),                            
                                parseFloat(highlines[i].getAttribute("lng")));
                                
        // html obsah informačního okna, které se zobrazí po kliknutí na značku                        
        s = "<h1>" + highlines[i].getAttribute("label") + "</h1><img src='"+highlines[i].getAttribute("image")+"' alt='"+highlines[i].getAttribute("label")+"' width='190'><br><a href='"+highlines[i].getAttribute("url")+"' target='_blank'>Webové stránky highliny &raquo;</a>";

        // vytvoření značky
        var marker = createMarker(point, s, highlines[i].getAttribute("label"));
        
        // přidání značky do globálního pole
        global_markers [global_markers.length] = marker;
        
        // přidání značky na mapu 
        map.addOverlay(marker);
      }
    });
             
  }      
  google.setOnLoadCallback(initialize);    

</script>
    
    
  </head>
  <body onload="initialize()" onunload="GUnload()">
    <div id="map" style="width: 400px; height: 400px;"></div>
  </body>
</html>
