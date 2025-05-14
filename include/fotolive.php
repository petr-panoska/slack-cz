<?php
require_once("include/fce.php");
require("include/databaze.php");
/* Popisek */
 $sql = "SELECT popis FROM fotolive WHERE fotka='fotolive.jpg'";
 $req = $link->query($sql);
 if($req){
  $zaznam = $req->fetch_assoc();
 } 
?>
<h3>FOTO<span class="red">live</span></h3>
 <a href="fotolive/fotolive_full.jpg" rel="lightbox" title="<?php echo $zaznam["popis"]; ?>"><img src="fotolive/fotolive.jpg" alt="FOTOlive"></a>          
 <div class="popisekObr">
 <?php
 if(isset($_SESSION["username"]) && isAdmin($_SESSION["username"])){
 echo '<a href="index.php?str=fotolive_update"><img src="img/ico/edit.png"></a>';
 }
 echo $zaznam["popis"];
 ?></div>