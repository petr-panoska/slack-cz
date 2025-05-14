<?php

namespace Slack\Models;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Form
 *
 * @author Vejvis
 */
class Form extends \Nette\Application\UI\Form
{

  function __construct()
  {
    parent::__construct();
    $this->elementPrototype->addAttributes(array('class' => 'form-horizontal'));

    $renderer = $this->getRenderer();
    $renderer->wrappers['controls']['container'] = 'dl';
    $renderer->wrappers['pair']['container'] = "div class='form-group'";
    $renderer->wrappers['label']['container'] = "label class='col-sm-2 control-label'";
    $renderer->wrappers['control']['container'] = "div class='col-sm-10'";
  }

  public function addPassword($name, $label = NULL, $cols = NULL, $maxLength = NULL)
  {
    return parent::addPassword($name, $label, $cols, $maxLength)->setAttribute("class", "form-control");
  }

  public function addText($name, $label = NULL, $cols = NULL, $maxLength = NULL)
  {
    return parent::addText($name, $label, $cols, $maxLength)->setAttribute("class", "form-control");
  }

  public function addCaptcha($name, $label = NULL)
  {
    return parent::addText($name, $label)->setAttribute("class", "form-control captcha");
  }

  public function addTextArea($name, $label = NULL, $cols = NULL, $rows = NULL)
  {
    return parent::addTextArea($name, $label, $cols, $rows)->setAttribute("class", "form-control");
  }

  public function addEditor($name, $label = NULL, $cols = NULL, $rows = NULL)
  {
    return parent::addTextArea($name, $label, $cols, $rows)->setAttribute("class", "form-control ckeditor");
  }

  public function addStarRating($name, $label = NULL, $min = 0, $max = 5, $step = 1)
  {
    return parent::addText($name, $label)
        ->setAttribute("class", "rating")
        ->setAttribute("data-min", $min)
        ->setAttribute("data-max", $max)
        ->setAttribute("data-step", $step)
        ->setAttribute("data-size", 'xs');
  }

  public function addSubmit($name, $caption = NULL)
  {
    return parent::addSubmit($name, $caption)->setAttribute("class", "btn btn-info");
  }

  public function addUpload($name, $caption = NULL, $multiple = false)
  {
    return parent::addUpload($name, $caption)->setAttribute("class", "file");
  }

  public function addDatePicker($name, $caption = null)
  {
    return parent::addText($name, $caption)
        ->setAttribute("class", "form-control datepicker")
        ->setAttribute("autocomplete", "off");
  }

}
