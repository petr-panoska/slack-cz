<?php
include("../include/databaze.php");

$q = $_GET["term"];

$tmp = $q;

if(($i=strpos($q, ' to '))!=false){
  $tmp = substr($q, 0, $i + 4);
  $q = substr($q, $i+4);
}else if(($i=strpos($q, ' 2 '))!=false){
  $tmp = substr($q, 0, $i + 3);
  $q = substr($q, $i+3);
}else if(($i=strpos($q, ' 4 '))!=false){
  $tmp = substr($q, 0, $i + 3);
  $q = substr($q, $i+3);
}else exit;

$sql = "SELECT nick FROM uzivatel WHERE nick LIKE ('$q%') GROUP BY nick LIMIT 10" ;
$req = $link->query($sql);
 
$json = '[';
$first = true;
while ($row = $req->fetch_assoc())
{
if (!$first) { $json .= ','; } else { $first = false; }
$json .= '{"value":"'.$tmp.$row['nick'].'"}';
}
$json .= ']'; 
echo $json;
?>
