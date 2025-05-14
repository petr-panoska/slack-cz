<?php
include("../include/databaze.php");

$q = $_GET["term"];

$sql = "SELECT misto FROM longline WHERE misto LIKE ('%$q%') GROUP BY misto LIMIT 10" ;
$req = $link->query($sql);
 
$json = '[';
$first = true;
while ($row = $req->fetch_assoc())
{
if (!$first) { $json .= ','; } else { $first = false; }
$json .= '{"value":"'.$row['misto'].'"}';
}
$json .= ']'; 
echo $json;
?>
