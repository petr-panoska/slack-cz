<?php
  require("include/databaze.php");
  $id_u = $_GET["id_u"];
  $sql = "SELECT * FROM uzivatel WHERE id=$id_u LIMIT 1";
  $req = $link->query($sql);
  if($req){ $uzivatel = $req->fetch_assoc(); }
?>
<center><h2><?php echo $uzivatel["nick"]; ?></h2></center>
<br />

<div id="tabs">
    <ul>
        <li><a href="#profil"><span>Profil</span></a></li>
        <li><a href="#highline"><span>Highline</span></a></li>
        <li><a href="#longline"><span>Longline</span></a></li>
        <li><a href="#aktivity"><span>Aktivity</span></a></li>
    </ul>
    <div id="profil">
    <?php
      include("include/denicek_profil.php");
    ?>   
    </div>
    <div id="highline">
    <?php    
      include("include/denicek_high.php");
    ?>
    </div>
    <div id="longline">
    <?php
      include("include/denicek_long.php");
    ?>
    </div>
    <div id="aktivity">
      <?php 
        include("include/denicek_activ.php");
      ?>  
    </div>
</div>