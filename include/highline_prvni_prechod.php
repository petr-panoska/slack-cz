<?php
include("include/databaze.php");
if(isset($_GET["id"])){ $id_l=$_GET["id"]; }else{$id_l=null;}
?>
<hr />
<h2>První přechody HL</h2>
<h3>Registrovaný uživatel</h3>
<form method="post" action="form_zpr.php">  
  <input type="hidden" name="akce" value="prvniPrechodRegAdd">
  <input type="hidden" name="id_l" value="<?php echo $id_l; ?>">
  <label>Přezdívka:   
  </label>  
  <select name="id_u">
<?php
  $sql = "SELECT id_u, nick FROM uzivatel ORDER BY nick ASC";
  $req = $link->query($sql);
if($req){
  while($zaznam = $req->fetch_assoc()){
    echo '<option value="'.$zaznam["id_u"].'">'.$zaznam["nick"];
  }
}
?>  
  </select>
<br />
  <label>Styl:   
  </label>
  <select name="styl">
    <option value="OS fm">OS FM
    <option value="OS">OS
    <option value="fm">fm
    <option value="one way">one way
    <option value="-">-
  </select>
  <input type="submit" value="Přidej">
</form>
<br />

<h3>Neregistrovaný uživatel</h3>
<form method="post" action="form_zpr.php">  
  <input type="hidden" name="akce" value="prvniPrechodNeregAdd">  
  <input type="hidden" name="id_l" value="<?php echo $id_l; ?>">
  <label>Jméno:   
  </label>  
  <input type="text" name="nick"><br />
  <label>Styl:   
  </label>
  <select name="styl">
    <option value="OS fm">OS FM
    <option value="OS">OS
    <option value="fm">fm
    <option value="one way">one way
    <option value="-">-
  </select>
  <input type="submit" value="Přidej">
</form>

<hr />
<h3>První přechody</h3>
<?php
  $sql = "(SELECT id_u, nick FROM prvni_prechody_hl WHERE id_l=$id_l)";
  $req = $link->query($sql);
  if($req){
    while($zaznam = $req->fetch_assoc()){
      if($zaznam["id_u"]==null){
        $nick = $zaznam["nick"];
        echo $zaznam["nick"];
        echo "<a href=\"form_zpr.php?akce=prvniPrechodDel&amp;id_l=$id_l&amp;nick=$nick\" onclick=\"return(confirm('Opravdu chcete smazat tento přechod?'));\"><img src=\"img/ico/trash.png\" alt=\"smazat přechod\"></a><br />";
      }else{
        $nick = $zaznam["nick"];
        echo '<a href="/denicek-'.$zaznam["id_u"].'">'.$zaznam["nick"].'</a>';
        echo "<a href=\"form_zpr.php?akce=prvniPrechodDel&amp;id_l=$id_l&amp;nick=$nick\" onclick=\"return(confirm('Opravdu chcete smazat tento přechod?'));\"><img src=\"img/ico/trash.png\" alt=\"smazat přechod\"></a><br />";
      }
    }
  }
?>  

