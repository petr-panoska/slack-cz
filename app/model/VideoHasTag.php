<?php

namespace HighlinesBook;

/**
 * Model starající se o tabulku user
 */
class VideoHasTag extends Table
{

    /** @var string */
    protected $tableName = 'slack_videoHasTag';
    public $video;
    public $tag;

    public function addVideoHasTag($videoId, $tag)
    {

        return $this->getTable()->insert(array(
                    'tag' => $tag,
                    'video' => $videoId,
        ));
    }

    public function removeVideos($videoId)
    {
        $this->findBy(array('video' => $videoId))->delete();
    }

}
