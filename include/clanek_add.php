<?php
session_start();
include_once("include/class/ClanekAdd.php");
include_once("include/databaze.php");

if(!isset($_SESSION["clanek_add"])){
    $clanek = new ClanekAdd($link);
    $clanek->nastavCast(1);
    $_SESSION["clanek_add"] = serialize($clanek); 
}else{
    $clanek = unserialize($_SESSION["clanek_add"]);
}

switch($clanek->getCast()){
  case 1:
    echo "<h2>1. část</h2>";
    include "include/clanek_add_1.php";
  break;
  case 2:
    echo "<h2>2. část</h2>";
    include "include/clanek_add_2.php";
  break;
  case 3:
    include "include/clanek_add_3.php"; 
  break;
}
?>