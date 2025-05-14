<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class HighlineFoto extends Table {

    /** @var string */
    protected $tableName = 'highline_foto';
    public $id;
    public $text;
    public $file;


    /**
     * @param string $text
     * @param string $file
     * @param int $highline_id
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function add($text, $file, $highline_id) {
        return $this->getTable()->insert(array(
                    'text' => $text,
                    'file' => $file,
                    'highline_id' => $highline_id,
                ));
    }

}
