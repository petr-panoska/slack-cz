<?php
include "include/databaze.php";
$id_clanku = $_GET["id"];
$sql = "SELECT * FROM clanky WHERE id=$id_clanku";
$req = $link->query($sql);
if($req){
  $zaznam = $req->fetch_assoc();
  echo "<h2>".$zaznam["nazev"]."</h2>";
  echo stripslashes(htmlspecialchars_decode($zaznam["text"]));
}

echo "<hr/>\n";
include("include/clanek_koment_add.php");
echo "<hr/>\n";
include("include/clanek_koment_list.php");
?>