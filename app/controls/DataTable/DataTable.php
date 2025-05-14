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

class DataTable extends Nette\Application\UI\Control {

    public function __construct() {
        parent::__construct(); // vždy je potřeba volat rodičovský konstruktor
    }

    public function render() {
        $this->template->setFile(__DIR__ . '/DataTable.latte');
        $this->template->render();
    }

}
