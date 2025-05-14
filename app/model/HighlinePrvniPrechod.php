<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class HighlinePrvniPrechod extends Table {

    /** @var string */
    protected $tableName = 'prvni_prechody_hl';
    public $id;
    public $nick;
    public $styl;

    /**
     * @param string $nick
     * @param string $styl
     * @param int $uzivatel_id
     * @param int $highline_id
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function add($nick, $styl, $uzivatel_id, $highline_id) {
        return $this->getTable()->insert(array(
                    'nick' => $nick,
                    'styl' => $styl,
                    'uzivatel_id' => $uzivatel_id,
                    'highline_id' => $highline_id
                ));
    }

}
