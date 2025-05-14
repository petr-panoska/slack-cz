<h2>Přidávání novinek</h2>
<form method="post" action="form_zpr.php" ENCTYPE="multipart/form-data">    
  <input type="hidden" name="akce" value="novinka_add">
  <input type="hidden" name="id" value="<?php if(isset($_SESSION["novinka_add"]["id"])){echo $_SESSION["novinka_add"]["id"];} ?>">
  <label>Název:
  </label>
  <input type="text" name="nazev" value="<?php if(isset($_SESSION["novinka_add"]["nazev"])){echo $_SESSION["novinka_add"]["nazev"];} ?>" /><br />
  <label>Odkaz:
  </label>
  <input type="text" name="odkaz" value="<?php if(isset($_SESSION["novinka_add"]["odkaz"])){echo $_SESSION["novinka_add"]["odkaz"];} ?>" /><br />
  <label>Náhled:
  </label>
  <input type="file" name="nahled" /><br />
  <label>Popis:
  </label>
<textarea name="popis"><?php if(isset($_SESSION["novinka_add"]["popis"])){echo $_SESSION["novinka_add"]["popis"];} ?></textarea><br />
  <label>&nbsp</label><input type="submit" value="<?php if(isset($_SESSION["novinka_add"]["id"])){ echo "Upravit"; }else{ echo "Přidej"; } ?>">
</form>

<?php
if(isset($_SESSION["novinka_add"]["id"])){
  $id = $_SESSION["novinka_add"]["id"];
  echo '<p>Náhledová fotka:</p>';
  echo "<img src=\"novinky/nahled_$id.jpg\" alt=\"Náhledová fotka videa\">";
}
unset($_SESSION["novinka_add"]);
?>