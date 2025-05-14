<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DataTable
 *
 * @author Vejvis
 */

namespace HighlinesBook;

use Nette;

class Data extends Nette\Application\UI\Control {

    public function __construct() {
        parent::__construct(); // vždy je potřeba volat rodičovský konstruktor
    }

    public function render() {
        $this->template->setFile(__DIR__ . '/Data.latte');
        $this->template->render();
    }

}
