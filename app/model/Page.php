<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class Page extends Table
{

    /** @var string */
    protected $tableName = 'slack_page';
    public $id;
    public $name;
    public $caption;
    public $text;
    public $lastChange;

    /**
     * @param int $id
     * @param string $text
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function update($id, $caption, $text)
    {
        return $this->getTable()->wherePrimary($id)->update(array(
                    'text' => $text,
                    'caption' => $caption,
                    'lastChange' => new \DateTime()
        ));
    }

    /**
     * @param string $name
     * @param string $caption
     * @param string $text
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function createPage($name, $caption, $text)
    {
        return $this->getTable()->insert(array(
                    'name' => $name,
                    'caption' => $caption,
                    'text' => $text,
                    'lastChange' => new \DateTime()
        ));
    }

}
