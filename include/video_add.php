<form method="post" action="form_zpr.php" ENCTYPE="multipart/form-data">    
  <input type="hidden" name="akce" value="video_add" />
  <input type="hidden" name="id" value="<?php if(isset($_SESSION["video_add"]["id"])){echo $_SESSION["video_add"]["id"];} ?>"/>
  <label>Název:
  </label>
  <input type="text" name="nazev" value="<?php if(isset($_SESSION["video_add"]["nazev"])){echo $_SESSION["video_add"]["nazev"];} ?>" /><br />
  <label>Adresa:
  </label>
  <input type="text" name="adresa" value="<?php if(isset($_SESSION["video_add"]["adresa"])){echo $_SESSION["video_add"]["adresa"];} ?>" /><br />
  <label>Náhled:
  </label>
  <input type="file" name="nahled" /><br />
  <label>Typ:
  </label>
  <select name="typ">
    <option <?php if(isset($_SESSION["video_add"]["typ"]) & ($_SESSION["video_add"]["typ"] == "nase")){echo "selected=\"\"";} ?> value="nase">naše</option>
    <option <?php if(isset($_SESSION["video_add"]["typ"]) & ($_SESSION["video_add"]["typ"] == "zahranicni")){echo "selected=\"\"";} ?> value="zahranicni">zahraniční</option>
    <option <?php if(isset($_SESSION["video_add"]["typ"]) & ($_SESSION["video_add"]["typ"] == "gibbon")){echo "selected=\"\"";} ?>value="gibbon">gibbon</option>
  </select><br />
  <label>Popis:
  </label>
<textarea name="popis"><?php if(isset($_SESSION["video_add"]["popis"])){echo $_SESSION["video_add"]["popis"];} ?></textarea><br />
  <label>&nbsp</label><input type="submit" value="<?php if(isset($_SESSION["video_add"]["id"])){ echo "Upravit"; }else{ echo "Přidej"; } ?>"/>
</form>

<?php
if(isset($_SESSION["video_add"]["id"])){
  $id = $_SESSION["video_add"]["id"];
  echo '<p>Náhledová fotka:</p>';
  echo "<img src=\"video/video_$id.jpg\" alt=\"Náhledová fotka videa\">";
  unset($_SESSION["video_add"]);
}
?>