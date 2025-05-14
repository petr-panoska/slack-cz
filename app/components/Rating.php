<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Rating
 *
 * @author Vejvis
 */

namespace HighlinesBook;

class Rating extends \Nette\Application\UI\Control{

    public function __construct() {
        parent::__construct(); // vždy je potřeba volat rodičovský konstruktor
    }

    public function render($rate = 0, $max = 5){
        $this->template->setFile(__DIR__ . '/Rating.latte');
        $this->template->rate = $rate;
        $this->template->max = $max;
        $this->template->disabled = false;
        $this->template->render();
    }
}
