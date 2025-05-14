<?php

use Slack\EnvironmentManager;

/**
 * Homepage presenter.
 */
class ArticlePresenter extends BasePresenter
{

    protected function startup()
    {
        parent::startup();
    }

    public function renderDefault($tag)
    {
        $em = EnvironmentManager::getEntityManager();
        $dql = "SELECT a FROM Slack\Models\Article a JOIN a.tags t WHERE t.name=:tag AND a.viditelnost=true ORDER BY a.datum DESC";
        $this->template->articles = $em->createQuery($dql)->setParameter('tag', $tag)->getResult();
    }

    public function renderTags()
    {
        $this->template->tags = EnvironmentManager::getEntityManager()->getRepository("Slack\Models\Tag")->findAll();
    }

}
