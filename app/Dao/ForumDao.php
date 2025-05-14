<?php

namespace Slack\Dao;

use Slack\Models\Highline;

/**
 * @author Vejvis
 */
class ForumDao extends BaseDao
{

    const ENTITY_NAME = "Slack\Models\Forum\Message";

    /**
     *
     * @param type $id
     * @return Highline
     */
    public static function find($id)
    {
        return parent::find(self::ENTITY_NAME, $id);
    }

    public static function search($term)
    {
        return parent::search(self::ENTITY_NAME, array("text"), $term, "datum", self::SORT_DESC);
    }

    public static function findAll()
    {
        return parent::findAll(self::ENTITY_NAME);
    }

}
