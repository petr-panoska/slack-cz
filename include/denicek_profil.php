<?php
  echo "<table border=\"0\">";
  echo "<tr>\n";
  echo "<td width=\"100\">Jméno:</td><td width=\"350\">".$uzivatel["jmeno"]."</td><td rowspan=\"7\">";
  if(file_exists("uzivatel/$id_u/profil_foto.jpg")){
    echo "<img src=\"uzivatel/$id_u/profil_foto.jpg\" alt=\"".$uzivatel["nick"]."\">";
  }else{ echo "<img src=\"img/ico/profil.jpg\" alt=\"Profilová fotka nenalezena.\">";}
  echo "</td>\n";
  echo "</tr><tr>\n";
  echo "<td>Příjmení:</td><td>".$uzivatel["prijmeni"]."</td>";
  echo "</tr><tr>\n";
  echo "<td>E-mail:</td><td><img src=\"include/email.php?id_u=$id_u\" alt=\"email\"></td>";
  echo "</tr><tr>\n";
  echo "<td>Nick:</td><td>".$uzivatel["nick"]."</td>";
  echo "</tr><tr>\n";
  echo "<td>Rok narození:</td><td>".$uzivatel["rok_nar"]."</td>";
  echo "</tr><tr>\n";
  echo "<td>Telefon:</td><td><img src=\"include/telefon.php?id_u=$id_u\" alt=\"telefon\"></td>";
  echo "</tr><tr>\n";
  echo "<td>Město:</td><td>".$uzivatel["mesto"]."</td>";
  echo "</tr>";
  echo "</table>";
?>
<hr />
<?php
include("include/denicek_foto.php");
?>