<?php

class cImageCropper {
	var $_lastError;
	
	function ImageCropper() { }
	
	function crop($x,$y,$xsize,$ysize,$source,$target = null,$quality = 80) {
	
		if (!file_exists($source)) {
			$this->_lastError = 'Nebyl nalezen zdrojovy obrбzek.';	
			return false;
		}
		
		$pic = @imagecreatefromjpeg($source);
		
		if ($pic) {
			
			$trimx = $x;
			$trimy = $y;
			$trimxsize = $xsize;
			$trimysize = $ysize;
			
			$outimage = imagecreatetruecolor($trimxsize, $trimysize);
			imagecopy($outimage, $pic, 0, 0, $trimx, $trimy, $trimxsize, $trimysize);
			
			if ($target) {
				$outimage = imagejpeg($outimage, $target, $quality);
			}
			
			return $outimage;
			
		} else {
		
			$this->_lastError = 'Nepodaшilo se otevшнt obrбzek! Chyba funkce imagecreatefromjpeg.';
			return false;
			
		}
	
	}
	
	function resizeImage($des_w, $des_h, $src_w, $src_h, $src_img, $desc_file, $desc_comp) {
		
		$des_img = imagecreatetruecolor($des_w, $des_h);
		
		imagecopyresampled($des_img, $src_img, 0, 0, 0, 0, $des_w, $des_h, $src_w, $src_h);
		
		if ($desc_file) {
			imagejpeg($des_img, $desc_file, $desc_comp);
			imagedestroy($des_img);
			return true;
		} else {
			return $des_img;
		}
		
	}
	
	function getLastError() {
		return $this->_lastError;
	}
}

?>