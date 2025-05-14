<?php
    if (isset($_GET["id"]) || isset($_SESSION["id_l"])){
        require "include/databaze.php";
        require "include/fce.php";
        $conf["highline_img"] = "line/high/";
        if(isset($_GET["id"])){
            $id_l = $_GET["id"];
        }else{
            $id_l = $_SESSION["id_l"];
            unset($_SESSION["id_l"]);
        }

        $sql = "SELECT * FROM highline WHERE id=\"$id_l\"";
        $req = $link->query($sql);
        $zaznam = $req->fetch_assoc();
        $obrazek = $conf["highline_img"].$zaznam["id"]."/foto.jpg";

        $info = $zaznam["info"];
        echo "<h2>Highline - ".$zaznam["jmeno"]."</h2>";
        echo '<table border="0">';
        echo '  <tr>';
        echo '    <td colspan="3" align="center">'."<img src=\"$obrazek\">".'</td>';
        echo '  </tr>';
        echo '  <tr>';
        echo '    <td width="190">Délka: '.$zaznam["delka"].'m</td>';
        echo '    <td width="190">Výška: '.$zaznam["vyska"].'m</td>';
        echo '    <td width="190">Expozice: '.vypis_hvezdicky($zaznam["hodnoceni"]).'</td>';
        echo '  </tr>'; 
        echo '  <tr>';
        echo '    <td>Stát: '.$zaznam["stat"].'</td>';
        echo '    <td>Oblast: '.$zaznam["oblast"].'</td>';
        echo '    <td>GPS: <a href="index.php?str=highline_mapa&amp;id='.$id_l.'" target="_blank">zde</a></td>';
        echo '  </tr>';
        echo '  <tr>';
        echo '    <td>';
        echo '      Kotvení:';
        echo '    </td>';
        echo '    <td colspan="2">';
        if (strchr($zaznam["kotveni"], "1")){ echo "<img src=\"img/highline/1.png\">"; }
        if (strchr($zaznam["kotveni"], "2")){ echo "<img src=\"img/highline/2.gif\">"; }
        if (strchr($zaznam["kotveni"], "3")){ echo "<img src=\"img/highline/3.gif\">"; }
        echo '    </td>';
        echo '  </tr>';
        echo '  <tr>';
        echo '    <td colspan="3">';
        if($info!=""){echo "Další info:<br />".$info;}
        echo '    </td>';
        echo '  </tr>';
        if(!isset($_GET["kotveni"])){
            echo '  <tr>';
            echo '    <td colspan="3">';
            $sql = "SELECT id_u, nick, styl FROM prvni_prechody_hl WHERE id_l=$id_l ORDER BY nick ASC";
            $req = $link->query($sql);
            if($req){
                echo '<br />First attempt: '.$zaznam["datum"]."<br />";
                while($zaznam = $req->fetch_assoc()){
                    if($zaznam["id_u"]==null){
                        $nick = $zaznam["nick"];
                        echo $zaznam["nick"]." - ".$zaznam["styl"]."<br />";
                    }else{
                        $nick = $zaznam["nick"];
                        echo '<a href="/denicek-'.$zaznam["id_u"].'">'.$zaznam["nick"].'</a> - '.$zaznam["styl"]."<br />";
                    }
                }
            }
            echo '    </td>';
            echo '  </tr>';
            echo '</table>';

            /*
            ******************** PRECHODY ************************
            */
          ?>
          <div id="prechody">
          <?php  include("include/highline_prechody.php"); ?>
          </div>
<?php
if(isset($_SESSION["id"])){
 ?>
            <div id="pridaniPrechoduHigh">
  <table>
    <tr><td>Poznámka: </td><td>
        <input type="hidden" id="id_l" value="<?php echo $id_l ?>">          
        <input type="text" id="poznamka" maxlength="100"></td>
    </tr>
    <tr><td>  Datum:</td><td>          
        <input type="text" id="datepicker" value="<?php echo date("Y-m-d"); ?>"></td>
    </tr>
    <tr><td>Styl:</td><td>          
        <select id="styl">                            
          <option value="-">-               
          </option>                            
          <option value="OS fm">OS fm               
          </option>             
          <option value="OS">OS               
          </option>             
          <option value="OS, fm">OS, fm               
          </option>             
          <option value="fm">fm               
          </option>             
          <option value="one way">one way               
          </option>                    
        </select></td>
    </tr>
  </table>            
</div>
<center>                
<a class="tlacitka" id="prechodHighAdd" onclick="" style="text-align: center;" alt=>
  <img src="img/test/book.png" alt="Zapsat přechod do deníčku"/>
</a>
</center>
<?php } ?>
<hr>
            <?php 

            /* FOTKY */  
            $sql = "SELECT id, nick, adresa, popis FROM highline_media WHERE ((highline_id=$id_l) AND (typ='foto'))";
            $req = $link->query($sql);
            if($req->num_rows > 0){
                echo '<h3>Fotky</h3>';
            }
            while($zaznam = $req->fetch_assoc()){
                echo "<a href=\"".stripslashes(htmlspecialchars_decode($zaznam["adresa"]))."\">".stripslashes(htmlspecialchars_decode($zaznam["popis"]))."</a>\n";
                if(isset($_SESSION["username"]) && (isAdmin($_SESSION["username"]) || $_SESSION["username"]==$zaznam["nick"])){
                    $id_m=$zaznam["id"];
                    echo "  &nbsp;<a href=\"form_zpr.php?akce=media_del&id_l=$id_l&id_m=$id_m\"><img src=\"img/ico/del.png\" alt=\"Vymazat odkaz\"></a>\n";
                    echo "<br />\n";
                }
            }
            /* VIDEA */  
            $sql = "SELECT id, nick, adresa, popis FROM highline_media WHERE ((highline_id=$id_l) AND (typ='video'))";
            $req = $link->query($sql);
            if($req->num_rows > 0){
                echo '<h3>Videa</h3>';
            }
            while($zaznam = $req->fetch_assoc()){
                echo "<a href=\"".stripslashes(htmlspecialchars_decode($zaznam["adresa"]))."\">".stripslashes(htmlspecialchars_decode($zaznam["popis"]))."</a>\n";
                if(isset($_SESSION["username"]) && (isAdmin($_SESSION["username"]) || $_SESSION["username"]==$zaznam["nick"])){
                    $id_m=$zaznam["id"];
                    echo "  &nbsp;<a href=\"form_zpr.php?akce=media_del&id_l=$id_l&id_m=$id_m\"><img src=\"img/ico/del.png\" alt=\"Vymazat odkaz\"></a>\n";
                    echo "<br />\n";
                }
            }
            /* CLANKY */  
            $sql = "SELECT id, nick, adresa, popis FROM highline_media WHERE ((highline_id=$id_l) AND (typ='clanek'))";
            $req = $link->query($sql);
            if($req->num_rows > 0){
                echo '<h3>Články</h3>';
            }
            while($zaznam = $req->fetch_assoc()){
                echo "<a href=\"".stripslashes(htmlspecialchars_decode($zaznam["adresa"]))."\">".stripslashes(htmlspecialchars_decode($zaznam["popis"]))."</a>\n";
                if(isset($_SESSION["username"]) && (isAdmin($_SESSION["username"]) || $_SESSION["username"]==$zaznam["nick"])){
                    $id_m=$zaznam["id"];
                    echo "  <a href=\"form_zpr.php?akce=media_del&id_l=$id_l&id_m=$id_m\"><img src=\"img/ico/del.png\" alt=\"Vymazat odkaz\"></a>\n";
                    echo "<br />\n";
                }
            }
        }else{
            echo "</table><br />\n";
            echo "<h2>Kotvení</h2>\n";
            
            $sql = "SELECT foto, popis FROM highline_foto WHERE highline_id=$id_l AND accept=true";
            $req = $link->query($sql);
            if($req){
                require_once("include/class/Galerie.php");
                $galerie = new Galerie("highline_foto");
                while($row = $req->fetch_assoc()){
                    $thumb = "highline/".$id_l."/kotveni/".$row["foto"]."_thumb.jpg";
                    $full = "highline/".$id_l."/kotveni/".$row["foto"].".jpg";
                    $galerie->AddPicture($thumb, $full, $row["popis"]);    
                }
                $galerie->Show();
            }
        }

        if(isset($_SESSION["username"])){
            include "include/highline_media.php";
            include "include/highline_foto.php";
        }else{
            //include "include/highline_prechody_n.php";
        }
    }
?>