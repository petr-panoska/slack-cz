<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class Promenna extends Table {

    /** @var string */
    protected $tableName = 'promenne';

    public $nazev;
    public $hodnota;
    public $hodnota2;
    public $hodnota3;
    public $hodnota4;

    /**
     * @param string $name
     * @param string $hodnota
     * @param string $hodnota2
     * @param string $hodnota3
     * @param string $hodnota4
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function update($name, $hodnota, $hodnota2, $hodnota3, $hodnota4) {
        return $this->getTable()->find($name)->update(array(
                    'hodnota' => $hodnota,
                    'hodnota2' => $hodnota2,
                    'hodnota3' => $hodnota3,
                    'hodnota4' => $hodnota4
                ));
    }

}
