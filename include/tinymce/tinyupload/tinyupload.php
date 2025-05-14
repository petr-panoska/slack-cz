<?php
//ini_set('display_errors', 'off');
session_start();

//###### Config ######
// pridafani clanku
if(isset($_SESSION["clanek_add"])){
  require("../../class/ClanekAdd.php");
  $clanek = unserialize($_SESSION["clanek_add"]);
  $album = $clanek->getAlbum();
  $absPthToDst = "http://www.slack.cz/clanky/$album/"; //The Absolute path (from the clients POV) to the destination folder.
  $absPthToDstSvr = "/home/ftponly/usr/gslackcz/web/clanky/$album/"; //The Absolute path (from the servers POV) to the destination folder. You will need to set permissions for this dir 0777 worked ok for me.
}
//editace článku
if(isset($_SESSION["clanek_edit"])){
  require("../../class/ClanekEdit.php");
  $clanek = unserialize($_SESSION["clanek_edit"]);
  $album = $clanek->getAlbum();
  $absPthToDst = "http://www.slack.cz/clanky/$album/"; //The Absolute path (from the clients POV) to the destination folder.
  $absPthToDstSvr = "/home/ftponly/usr/gslackcz/web/clanky/$album/"; //The Absolute path (from the servers POV) to the destination folder. You will need to set permissions for this dir 0777 worked ok for me.
}
//editace krouzku
if(isset($_SESSION["krouzek_edit"])){
  $absPthToDst = "http://www.slack.cz/krouzky/1/fotky/"; //The Absolute path (from the clients POV) to the destination folder.
  $absPthToDstSvr = "/home/ftponly/usr/gslackcz/web/krouzky/1/fotky/"; //The Absolute path (from the servers POV) to the destination folder. You will need to set permissions for this dir 0777 worked ok for me.
}
$absPthToSlf = 'http://www.slack.cz/include/tinymce/tinyupload/tinyupload.php'; //The Absolute path (from the clients POV) to this file.

function hasAccess(){
	/**
	If you need to do a securty check on your user here is where you should put the code.
	*/
	return true;
}

//###### You should not need to edit past this point ######
if($_GET["poll"]){
	$dh  = opendir($absPthToDstSvr);
	while (false !== ($filename = readdir($dh))) {
	  $files[] = $filename;
	}
	sort($files);
	
	//Filter out html files and directories.
	function filterHTML($var){
		if(is_dir($absPthToDstSvr . $var) or substr_count($var, '.html') > 0){
			return false;
		}
		else{
			return true;
		}
	}
	$files = array_filter($files, 'filterHTML');
	$str = '[';
	foreach ($files as $file){
		$str .= '{';
		$str .= '"url":"'. $absPthToDst . $file .'",';
		$str .= '"file":"'. $file .'"';
		$str .= '}, ';
	}
	$str .= ']';
	echo $str;
}
elseif (hasAccess()){
	if($_FILES['tuUploadFile']['tmp_name']){
		$target_path = $absPthToDstSvr. basename( $_FILES['tuUploadFile']['name']); 
		move_uploaded_file($_FILES['tuUploadFile']['tmp_name'], $target_path);
    include("SimpleImage.php");
    $image = new SimpleImage();
    $image->load($target_path);
    $image->resizeToWidth(550);
    $image->save($target_path);
	}
?>
<html>
<head>
<style type="text/css">
	body{
		font-size:10px;
		margin:0px;
		padding:0px;
		height:20px;
		overflow:hidden;
	}
</style>
<script type="text/javascript">
	window.onload = function(){
		parent.tuIframeLoaded();
	}
	function tuSmt(){
		filePath = '<?php echo $absPthToDst; ?>' + (document.getElementById('tuUploadFile').value).replace(/^.*[\/\\]/g, '');
		if(parent.tuFileUploadStarted(filePath, document.getElementById('tuUploadFile').value)){
			window.document.body.style.cssText = 'border:none;padding-top:100px';
			document.getElementById('tuUploadFrm').submit();
		}
	}
</script>
</head>
<body style="border:none;">
	<form enctype="multipart/form-data" method="post" action="<?php echo $absPthToSlf; ?>" id="tuUploadFrm">
		<div style="height:22px;vertical-align:top;">
			<input type="file" size="15" style="height:22px;" id="tuUploadFile" name="tuUploadFile" />
      <input type="button" value="Upload" onclick="javascript:tuSmt();" style="margin:0px 0px 20px 2px;border:1px solid #808080;background:#fff;height:20px;"/>
		</div>
	</form>
</body>
</html>
<?php
}
?>