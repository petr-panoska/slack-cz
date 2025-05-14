<?php
include "include/databaze.php";
if(isset($_GET["id"])){
  $id = $_GET["id"];
}
$sql = "SELECT * FROM highline WHERE id=$id";
$req = $link->query($sql);
if($req){
  $zaznam = $req->fetch_assoc();
}
?><h2>Úprava highline - 
  <?php echo $zaznam["jmeno"]; ?></h2>                   
<form method="post" action="form_zpr.php" ENCTYPE="multipart/form-data">                                                                        
    <input type="hidden" name="akce" value="highline_update">
    <input type="hidden" name="id_l" value="<?php echo $id; ?>">                                     
  <label>Jméno:</label>
  <input type="text" length="30" name="jmeno" value="<?php echo $zaznam["jmeno"]; ?>">                                                                                       </td>                                     
<br />                                     
  <label>Délka:</label>
  <input type="text" length="3" name="delka" value="<?php echo $zaznam["delka"]; ?>">                                                                                                    </td>                                     
<br />                                  
  <label>Výška:</label>
  <input type="text" length="3" name="vyska" value="<?php echo $zaznam["vyska"]; ?>">                                                                                                </td>                                  
<br />                                  
  <label>Expozice:</label>
  <input type="text" length="3" name="hodnoceni" value="<?php echo $zaznam["hodnoceni"]; ?>">                                                                                                </td>                                  
<br />                                  
  <label>Oblast:</label>
  <input type="text" length="30" name="oblast" value="<?php echo $zaznam["oblast"]; ?>"">                                          </td>                                  
<br />
  <label>GPS:</label>
  <input type="text" length="50" name="gps" value="<?php echo $zaznam["gps"]; ?>"">                                          </td>                                  
<br />                                  
  <label>Stát:</label>
  <input type="text" length="30" name="stat" value="<?php echo $zaznam["stat"]; ?>"">                                          </td>                                  
<br />
  <label>Speciální info:</label>
  <textarea rows="6" cols="30" name="info"><?php echo $zaznam["info"]; ?></textarea></td>                             
<br />                                                                              
  <label>Kotvení:</label><img src="img/highline/1.png"><input type="checkbox" name="kotveni_strom" <?php if(strchr($zaznam["kotveni"], "1")){ echo "checked=\"checked\""; } ?>><br />
  <label>&nbsp;</label><img src="img/highline/2.gif"><input type="checkbox" name="kotveni_kruh" <?php if(strchr($zaznam["kotveni"], "2")){ echo "checked=\"checked\""; } ?>><br />
  <label>&nbsp;</label><img src="img/highline/3.gif"><input type="checkbox" name="kotveni_skala" <?php if(strchr($zaznam["kotveni"], "3")){ echo "checked=\"checked\""; } ?>>
<br />
  <label>Autor:</label>
  <input type="text" length="3" name="autor" value="<?php echo $zaznam["autor"]; ?>"">                                          </td>                                  
<br />
  <label>Datum:</label>
  <input type="text" length="3" name="datum" value="<?php echo $zaznam["datum"]; ?>">                                          </td>                                  
<br />
  <label>Náhledová fotka:</label>
<?php 
  if(file_exists("line/high/".$zaznam["id"]."/nahled.jpg")){
    echo "<img src=\"line/high/".$zaznam["id"]."/nahled.jpg\"><br />";
  }
?>                                                     
  <label>&nbsp;</label><input TYPE="file" NAME="nahled">
<br />                                                                        
  <label>Fotka:</label>
<?php 
  if(file_exists("line/high/".$zaznam["id"]."/foto.jpg")){
    echo "<img src=\"line/high/".$zaznam["id"]."/foto.jpg\"><br />";
  }
?>
  <label>&nbsp;</label><input TYPE="file" NAME="fotka">
<br />
  <label>&nbsp;</label>
  <input type="submit" value="Uložit změny">
  </form>
<?php
include("include/highline_prvni_prechod.php");
?>