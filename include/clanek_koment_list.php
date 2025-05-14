<?php
include_once("include/databaze.php");
include("include/fce.php");
if(isset($_GET["id"])){
  $id=$_GET["id"];
  $sql = "SELECT id, id_u, id_c, nick, datum, text FROM clanky_koment WHERE id_c=$id ORDER BY datum DESC";
  $req = $link->query($sql);
  if($req){
    while($zaznam=$req->fetch_assoc()){
      if($zaznam["id_u"]!=NULL){
        $id_u = $zaznam["id_u"];        
// Prispevek        
        echo '<div class="forum_prispevek">';
        echo '<div class="forum_fotka">'; 
        if(file_exists("uzivatel/$id_u/profil_foto.jpg")){
          $profil_foto = "uzivatel/$id_u/profil_foto.jpg";
        }else{ $profil_foto = "img/ico/profil.jpg"; }
        echo "<img src=\"$profil_foto\" alt=\"".$zaznam["nick"]."\">";
        echo '</div>';
        echo '   <table class= "prispevek" cellspacing="0" cellpadding="0"><tbody><tr>'; 
        echo '    <td align="left"><a class="bez_podtrzeni" href="index.php?str=denicek&id_u='.$id_u.'">'.stripslashes(htmlspecialchars_decode($zaznam["nick"])).'</a>';
        if(((isset($_SESSION["id"])) && $zaznam["id_u"]==$_SESSION["id"]) || isAdmin($_SESSION["username"])){
          $id = $zaznam["id"];
          $id_c = $zaznam["id_c"];
          echo "<a href=\"form_zpr.php?akce=clanek_koment_del&amp;id=$id&amp;id_c=$id_c\" onclick=\"return(confirm('Opravdu chcete vymazat tento příspěvek ?'));\"><img src=\"img/ico/trash.png\"></a>";
        }
        echo '    </td>';
        echo '    <td align="right"><font size="2">'.$zaznam["datum"].'</font></td>';
        echo '    </tr><tr>';  
        echo '    <td colspan="2">'.stripslashes(htmlspecialchars_decode($zaznam["text"])).'</td>';
        echo '   </tr>';
        echo '  </tbody></table>'; 
        echo '  </div><div class="clear"></div>';
        echo ' <hr>';  
        }else{
// neregistrovany uzivatel        
        echo '<div class="forum_prispevek">';
        echo '<div class="forum_fotka">'; 
        $profil_foto = "img/ico/profil.jpg";
        echo "<img src=\"$profil_foto\" alt=\"".$zaznam["nick"]."\">";
        echo '</div>';
        echo '   <table class= "prispevek" cellspacing="0" cellpadding="0"><tbody><tr>'; 
        echo '    <td align="left">'.stripslashes(htmlspecialchars_decode($zaznam["nick"]));
        if(isAdmin($_SESSION["username"])){
          $id = $zaznam["id"];
          $id_c = $zaznam["id_c"];
          echo "<a href=\"form_zpr.php?akce=clanek_koment_del&amp;id=$id&amp;id_c=$id_c\" onclick=\"return(confirm('Opravdu chcete vymazat tento příspěvek ?'));\"><img src=\"img/ico/trash.png\"></a>";
        }
        echo '    </td>';
        echo '    <td align="right"><font size="2">'.$zaznam["datum"].'</font></td>';
        echo '    </tr><tr>';  
        echo '    <td colspan="2">'.stripslashes(htmlspecialchars_decode($zaznam["text"])).'</td>';
        echo '   </tr>';
        echo '  </tbody></table>'; 
        echo '  </div><div class="clear"></div>';
        echo ' <hr>'; 
      }
    }
  }
}
?>
