<h2>Triky</h2>
<br />
<?php
    require("include/databaze.php");
    require("include/class/GibbonTrick.php");
    $date = date("Y-m-d");
    $sql = "SELECT id FROM `gibbon_trick` WHERE date<='$date' ORDER BY date DESC";
    $req = $link->query($sql);
    
    $aktualni = $req->fetch_assoc();
    $trik = new GibbonTrick($link);
    $trik->load($aktualni["id"]);
    echo "<h3>Trik na tento týden</h3>";
    echo $trik->getObject();
    unset($trik);
    
    echo "<br /><br />";
    echo "<h3>Starší triky:</h3>";
    while($row = $req->fetch_assoc()){
        $trik = new GibbonTrick($link);
        $trik->load($row["id"]);
        echo '<a href="?str=gibbon_trick&amp;id='.$row["id"].'#'.$row["id"].'" id="'.$row["id"].'".>'.$trik->getDatum().' '.$trik->getName().'</a><br />';
        if($_GET["id"] == $row["id"]){
            echo $trik->getObject()."<br />";
        }
        unset($trik);  
    }
?>
