<?php

require('class/cImageCropper.php');

if (isset($_GET['x1'])) {
	
	$image = 'img/boxsters.jpg';
	
	$x1 = $_GET['x1'];
	$y1 = $_GET['y1'];
	$newW = $_GET['x2'] - $_GET['x1'];
	$newH = $_GET['y2'] - $_GET['y1'];
	$resW = $_GET['width'];
	$resH = $_GET['height'];
	
	$orezavatko = new cImageCropper();
	$picture = $orezavatko->crop($x1,$y1,$newW,$newH,$image);
	if ( ($resW != $newW) || ($resH != $newH) ) {
		$picture = $orezavatko->resizeImage($resW, $resH, $newW, $newH, $picture, null, 80);
	}
	
	if ($picture) {
		
		header('Content-type: image/jpeg');
		imagejpeg($picture,null,80);
		
	} else {
		die ($orezavatko->getLastError());
	}
	
}
?>