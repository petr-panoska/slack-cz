<?php

   

    // třída starající se o vygenerování tabulky s 5. novinkami
    class novinka
    {
        var $databaze; // obsahuje ´odkaz na databázi
        var $txt; // ukládá se do ní html kod který se poto vypíše 
        var $adresa , $obrazek; // slouží pro uložení odkazu na novinku a cesty k náhledu
        var $nazev; // slouží k uložení názvu novinky
        var $druh; //slouží pro uložení informace zda se jedná o video či článek

        // konctruktor 
        function novinka()
        {
            include_once("include/fce.php");
            require("include/databaze.php");
            $sql = 'SELECT * FROM (SELECT id, "video" AS typ, nazev, adresa, "" AS album, datum FROM videa
            UNION
            SELECT id, "clanek" AS typ, nazev, "" AS adresa, album, datum FROM clanky WHERE viditelnost=1
            UNION
            SELECT id, "novinka" AS typ, nazev, odkaz AS adresa, "" AS album, datum FROM novinky) AS novinky ORDER BY datum DESC LIMIT 5';
            $this->databaze = $link->query($sql);        
        }

        // vybere o jak typ novinky se jedná a podle toho nastaví cestu k dané novince a cestu k náhledu
        function vyber()
        {
            if($zaznam = $this->databaze->fetch_assoc()){
                $this->nazev = $zaznam["nazev"];
                switch($zaznam["typ"])
                {
                    case "video":
                        $this->adresa = $zaznam["adresa"];
                        $this->obrazek = "video/video_" . $zaznam["id"] . ".jpg";
                        $this->druh = "video";
                        break;

                    case "clanek":
                        $this->adresa = "index.php?str=clanek&amp;id=".$zaznam["id"];
                        $this->obrazek = "clanky/" . $zaznam["album"] . "/nahled.jpg";
                        $this->druh = "článek";
                        break;

                    case "novinka":
                        $this->adresa = stripslashes(htmlspecialchars_decode($zaznam["adresa"]));
                        $this->obrazek = "novinky/nahled_" . $zaznam["id"] . ".jpg";
                        $this->druh = "novinka";
                        break;
                }
            }else{
                $this->adresa= NULL;
            }
        }

        //dává dohromady samotnou tabulku 
        function vypis()
        {
            $this->vyber();
            echo "<table rules=\"all\" style=\"border: 1px solid white;\"> \n";
            echo "     <tr> \n";
            echo "         <td rowspan=\"2\"><a href=\"$this->adresa\"><img src=\"$this->obrazek\" width=\"140px\" alt=\"$this->druh - $this->nazev\" onmouseout=\"vypis_nazvu('');\" onmouseover=\"vypis_nazvu(alt);\"></a></td> \n";
            echo "         <td colspan=\"4\"><div id=\"vystup\">novinky</div>";
            echo "         </td>\n";
            echo "     </tr> \n";
            echo "     <tr> \n";
            for($i = 0; $i < 4; $i++)
            {
                $this->vyber();
                if($this->adresa != NULL)
                    echo "         <td><a href=\"$this->adresa\"><img src=\"$this->obrazek\" width=\"120px\" alt=\"$this->druh - $this->nazev\" onmouseout=\"vypis_nazvu('')\" onmouseover=\"vypis_nazvu(alt)\" ></a></td> \n";
                else{
                    echo "<td width=\"120px\">&nbsp;</td>";
                }
            }
            $this->txt .= "     </tr> \n";
            $this->txt .= "</table> \n";
            return $this->txt;
        }
    } 
?>
<?php 
    $tabulka = new novinka();
    $text = $tabulka->vypis();
    echo "$text";
?>

<h2>O nás</h2>
<p> 
    <img src="/clanky/100618_9014/1..jpg" alt="slack" width="550" height="109" >
</p>
<p>Vítejte na stránkách  věnovaných SLACKLINE- modernímu  provazochodectví,      zábavě, sportu,  způsobu  odreagování se od každodenního stresu,  balancování na tenkém popruhu, kterému  prostě říkáme LAJNA.
</p>
<p>Přestože jsme stáli u samotného zrodu SLACKLINE v Česku a od konce  roku 2007 provozujeme tyto stránky  upozorňujeme, že se nechceme tvářit,  jako bychom o lajnách věděli vše. Vždyť je to teprve pár let od doby,  kdy byla u nás  natažena první lajna po vzoru Yosemitských Rezidents.
</p>
<p>
    <img style="float: right;" src="/clanky/100618_9014/6..jpg" alt="slackline" width="200" height="267" > Je tedy celkem jisté, že některé poznatky a  způsoby napínání, jakož i používaný materiál, budou zanedlouho  překonány. Přesto, anebo právě proto nás těší, že můžeme být tam, kde  vzniká něco zcela nového.     Naše poznávání zákonitostí a krás  SLACKLINE rostlo s každou  přetrženou lajnou, modřinou, spáleninou, nebo stehem na rozbité hlavě,  jakož i ve chvílích vítězství a radosti z překonávání vlastních limitů.
</p>
<p>Stejně tak i nové způsoby kotvení, upínání a natahování, se rodily a  rodí v nekonečných diskuzích nad půllitry piva, sklenkami vína, či v  bezesných nocích slacklajnových nadšenců.
</p>
<p>Velice nás těší setkávat se v tomto kyberprostoru s lidmi, kteří  stejně jako my propadli kouzlu balancování na lajně natažené pár  centimetrů nad zelenou loukou, vnímajících napětí monster dlouhých  desítky metrů, užívajících si lajn natažených nad vodou, či  adrenalinových labužníků vznášejících se na highline vysoko mezi skalami  v magické prázdnotě mezi nebem a zemí.
</p>
<p>Mnohem více nás však těší společná setkávání na lajně, ať už se  jedná o komorní akcičku uprostřed týdne někde v parku, adrenalinovou  highline, nebo o víkendový slacklinefest.
</p>
<p>Proto budeme velice potěšeni, pokud se tyto stránky stanou zdrojem  informací a inspirace pro každého. Od úplného začátečníka až  po  skalního lajnera, kterému se SLACKLINE stala životním stylem.
</p>
<p class="alignCenter">
    <img src="/clanky/100618_9014/4..jpg" alt="slackline" width="550" height="260" >
</p>
<p>Ovšem žijeme v dodě, kdy je nezbytně nutné připsat ještě  následující:
</p>
<p>Provozování  SLACKLINE může být nebezpečné! Doporučujeme proto,  stejně jako při každé jiné lidské činnosti, používat hlavu a konkrétně  pak mozek a rozum, aby nedocházelo ke zbytečným zraněním.     Slack.cz nenese žádnou odpovědnost za případné úrazy či úmrtí v  souvislosti s jejím  provozováním.
</p>
<p>Slack zdar!
</p>
<p>kolouch
</p>
