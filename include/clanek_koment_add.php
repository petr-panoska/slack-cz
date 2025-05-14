<h3>Komentáře</h3>
<form method="post" action="form_zpr.php">
<input type="hidden" name="id_c" value="<?php if(isset($_GET["id"])){ echo $_GET["id"]; } ?>">
<input type="hidden" name="akce" value="clanek_koment_add">
<label>Přezdívka:</label><input type="text" name="nick" style="background-color:rgb(82,128,177);" value="<?php if(isset($_SESSION["username"])){echo $_SESSION["username"];} ?>" <?php if(isset($_SESSION["username"])){echo 'disabled="disable"';} ?>><br />
<label>Text:</label><textarea name="text" style="width:400px; height:100px;background-color:rgb(82,128,177);"></textarea><br />
<label>&nbsp;</label>
<?php 
            if(!isset($_SESSION["username"])){
                echo '<label class="forum" style="width:140px;"><img src="include/clanek_koment_kod.php" width="140px"></label><label class="forum" style="width: 40px; margin: 10px 0 0 0;">Kód:</label><input type="text" maxlength="5" name="kod" style="background-color:rgb(82,128,177);width:80px; margin:10px 0 0 0;"><br />';
            }
?>
<label>&nbsp;</label><input type="submit" value="Přidej">
</form>