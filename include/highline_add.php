<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=ABQIAAAAYnMQ0vrvEnLnf0UB2kzxGBTx33XFWoBiqFSeFRmsb1abfZkfShSzN8IZpVrrAM4_3x15tXWjxPb9KA" 
    type="text/javascript"></script>
<script type="text/javascript">


    var map;
    var geocoder;
    var address;
    var souradnice;  

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
            souradnice = latlng;
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
            marker = new GMarker(souradnice);
            map.addOverlay(marker);
            marker.openInfoWindowHtml(
            '<font color="black"><b>GPS:</b> ' + souradnice + '<br />' +
            '<b>Stát:</b> ' + place.AddressDetails.Country.CountryName + '<br />' + 
            '<b>Kraj:</b> ' + place.AddressDetails.Country.AdministrativeArea.AdministrativeAreaName + '</font>');
            gps(souradnice);
            stat(place.AddressDetails.Country.CountryName);
            kraj(place.AddressDetails.Country.AdministrativeArea.AdministrativeAreaName);
        }
    }

    function gps(text){
        document.highline_add.gps.value=text;
    }
    function stat(text){
        document.highline_add.stat.value=text;
    }
    function kraj(text){
        document.highline_add.kraj.value=text;
    }


</script>
<h2>Přidání highliny</h2>
<form method="post" action="form_zpr.php" ENCTYPE="multipart/form-data" name="highline_add">                                                                   
    <input type="hidden" name="akce" value="highline_add"/>                                
    <label>Jméno:</label>
    <input type="text" length="30" name="jmeno" value="<?php if(isset($_SESSION["jmeno"]["hodnota"])){ echo $_SESSION["jmeno"]["hodnota"]; } ?>"/>                                                                              
    <span class="chyba">          
        <?php if(isset($_SESSION["jmeno"]["popis"])){ echo $_SESSION["jmeno"]["popis"]; } ?>        
    </span>
    <br />
    <label>Délka:</label>
    <input type="text" length="3" name="delka" value="<?php if(isset($_SESSION["delka"]["hodnota"])){ echo $_SESSION["delka"]["hodnota"]; } ?>">                                                                                           
    <span class="chyba">          
        <?php if(isset($_SESSION["delka"]["popis"])){ echo $_SESSION["delka"]["popis"]; } ?>        
    </span>
    <br />  
    <label>Výška:</label>
    <input type="text" length="3" name="vyska" value="<?php if(isset($_SESSION["vyska"]["hodnota"])){ echo $_SESSION["vyska"]["hodnota"]; } ?>">                                                                                       
    <span class="chyba">          
        <?php if(isset($_SESSION["vyska"]["popis"])){ echo $_SESSION["vyska"]["popis"]; } ?>        
    </span>
    <br />
    <label>Expozice:</label>
    <input type="text" length="3" name="hodnoceni" value="<?php if(isset($_SESSION["hodnoceni"]["hodnota"])){ echo $_SESSION["hodnoceni"]["hodnota"]; } ?>">                                                                                       
    <span class="chyba">          
        <?php if(isset($_SESSION["hodnoceni"]["popis"])){ echo $_SESSION["hodnoceni"]["popis"]; } ?>        
    </span>
    <br />
    <label>Oblast:</label>
    <input type="text" length="30" name="oblast" value="<?php if(isset($_SESSION["oblast"]["hodnota"])){ echo $_SESSION["oblast"]["hodnota"]; } ?>">                           
    <br />                                                     
    <label>Speciální info:</label>
    <textarea rows="6" cols="30" name="info"><?php if(isset($_SESSION["info"]["hodnota"])){ echo $_SESSION["info"]["hodnota"]; } ?></textarea>
    <br />                                                                      
    <label>Kotvení:</label><img src="img/highline/1.png"><input type="checkbox" name="kotveni_strom"><br />
    <label>&nbsp;</label><img src="img/highline/2.gif"><input type="checkbox" name="kotveni_kruh"><br />
    <label>&nbsp;</label><img src="img/highline/3.gif"><input type="checkbox" name="kotveni_skala">
    <br />                                                                                       
    <label>Autor:</label>
    <input type="text" length="3" name="autor" value="<?php if(isset($_SESSION["autor"]["hodnota"])){ echo $_SESSION["autor"]["hodnota"]; } ?>">                            
    <br />                            
    <label>Datum:</label>
    <input type="text" length="3" name="datum" value="<?php if(isset($_SESSION["datum"]["hodnota"])){ echo $_SESSION["datum"]["hodnota"]; }else{ echo date("Y-m-d"); } ?>">                                 
    <br />                             
    <label>Náhledová fotka:</label>
    <input TYPE="file" NAME="nahled"></td>                                                                   
    <br />
    <label>Fotka:</label>
    <input TYPE="file" NAME="fotka"></td>                                                                   
    <br />
    
    
    <div id="map_canvas" style="width: 590px; height: 400px; margin:10px 0 0 0;"></div>
    <br />
    <label>GPS:</label>
    <input type="text" length="50" name="gps" value="<?php if(isset($_SESSION["gps"]["hodnota"])){ echo $_SESSION["gps"]["hodnota"]; } ?>" readonly="readonly">                       
    <br />
    <label>Stát:</label>
    <input type="text" length="50" name="stat" value="<?php if(isset($_SESSION["stat"]["hodnota"])){ echo $_SESSION["stat"]["hodnota"]; } ?>" readonly="readonly">                       
    <br />
    <label>Kraj:</label>
    <input type="text" length="50" name="kraj" value="<?php if(isset($_SESSION["kraj"]["hodnota"])){ echo $_SESSION["kraj"]["hodnota"]; } ?>" readonly="readonly">                       
    <br />
    
    
                                 
    <label>&nbsp;</label><input type="submit" value="Přidej">
</form>         