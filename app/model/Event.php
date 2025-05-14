<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class Event extends Table {

    /** @var string */
    protected $tableName = 'event';

    public $nazev;
    public $start;
    public $stop;
    public $text;
    public $cena;
    public $kontakt;
    public $adresa;
    public $gps_id;


    /**
     * @param string $name
     * @param int $height
     * @param int $length
     * @param string $text
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function addEvent($nazev, $start, $stop, $text, $cena, $kontakt, $adresa, $gps_id = NULL, $uzivatel_id = NULL) {
        return $this->getTable()->insert(array(
                    'nazev' => $nazev,
                    'start' => $start,
                    'stop' => $stop,
                    'text' => $text,
                    'cena' => $cena,
                    'kontakt' => $kontakt,
                    'adresa' => $adresa,
                    'gps_id' => $gps_id,
                    'uzivatel_id' => $uzivatel_id
                ));
    }
}
