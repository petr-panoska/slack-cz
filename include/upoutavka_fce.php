<?
function pripojdatabazi(){
  mysql_connect ("localhost", "gslackcz", "xmy52ar245");
  mysql_select_db("gslackczdb");
} 

function upoutavka(){
	pripojdatabazi();
	$sql="SELECT hodnota FROM config WHERE parametr='upoutavka'";
	$vysledek=mysql_query($sql) or die (mysql_error());
	$upoutavka=mysql_fetch_array($vysledek);
	return $upoutavka["hodnota"];
}
?>