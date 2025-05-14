<h2>Vaše heslo</h2>

<?php 

    require("databaze.php");

    function gen_password($len = 6)
    {
        $r = '';
        for($i=0; $i<$len; $i++)
            $r .= chr(rand(0, 25) + ord('a'));
        return $r;
    }

    $telo_mejlu = "Dobrý den, někdo nejspíš vy jste zadal na internetových stránkách SLACK.CZ pokyn pro zaslání zapomenutého hesla. 
    \n Kvůli bezpečnosti Vám musí bý vygenerováno heslo nové. Nové heslo je: ";

    $hlavicky  = 'MIME-Version: 1.0' . "\r\n";
    $hlavicky .= 'Content-type: text/html; charset=utf-8' . "\r\n";
    $hlavicky .= 'From: Slack.cz <info@slack.cz>' . "\r\n";
    $bNick = false;
    $bEmail= false;
    if(isset($_POST["nick"])){
    $nick = addslashes(htmlspecialchars($_POST["nick"]));
        $sql = "SELECT * FROM uzivatel";
        $result = mysqli_query($link, $sql);                 
        if ($result){
            while ($row = mysqli_fetch_assoc($result)){
                if($row['nick'] == $nick){
                    $bNick = true;
                    if($row['email']== $_POST["email"]){
                        $bEmail= true;
                        $email = $row['email']; 
                          
                        $heslo= gen_password(6); 
                        
                        $sql2 = "UPDATE uzivatel SET heslo = '".addslashes(htmlspecialchars(md5($heslo)))."' WHERE nick = '$nick'";
                        mysqli_query($link,$sql2);   
                        $telo_mejlu .= $heslo;
                        mail($email,"Zapomenute heslo - Slack.cz",$telo_mejlu,$hlavicky);
                        echo "<p>Vaše heslo bylo odesláno na Váš e-mail: ".$email."</p>";
                    }

                } 
            }
        }   
    }

    if(!$bNick){ 
        echo "<p>Vaše heslo nebylo odesláno na Váš e-mail, protože tento nick neexistuje.</p>
        <a href=\"index.php?str=hesloNaMail\">Zpět na zaslání hesla</a>"; 
    }else{
        if(!$bEmail) echo "<p>Vaše heslo nebylo odesláno na Váš e-mail, protože kombinace nicku a e-mailu není správná.</p>
            <a href=\"index.php?str=hesloNaMail\">Zpět na zaslání hesla</a>"; 
    }






?>