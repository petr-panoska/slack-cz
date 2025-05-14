<?php

use HighlinesBook\Video;
use Slack\EnvironmentManager;

class VideoPresenter extends BasePresenter
{

    /**
     * @var Video
     */
    private $video;

    protected function startup()
    {
        parent::startup();
        $this->video = $this->context->video;
    }

    public function renderDefault($tag)
    {
        $em = EnvironmentManager::getEntityManager();
        $this->template->tag = $tag;
        $dql = "SELECT v FROM Slack\Models\Video v JOIN v.tags t WHERE t.name=:tag ORDER BY v.datum DESC";
        $this->template->videos = $em->createQuery($dql)->setParameter('tag', $tag)->getResult();
    }

    public function renderTags()
    {
        $em = EnvironmentManager::getEntityManager();
        $this->template->tags = $em->getRepository("Slack\Models\Tag")->findAll();
    }

    public function renderFilter($tag)
    {

    }

    public function renderShow($id)
    {
        $video = EnvironmentManager::getEntityManager()->find('Slack\Models\Video', $id);
        $this->redirectUrl($video->getAdresa());
    }

}
