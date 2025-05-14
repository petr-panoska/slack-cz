<h2>Galerie</h2>
<?php
    require_once("include/class/Galerie.php");
    $sql = "SELECT * FROM uzivatel_galerie WHERE id_u=$id_u";
    $req = $link->query($sql);
    if($req){
        $galerie = new Galerie();
        while($zaznam = $req->fetch_assoc()){
            $id_f = $zaznam["id_f"];
            $text = $zaznam["popis"];
            $thumb = "uzivatel/$id_u/galerie/$id_f.jpg";
            $full = "uzivatel/$id_u/galerie/$id_f-full.jpg";
            
            
            if(isset($_SESSION["id"]) && isset($_GET["id_u"]) && $_SESSION["id"]==$_GET["id_u"]){
                $galerie->AddPicture($thumb, $full, $text, "<a href=\"form_zpr.php?akce=denicek_galerie_del&id_f=$id_f\"><img src=\"img/ico/del.png\"></a>");
            }else{
                $galerie->AddPicture($thumb, $full, $text, "");
            }
        }
        $galerie->Show();
        unset($id_f);
        unset($text);
        unset($thumb);
        unset($full);
        unset($galerie);

    }


    if($_SESSION["id"]==$_GET["id_u"]){
        echo '
        <form method="post" action="form_zpr.php" enctype="multipart/form-data">
        <input type="hidden" name="akce" value="denicek_galerie_add">
        <label>Fotka:</label><input type="file" name="foto"><br />
        <label>Popisek:</label><input type="text" name="popis" maxlength="50"><br />
        <label>&nbsp;</label><input type="submit" value="Přidej">
        </form>
        ';
    }
?>