<h2>Editace článku</h2>
<?php
  include("include/class/Forms.php");
  
  $form = new Forms("clanek_edit_1", "form_zpr.php", "POST");
  $form->AddHidden("akce", "clanek_edit_1");
  $form->AddHidden("kam", "dalsi");
  $form->AddInput("Název článku:", "nazev", "text", 50, $clanek->getNazev(), false);
  $form->AddTextarea("Popisek:", "popis", "300px", "100px", $clanek->getPopis());
  //$form->AddInput("Datum zobrazení:", "datum", "text", 20, $clanek->getDatum(), false);
  $form->AddInput("Album:", "album", "text", 50, $clanek->getAlbum(), true);
  $form->SetSubmitValue("Další");
  $form->ShowForm();
  unset($form);
?>

