<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class Highline extends Table {

    /** @var string */
    protected $tableName = 'highline';
    public $id;
    public $jmeno;
    public $delka;
    public $vyska;
    public $stat;
    public $oblast;
    public $info;
    public $datum;
    public $autor;
    public $uzivatel_id;

    /**
     * @param string $name
     * @param int $height
     * @param int $length
     * @param string $text
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function createHighline($jmeno, $delka, $vyska, $stat, $oblast, $kraj, $gps, $hodnoceni, $kotveni, $info, $autor, $datum, $admin) {
        return $this->getTable()->insert(array(
                    'jmeno' => $jmeno,
                    'delka' => $delka,
                    'vyska' => $vyska,
                    'stat' => $stat,
                    'oblast' => $oblast,
                    'kraj' => $kraj,
                    'gps' => $gps,
                    'hodnoceni' => $hodnoceni,
                    'kotveni' => $kotveni,
                    'info' => $info,
                    'autor' => $autor,
                    'datum' => $datum,
                    'uzivatel_id' => $admin
                ));
    }

    /**
     * @param string $name
     * @param int $height
     * @param int $length
     * @param string $text
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function updateHighline($id, $jmeno, $delka, $vyska, $stat, $oblast, $kraj, $gps, $hodnoceni, $kotveni, $info, $autor, $datum, $admin) {
        return $this->getTable()->find($id)->update(array(
                    'jmeno' => $jmeno,
                    'delka' => $delka,
                    'vyska' => $vyska,
                    'stat' => $stat,
                    'oblast' => $oblast,
                    'kraj' => $kraj,
                    'gps' => $gps,
                    'hodnoceni' => $hodnoceni,
                    'kotveni' => $kotveni,
                    'info' => $info,
                    'autor' => $autor,
                    'datum' => $datum,
                ));
    }

    /**
     * @param int $highline_id
     * @param int $oddil
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function setOddil($highline_id, $oddil) {
        return $this->getTable()->find($highline_id)->update(array(
                    'highline_oddily_id' => $oddil
                ));
    }

}
