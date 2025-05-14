<h2>Upravení profilu</h2>

<?php
    require ("validator_r.php");
    require ("databaze.php");
    require ("uprav_uzivatele.php");   

    if($_SESSION['prihlasit']==1){

        if(zkontroluj_u() == ""){
            uprav(); 
            $_POST = array();
            $_FILES = array();

        }else{          
            echo zkontroluj_u(); 
            $id=$_SESSION['id'];
            $sql = "SELECT * FROM uzivatel WHERE $id=id";
            $result = mysqli_query($link, $sql);
            if($result){
                $row = mysqli_fetch_assoc($result); 
            ?>   
            <form action="index.php?str=upravitProfil" enctype="multipart/form-data" method="post">
                <fieldset>
                    <legend>Přihlašovací údaje</legend>

                    <p>
                        <label class="formlabel" for="nick">Nick: </label>
                        <input id="nick" disabled="disabled" type="text" name="nick" value="<?php if(isset($_POST['nick'])){echo $_POST['nick'];}else{echo $row['nick'];} ?>"  onKeyup = "javascript: zmena3Pismena('nick');" 

                            />
                    </p>

                    <p>
                        <label class="formlabel" for="pHeslo">*Původní heslo: </label>
                        <input id="pHeslo" type="password" name="pHeslo" value=""/>
                    </p>


                    <p>
                        <label class="formlabel" for="heslo">Nové heslo: </label>
                        <input id="heslo" type="password" name="heslo" value=""  onKeyup ="javascript: zmenaHesla();"/>
                    </p>
                    <p>
                        <label class="formlabel" for="heslo2">Nové heslo znovu: </label>
                        <input id="heslo2" type="password" name="heslo2" value=""  onKeyup ="javascript: zmenaHesla2();"/>
                    </p>


                </fieldset>
                <fieldset>
                    <legend>Další údaje</legend>
                    <div class="sirka400">
                    <p class="formlabelvyska">
                        <label class="formlabel" for="jmeno">Jméno: </label>
                        <input id="jmeno" type="text" name="jmeno" value="<?php if(isset($_POST['jmeno'])){echo $_POST['jmeno'];}else{echo $row['jmeno'];} ?>" onKeyup ="javascript: zmena3Pismena('jmeno')"/>

                    </p>
                    <p class="formlabelvyska">
                        <label class="formlabel" for="prijmeni">Příjmení: </label>
                        <input id="prijmeni" type="text" name="prijmeni" value="<?php if(isset($_POST['prijmeni'])){echo $_POST['prijmeni'];}else{echo $row['prijmeni'];} ?>" onKeyup ="javascript: zmena3Pismena('prijmeni');"/>
                    </p>

                    <p class="formlabelvyska">
                        <label class="formlabel" for="rok">Rok narození: </label>
                        <input id="rok" type="text" name="rok" value="<?php if(isset($_POST['rok'])){echo $_POST['rok'];}else{echo $row['rok_nar'];} ?>" onKeyup ="javascript: zmenaRok();"/>
                    </p>




                    <p>
                        <label class="formlabel" for="email">E-mail: </label>
                        <input id="email" type="text" name="email" value="<?php if(isset($_POST['email'])){echo $_POST['email'];}else{echo $row['email'];} ?>"  onKeyup = "javascript: zmenaEmail();"
                            />

                    </p>  

                    <p class="formlabelvyska">
                        <label class="formlabel" for="telefon">Telefon: </label>
                        <input id="telefon" type="text" name="telefon" value="<?php if(isset($_POST['telefon'])){echo $_POST['telefon'];}else{echo $row['telefon'];} ?>" onKeyup= "javascript: zmenaTelefon();" />
                    </p>

                    <p class="formlabelvyska">
                        <label class="formlabel" for="mesto">Město: </label>
                        <input id="mesto" type="text" name="mesto"  value="<?php if(isset($_POST['mesto'])){echo $_POST['mesto'];}else{echo $row['mesto'];} ?>" cols="50"/>
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
                    <input class="odsazeniForm" type="submit" value="Uprav profil" />
                </p>    
            </form>

            <?php
            }
        }
    } else {
        echo ' Nesjte přihlášen, proto nemžete upravovat svůj profil.';
    }

?>       