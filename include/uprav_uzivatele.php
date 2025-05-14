<?php
    /**
    * objednavka.php
    *
    *
    * zde je pouze funkce pro úpravu uživatelů
    * @author Mayer (mayersta)
    *
    */

    /**
    * 
    * Tato funkce UPDATUJE udaje v databázi podle uzivatele 
    * 
    *
    */
    function uprav(){
        require("databaze.php"); 
        
        $jmeno=addslashes(htmlspecialchars($_POST['jmeno']));
        $prijmeni=addslashes(htmlspecialchars($_POST['prijmeni']));
        $rok=addslashes(htmlspecialchars($_POST['rok']));
        $email=addslashes(htmlspecialchars($_POST['email']));  
        $telefon=addslashes(htmlspecialchars($_POST['telefon']));
        $mesto=addslashes(htmlspecialchars($_POST['mesto']));
        $id= $_SESSION['id'];

        $sql = "UPDATE `gslackczdb`.`uzivatel` SET `jmeno` = '$jmeno',
        `prijmeni` = '$prijmeni', `rok_nar` = '$rok', `email` = '$email', `telefon` = '$telefon', `mesto` = '$mesto'";
        
        if($_POST['heslo'] != ""){
            
            $heslo=addslashes(htmlspecialchars(md5($_POST['heslo'])));
            $sql = $sql.", `heslo` = '$heslo'"; 
        }
        
        $sql = $sql." WHERE id = $id";
        $result = mysqli_query($link, $sql);
        
        if ($result) {
            echo "Váš profil byl úspěšně změněn.";
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
        }

        mysqli_close($link);




    }
?>
