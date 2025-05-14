<?php
session_start();
include("include/class/Forms.php");
if(isset($_SESSION["clanek_add"])){
    $clanek = unserialize($_SESSION["clanek_add"]);
    $clanek->vypisChyby();
}else{
    echo '<p class="chyba">Špatné vytvoření článku!</p>';
}

$form = new Forms("clanek_add_1", "form_zpr.php", "POST");
$form->AddHidden("akce", "clanek_add_1");
$form->AddInput("Název článku:", "nazev", "text", 50, $clanek->getNazev());
$form->AddTextarea("Popis článku:", "popis", 200, 100, $clanek->getPopis());
$form->ShowResetButton(false);
$form->SetSubmitValue("Další");
$form->ShowForm();
unset($form);

$form = new Forms("clanek_add_konec", "form_zpr.php", "POST");
$form->AddHidden("akce", "clanek_add_konec");
$form->SetSubmitValue("Ukončit přidávání článku");
$form->ShowResetButton(false);
$form->ShowForm();
unset($form);
?>               
<br />
<hr />
<br />
<u>Název článku:</u>  Nadpis článku.<br />
<u>Popis článku:</u>  Krátký popis obsahu článku, bude zobrazen v novinkách.