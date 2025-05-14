<?php
session_start();
include("include/class/ClanekEdit.php");
  if(isset($_SESSION["clanek_edit"])){
      $clanek = unserialize($_SESSION["clanek_edit"]);
  }else{
      echo "<p class=\"error\">Článek neexistuje</p>\n";
  }
  switch($clanek->getCast()){
    case 1:
        include("include/clanek_edit_1.php");
    break;
    case 2:
        include("include/clanek_edit_2.php");
    break;
    case 3:
        include("include/clanek_edit_3.php");
    break;    
  }
?>
