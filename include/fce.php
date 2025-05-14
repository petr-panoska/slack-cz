<?php

    /**
    * Vrátí true, pokud je formulář zpracovávaný poprvé.
    * Funguje jen při prvním volání, protože při něm maže záznam z session proměnné.
    * V jiném případě vrátí false.
    * 
    */
    function dvojiOdeslani($token){
        if(isset($_SESSION["forum_token"])){
            if($_SESSION["forum_token"] == $token)
                return false;
            else{
                $_SESSION["forum_token"] = $token;
                return true;
            } 
        }else{
            $_SESSION["forum_token"] = $token;
            return true;
        } 
    }

function nastavPromennou($nazev, $hodnota, $hodnota2, $hodnota3, $hodnota4){
        require("include/databaze.php");
        $hodnota = addslashes($hodnota);
        $hodnota2 = addslashes($hodnota2);
        $hodnota3 = addslashes($hodnota3);
        $hodnota4 = addslashes($hodnota4);
        $sql = "UPDATE promenne SET hodnota='$hodnota', hodnota2='$hodnota2', hodnota3='$hodnota3', hodnota4='$hodnota4' WHERE nazev='$nazev'";
        $req = $link->query($sql);
        if($req){ return true; }else{ return false; }
    }

    function vratPromennou($nazev){
        require("include/databaze.php");
        $sql = "SELECT nazev, hodnota, hodnota2, hodnota3, hodnota4 FROM promenne WHERE nazev='$nazev' LIMIT 1";
        $req = $link->query($sql);
        if($req){
            $zaznam = $req->fetch_assoc();
            $zaznam["hodnota"] = stripslashes($zaznam["hodnota"]);
            $zaznam["hodnota2"] = stripslashes($zaznam["hodnota2"]);
            $zaznam["hodnota3"] = stripslashes($zaznam["hodnota3"]);
            $zaznam["hodnota4"] = stripslashes($zaznam["hodnota4"]);
            return $zaznam;
        }else{
            return false;
        }
    }

    function nick_uzivatele($id_u){
        require("include/databaze.php");
        $sql = "SELECT nick FROM uzivatel WHERE id=$id_u LIMIT 1";
        $req = $link->query($sql);
        $zaznam = $req->fetch_assoc();
        $nick = $zaznam["nick"];
        if($nick!=""){
            return $nick;
        }else{ return false; }
    }

    function isAdmin($username){
        if($username=="Vejvis" || $username=="kolouch" || $username=="stanely" || $username=="Roman"){
            return true;
        }else{
            return false;
        }
    }

    function isGPS($text){
        $ok = true;
        if(substr($text, 0, 1)=="(" && substr($text, strlen($text)-1, 1)==")" ){
            $souradnice = explode("," , substr($text, 1, strlen($text)-2));
            $gps["lat"] = ltrim($souradnice[0]);
            $gps["lng"] = ltrim($souradnice[1]);
            if(!is_numeric($gps["lat"]) || !is_numeric($gps["lng"])){
                $ok = false;   
            }
        }else{
            $ok = false;
        }

        if($ok){
            return $gps;
        }else return false;   
    }

    function make_clickable($text){
        return preg_replace("#[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]#ies", "'<a href=\"\\0\">'.(strlen(\"\\0\") > 20 ? (substr(\"\\0\", 0, 20)).\"...\" : \"\\0\").'</a>'", $text);
    }

    function vloz_smajliky($text){
        $text = str_replace("*1*", '<img src="img/forum/smiles/1.gif" alt="1">', $text);
        $text = str_replace("*2*", '<img src="img/forum/smiles/2.gif" alt="2">', $text);
        $text = str_replace("*3*", '<img src="img/forum/smiles/3.gif" alt="3">', $text);
        $text = str_replace("*4*", '<img src="img/forum/smiles/4.gif" alt="4">', $text);
        $text = str_replace("*5*", '<img src="img/forum/smiles/5.gif" alt="5">', $text);
        $text = str_replace("*6*", '<img src="img/forum/smiles/6.gif" alt="6">', $text);
        $text = str_replace("*7*", '<img src="img/forum/smiles/7.gif" alt="7">', $text);
        $text = str_replace("*8*", '<img src="img/forum/smiles/8.gif" alt="8">', $text);
        $text = str_replace("*9*", '<img src="img/forum/smiles/9.gif" alt="9">', $text);
        $text = str_replace("*10*", '<img src="img/forum/smiles/10.gif" alt="10">', $text);
        $text = str_replace("*11*", '<img src="img/forum/smiles/11.gif" alt="10">', $text);
        $text = str_replace("*12*", '<img src="img/forum/smiles/12.gif" alt="12">', $text);
        $text = str_replace("*13*", '<img src="img/forum/smiles/13.gif" alt="13">', $text);
        $text = str_replace("*14*", '<img src="img/forum/smiles/14.gif" alt="14">', $text);
        $text = str_replace("*16*", '<img src="img/forum/smiles/16.gif" alt="16">', $text);
        $text = str_replace("*21*", '<img src="img/forum/smiles/21.gif" alt="21">', $text);
        $text = str_replace("*24*", '<img src="img/forum/smiles/24.gif" alt="24">', $text);
        $text = str_replace("*31*", '<img src="img/forum/smiles/31.gif" alt="31">', $text);
        $text = str_replace("*33*", '<img src="img/forum/smiles/33.gif" alt="33">', $text);
        $text = str_replace("*70*", '<img src="img/forum/smiles/70.gif" alt="70">', $text);
        $text = str_replace("*111*", '<img src="img/forum/smiles/111.gif" alt="111">', $text);
        $text = str_replace("*144*", '<img src="img/forum/smiles/144.gif" alt="144">', $text);
        $text = str_replace("*171*", '<img src="img/forum/smiles/171.gif" alt="171">', $text);
        $text = str_replace("*178*", '<img src="img/forum/smiles/178.gif" alt="178">', $text);
        $text = str_replace("*251*", '<img src="img/forum/smiles/251.gif" alt="251">', $text);
        return $text;
    }

    function vypis_hvezdicky($hodnoceni){
        $celek  = $hodnoceni / 2;
        $zbytek = $hodnoceni % 2;
        $text="";  
        while($celek>0):
            $text.= '<img src="img/ico/star.png" alt="hodnocení">';
            $celek--;
            endwhile;
        return $text;
    }

    function logAdd($popis){
        require("include/databaze.php");
        $popis = addslashes($popis);
        $datum = date("Y-m-d H:i:s");
        $ip = $_SERVER["REMOTE_ADDR"];
        if(isset($_SESSION["id"])){
            $id_u = $_SESSION["id"];
        }else{
            $id_u = 0;
        }
        $sql = "INSERT INTO log (datum, ip, id_u, popis) VALUES ('$datum', '$ip', $id_u, '$popis')";
        $req = $link->query($sql);
    }
?>