<?php
    require("databaze.php");
    $sql = 'SELECT id, jmeno, delka, vyska, gps FROM highline';
    $req = $link->query($sql);

    // Start XML file, create parent node
    $doc = new DomDocument('1.0');
    $highlines = $doc->createElement("highlines");
    $highline = $doc->appendChild($highlines);

    header("Content-type: text/xml");

    while($row = $req->fetch_assoc()){
        if(substr($row["gps"], 0, 1)=="(" && substr($row["gps"], strlen($row["gps"])-1, 1)==")" ){
            $gps = substr($row["gps"], 1, strlen($row["gps"])-2);
            $gps = explode(",", $gps);
            $lat = ltrim($gps[0]);
            $lng = ltrim($gps[1]);
            $id_l = $row["id"];
            $highlines = $doc->createElement("highline"); 
            $nova_highline = $highline->appendChild($highlines);

            $nova_highline->setAttribute("label", $row["jmeno"]);
            $nova_highline->setAttribute("delka", $row["delka"]);
            $nova_highline->setAttribute("vyska", $row["vyska"]);
            $nova_highline->setAttribute("lat", $lat);
            $nova_highline->setAttribute("lng", $lng);
            $nova_highline->setAttribute("image", "http://www.slack.cz/line/high/$id_l/nahled.jpg");
            $nova_highline->setAttribute("url", "http://www.slack.cz/highline-$id_l");
        }

    }
    $xmlfile = $doc->saveXML();
    echo $xmlfile;
?>
