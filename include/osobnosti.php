<?php

/**
 * @author Roman Fischer
 */

include_once("include/fce.php");
include "include/databaze.php";
$sql =  "SELECT * FROM clanky WHERE id=61 OR id=76 OR id=72 ORDER BY datum DESC";
$req = $link->query($sql);
if($req){
  while($zaznam = $req->fetch_assoc()){
    $album = stripslashes(htmlspecialchars_decode($zaznam["album"]));
    $popis = stripslashes(htmlspecialchars_decode($zaznam["popis"]));
    $text = stripslashes(htmlspecialchars_decode($zaznam["text"]));
    $id = $zaznam["id"];
     echo "<div class=\"novinka\">\n";
     echo "  <a href=\"/clanek-$id\">".$zaznam["nazev"]."</a>\n";
     if(isset($_SESSION["username"]) && isAdmin($_SESSION["username"])){
       echo "    <a href=\"form_zpr.php?akce=clanek_update&id=$id\" alt=\"Upravit článek\"><img src=\"img/ico/edit.png\" class=\"ico\" alt=\"Upravit článek\"></a>\n";
       echo "    <a href=\"form_zpr.php?akce=clanek_del&id=$id\" onclick=\"return(confirm('Opravdu chcete smazat tento článek ?'));\" alt=\"Smazat článek\"><img src=\"img/ico/del.png\" class=\"ico\" alt=\"Smazat ÄŤlĂˇnek\"></a>";
     }
     echo "  <br><p><img src=\"clanky/$album/nahled.jpg\" alt=\"".$zaznam["nazev"]."\">\n";
     echo $zaznam["popis"]."\n";
     echo "  </p><div class=\"clear\"></div>\n";
     echo "<hr>";
     echo "</div>\n";
  }
}
else
 {
	echo "nefunguje";	
	 }
?>