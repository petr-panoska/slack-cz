<?php

use HighlinesBook\Highline;
use HighlinesBook\Page;
use Slack\EnvironmentManager;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{
  /** @var Highline */
  private $highline;

  /** @var Page */
  private $page;

  protected function startup()
  {
    parent::startup();
    $this->highline = $this->context->highline;
    $this->page = $this->context->page;
  }

  public function actionDefault()
  {
    $this->redirect('Homepage:page', 'vitejte');
  }

  public function renderPage($name)
  {
    $this->template->page = $this->page->find($name);
    $em = EnvironmentManager::getEntityManager();
    if ($this->template->page == null) {
      echo "Stránka nenalezena";
      $this->terminate();
    }
    switch ($name) {
      case 'vitejte':
        $em = EnvironmentManager::getEntityManager();
        $this->template->news = $em->createQuery("SELECT n FROM Slack\Models\News n ORDER BY n.datum DESC")->setMaxResults(3)->getResult();
        break;
      case 'stoplus':
        $this->template->longliners = $em->createQuery("SELECT l FROM Slack\Models\Longliner l ORDER BY l.datum ASC")->getResult();
        break;
    }
  }

  public function renderArticle($article)
  {
    $this->template->article = EnvironmentManager::getEntityManager()->find("Slack\Models\Article", $article);
    if ($this->template->article == null) {
      echo "Článek nenalezen";
      $this->terminate();
    }
  }

  public function actionShowNews($type, $idNew)
  {
    switch ($type) {
      case 'video':
        $this->redirect("Video:show", $idNew);
        break;
      case 'article':
        $this->redirect("Homepage:article", $idNew);
        return;
    }
  }

  public function renderFotoliveSlider($fotolive)
  {
    $this->setLayout(false);
    $em = EnvironmentManager::getEntityManager();
    $actualFotolive = $em->find("Slack\Models\Fotolive", $fotolive);
    $this->template->actualFotolive = $actualFotolive;
    $this->template->nextFotolive = $em->createQuery("SELECT f FROM Slack\Models\Fotolive f WHERE f.fromDate > :fromDate AND f.fromDate <= :now ORDER BY f.fromDate ASC")
        ->setMaxResults(1)->setParameters(array("fromDate" => $actualFotolive->getFromDate(), "now" => new DateTime()))->getOneOrNullResult();
    $this->template->previousFotolive = $em->createQuery("SELECT f FROM Slack\Models\Fotolive f WHERE f.fromDate < :fromDate AND f.fromDate <= :now ORDER BY f.fromDate DESC")
        ->setMaxResults(1)->setParameters(array("fromDate" => $actualFotolive->getFromDate(), "now" => new DateTime()))->getOneOrNullResult();
  }
}
