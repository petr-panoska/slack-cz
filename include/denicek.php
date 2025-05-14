<?php
  require("include/databaze.php");    
  if(!isset($_GET["id_u"])){
    //$sql = "SELECT * FROM uzivatel ORDER BY nick ASC";
    $sql = "SELECT uzivatel.id, MAX(delka) AS max_long, nick, jmeno, prijmeni, rok_nar, mesto FROM uzivatel LEFT JOIN longline ON uzivatel.id=longline.uzivatel_id GROUP BY uzivatel.id ORDER BY nick ASC";
    $req = $link->query($sql);
    echo "<h2>Deníčky</h2>\n";
    while($zaznam = $req->fetch_assoc()){
        echo "<div class=\"denik\">\n";      
        $id_u = $zaznam["id"];
        if(file_exists("uzivatel/$id_u/profil_foto.jpg")){
          $profil_foto = "uzivatel/$id_u/profil_foto.jpg";
        }else{ $profil_foto = "img/ico/profil.jpg"; }
        if(file_exists("uzivatel/$id_u/profil_foto_full.jpg")){
            echo "<span style=\"float:left; padding-right: 5px;\"><a href=\"uzivatel/$id_u/profil_foto_full.jpg\" rel=\"lightbox\" title=\"".$zaznam["nick"]."\"><img class=\"obr_n\" src=\"$profil_foto\" alt=\"".$zaznam["nick"]."\"></a></span>\n";
        }else{
            echo "<span style=\"float:left; padding-right: 5px;\"><img class=\"obr_n\" src=\"$profil_foto\" alt=\"".$zaznam["nick"]."\"></span>\n";
        }
        echo "<div style=\"float:left;\"><a class=\"bez_podtrzeni\" href=\"denicek-$id_u\">".$zaznam["nick"]."</a><br/>\n";
        echo $zaznam["jmeno"]."\n";
        echo $zaznam["prijmeni"]."\n";
        echo "<br/>Rok narození: ".$zaznam["rok_nar"]; 
        if($zaznam["mesto"] != NULL) 
          echo "<br/>Město: ".$zaznam["mesto"];
        if($zaznam["max_long"] != NULL) 
          echo "<br/>Longline: ".$zaznam["max_long"]."<br />";
        echo "</div>\n";
        echo "<div class=\"clear\"></div>";    
        echo "</div>\n";  
    }
    
                         
  }else{
    include("denicek_detail.php");
  }                         
?>
