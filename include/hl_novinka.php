<?php
include_once("include/fce.php");
require("include/databaze.php");
$sql = 'SELECT * FROM (SELECT id, "video" AS typ, nazev, popis, adresa, "" AS album, datum FROM videa
  UNION
  SELECT id, "clanek" AS typ, nazev, popis, "" AS adresa, album, datum FROM clanky WHERE viditelnost=1
  UNION
  SELECT id, "novinka" AS typ, nazev, popis, odkaz AS adresa, "" AS album, datum FROM novinky) AS novinky ORDER BY datum DESC LIMIT 1';
$req = $link->query($sql);  
$zaznam = $req->fetch_assoc();
switch($zaznam["typ"]){
    case "video":
      $adresa = $zaznam["adresa"];
      $id = $zaznam["id"];
      echo "  <a href=\"$adresa\"><img src=\"video/video_$id.jpg\"></a>\n";
    break;
    case "clanek":
      $album = $zaznam["album"];
      $adresa = "index.php?str=clanek&amp;id=".$zaznam["id"];
      $id = $zaznam["id"];
      echo "  <a href=\"$adresa\"><img src=\"clanky/$album/nahled.jpg\"></a>\n";
    break;  
    case "novinka":
      $album = $zaznam["album"];
      $id = $zaznam["id"];
      $adresa = stripslashes(htmlspecialchars_decode($zaznam["adresa"]));
      if($adresa!=""){
        echo "  <a href=\"$adresa\"><img src=\"novinky/nahled_$id.jpg\"></a>\n";
      }
    break;  
  }
?>
