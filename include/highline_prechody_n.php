<fieldset>
<legend>Přidání přechodu</legend>
<form method="post" action="form_zpr.php">
  <input type="hidden" name="akce" value="highline_prechod_n_add">
  <input type="hidden" name="id_l" value="<?php echo $id_l ?>">
  <label>Přezdívka:</label>
  <input type="text" name="nick" value=""><br />
  <label>Datum:</label>
  <input type="text" name="datum" value="<?php echo date("Y-m-d"); ?>">
<br />
  <label>Styl:</label>
  <select name="styl">  
    <option value="OS fm">OS fm   
    <option value="OS">OS
    <option value="fm">fm
    <option value="one way">one way
  </select>
<br />
  <label>Poznámka:</label>
  <textarea name="poznamka">
  </textarea>
<br />
  <label>&nbsp;</label>
  <input type="submit" value="Přidej do deníčku">
</form>
</fieldset>