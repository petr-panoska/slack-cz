<?php
require("include/databaze.php");
require("include/fce.php");
$sql = 'SELECT id, nazev, adresa, typ, popis, datum FROM videa ORDER BY datum DESC';
$req = $link->query($sql);
$kontrola = false;

if($_GET["podstr"] == "vsechno")
    $kontrola = true;
while($zaznam = $req->fetch_assoc()){
  $typ = $zaznam["typ"];
  if(($typ == $_GET["podstr"]) || $kontrola)
  {  
    $adresa = $zaznam["adresa"];
    $id = $zaznam["id"];
    $typ = $zaznam["typ"];
    echo "<div class=\"video\">\n";
    echo "  <a href=\"$adresa\"><img src=\"video/video_$id.jpg\" width=\"120px\" alt=\"".$zaznam["nazev"]."\"></a>\n";
    echo "  <h3>".$zaznam["nazev"]."</h3>\n";
    echo "  <p>".$zaznam["popis"]."</p>\n";
    echo "  <div class=\"clear\"></div>\n";
    if(isset($_SESSION["username"]) && isAdmin($_SESSION["username"])){
        echo "  <p class=\"admin\"><a href=\"form_zpr.php?akce=video_update&id=$id\"><img src=\"img/ico/edit.png\" alt=\"Upravit video\"></a>\n";
        echo "  <a href=\"form_zpr.php?akce=video_del&id=$id\" onClick=\"return(confirm('Opravdu chcete vymazat toto video ?'));\"><img src=\"img/ico/del.png\" alt=\"Smazat video\"></a></p>\n";
    }
    echo "<div class=\"clear\"></div>";
    echo "</div>\n";
  }
}
?>

<a href="http://www.slacklive.cz/videa/videa.htm">Starší videa</a>
<br /><br />
<a href="index.php?str=video_add">Přidat video</a>