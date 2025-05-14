<script type="text/javascript" src="http://www.google.com/jsapi?key=ABQIAAAAYnMQ0vrvEnLnf0UB2kzxGBTx33XFWoBiqFSeFRmsb1abfZkfShSzN8IZpVrrAM4_3x15tXWjxPb9KA"></script>    
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
        map.setCenter(new GLatLng(49.887557,15.216064), 7);   
        //javascript:void(prompt('Aktualni souradnice mapy:',map.getCenter()));
        //javascript:void(prompt('',gApplication.getMap().getCenter()));  

        // Načtení a zpracování dat z XML souboru
        GDownloadUrl("include/highlines_maps_xml.php", function(data, responseCode) {  
            var xml = GXml.parse(data);                                  
            var s;
            var highlines = xml.documentElement.getElementsByTagName("highline");

            // smyčka přes všechny hotely v XML souboru  
            for (var i = 0; i < highlines.length; i++) {
                // souřadnice hotelu    
                var point = new GLatLng(parseFloat(highlines[i].getAttribute("lat")),                            
                parseFloat(highlines[i].getAttribute("lng")));

                // html obsah informačního okna, které se zobrazí po kliknutí na značku                        
                s = "<font color=\"black\"><h3 style='color: black;'>" + highlines[i].getAttribute("label") + "</h3><strong>Délka:</strong>" + highlines[i].getAttribute("delka") + "m<br /><strong>Výška:</strong>" + highlines[i].getAttribute("vyska") + "m<br /><img src='"+highlines[i].getAttribute("image")+"' alt='"+highlines[i].getAttribute("label")+"'><br><a href='"+highlines[i].getAttribute("url")+"' target='_blank' style='color: blue;'>Webové stránky highliny &raquo;</a></font>";

                // vytvoření značky
                var marker = createMarker(point, s, highlines[i].getAttribute("label"));

                // přidání značky do globálního pole
                global_markers [global_markers.length] = marker;

                // přidání značky na mapu 
                map.addOverlay(marker);
            }
        <?php
        // pokud je zadano id highliny, rovnou se na ni zazoomuje
        // pouzito v odkazu na souradnice highliny v highline_detail.php
             if(isset($_GET["id"])){
                 $id = addslashes($_GET["id"]);
                 $req = $link->query("SELECT gps FROM highline WHERE id=$id LIMIT 1");
                 $row = $req->fetch_assoc();
                 $gps = substr($row["gps"], 1, strlen($row["gps"])-2);
                 $gps = explode(",", $gps);
                 $lat = ltrim($gps[0]);
                 $lng = ltrim($gps[1]);
                 echo 'var point2 = new GLatLng(parseFloat("'.$lat.'"),                            
                parseFloat("'.$lng.'"));
                map.setCenter(point2, 15);';
             }
        ?>    
        });

    }      
    google.setOnLoadCallback(initialize);
        

</script>
    <h2>Highline mapa</h2>
    <div id="map" style="width: 630px; height: 400px; margin: 10px 0 0 0"></div>