<?php

namespace HighlinesBook\Eshop;

/**
 * Model starající se o tabulku user
 */
class Product extends \HighlinesBook\Table {

    /** @var string */
    protected $tableName = 'products';

    public $nazev;
    public $cena;
    public $id;
    public $del;
    public $slack;
    public $obrshow;

}
