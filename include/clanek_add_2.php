<?php
include("include/tinymce/tiny_mce.php");
include("include/class/Forms.php");
if(isset($_SESSION["clanek_add"])){
    $clanek = unserialize($_SESSION["clanek_add"]);
    $clanek->vypisChyby();
}else{
    echo '<p class="chyba">Špatné vytvoření článku!</p>';
}

$form = new Forms("clanek_add_text", "form_zpr.php", "POST");
$form->AddHidden("akce", "clanek_add_2");
$form->AddHidden("kam", "text");
$form->AddTinymce("text", 500, 200, $clanek->getTextClanku());
$form->SetSubmitValue("Ulož text");
$form->ShowResetButton(false);
$form->ShowForm();
unset($form);

$form = new Forms("clanek_add_predchozi", "form_zpr.php", "POST");
$form->AddHidden("akce", "clanek_add_2");
$form->AddHidden("kam", "predchozi");
$form->SetSubmitValue("Předchozí");
$form->ShowResetButton(false);
$form->ShowForm();
unset($form);

$form = new Forms("clanek_add_predchozi", "form_zpr.php", "POST");
$form->AddHidden("akce", "clanek_add_2");
$form->AddHidden("kam", "dalsi");
$form->SetSubmitValue("Další");
$form->ShowResetButton(false);
$form->ShowForm();
unset($form);

$form = new Forms("clanek_add_predchozi", "form_zpr.php", "POST");
$form->AddHidden("akce", "clanek_add_konec");
$form->SetSubmitValue("Konec");
$form->ShowResetButton(false);
$form->ShowForm();
unset($form);
echo "album: ".$clanek->getAlbum();
?>      
<br />
<hr />
<br />
<u>Info:</u>
<br />
Před stisknutím tlačítka <i>Další</i> nebo <i>Předchozí</i> musíte vždy stisknou tlačítko <i>Ulož text</i>, jinak nebudou provedené změny uloženy!
<br />
<br />
<u>Jak vložit obrázek?</u>
<br />
V menu klikněte na ikonu <img src="img/ico/picture_add.gif" alt="obrázku se stromem">. 
Otevře se vám nové okno Insert/Edit image. V něm se po kliknutí na ikonu <img src="img/ico/picture_up.gif" alt=""> zobrazí formulář pro nahrání souboru. Vyberte soubor z vašeho počítače a klikněte na <i>Upload</i>. Vaše fotka se to zobrazí v rámečku preview a po kliknutí na <i>Insert</i> se vloží do článku.
<br />
<br />
<u>Jak vložit obrázek?</u>
<br />
Nejprve označte text, který chcete změnit v odkaz. Po kliknutí na ikonu <img src="img/ico/link.gif" alt=" řetězu "> se vám zobrazí formulář pro nastavení parametru odkazu. Stačí vyplnit Link URL a stisknout <i>Insert</i>.