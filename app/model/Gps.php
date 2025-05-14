<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class Gps extends Table {

    /** @var string */
    protected $tableName = 'gps';

    public $lat;
    public $lng;
    public $zoom;


    /**
     * @param float $lat
     * @param float $lng
     * @param int $zoom
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function addGps($lat, $lng, $zoom) {
        return $this->getTable()->insert(array(
                    'lat' => $lat,
                    'lng' => $lng,
                    'zoom' => $zoom
                ));
    }
}
