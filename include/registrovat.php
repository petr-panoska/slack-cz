<h2>Registrace</h2>

<?php 
    /**
    * registrovat.php
    *
    *
    * zde vypisuju formulář pro registraci. Kontroluji zddali už uživatel není přihlášen.
    * @author Mayer (mayersta)
    *
    */

    require ("validator_r.php");
    require ("vloz_uzivatele.php");


    if($_SESSION['prihlasit']==0){

        if(zkontroluj_r() == ""){
            vloz();
            $_POST = array();
            $_FILES = array();
            $_SESSION['formular_znovu']=0;
            if($str=="registrovat") $str="onas";

        }else{          
            echo zkontroluj_r();




        ?>   
        <form action="index.php?str=registrovat" enctype="multipart/form-data" method="post">
            <fieldset>

                <legend>Přihlašovací údaje</legend>

                <p>
                    <label class="formlabel" for="nick">*Nick: </label>
                    <input id="nick" type="text" name="nick" value="<?php if(isset($_POST['nick'])){echo $_POST['nick'];} ?>"  onKeyup = "javascript: zmena3Pismena('nick');"

                        />
                </p>

                <p>
                    <label class="formlabel" for="heslo">*Heslo: </label>
                    <input id="heslo" type="password" name="heslo" value=""  onKeyup ="javascript: zmenaHesla();"/>
                </p>
                <p>
                    <label class="formlabel" for="heslo2">*Heslo znovu: </label>
                    <input id="heslo2" type="password" name="heslo2" value=""  onKeyup ="javascript: zmenaHesla2();"/>
                </p>


            </fieldset>
            <fieldset>

                <legend>Další údaje</legend>
                <div class="sirka400"> 
                    <p class="formlabelvyska">
                        <label class="formlabel" for="jmeno">*Jméno: </label>
                        <input id="jmeno" type="text" name="jmeno" value="<?php if(isset($_POST['jmeno'])){echo $_POST['jmeno'];} ?>" onKeyup ="javascript: zmena3Pismena('jmeno')"/>

                    </p>
                    <p class="formlabelvyska">
                        <label class="formlabel" for="prijmeni">*Příjmení: </label>
                        <input id="prijmeni" type="text" name="prijmeni" value="<?php if(isset($_POST['prijmeni'])){echo $_POST['prijmeni'];} ?>" onKeyup ="javascript: zmena3Pismena('prijmeni');"/>
                    </p>

                    <p class="formlabelvyska">
                        <label class="formlabel" for="rok">*Rok narození: </label>
                        <input id="rok" type="text" name="rok" value="<?php if(isset($_POST['rok'])){echo $_POST['rok'];} ?>" onKeyup ="javascript: zmenaRok();"/>
                    </p>




                    <p>
                        <label class="formlabel" for="email">*E-mail: </label>
                        <input id="email" type="text" name="email" value="<?php if(isset($_POST['email'])){echo $_POST['email'];}else{echo '@';} ?>"  onKeyup = "javascript: zmenaEmail();"
                            />

                    </p>  

                    <p class="formlabelvyska">
                        <label class="formlabel" for="telefon">Telefon: </label>
                        <input id="telefon" type="text" name="telefon" value="<?php if(isset($_POST['telefon'])){echo $_POST['telefon'];} ?>" onKeyup= "javascript: zmenaTelefon();" />
                    </p>

                    <p class="formlabelvyska">
                        <label class="formlabel" for="mesto">*Město: </label>
                        <input id="mesto" type="text" name="mesto"  value="<?php if(isset($_POST['mesto'])){echo $_POST['mesto'];} ?>" cols="50"/>
                    </p>
                </div>
                <div>
                    <p>
                        <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
                        Vaše profilová fotografie: <input name="pObrazek" type="file" />

                    </p>

                </div>
            </fieldset>
            <p>
                <input class="odsazeniForm" type="submit" value="Registruj" />
            </p>    
        </form>

        <?php
        }


    } else{
        echo 'Jste přihlášen, pokud chcete další registraci prosím odhlašte se.';
    }

?>   
  
   
   
