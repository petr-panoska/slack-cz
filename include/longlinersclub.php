<h2>Vítejte v Longliners Clubu!</h2>
<h3>Prolog</h3>
<p>
    Rachtání řetězového hup- cuku konečně zmlklo. Nad čerstvě posečenou trávou fotbalového hřiště v Sachrnitz se třepotá šedočerná lajna. Okolo ní se tísní spousta lidí zvědavých jak to všechno dopadne. Také kamera televizního štábu je připravena, takže akce může začít. Borec se ladně vyhoupne na lajnu, chvíli soustředěně sedí, nevnímajíc pozornost publika kterou svým smělým pokusem vyvolal. Nevídaná věc.Přejít slackline dlouhou sto metrů! Nádech, výdech, nastupuje  a s rozvahou začíná svůj boj s tímto dlouhatánským monstrem.  Publikum ani nedutá když je míjí soustředěn kráčejíc k cíli. Kamera zaznamenává každý jeho krok pro budoucnost. Ještě třicet  metrů… dvacet…deset a… borec padá! Kamera se zastavuje dav odněňuje smělého mladíka rozpačitým potleskem…
</p>
<p>
    Scharnitz    červenec 2007
</p>
<h3>Současnost</h3>
<p>
    Od doby, kdy jsme jako hrstka slacklajnových zelenáčů navštívili slackfestival v rakouském Scharnitz a byli svědky pokusu Damiana Cookseyho přejít sto metrovou lajnu uplynulo už hodně času a stalo se mnohé. Vylepšil se materiál, rozšířilo se poznání, přibyly zkušenosti. Taky sto metrová hranice už nebudí zdaleka takovou pozornost veřejnosti jako tehdy.
</p>
<p>
    Co ovšem zůstalo je lidská touha překonávat vlastní limity a posouvat hranice svých možností. Jednou takovou disciplínou je longline a konkrétně pak ona magická, tříciferná hranice sta metrů a více.
</p>
<p>
    Z tohoto popudu vznikl nápad založit klub lidí, kteří tuto vzdálenost úspěšně překonali.
</p>
<p>
    Na stránkách toho klubu se právě nacházíte.
</p>

<h3>Vývoj longline 100+ v Česku:</h3>
<p>Prvním Čechem, který kdy přešel stometrovou longline byl Kajoch. Stalo se to na Slackfestu v Chemnitz v sobotu 15. 9.2007</p>
<p>O den později,16. 9. 2007 po celodenním boji, utrpěl vítězství na lajně 124 metrů Kolouch.</p>
<p>Vyrovnání rekordu přinesl Slack fest v Německém Boden See v roce 2009. 11.července  Kwjet a po něm i Kajoch v silném větru zdolali lajnu 124m a kus k tomu, ale shodli se na : "vyrovnání rekordu".</p>
<p>2.9. 2009 přešel Honzís lajnu 125 m dlouhou a posunul tak rekord o jeden metr. Za týden na to,  9. 9.2009 se mu podařilo posunout hranici na 135m.</p>
<p>22. 9.2009  Kwjet ve Stromovce 152m</p>
<p>28.11. 2009 Kwjet- 168m v Borském parku</p>
<p>4. 7. 2010 Peeto 177m  v Šimkových sadech</p>
<p>29. 7. 2010 Kwjet 217m na Bišíku</p>
<p>27.10.2010 Kwjet a Peeto 222m ve Stromovce v Praze</p>
<p>22.7.2012 padla magická hranice 300metrů. Kwjet a Danny zdolali lajnu dlouhou 327 metrů. Jde o nový český rekord a zároveň třetí nejdelší překonanou longline v Evropě (evropský rekord je 340 m). Lajna navíc byla překonána oběma slackery v nejčistším možném stylu - on-sight (na první pokus)</p>
<h2> Jak se stát členem Longhliners Clubu:</h2>
<p>Přejít lajnu delší než sto metrů. Vzdálenost lajny se měří od stromu ke stromu, popřípadě jinému pevnému bodu. Přechod lze uznat po překonání celé délky lajny po které se dá jít. Např. nástup za klakostrojem a konec po kontaktu se stromem na druhé straně, popřípadě kontakt s šeklem, karabinou či jiným blokantem. Pád těsně před koncem znamená zmaření celého pokusu a ten tudíž nelze uznat.</p>


<h2>100+</h2>
<table style="border: 1px solid black;">
    <tr>
        <th width="70px">Pořadí:</th>
        <th width="130px">Přezdívka:</th>
        <th width="70px">Délka:</th>
        <th width="200px">Místo:</th>
        <th>Datum:</th>
    </tr>
    <?php
        require_once("include/databaze.php");
        require_once("include/fce.php");
        $sql = "SELECT * FROM longlinersclub WHERE oddil='100+' ORDER BY datum ASC, poradi ASC";
        $req = $link->query($sql);
        $i=1;
        while($row = $req->fetch_assoc()){
            echo '<tr>';
            echo '<td>'.$i.'</td>';
            if($row["id_u"]==0){
                echo '<td>'.$row["jmeno"].'</td>';    
            }else{
                echo '<td><a href="denicek-'.$row["id_u"].'">'.nick_uzivatele($row["id_u"]).'</a></td>';
            }
            echo '<td>'.$row["delka"].'</td>';
            echo '<td>'.$row["misto"].'</td>';
            echo '<td>'.$row["datum"].'</td>';
            echo '</tr>';
            $i++;
        }
    ?>
</table>