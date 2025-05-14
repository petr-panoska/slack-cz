<?php
   require("databaze.php"); 
  if (!isset($_SESSION['prihlasit'])){ $_SESSION['prihlasit']=0;}
   
   
  if($_SESSION['prihlasit']==0){
echo '<form id="prihlaseniform" action="" method="post">
                        <fieldset id="prihlasenifield">
                            <legend id="prihlasenilegend">Přihlášení do SLACK.CZ</legend>
                            
                                <label class="formlabelM" for="username">Přezdívka:</label>
                                <input id="username" type="text" name="username" value="">
                            
                                <br>
                                <label class="formlabelM" for="pass">Heslo: </label>
                                <input id="pass" type="password" name="password" value="">
                                <br>
                                
                                <label id="trvale">Přihlásit se trvale na tomto PC: </label>
                                <input name="trvale" type="checkbox">
                                <br>
                                
                                <input id="prihlasit" name="prihlasit" type="submit" value="Přihlásit">              
                                <br>
                                <br>
                                <a class="zapomelHeslo" href="index.php?str=hesloNaMail">Zapomněli jste heslo?</a>    
                                <a class="registrovat" href="index.php?str=registrovat">Zaregistrovat</a>
                        </fieldset>
                         
                         
                    </form>
                   ';
}else{
echo '<form id="prihlaseniform" action="index.php" method="get">
        <fieldset>
            <legend id="prihlasenilegend">Přihlášení</legend>
            <p class="paddingMargin">Jste přihlášen jako: ';
    $id = $_SESSION['id'];
    $sql = "SELECT * FROM uzivatel WHERE id=$id";
    $result = mysqli_query($link, $sql);
    if($result){
       $row = mysqli_fetch_assoc($result);
      
       echo "<a href=\"index.php?str=denicek&id_u=$id\">".$row['nick']."</a>"; 
       echo "</p><p>";
       echo $row['jmeno']." ".$row['prijmeni'];
       echo "</p>";
        if(file_exists("uzivatel/$id/profil_foto.jpg")){
          $profil_foto = "uzivatel/$id/profil_foto.jpg";
        }else{ $profil_foto = "img/ico/profil.jpg"; }
       echo "<img src=\"$profil_foto\"/>";   
    }else{
        echo "Chyba v přihlášení.";
    }
    
    
    
echo '</span></p><br/><input type="hidden" name="odhlasit" value="Odhlasit"><input id="odhlasit" type="submit" value="Odhlásit"><a class="registrovat" href="index.php?str=upravitProfil">Upravit profil</a></fieldset></form>';
}
?>