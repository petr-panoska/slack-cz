<?php
    session_start();
    include_once("fce.php");
    include_once("databaze.php");
    if(isset($_GET["id"])){ $id_l = $_GET["id"]; }else{ exit; }
            $sql= "SELECT highline_id, uzivatel.id AS id_u, datum, styl, poznamka, nick FROM highline_prechody JOIN uzivatel ON highline_prechody.uzivatel_id=uzivatel.id WHERE highline_id=$id_l ORDER BY datum DESC";
            $req = $link->query($sql);
            echo "<hr />\n";
            echo "<h3>Přechody</h3>";
            echo "<table id=\"prechody\">";
            echo "<tr>\n";
            echo "<th width=\"100\">Přezdívka</th><th width=\"70\">Styl</th><th width=\"100\">Datum</th><th>Poznámka</th>\n";
            echo "</tr>\n";
            while($zaznam = $req->fetch_assoc()){
                $id_u = $zaznam["id_u"];
                echo "<tr>";
                echo "  <td>";
                if($id_u != NULL){
                    echo "<a href=\"index.php?str=denicek&id_u=$id_u\">".$zaznam["nick"]."</a>";
                }else{ echo $zaznam["nick"]; }
                echo "  </td>";
                echo "  <td>";
                echo $zaznam["styl"];
                echo "  </td>";
                echo "  <td>";
                echo $zaznam["datum"];
                echo "  </td>";
                echo "  <td>";
                echo $zaznam["poznamka"];
                echo "  </td>";
                echo "  <td>";
                if(isset($_SESSION["username"]) && isAdmin($_SESSION["username"]) && $id_u==NULL){
                    $id=$zaznam["id"];
                    echo "<a href=\"form_zpr.php?akce=smazat_prechod_n&amp;id=$id&amp;id_l=$id_l\" onclick=\"return(confirm('Opravdu chcete smazat tento přechod?'));\"><img src=\"img/ico/trash.png\" alt=\"smazat přechod\"></a>";
                }
                if(isset($_SESSION["username"]) && ($zaznam["nick"]==$_SESSION["username"] || isAdmin($_SESSION["username"])) && $id_u!=NULL){
                    echo "<a href=\"form_zpr.php?akce=smazat_prechod&id_l=$id_l&id_u=$id_u\" onclick=\"return(confirm('Opravdu chcete smazat tento přechod?'));\"><img src=\"img/ico/trash.png\" alt=\"smazat přechod\"></a>";
                }
                echo "  </td>";
                echo "</tr>";
            }
            echo "</table>\n";
?>
