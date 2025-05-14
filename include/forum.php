<?php
/* zpracovani pridani prispevku */
include_once("include/fce.php");
include("include/databaze.php");         
if((isset($_POST["akce"])) && ($_POST["akce"]=="forum_add")){
  if(isset($_POST["jmeno"])){     $jmeno = addslashes(htmlspecialchars($_POST["jmeno"])); }else{$jmeno="";}
  if(isset($_POST["email"])){     $email = addslashes(htmlspecialchars($_POST["email"])); }
  if(isset($_POST["www"])){       $www =   addslashes(htmlspecialchars($_POST["www"])); }
  if(isset($_POST["prispevek"])){ $text= $_POST["prispevek"]; }
  if(isset($_SESSION["username"])){ $login = $_SESSION["username"]; }else{$login = "";};
  if(isset($_SESSION["id"])){ $id_u = $_SESSION["id"]; }else{$id_u = "NULL";};
  if(isset($_POST["token"])){ $token = $_POST["token"]; }else{$token = ""; }
  $datum = date("Y-m-d H:i:s");
  $ip = $_SERVER["REMOTE_ADDR"];
//uprava textu  
  $text = vloz_smajliky($text);
  $text = preg_replace('#(http://|ftp://|(www\.))([\w\-]*\.[\w\-\.]*([/?][^\s]*)?)#e',"'<a href=\"'.('\\1'=='www.'?'http://':'\\1').'\\2\\3\">'.((strlen('\\2\\3')>23)?(substr('\\2\\3',0,20).'&hellip;'):'\\2\\3').'</a>'",$text);
  $text = nl2br($text);
  //$text = preg_replace("#[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]#ies", "'<a href=\"\\0\">'.(strlen(\"\\0\") > 30 ? (substr(\"\\0\", 0, 30)).\"...\" : \"\\0\").'</a>'", $text);
  $text = addslashes(htmlspecialchars($text));
  if($text!="" && $jmeno!="" && dvojiOdeslani($token)){
    if(!isset($_SESSION["username"])){
      /* pokud neni uzivatel prihlasen zkontroluj spravnost kodu z obrazku */
      if(isset($_POST["kod"]) && isset($_SESSION["forum"]["kod"]) && $_POST["kod"]==$_SESSION["forum"]["kod"]){
        $sql = "INSERT INTO forum VALUES (NULL, \"$jmeno\", \"$email\", \"$www\", \"$text\", \"$datum\", \"$login\", $id_u, \"$ip\")";
        $req = $link->query($sql);
        unset($_SESSION["forum"]);
      }
    }else{
        $sql = "INSERT INTO forum VALUES (NULL, \"$jmeno\", \"$email\", \"$www\", \"$text\", \"$datum\", \"$login\", $id_u, \"$ip\")";
        $req = $link->query($sql);
        unset($_SESSION["forum"]);
    }
  }else{
    $_SESSION["forum"]["text"]=$text;
    $_SESSION["forum"]["jmeno"]=$jmeno;
    $_SESSION["forum"]["email"]=$email;
    $_SESSION["forum"]["www"]=$www;  
  }
}
/* konec zpracovani pridani prispevku */

/*
 * dvoji odeslani formulare
 */
 $token = md5(time() + rand(1,10000));

?> 
<h2>Fórum</h2>
<div id="vkladani">              
  <div id="forum_form">                                    
    <form method="post" action="/forum-1" name="pridej_prispevek">                                                             
      <input type="hidden" name="token" value="<?php echo $token; ?>">
      <input type="hidden" name="akce" value="forum_add">                          
        <label class="forum">Jméno:</label>
<input style="width:210px;" type="text" id="forumJmenoNasept" name="jmeno" value="<?php
          if(isset($_SESSION["username"])){
            echo $_SESSION["username"];
          }else if(isset($_SESSION["forum"]["jmeno"])){
            echo $_SESSION["forum"]["jmeno"];
          }?>">
                  <br />       
        <label class="forum">Email:</label><input type="text" name="email" style="width:210px;" value="<?php if(isset($_SESSION["forum"]["email"])){echo $_SESSION["forum"]["email"];}  ?>"><br>
        <label class="forum">WWW:</label><input type="text" name="www" style="width:210px;" value="<?php if(isset($_SESSION["forum"]["www"])){echo $_SESSION["forum"]["www"];} ?>"><br>
        <textarea name="prispevek" style="width:278px; white-space: pre;" rows="4" cols="10"><?php if(isset($_SESSION["forum"]["text"])){echo $_SESSION["forum"]["text"];} ?></textarea><br>                                                           
        <?php 
            if(!isset($_SESSION["username"])){
                echo '<label class="forum" style="width:140px;"><img src="include/forum_kod.php" width="140px" alt="Kontrola proti spamu"></label><label class="forum" style="width: 40px; margin: 10px 0 0 0;">Kód:</label><input type="text" maxlength="5" name="kod" style="width:80px; margin:10px 0 0 0;"><br />';
            }
         ?>        
        <label class="forum">&nbsp;</label>
          <input type="submit" value="Odeslat" class="tlacitko">
          <input type="reset" value="Vymaž" class="tlacitko">                                                      
    </form>              
  </div>                          
  <div id="smajlici">                           
    <a href="javascript:smajlik('*1*')">                                       
      <img src="img/forum/smiles/1.gif" alt="*1*"></a>                           
    <a href="javascript:smajlik('*2*')">                                       
      <img src="img/forum/smiles/2.gif" alt="*2*"></a>                           
    <a href="javascript:smajlik('*3*')">                                       
      <img src="img/forum/smiles/3.gif" alt="*3*"></a>                           
    <a href="javascript:smajlik('*4*')">                                       
      <img src="img/forum/smiles/4.gif" alt="*4*"></a>                           
    <a href="javascript:smajlik('*5*')">                                       
      <img src="img/forum/smiles/5.gif" alt="*5*"></a>                           
    <a href="javascript:smajlik('*6*')">                                       
      <img src="img/forum/smiles/6.gif" alt="*6*"></a>                           
    <a href="javascript:smajlik('*7*')">                                       
      <img src="img/forum/smiles/7.gif" alt="*7*"></a>                           
    <a href="javascript:smajlik('*8*')">                                       
      <img src="img/forum/smiles/8.gif" alt="*8*"></a>                           
    <a href="javascript:smajlik('*9*')">                                       
      <img src="img/forum/smiles/9.gif" alt="*9*"></a>                           
    <a href="javascript:smajlik('*10*')">                                       
      <img src="img/forum/smiles/10.gif" alt="*10*"></a>                           
    <a href="javascript:smajlik('*11*')">                                       
      <img src="img/forum/smiles/11.gif" alt="*11*"></a>                           
    <a href="javascript:smajlik('*12*')">                                       
      <img src="img/forum/smiles/12.gif" alt="*12*"></a>                           
    <a href="javascript:smajlik('*13*')">                                       
      <img src="img/forum/smiles/13.gif" alt="*13*"></a>                           
    <a href="javascript:smajlik('*14*')">                                       
      <img src="img/forum/smiles/14.gif" alt="*14*"></a>                           
    <a href="javascript:smajlik('*16*')">                                       
      <img src="img/forum/smiles/16.gif" alt="*16*"></a>                           
    <a href="javascript:smajlik('*21*')">                                       
      <img src="img/forum/smiles/21.gif" alt="*21*"></a>                           
    <a href="javascript:smajlik('*24*')">                                       
      <img src="img/forum/smiles/24.gif" alt="*24*"></a>                           
    <a href="javascript:smajlik('*31*')">                                       
      <img src="img/forum/smiles/31.gif" alt="*31*"></a>                           
    <a href="javascript:smajlik('*33*')">                                       
      <img src="img/forum/smiles/33.gif" alt="*33*"></a>                           
    <a href="javascript:smajlik('*70*')">                                       
      <img src="img/forum/smiles/70.gif" alt="*70*"></a>                           
    <a href="javascript:smajlik('*111*')">                                       
      <img src="img/forum/smiles/111.gif" alt="*111*"></a>                           
    <a href="javascript:smajlik('*144*')">                                       
      <img src="img/forum/smiles/144.gif" alt="*144*"></a>                           
    <a href="javascript:smajlik('*171*')">                                       
      <img src="img/forum/smiles/171.gif" alt="*171*"></a>                           
    <a href="javascript:smajlik('*178*')">                                       
      <img src="img/forum/smiles/178.gif" alt="*178*"></a>                           
    <a href="javascript:smajlik('*251*')">                                       
      <img src="img/forum/smiles/251.gif" alt="*251*"></a>              
  </div>
</div>
<div class="clear"></div>
<hr />
<?php 
/* strankovani */
if(isset($_SESSION["strana_fora"])){
  $strana = $_SESSION["strana_fora"];
  unset($_SESSION["strana_fora"]);
}
if(isset($_GET["strana"])){
  $strana = $_GET["strana"];
}else{ 
  $strana = 1;
}
$pocet_prispevku = $link->query("SELECT * FROM forum")->num_rows;
$prispevku_na_stranu = 15;
$pocet_stran = ceil($pocet_prispevku / $prispevku_na_stranu);
/* vypsani prispevku */
$sql = "SELECT * FROM forum ORDER BY datum DESC LIMIT ".$prispevku_na_stranu*($strana-1).",$prispevku_na_stranu";
$req = $link->query($sql);
while($zaznam = $req->fetch_assoc()){  
  $text = stripslashes(htmlspecialchars_decode($zaznam["text"]));
  $id_u = $zaznam["id_u"];
  echo '<div class="forum_prispevek">';
  echo '<div class="forum_fotka">'; 
 // echo '  <tr style="background: url(img/forum_info.jpg) center center repeat-x;">';
 
  if(file_exists("uzivatel/$id_u/profil_foto.jpg")){
     $profil_foto = "uzivatel/$id_u/profil_foto.jpg";
  }else{ $profil_foto = "img/ico/profil.jpg"; }
  if(file_exists("uzivatel/$id_u/profil_foto_full.jpg")){
    echo "<a href=\"uzivatel/$id_u/profil_foto_full.jpg\" rel=\"lightbox\" title=\"".$zaznam["nick"]."\"><img class=\"obr_n\" src=\"$profil_foto\" alt=\"".$zaznam["nick"]."\"></a>\n";
  }else{
    echo "<img src=\"$profil_foto\" alt=\"".$zaznam["nick"]."\">";
  }
  echo '   </div>';
  echo '   <table class= "prispevek" cellspacing="0" cellpadding="0"><tbody><tr>'; 
  if($id_u){  
    echo '    <td align="left"><a class="bez_podtrzeni" href="index.php?str=denicek&amp;id_u='.$id_u.'">'.stripslashes(htmlspecialchars_decode($zaznam["jmeno"])).'</a>';
  }else{
    echo '    <td align="left">'.stripslashes(htmlspecialchars_decode($zaznam["jmeno"]));
  }
  if($zaznam["email"]!=""){
    echo "<a style=\"margin-left: 5px\" href=\"mailto:".stripslashes(htmlspecialchars_decode($zaznam["email"]))."\">".'<img src="img/forum/mail.png" alt="E-Mail"></a>';
  }
  if($zaznam["www"]!=""){
    echo "<a style=\"margin-left: 5px\" href=\"".stripslashes(htmlspecialchars_decode($zaznam["www"]))."\">".'<img src="img/forum/home.png" alt="www"></a>';
  }
  /*odkaz na smazani prispevku*/
  if(isset($_SESSION["username"])){
    if($_SESSION["username"] == $zaznam["nick"] || isAdmin($_SESSION["username"])){
      echo '<a href="form_zpr.php?akce=forum_del&id='.$zaznam["id"]."\" onclick=\"return(confirm('Opravdu chcete vymazat tento příspěvek ?'));\"><img src=\"img/ico/trash.png\" alt=\"Smazat příspěvek\"></a>";
      $_SESSION["strana_fora"]=$strana;
    }
  } 
  /*konec mazani prispevku*/
  echo '    </td>';
  echo '    <td align="right"><font size="2">'.$zaznam["datum"].'</font></td>';
  echo '    </tr><tr>';  
  echo '    <td colspan="2">'.$text.'</td>';
  echo '   </tr>';
  echo '  </tbody></table>'; 
  echo '  </div><div class="clear"></div>';
  echo ' <hr>';  
}
  echo "<div class=\"odstrankovani\">";
echo "<ul>";
//Predchozi
if(($strana-1) > 0){ echo "<li class=\"dalsi-stranka\"><a href=\"forum-".($strana-1)."\">« Předchozí</a></li>"; }
else {echo "<li class=\"zruseny-odkaz\">« Předchozí</li>"; }
//Strany
if($pocet_stran>=11){
    if($strana<5){$a=1;}else{$a=$strana-5;}
    if($strana+5>$pocet_stran){$b=$pocet_stran;}else{$b=$strana+5;}
    while($a<=$b){
        if($strana==$a){
            echo "<li class=\"aktualni-stranka\">".($a)."</li>";    
        }else{
            echo "<li><a href=\"forum-".($a)."\">".($a)."</a></li>";
        }
    $a++; 
    }
}else{
    $i = 1;
    while($i<$pocet_stran){
        if($strana==$i){
            echo "<li class=\"aktualni-stranka\">".($i)."</li>";    
        }else{
            echo "<li><a href=\"forum-".($i)."\">".($i)."</a></li>";
        } 
    $i++;
    }
}
//Nasledujici
if($strana<$pocet_stran){
    echo "<li class=\"dalsi-stranka\"><a href=\"forum-".($strana+1)."\">"."Další »</a></li>"; 
}else{
    echo "<li class=\"zruseny-odkaz\">Další »</li>"; 
}
echo "</ul>";
echo "</div>";
?>
<script language="javascript" type="text/javascript">
<!--
function smajlik(text) {
if (document.pridej_prispevek.prispevek.createTextRange && document.pridej_prispevek.prispevek.caretPos)
{
  var caretPos = document.pridej_prispevek.text.caretPos;
  caretPos.text =  caretPos.prispevek.charAt(caretPos.text.length - 1) == ' ' ? text + ' ' : text;
}
else
   document.pridej_prispevek.prispevek.value += text;
   document.pridej_prispevek.prispevek.focus(caretPos)
}
//-->
</script>