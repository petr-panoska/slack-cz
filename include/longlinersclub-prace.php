<?php
require("databaze.php");
if(isset($_POST)){
    if(isset($_POST["jmeno"])){$jmeno = $_POST["jmeno"];}
    if(isset($_POST["delka"])){$delka = $_POST["delka"];}
    if(isset($_POST["misto"])){$misto = $_POST["misto"];}
    if(isset($_POST["datum"])){$datum = $_POST["datum"];}
    if(isset($_SESSION["id"])){
        $id_u = $_SESSION["id"];
        $nick = $_SESSION["username"];
    }else{
        $id_u = "NULL";
        $nick = "NULL";
    }
    $sql = "INSERT INTO `longlinersclub-pracovni` (jmeno, delka, misto, datum, id_u, nick) VALUES ('$jmeno', $delka, '$misto', '$datum', $id_u, '$nick')";
    $link->query($sql);
}
?>
<h2>Longliners Club - pracovní verze</h2>
<form name="longlinersclub" method="post" action="">
     <label width:>Jméno:</label><input type="text" name="jmeno" maxlength="50"><br />
     <label>Délka:</label><input type="text" name="delka" maxlength="3"><br />
     <label>Místo:</label><input type="text" name="misto" maxlength="50"><br />
     <label>Datum:</label><input type="text" name="datum" value="2010-08-25" maxlength="10"><br />
     <label>&nbsp;</label><input type="submit" value="Přidej"><input type="reset" value="Reset">
</form>
<hr />
<br />
<center>
<table frame="border" rules="rows" bordercolor="white">
<tr>
<td width="50">Pořadí:</td><td width="100">Datum:</td><td width="150">Jméno:</td><td width="100">Délka:</td><td>Místo:</td>
</tr>

<?php
   $sql = "SELECT * FROM `longlinersclub-pracovni` ORDER BY datum ASC";
   $req = $link->query($sql);
   if($req){
       $i = 1;
        while($zaznam = $req->fetch_assoc()){
            echo "<tr>\n";
            echo "  <td>".$i."</td>\n";
            echo "  <td>".$zaznam["datum"]."</td>\n";
            if(isset($zaznam["id_u"])){
                $id_u = $zaznam["id_u"];
                echo "  <td><a href=\"/denicek-$id_u\">".$zaznam["jmeno"]."</a></td>\n";
            }else{
                echo "  <td>".$zaznam["jmeno"]."</td>\n";
            }
            echo "  <td>".$zaznam["delka"]."</td>\n";
            echo "  <td>".$zaznam["misto"]."</td>\n";
            echo "</tr>\n";
            $i++;
        }
   }
?>
    
</table>
</center>
<br />
<a href="/clanek-47" title="Zpět na článek Longliners Club - pracovní verze">Zpět na článek Longliners Club - pracovní verze</a>