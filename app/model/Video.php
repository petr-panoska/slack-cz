<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class Video extends Table
{

    /** @var string */
    protected $tableName = 'videa';
    public $id;
    public $nazev;
    public $adresa;
    public $objekt;
    public $typ;
    public $popis;
    public $uzivatel_id;
    public $datum;

    /**
     * @param string $name
     * @param string $adresa
     * @param string $objekt
     * @param string $typ
     * @param string $popis
     * @param string $nick
     * @param date $datum
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function addVideo($nazev, $adresa, $objekt, $typ, $popis, $uzivatel_id, $datum)
    {
        return $this->getTable()->insert(array(
                    'nazev' => $nazev,
                    'adresa' => $adresa,
                    'objekt' => $objekt,
                    'Typ' => $typ,
                    'popis' => $popis,
                    'uzivatel_id' => $uzivatel_id,
                    'datum' => $datum
        ));
    }

    public function videoHasTag($videoId, $tag)
    {
        foreach ($this->getTable() as $video)
            if ($video->id == $videoId)
            {
                foreach ($video->related('slack_videoHasTag') as $videoTag)
                {
                    if ($videoTag->tag == $tag)
                    {
                        return true;
                    }
                }
            }
        return false;
    }

}
