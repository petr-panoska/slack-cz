<?php
include "include/fce.php";
include "include/databaze.php";

$sql = "SELECT * FROM fb_skupiny ORDER BY id";
if($reg = $link->query($sql))
{
    while($radek = $reg->fetch_assoc())
    {
        $nazev = $radek["nazev"];
        $odkaz = $radek["odkaz"];
        $popis = $radek["popis"];
        $id = $radek["id"];
        echo"<div class=\"novinka\">";
        echo"   <a href=\"$odkaz\">$nazev</a><br />";
        echo"   <img src=\"img/facebook/$id.jpg\"/>";
        echo"   <p>$popis</p>";
        echo"   <div class=\"clear\"></div>";
        echo"   <hr/>";
        echo"</div>";
    }
}

if(isset($_SESSION["username"]) && isAdmin($_SESSION["username"]))
{
    include "include/skupiny_pridej.php";
}

?>


<!--
<div class="novinka">
    <a href="!!!!">!!!!!!</a><br />
    <img src="img/facebook/08.jpg"/>
    <p>!!!!!</p>
    <div class="clear"></div>
    <hr />
</div>
-->