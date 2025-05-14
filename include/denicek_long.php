<h3>Longline</h3>
<table id="myTable" class="tablesorter">
  <thead>
    <tr>
      <td width="320px">místo</td>
      <td width="70px">délka</td>
      <td width="80px">styl</td>
      <td width="100px">datum</td>
    </tr>
  </thead>
  <tbody>
<?php
  $sql = "SELECT * FROM longline WHERE uzivatel_id=$id_u ORDER BY datum DESC";
  $req = $link->query($sql);
  while($zaznam = $req->fetch_assoc()){
    $id_l = $zaznam["id"];
    echo "<tr>\n";
    echo "  <td>".$zaznam["misto"]."</td>\n";
    echo "  <td>".$zaznam["delka"]."m</td>\n";
    echo "  <td>".$zaznam["styl"]."</td>\n";
    echo "  <td>".$zaznam["datum"]."</td>\n";
    echo "  <td>";
    if($id_u == $_SESSION["id"]){
      echo "<a href=\"form_zpr.php?akce=smazat_prechod_longline&id=$id_l\" onclick=\"return(confirm('Opravdu chcete smazat tento přechod?'));\"><img src=\"img/ico/trash.png\" alt=\"smazat přechod\"></a>";
    }
    echo "  </td>";
    echo "</tr>\n";
  }
    ?>
  </tbody>
</table>
<hr />
<?php
if(isset($_SESSION["username"]) && $_SESSION["username"]==$uzivatel["nick"]){
  include("include/longline_add.php");
}
?>
