<?php
include("../include/fce.php");
include("../include/databaze.php");
$conf["highline_img"]="line/high/";
$sql = "SELECT id, uzivatel_id, jmeno, delka, vyska, oblast, hodnoceni, pocet_prechodu FROM highline LEFT OUTER JOIN ((SELECT highline_id, count(highline_id) AS pocet_prechodu FROM highline_prechody GROUP BY highline_id) AS pocty) ON highline.id=pocty.highline_id";
$where = false;
if(isset($_GET["t"])){
    $p = explode(".", chunk_split($_GET["t"], 1, "."));
    $sql.=" WHERE (";
    $where = true;
    for($i=0;$i<count($p)-1;$i++){
        $sql.=" highline_oddily_id=".$p[$i];
        if($i<count($p)-2){
            $sql.=" OR";
        }
    }
    $sql.=")";        
}
if(isset($_GET["s"])){
    if(!$where){
        $sql.=" WHERE jmeno like '%".$_GET["s"]."%'";
    }else{
        $sql.= " AND jmeno like '%".$_GET["s"]."%'";
    }
}

if(isset($_GET["o"])){
    switch($_GET["o"]){
        case "1":
            $sql.= " ORDER BY jmeno ASC";
        break;
        case "2":
            $sql.= " ORDER BY jmeno DESC";
        break;
        case "3":
            $sql.= " ORDER BY delka ASC";
        break;
        case "4":
            $sql.= " ORDER BY delka DESC";
        break;
        case "5":
            $sql.= " ORDER BY vyska ASC";
        break;
        case "6":
            $sql.= " ORDER BY vyska DESC";
        break;
        case "7":
            $sql.= " ORDER BY hodnoceni ASC";
        break;
        case "8":
            $sql.= " ORDER BY hodnoceni DESC";
        break;        
    }
}else{  $sql.= " ORDER BY jmeno ASC";  }    

    
$req = $link->query($sql);
while($zaznam = $req->fetch_assoc()){
$id = $zaznam["id"];
echo "<table class=\"prispevek\">";
echo "  <tr>";
echo "    <td rowspan=\"3\" width=\"120\">";
echo "      <img src=\"".$conf["highline_img"].$zaznam["id"]."/nahled.jpg\" alt=\"".$zaznam["nazev"]."\">";
echo "    </td>";
echo "    <td colspan=\"4\">";
echo "      <h3><a href=\"/highline-".$zaznam["id"]."\">".$zaznam["jmeno"]."</a></h3>";
echo "    </td>";
echo "  </tr>";
echo "  <tr>";
echo "    <td colspan=\"2\" width=\"250\">";
echo "      Oblast: ".$zaznam["oblast"];
echo "    </td>";
echo "    <td>";
if($zaznam["pocet_prechodu"]!=""){
  $pocet_prechodu = $zaznam["pocet_prechodu"];
}else{$pocet_prechodu=0;}
echo "      Počet přechodů: ".$pocet_prechodu;
echo "    </td>";
echo "    <td>";
if(((isset($_SESSION["username"])) && (isAdmin($_SESSION["username"]))) || (isset($_SESSION["id"]) && $_SESSION["id"]==$zaznam["admin"])){
  echo "<a href=\"/uprava-highline-$id\"  alt=\"upravit highlinu\"><img src=\"img/ico/edit.png\"></a>";
}
echo "    </td>";
echo "  </tr>";
echo "  <tr>";
echo "    <td>";
echo "      Délka: ".$zaznam["delka"]." m";
echo "    </td>";
echo "    <td>";
echo "      Výška: ".$zaznam["vyska"]." m";
echo "    </td>";
echo "    <td width=\"160\">";
echo "      Hodnocení: ".vypis_hvezdicky($zaznam["hodnoceni"]);
echo "    </td>";
echo "    <td>";
if((isset($_SESSION["username"])) && (isAdmin($_SESSION["username"]))){
  echo "<a href=\"form_zpr.php?akce=highline_del&id=$id\" onclick=\"return(confirm('Opravdu chcete vymazat tuto highline ?'));\"  alt=\"smazat highlinu\"><img src=\"img/ico/del.png\"></a>";
} 
echo "    </td>";
echo "  </tr>";
echo "</table>\n";
}
?>