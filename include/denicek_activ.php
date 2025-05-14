<h2>Aktivity</h2>
<?php
    /*
    *  Vydané články
    */
    $sql = "SELECT id, nazev, datum FROM clanky WHERE uzivatel=(SELECT nick FROM uzivatel WHERE id=$id_u) AND viditelnost=1";
    $req = $link->query($sql);
    echo "<table>\n";
    if($id_u == $_SESSION["id"]){
        echo "<tr><th colspan=\"2\">Vydané články</th></tr>";
        echo "<tr><th width=\"350px\">Název článku:</th><th>Datum vydání:</th><th>Upravit:</th></tr>";
    }else{
        echo "<tr><th colspan=\"1\">Vydané články</th></tr>";
        echo "<tr><th width=\"350px\">Název článku:</th><th>Datum vydání:</th></tr>"; 
    }
    while($row = $req->fetch_assoc()){
        $id_c = $row["id"];
        echo "<td><a href=\"/clanek-".$row["id"]."\">".$row["nazev"]."</a></td>";
        echo "<td>".$row["datum"]."</td>";
        if($id_u == $_SESSION["id"]){
            echo "<td align=\"center\"><a href=\"form_zpr.php?akce=clanek_update&id=$id_c\" alt=\"Upravit článek\"><img src=\"img/ico/edit.png\" class=\"ico\" alt=\"Upravit článek\"></a><td/>\n";
        }
        echo "</tr>";
    }

    /*
    *  Hotové články
    */
    /*$sql = "SELECT id, nazev, datum FROM clanky WHERE uzivatel=(SELECT nick FROM uzivatel WHERE id_u=$id_u) AND viditelnost=1 AND datum>'".date("Y-m-d H:m:i");
    $req = $link->query($sql);
    if($req){
        if($id_u == $_SESSION["id"]){
            echo "<tr><th colspan=\"2\">Hotové články čekající na vydání</th></tr>";
            echo "<tr><th width=\"350px\">Název článku:</th><th>Datum vydání:</th><th>Upravit:</th></tr>";
        }else{
            echo "<tr><th colspan=\"1\">Hotové články čekající na vydání</th></tr>";
            echo "<tr><th width=\"350px\">Název článku:</th><th>Datum vydání:</th></tr>"; 
        }
        while($row = $req->fetch_assoc()){
            $id_c = $row["id"];
            echo "<td><a href=\"/clanek-".$row["id"]."\">".$row["nazev"]."</a></td>";
            echo "<td>".$row["datum"]."</td>";
            if($id_u == $_SESSION["id"]){
                echo "<td align=\"center\"><a href=\"form_zpr.php?akce=clanek_update&id=$id_c\" alt=\"Upravit článek\"><img src=\"img/ico/edit.png\" class=\"ico\" alt=\"Upravit článek\"></a><td/>\n";
            }
            echo "</tr>";
        }
    } */
    /*
    *  Rozpracované články
    */
    /*$sql = "SELECT id, nazev, datum FROM clanky WHERE uzivatel=(SELECT nick FROM uzivatel WHERE id_u=$id_u) AND viditelnost=0";
    $req = $link->query($sql);
    if($req){
        if($id_u == $_SESSION["id"]){
            echo "<tr><th colspan=\"2\">Rozpracované články</th></tr>";
            echo "<tr><th width=\"350px\">Název článku:</th><th>Upravit:</th></tr>";
        }else{
            echo "<tr><th colspan=\"1\">Rozpracované články</th></tr>";
            echo "<tr><th width=\"350px\">Název článku:</th></tr>"; 
        }
        while($row = $req->fetch_assoc()){
            $id_c = $row["id"];
            echo "<td><a href=\"/clanek-".$row["id"]."\">".$row["nazev"]."</a></td>";
            if($id_u == $_SESSION["id"]){
                echo "<td align=\"center\"><a href=\"form_zpr.php?akce=clanek_update&id=$id_c\" alt=\"Upravit článek\"><img src=\"img/ico/edit.png\" class=\"ico\" alt=\"Upravit článek\"></a><td/>\n";
            }
            echo "</tr>";
        }
    }

               */
    echo "</table>";
?>
