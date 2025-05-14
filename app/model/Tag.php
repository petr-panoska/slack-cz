<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class Tag extends Table
{

    /** @var string */
    protected $tableName = 'slack_tag';
    public $name;
    public $caption;

    public function tagHasVideo($video)
    {
        foreach ($this->getTable()->find(array('name'=>'longline', 'caption'=>'longline'))->related('slack_videoHasTag') as $v)
        {
            if ($v->id == $video)
            {
                return true;
            }
        }
        return false;
    }

    public function addVideoTag($name, $caption)
    {

        return $this->getTable()->insert(array(
                    'name' => $name,
                    'caption' => $caption,
        ));
    }

}
