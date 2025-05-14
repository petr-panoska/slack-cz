<h2>Editace článku</h2>
<?php
  include("include/class/Forms.php");
  include("include/tinymce/tiny_mce.php");
  
  $form = new Forms("clanek_edit_2", "form_zpr.php", "POST");
  $form->AddHidden("akce", "clanek_edit_2");
  $form->AddHidden("kam", "uloz_text");
  $form->AddTinymce("text", "500", "100", $clanek->getTextClanku());
  $form->SetSubmitValue("Ulož text");
  $form->ShowForm();
  unset($form);
  
  $form = new Forms("clanek_edit_2", "form_zpr.php", "POST");
  $form->AddHidden("akce", "clanek_edit_2");
  $form->AddHidden("kam", "predchozi");
  $form->ShowResetButton(false);
  $form->SetSubmitValue("Předchozí");
  $form->ShowForm();
  unset($form);
  
  $form = new Forms("clanek_edit_2", "form_zpr.php", "POST");
  $form->AddHidden("akce", "clanek_edit_2");
  $form->AddHidden("kam", "dalsi");
  $form->ShowResetButton(false);
  $form->SetSubmitValue("Další");
  $form->ShowForm();
  unset($form);
?>
