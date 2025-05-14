<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of HighlinePrechod
 *
 * @author Vejvis
 */
namespace HighlinesBook;

use HighlinesBook\Uzivatel;

class HighlinePrechod extends Table{

    /** @var string */
    protected $tableName = 'highline_prechody';

    public $datum;
    public $styl;
    public $poznamka;
    public $hodnoceni;
    public $uzivatel_id;

    /**
	 * @param string $poznamka
	 * @param string $styl
	 * @param date $datum
         * @param int $hodnoceni
	 *
	 * @return \Nette\Database\Table\ActiveRow
	 */
	public function addAttempt($poznamka, $styl, $datum, $hodnoceni, $highline_id, $uzivatel_id)
	{
		return $this->getTable()->insert(array(
			'poznamka' => $poznamka,
			'datum' => $datum,
			'styl' => $styl,
			'hodnoceni' => $hodnoceni,
                        'highline_id' => $highline_id,
                        'uzivatel_id' => $uzivatel_id
		));
	}

}
