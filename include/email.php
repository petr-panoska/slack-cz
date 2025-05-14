<?php
require("databaze.php");
if(isset($_GET["id_u"])){
  $id_u = $_GET["id_u"];
}else{exit;}
$sql = "SELECT email FROM uzivatel WHERE id_u=$id_u LIMIT 1";
$req = $link->query($sql);
if($req){
  $email = $req->fetch_assoc();
}
header('Content-type: image/png');

// Create the image
$im = imagecreatetruecolor(400, 20);

// Create some colors
$white = imagecolorallocate($im, 255, 255, 255);
$grey = imagecolorallocate($im, 128, 128, 128);
$black = imagecolorallocate($im, 0, 0, 0);
imagefilledrectangle($im, 0, 0, 399, 29, $black);

// Replace path by your own font path
$font = '../font/calibri.ttf';

// Add some shadow to the text
imagettftext($im, 14, 0, 0, 15, $white, $font, $email["email"]);
imagecolortransparent($im, $black);

// Using imagepng() results in clearer text compared with imagejpeg()
imagepng($im);
imagedestroy($im);
?>
