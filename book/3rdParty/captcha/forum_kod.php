<?php

session_start();
//error_reporting(E_ALL);
//ini_set("display_errors", 1);

$obrazek = imagecreatefrompng("kontr-text.png");

$text = "";
for ($i = 0; $i < 5; $i++)
{
    $str = chr(rand(65, 90));
    $text.= $str;
    $textcolor = imagecolorallocate($obrazek, rand(0, 130), rand(0, 130), rand(0, 130));
    imagettftext($obrazek, rand(15, 25), rand(-45, 45), 15 + ($i * 38), 35, $textcolor, __DIR__."/calibri.ttf", $str);
}
$_SESSION["forum"]["kod"] = $text;

//echo $text;

$text = NULL;
$str = NULL;


header("Content-type: image/png");

imagepng($obrazek);

imagedestroy($obrazek);

