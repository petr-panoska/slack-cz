<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class Forum extends Table {

    /** @var string */
    protected $tableName = 'forum';

    public $id;
    public $jmeno;
    public $email;
    public $www;
    public $text;
    public $datum;
    public $nick;
    public $id_u;
    public $ip;


}
