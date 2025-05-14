<?php
    /**
    * index.php
    *
    *Hlavní stránka celého projektu includuje spousty podsouboru
    *
    * @author Mayer (mayersta)
    * 
    */

    /**
    * 
    *
    *Funkce zkontroluje email, který mu předáme jako parametr vrátí boolean
    *
    * @$email proměnná pro email
    * @return vraci zda-li je email správný
    * 
    */
    function checkEmail($email){
        $atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]'; // znaky tvořící uživatelské jméno
        $domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // jedna komponenta domény
        return eregi("^$atom+(\\.$atom+)*@($domain?\\.)+$domain\$", $email);
    }
    /* 
    *
    *Funkce zkontroluje heslo, které mu předáme jako parametr vrátí boolean
    *
    * @$heslo proměnná pro email
    * @return vraci zda-li je heslo správný
    * 
    */
    function checkHeslo($heslo){
        if($heslo!=""){
            if ((strlen($heslo) < 5)){
                return false;
            } else return true;
        }else return false;
    }    
    /* 
    *
    *Funkce zkontroluje jmeno, které mu předáme jako parametr vrátí boolean
    *
    * @$heslo proměnná pro email
    * @return vraci zda-li je prijmeni správné
    * 
    */

    function checkJmeno($jmeno){
        if($jmeno!=""){
            if ((strlen($jmeno) < 3) || strrpos($jmeno," ")){
                return false;
            }else return true;
        } else return false;

    }   
    /* 
    *
    *Funkce zkontroluje telefon, které mu předáme jako parametr vrátí boolean
    *
    * @$tel proměnná pro email
    * @return vraci zda-li je telefon správný
    * 
    */
    function checkTel($tel){
        $kontrola =false;
        if(eregi("^[1-9]{1}[0-9]*$", $tel)) $kontrola=true;
        if(eregi("^[+]{1}[1-9]{1}[0-9]*$", $tel)) $kontrola=true;
        if(eregi("^[1-9]{1}[0-9]{2}[ ]{1}[0-9]{3}[ ]{1}[0-9]{3}$", $tel)) $kontrola=true;
        if(eregi("^[+]{1}[0-9]{3}[ ]{1}[1-9]{1}[0-9]{2}[ ]{1}[0-9]{3}[ ]{1}[0-9]{3}$", $tel)) $kontrola=true;

        if ($kontrola == false){
            return false;
        }else {
            if((strlen($tel) >=9) && (strlen($tel) <=16)){
                return true;
            }else return false;
        }
    }    

    function checkRok($rok){
        if(is_int($rok) && $rok < Date("Y") && $rok > (Date("Y")-100)){
            return true;
        }else return false;
    }

    /* 
    *
    *Funkce zkontroluje veškeré údaje pro registraci uživatelů
    *
    * 
    * @return hlášky které vypíšeme před formulář
    * 
    */    
    function zkontroluj_r(){
        require("databaze.php");
        $hlasky = "";

        if(isset($_POST['nick'])){      
            $sql = "SELECT nick FROM uzivatel";
            $result = mysqli_query($link, $sql);                 
            if ($result){
                while ($row = mysqli_fetch_assoc($result))
                    if(($row['nick']== $_POST['nick']))
                        $hlasky = $hlasky. "<p class=\"paddingMargin\">Uživatel se stejnou přzdívkou již existuje.</p>";
            }
            if(!checkJmeno($_POST['nick'])){
                $hlasky = $hlasky. "<p class=\"paddingMargin\">Nick musí být delší než 2 písmena.</p>";
            }

        }else $hlasky = $hlasky. "<p class=\"paddingMargin\">Nick musí být delší než 2 písmena.</p>";    

        if(isset($_POST['heslo'])){
            if(!checkHeslo($_POST['heslo'])){
                $hlasky= $hlasky."<p class=\"paddingMargin\">Heslo je příliš krátké.</p>";         
            } 

            if(isset($_POST['heslo2'])){
                if($_POST['heslo2'] != $_POST['heslo']){
                    $hlasky= $hlasky."<p class=\"paddingMargin\">Hesla se neshodují.</p>";  
                }
            }else $hlasky= $hlasky."<p class=\"paddingMargin\">Hesla se neshodují.</p>"; 


        }else $hlasky= $hlasky."<p class=\"paddingMargin\">Heslo je příliš krátké.</p>";  

        if(isset($_POST['jmeno'])){  
            if(!checkJmeno($_POST['jmeno'])){
                $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím svoje jméno.</p>";    
            }
        }else  $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím svoje jméno.</p>";     

        if(isset($_POST['prijmeni'])){    
            if(!checkJmeno($_POST['prijmeni'])){
                $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím svoje příjmení.</p>";    
            }
        }else $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím svoje příjmení.</p>"; 

        if(isset($_POST['telefon'])){  
            if($_POST['telefon'] != ""){    
                if(!checkTel($_POST['telefon'])){
                    $hlasky= $hlasky."<p class=\"paddingMargin\">Nemáte správný formát telefonu.</p>";   
                }
            }
        }  

        if(isset($_POST['email'])){
            if(!checkEmail($_POST['email'])){
                $hlasky= $hlasky."<p class=\"paddingMargin\">Nemáte správný formát emailu.</p>";            
            }  
        }else $hlasky= $hlasky."<p class=\"paddingMargin\">Nemáte správný formát emailu.</p>";   

        if(isset($_POST['rok'])){
        }else $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím Váš rok narození.</p>";    



        return $hlasky;

    }
    /* 
    *
    *Funkce zkontroluje veškeré údaje pro úpravu uživatelů
    *
    * 
    * @return hlášky které vypíšeme před formulář
    * 
    */    
    function zkontroluj_u(){
        require("databaze.php");
        $hlasky = "";

        $id= $_SESSION['id'];

        if(isset($_POST['pHeslo'])){
            if($_POST['pHeslo'] != ""){
                
                $sql = "SELECT id_u,heslo FROM uzivatel WHERE id_u= $id";
                $result = mysqli_query($link, $sql);
                if($result){
                    $row = mysqli_fetch_assoc($result);

                    if(md5($_POST['pHeslo']) != $row['heslo']){
                        
                        $hlasky =  $hlasky."Zadali jste špatné původní heslo."; 
                    } 

                }else $hlasky =  $hlasky."Zadali jste špatné původní heslo.";    
            }
        }  

        if(isset($_POST['heslo'])){
            if($_POST['heslo'] != ""){ 
                if(isset($_POST['pHeslo'])){   
                    if(!checkHeslo($_POST['heslo'])){
                        $hlasky= $hlasky."<p class=\"paddingMargin\">Heslo je příliš krátké.</p>";         
                    } 

                    if(isset($_POST['heslo2'])){
                        if($_POST['heslo2'] != $_POST['heslo']){
                            $hlasky= $hlasky."<p class=\"paddingMargin\">Hesla se neshodují.</p>";  
                        }
                    }else $hlasky= $hlasky."<p class=\"paddingMargin\">Hesla se neshodují.</p>";   

                }else $hlasky= $hlasky."<p class=\"paddingMargin\">Musíte zadat původní heslo</p>";
            }
        }  

        if(isset($_POST['jmeno'])){  
            if(!checkJmeno($_POST['jmeno'])){
                $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím svoje jméno.</p>";    
            }
        }else  $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím svoje jméno.</p>";     

        if(isset($_POST['prijmeni'])){    
            if(!checkJmeno($_POST['prijmeni'])){
                $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím svoje příjmení.</p>";    
            }
        }else $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím svoje příjmení.</p>"; 

        if(isset($_POST['telefon'])){ 
            if($_POST['telefon'] != ""){     
                if(!checkTel($_POST['telefon'])){
                    $hlasky= $hlasky."<p class=\"paddingMargin\">Nemáte správný formát telefonu.</p>";   
                }
            }
        }  

        if(isset($_POST['email'])){
            if(!checkEmail($_POST['email'])){
                $hlasky= $hlasky."<p class=\"paddingMargin\">Nemáte správný formát emailu.</p>";            
            }  
        }else $hlasky= $hlasky."<p class=\"paddingMargin\">Nemáte správný formát emailu.</p>";   

        if(isset($_POST['rok'])){
        }else $hlasky= $hlasky."<p class=\"paddingMargin\">Opravte prosím Váš rok narození.</p>";    



        return $hlasky;

    }
    /* 
    *
    *Funkce zkontroluje veškeré údaje pro objednávku produktů
    *
    * 
    * @return hlášky které vypíšeme před formulář
    * 
    */    
    function zkontroluj_o(){

        $hlasky = "";
        if(!checkEmail($_POST['email'])){
            $hlasky= $hlasky."<p>Nemáte správný formát emailu.</p>";            
        }                        

        if(!checkJmeno($_POST['jmeno'])){
            $hlasky= $hlasky."<p>Opravte prosím svoje jméno.</p>";    
        }

        if(!checkJmeno($_POST['prijmeni'])){
            $hlasky= $hlasky."<p>Opravte prosím svoje příjmení.</p>";    
        }
        if(!checkTel($_POST['telefon'])){
            $hlasky= $hlasky."<p>Nemáte správný formát telefonu.</p>";   
        }

        return $hlasky;

    }
    /* 
    *
    *Funkce zkontroluje veškeré údaje pro zapsání receptů
    *
    * 
    * @return hlášky které vypíšeme před formulář
    * 
    */    

?>
