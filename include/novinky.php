<h2>Novinky</h2>
<?php
include_once("include/fce.php");
require("include/databaze.php");
$sql = 'SELECT * FROM (SELECT id, "video" AS typ, nazev, popis, adresa, "" AS album, datum FROM videa
  UNION
  SELECT id, "clanek" AS typ, nazev, popis, "" AS adresa, album, datum FROM clanky WHERE viditelnost=1
  UNION
  SELECT id, "novinka" AS typ, nazev, popis, odkaz AS adresa, "" AS album, datum FROM novinky) AS novinky ORDER BY datum DESC LIMIT 15';
$req = $link->query($sql);
while($zaznam = $req->fetch_assoc()){
  switch($zaznam["typ"]){
    case "video":
      $adresa = $zaznam["adresa"];
      $id = $zaznam["id"];
      echo "<div class=\"novinka\">\n";
      echo "  <a href=\"$adresa\">Video: ".stripcslashes(htmlspecialchars_decode($zaznam["nazev"]))."</a>\n";
      echo "  <div class=\"clear\"></div>\n"; 
      echo "  <p><img class=\"obr_n\" src=\"video/video_$id.jpg\" alt=\"video\">\n"; 
      echo $zaznam["popis"]."</p>\n";
      if(isset($_SESSION["username"]) && isAdmin($_SESSION["username"])){
        echo "  <a href=\"form_zpr.php?akce=video_update&id=$id\"><img src=\"img/ico/edit.png\" alt=\"Upravit video\"></a>\n";
        echo "  <a href=\"form_zpr.php?akce=video_del&id=$id\" onClick=\"return(confirm('Opravdu chcete vymazat toto video ?'));\"><img src=\"img/ico/del.png\" alt=\"Smazat video\"></a>\n";
      }
      echo "  <div class=\"clear\"></div>\n";
      echo "  <hr>\n";
      echo "</div>\n";
    break;
    case "clanek":
      $album = $zaznam["album"];
      $adresa = "index.php?str=clanek&amp;id=".$zaznam["id"];
      $id = $zaznam["id"];
      echo "<div class=\"novinka\">\n";
      echo "  <a href=\"$adresa\">Článek: ".$zaznam["nazev"]."</a>\n";
      if(isset($_SESSION["username"]) && isAdmin($_SESSION["username"])){
       echo "    <a href=\"form_zpr.php?akce=clanek_update&id=$id\" alt=\"Upravit článek\"><img src=\"img/ico/edit.png\" alt=\"Upravit článek\" class=\"ico\"></a>\n";
       echo "    <a href=\"form_zpr.php?akce=clanek_del&id=$id\" onclick=\"return(confirm('Opravdu chcete smazat tento článek ?'));\" alt=\"Smazat článek\"><img src=\"img/ico/del.png\"  class=\"ico\" alt=\"Smazat článek\"></a>";
      }
      echo "  <div class=\"clear\"></div>\n"; 
      echo "  <p>\n";
      echo "  <img src=\"clanky/$album/nahled.jpg\" alt=\"".$zaznam["nazev"]."\">\n";  
      echo $zaznam["popis"]."</p>\n";
      echo "  <div class=\"clear\"></div>\n";
      echo "  <hr>\n";
      echo "</div>\n";
    break;  
    case "novinka":
      $album = $zaznam["album"];
      $id = $zaznam["id"];
      $adresa = stripslashes(htmlspecialchars_decode($zaznam["adresa"]));
      echo "<div class=\"novinka\">\n"; 
      if($adresa!=""){
        echo "  <a href=\"$adresa\">Novinka: ".$zaznam["nazev"]."</a>\n";
      }else{echo "<h3>".$zaznam["nazev"]."</h3>\n";}
      echo "  <div class=\"clear\"></div>\n"; 
      echo "  <p><img src=\"novinky/nahled_$id.jpg\" alt=\"novinka\">\n";
      echo $zaznam["popis"]."</p>\n";
        
      if(isset($_SESSION["username"]) && isAdmin($_SESSION["username"])){
       echo "    <a href=\"form_zpr.php?akce=novinka_update&id=$id\"><img src=\"img/ico/edit.png\" alt=\"Upravit novinku\"></a>\n";
       echo "    <a href=\"form_zpr.php?akce=novinka_del&id=$id\" onclick=\"return(confirm('Opravdu chcete smazat tuto novinku ?'));\"><img src=\"img/ico/del.png\" alt=\"Smazat novinku\"></a>";
      }
      echo "  <div class=\"clear\"></div>\n";
      echo "  <hr>\n";
      echo "</div>\n";
    break;  
  }
}

if(isset($_SESSION["username"]) && isAdmin($_SESSION["username"])){
  echo '<a href="index.php?str=pridejNovinku">Přidat novinku</a>';
}
?>
