<?php

/**
 * @author Roman Fischer
 */

include_once("include/fce.php");
include "include/databaze.php";
$sql =  $sql = "SELECT * FROM media_o_nas ORDER BY id";
$req = $link->query($sql);
if($req){
	while($zaznam = $req->fetch_assoc()) {
	 $adresa = $zaznam["adresa"];
    $id = $zaznam["id"];
    echo "<div class=\"novinka\">\n";
	 echo "  <a href=\"$adresa\">".$zaznam["nazev"]."</a>\n";	    
    echo "  <br><p><img src=\"img/media_o_nas/$id.jpg\" alt=\"".$zaznam["nazev"]."\">\n";
    echo "  ".$zaznam["popis"]."</p>\n";
    echo "	<div class=\"clear\"></div>";
    echo "<hr></div>";
   }
}
?>
