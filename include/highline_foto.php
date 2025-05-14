<?php
  require_once("include/class/Forms.php");
  $form = new Forms("uploadFoto", "form_zpr.php", "POST");
  $form->AddHidden("id_l", $id_l);
  $form->AddHidden("akce", "highline_kotveni_foto_add");
  $form->AddInput("Popis:", "popis", "text", "50", null);
  $form->AddUpload("Fotka:", "foto", null);
  $form->ShowForm();
?>
