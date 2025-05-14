<h3>Highline</h3>
<table>
<tr>
<td width="250px"><a href="index.php?str=denicek&amp;id_u=<?php echo $id_u."&amp;sorth=1" ?>">název</a></td>
<td width="70px"><a href="index.php?str=denicek&amp;id_u=<?php echo $id_u."&amp;sorth=2" ?>">délka</a></td>
<td width="70px"><a href="index.php?str=denicek&amp;id_u=<?php echo $id_u."&amp;sorth=3" ?>">výška</a></td>
<td width="80px"><a href="index.php?str=denicek&amp;id_u=<?php echo $id_u."&amp;sorth=4" ?>">styl</a></td>
<td width="100px"><a href="index.php?str=denicek&amp;id_u=<?php echo $id_u."&amp;sorth=5" ?>">datum</a></td>
</tr>
<?php
  $sql = "SELECT highline_id, highline_prechody.datum, styl, poznamka, jmeno, delka, vyska FROM highline_prechody JOIN highline ON highline_prechody.highline_id=highline.id WHERE highline_prechody.uzivatel_id=$id_u ";
  if(isset($_GET["sorth"])){
    switch($_GET["sorth"]){
      case "1":
        $sql.= " ORDER BY jmeno ASC";
      break;
      case "2":
        $sql.= " ORDER BY delka DESC";
      break;
      case "3":
        $sql.= " ORDER BY vyska DESC";
      break;
      case "4":
        $sql.= " ORDER BY styl DESC";
      break;
      case "5":
        $sql.= " ORDER BY datum DESC";
      break;
    }
  }else{
    $sql.= " ORDER BY datum DESC";
  }
  $req = $link->query($sql);
  while($zaznam = $req->fetch_assoc()){
    $id_l=$zaznam["highline_id"];
    echo "<tr>\n";
    echo "  <td><a href=\"http://book.slack.cz/highlines/detail/$id_l\">".$zaznam["jmeno"]."</a></td>\n";
    echo "  <td>".$zaznam["delka"]." m</td>\n";
    echo "  <td>".$zaznam["vyska"]." m</td>\n";
    echo "  <td>".$zaznam["styl"]."</td>\n";
    echo "  <td>".$zaznam["datum"]."</td>\n";
    echo "  <td>";
    if($id_u == $_SESSION["id"]){
      echo "<a href=\"form_zpr.php?akce=smazat_prechod&amp;id_l=$id_l&amp;id_u=$id_u&amp;back=denicek\" onclick=\"return(confirm('Opravdu chcete smazat tento přechod?'));\"><img src=\"img/ico/trash.png\" alt=\"smazat přechod\"></a>";
    }
    echo "  </td>";
    echo "</tr>\n";
  }
?>
</table>