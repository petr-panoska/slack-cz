<h2>Longline</h2>
<form method="get" action="index.php" name="razeni">
<input type="hidden" name="str" value="longline">
<label>Řadit podle </label>
<select name="sort" onchange="document.razeni.submit();">
  <option value="0" <?php if(isset($_GET["sort"]) && $_GET["sort"]=="0"){ echo 'selected="selected"'; }  ?>>přezdívky
  <option value="1" <?php if(isset($_GET["sort"]) && $_GET["sort"]=="1"){ echo 'selected="selected"'; }  ?>>rekordu
</select>
</form>
<table>
<tr>
<td><?php
    if(isset($_GET["sort"]))if( $_GET["sort"] == 1) echo"Pořadí";
?></td>
<td>Přezdívka</td>
<td>Rekord</td>
</tr>
<?php
  require("include/databaze.php");
  $sql = "SELECT uzivatel.id_u, nick, rekord FROM uzivatel LEFT JOIN(SELECT id_u, MAX(delka) AS rekord FROM longline GROUP BY id_u) AS rekordy ON rekordy.id_u=uzivatel.id_u";
if(isset($_GET["sort"])){ $razeni=$_GET["sort"]; }else{ $razeni="0"; }
switch($razeni){
  case "0":
    $sql.=" ORDER BY nick ASC";
  break;
  case "1":
    $sql.=" ORDER BY rekord DESC";
  break;
}
  $req = $link->query($sql);
  $i = 0;
  while($zaznam = $req->fetch_assoc()){
    $i++;  
    echo "<tr>\n";
    $id_u = $zaznam["id_u"];
    if($zaznam["rekord"]==NULL){
      $rekord = 0;
    }else{ $rekord = $zaznam["rekord"]; }
    echo "<td>".$i.".</td>" ;
    echo "  <td><a href=\"index.php?str=denicek&id_u=$id_u\">".$zaznam["nick"]."</td>\n";
    echo "  <td>".$rekord."m</td>\n";
    echo "</tr>\n";
  }
?>
</table>