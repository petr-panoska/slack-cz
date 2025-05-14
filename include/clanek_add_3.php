<?php
    include("include/class/Forms.php");
    if(isset($_SESSION["clanek_add"])){
        $clanek = unserialize($_SESSION["clanek_add"]);
        $clanek->vypisChyby();
    }else{
        echo '<p class="chyba">Špatné vytvoření článku!</p>';
    }
?>
<h2>Přidání článku - konečný náhled</h2><br />

<h3>náhled novinky</h3>
<?php
    echo "<div class=\"novinka\" style=\"border: 1px solid black; margin-left: 0px; padding-left: 5px;\">\n";
    echo "  <a href=\"/clanek-".$clanek->getId()."\">".$clanek->getNazev()."</a>\n";
    echo "  <br /><p><img src=\"clanky/".$clanek->getAlbum()."/nahled.jpg\" alt=\"".$clanek->getNazev()."\">\n";
    echo $clanek->getPopis()."\n";
    echo "  </p><div class=\"clear\"></div>\n";
    echo "</div>\n";

    $form = new Forms("nahled", "form_zpr.php", "POST");
    $form->AddHidden("akce", "clanek_add_3");
    $form->AddHidden("kam", "nahled_upload");
    $form->AddUpload("Náhledová fotka:", "nahled");
    $form->SetSubmitValue("Nahraj fotku");
    $form->ShowResetButton(false);
    $form->ShowForm();

    echo "<br /><h3>náhled článku</h3>";

    echo $clanek->getTextClanku();

    echo "<br /><hr /><br />";

    echo "<table><tr><td>";

    $form = new Forms("clanek_add_3", "form_zpr.php", "POST");
    $form->AddHidden("akce", "clanek_add_3");
    $form->AddHidden("kam", "predchozi");
    $form->ShowResetButton(false);
    $form->SetSubmitValue("Předchozí");
    $form->ShowForm();
    unset($form);

    echo "</td><td>";

    $form = new Forms("clanek_add_3", "form_zpr.php", "POST");
    $form->AddHidden("akce", "clanek_add_3");
    $form->AddHidden("kam", "uloz_clanek");
    $form->AddInput("Datum vydání:", "datum", "text", 20, date("Y-m-d H:m:i"));
    $form->ShowResetButton(false);
    $form->SetSubmitValue("Ulož článek");
    $form->ShowForm();
    unset($form);

    echo "</td></tr></table>";
?>