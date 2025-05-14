<?php
    /**
    * havni.php
    *
    *
    * hlavní část kódu ve které pracuji se sešnama pro přihlášení a odhlášení uživatelů.
    *
    * @author Mayer (mayersta)
    *
    */

    if(isset($_GET['odhlasit'])){
        require("include/databaze.php");
        $sql="DELETE FROM trvale_prihlaseni WHERE id_u=".$_SESSION["id"]." LIMIT 1";
        $req=$link->query($sql);
        unset($_SESSION['prihlasit']);
        unset($_SESSION['username']);
        unset($_SESSION['id']);
    }
    if(!isset($_SESSION["username"])){
        if(isset($_COOKIE["trvale_prihlaseni"])){
            require("include/databaze.php");
            $hash = $_COOKIE["trvale_prihlaseni"];  
            $sql = "SELECT * FROM trvale_prihlaseni WHERE hash='$hash' LIMIT 1";
            $req = $link->query($sql);
            if($req){
                $zaznam=$req->fetch_assoc();
                if(isset($zaznam) && $zaznam["hash"]==$hash){
                    $_SESSION["id"]=$zaznam["id_u"];
                    $_SESSION["username"]=$zaznam["nick"];
                    $_SESSION["prihlasit"]=1;
                }
            }
            $hash = NULL;
        }
    }

    if(!isset($_SESSION['prihlasit'])){$_SESSION['prihlasit']=0;}
    if(isset($_POST['username']) && isset($_POST['password'])){
        require("databaze.php");
        $username=$_POST['username'];
        $password=md5($_POST['password']);
        $sql = "SELECT id,nick,heslo FROM uzivatel WHERE nick='".$username."'";
        $result = mysqli_query($link, $sql);

        if ($result) {
            $row = mysqli_fetch_assoc($result);
            if(($row['nick']==$username)&&($row['heslo']==$password)){
                $_SESSION['prihlasit']=1;
                $_SESSION['username']=$username;
                $_SESSION['id']=$row['id'];
                if(isset($_POST["trvale"])){
                    $sql = "DELETE FROM trvale_prihlaseni WHERE id_u=".$_SESSION["id"];
                    $link->query($sql);
                    $datum = date("Y-m-d H:i:s");

                    $hash = session_id();

                    $sql = "INSERT INTO trvale_prihlaseni (id_u, nick, datum, hash) VALUES (".$_SESSION["id"].", '$username', '$datum', '$hash')";

                    setcookie("trvale_prihlaseni", $hash, time()+7776000);

                    $link->query($sql);
                }
            }
            $result=NULL;
        }
        mysqli_close($link);
    }




?>
