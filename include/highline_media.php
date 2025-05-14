<fieldset>
<legend>
Přidání fotek, článků a videí k highlině
</legend>
<form method="post" action="form_zpr.php">
  <input type="hidden" name="akce" value="highline_media_add">
  <input type="hidden" name="id_l" value="<?php if(isset($id_l)){echo $id_l;} ?>">  
  <select name="typ">    
    <option value="foto">fotky      
    <option value="video">video      
    <option value="clanek">článek    
  </select>  popis
  <input type="text" name="popis">adresa
  <input type="text" name="adresa">
  <input type="submit" value="Přidej">
</form>
</fieldset>