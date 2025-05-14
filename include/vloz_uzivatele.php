<?php
    /**
    * vloz.php
    *
    *
    * Zde je funkce pro vlozeni uzivatelu
    * 
    *
    */

    /**
    * 
    *
    *
    * Funkce pro vlozeni uzivatelu do databaze
    * 
    *
    */

    function Vloz(){
        require("databaze.php");

        $nick=addslashes(htmlspecialchars(($_POST['nick']))); 
        $heslo=addslashes(htmlspecialchars(md5($_POST['heslo'])));
        $jmeno=addslashes(htmlspecialchars($_POST['jmeno']));
        $prijmeni=addslashes(htmlspecialchars($_POST['prijmeni']));
        $rok=addslashes(htmlspecialchars($_POST['rok']));
        $email=addslashes(htmlspecialchars($_POST['email']));  
        
        $telefon=addslashes(htmlspecialchars($_POST['telefon']));
        $mesto=addslashes(htmlspecialchars($_POST['mesto']));
        
        $sql = "INSERT INTO `gslackczdb`.`uzivatel` (`nick` ,`heslo`, `jmeno` ,`prijmeni` ,`rok_nar` ,`email` ,`telefon` ,`mesto`)
        VALUES ('$nick', '$heslo', '$jmeno', '$prijmeni', '$rok', '$email', '$telefon', '$mesto');";
        $result = $link->query($sql);

        if ($result) {
            echo "Byl jste uspěšně zaregistrován.";
            $id = $link->insert_id;
            mkdir("uzivatel/$id/", 0777);
            if($_FILES["pObrazek"]["tmp_name"] != ""){
                move_uploaded_file($_FILES["pObrazek"]["tmp_name"], "uzivatel/".$id."/profil_foto.jpg");
                require('include/SimpleImage.php');
                $image_full = new SimpleImage();
                $image_full->load("uzivatel/".$id."/profil_foto.jpg");
                $image_full->resizeToWidth(640);
                if($image_full->getHeight() > 480) $image_full->resizeToHeight(480);   
                $image_full->save("uzivatel/".$id."/profil_foto_full.jpg");

                
                $image = new SimpleImage();
                $image->load("uzivatel/".$id."/profil_foto.jpg");
                $image->resizeToWidth(60);
                if($image->getHeight() > 100) $image->resizeToHeight(100);  
                $image->save("uzivatel/".$id."/profil_foto.jpg");

            }
            $result=NULL;
        }else {
            echo "Chyba při zápisu do DB."; 
        }

        mysqli_close($link);

    }
?>
