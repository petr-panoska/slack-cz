<?php
session_start();

$obrazek = imagecreatefrompng("../img/forum/kontr-text.png");

for($i=0;$i<5;$i++){
    $str = chr(rand(65,90));
    $text.= $str;
    $textcolor = imagecolorallocate($obrazek,rand(0,130),rand(0,130),rand(0,130));
    imagettftext ($obrazek,rand(15,25),rand(-45,45),15+($i*38),35, $textcolor,"../font/calibri.ttf",$str) ;
}
$_SESSION["clanek_koment"]["kod"]=$text;

$text = NULL;
$str = NULL;


header("Content-type: image/png");

imagepng($obrazek);

imagedestroy ($obrazek)

  
?>