<?php
require("../include/databaze.php");
// Start XML file, create parent node
$doc = new DomDocument('1.0');
$highlines = $doc->createElement("highlines");
$highline = $doc->appendChild($highlines);

$sql = "SELECT id, jmeno, delka, gps, vyska FROM highline";
$req = $link->query($sql);

header("Content-type: text/xml");

while($row = $req->fetch_assoc()){
    if(strlen($row["gps"])==19){
        $id_l = $row["id"];
        $gps = explode(",", $row["gps"]);
        $highlines = $doc->createElement("highline"); 
        $nova_highline = $highline->appendChild($highlines);
      
        $nova_highline->setAttribute("label", $row["jmeno"]);
        $nova_highline->setAttribute("delka", $row["delka"]);
        $nova_highline->setAttribute("vyska", $row["vyska"]);
        $nova_highline->setAttribute("lat", $gps[0]);
        $nova_highline->setAttribute("lng", $gps[1]);
        $nova_highline->setAttribute("image", "http://www.slack.cz/line/high/$id_l/nahled.jpg");
        $nova_highline->setAttribute("url", "http://www.slack.cz/highline-$id_l");
    }
}

$xmlfile = $doc->saveXML();
echo $xmlfile;
?>
