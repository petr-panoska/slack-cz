<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class HighlineMedia extends Table {

    /** @var string */
    protected $tableName = 'highline_media';

    public $typ;
    public $popis;
    public $adresa;
    public $nick;


    /**
	 * @param string $name
	 * @param int $height
	 * @param int $length
         * @param string $text
	 *
	 * @return \Nette\Database\Table\ActiveRow
	 */
	public function createHighline($name, $height, $length, $text)
	{
		return $this->getTable()->insert(array(
			'name' => "ddd",
			'height' => 12,
			'length' => 32,
			'text' => "dfsd"
		));
	}

}
