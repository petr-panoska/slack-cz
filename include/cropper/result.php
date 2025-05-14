<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
	<title>Javascriptov� o�ez obr�zk� (Javascript Image Cropper)</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-2" />
	<link rel="StyleSheet" href="css/crop.css?1" media="all" type="text/css">
	<script type="text/javascript" src="js/imageCropper.js?1.1.5"></script>
</head>
<body>

<div id="stranka">

	<h1>O�ez obr�zku pomoc� javascriptu a PHP - v�sledek</h1>
	
	<p>V�pis o�ezan�ch obr�zk�:</p>
	
	<?php

	if (isset($_POST['crop'])) {
		
		foreach ($_POST['crop']['obrazek'] as $cropBox) {
			
			echo '<p class="croppedImg"><img src="makeimage.php?x1='.$cropBox['x1'].'&amp;y1='.$cropBox['y1'].'&amp;x2='.$cropBox['x2'].'&amp;y2='.$cropBox['y2'].'&amp;width='.$cropBox['w'].'&amp;height='.$cropBox['h'].'" width="'.$cropBox['w'].'" height="'.$cropBox['h'].'" alt="" /></p>';
			
		}
		
	}
	?>
	
	<h2>Odkazy:</h2>
	<ul>
		<li><a href="/priklady/orez-obrazku/">Zp�t na str�nku s o�ezem</a></li>
		<li><a href="imageCropper.zip">St�hnout zdrojov� soubory s p��kladem (96 KB, ZIP)</a></li>
		<li><a href="/weblog/index.php/orez-obrazku-pomoci-js-a-php/">Informace a popis skriptu</a></li>
	</ul>
	
</div>

<!-- statistika toplistu -->
<a href="http://www.toplist.cz" target="_top"><script type="text/javascript">
<!--
document.write ('<img src="http://toplist.cz/dot.asp?id=78871&amp;http='+escape(document.referrer)+'&amp;wi='+escape(window.screen.width)+'&he='+escape(window.screen.height)+'&amp;cd='+escape(window.screen.colorDepth)+'&amp;t='+escape(document.title)+'" width=1 height=1 border=0 alt="TOPlist"/>'); 
//--></script><noscript><img SRC="http://toplist.cz/dot.asp?id=78871" border="0" alt="TOPlist" width="1" height="1" /></noscript></a>

</body>
</html>
