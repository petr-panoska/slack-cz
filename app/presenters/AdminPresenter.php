<?php

use HighlinesBook\Event;
use HighlinesBook\Highline;
use HighlinesBook\HighlineOddil;
use HighlinesBook\Page;
use HighlinesBook\Promenna;
use HighlinesBook\Tag;
use HighlinesBook\Video;
use HighlinesBook\VideoHasTag;
use ImageOptimizer\OptimizerFactory;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Image;
use Nette\Utils\Strings;
use Slack\EnvironmentManager;
use Slack\Models\Article;
use Slack\Models\Components\Collection\Collection;
use Slack\Models\Form as Form2;
use Slack\Models\Fotolive;
use Slack\Models\Longliner;
use Slack\Models\Tag as Tag2;
use Slack\Models\Video as Video2;

class AdminPresenter extends BasePresenter
{
  /**
   * @var Promenna
   */
  private $promenna;

  /**
   * @var Highline
   */
  private $highline;

  /**
   * @var HighlineOddil
   */
  private $highlineOddil;

  /**
   * @var Event
   */
  private $event;

  /**
   * @var Page
   */
  private $page;

  /**
   * @var Video
   */
  private $video;

  /**
   * @var Tag
   */
  private $tag;

  /**
   * @var VideoHasTag
   */
  private $videoHasTag;

  protected function startup()
  {
    parent::startup();
    if (!$this->getUser()->isInRole('admin')) {
      echo "Nemáte oprávnění k přístupu na tyto stránky.";
      $this->terminate();
    }

    $this->promenna = $this->context->promenna;
    $this->highline = $this->context->highline;
    $this->highlineOddil = $this->context->highlineOddil;
    $this->event = $this->context->event;
    $this->page = $this->context->page;
    $this->video = $this->context->video;
    $this->videoHasTag = $this->context->videoHasTag;
    $this->tag = $this->context->tag;
  }

  public function renderUsers()
  {
    $em = EnvironmentManager::getEntityManager();
    $this->template->users = $em->getRepository("Slack\Models\User")->findBy(array("enabled" => true));
  }

  public function renderElfinder()
  {

  }

  public function renderFotolive()
  {
    $this->template->fotolives = EnvironmentManager::getEntityManager()->createQuery("SELECT f FROM Slack\Models\Fotolive f ORDER BY f.fromDate DESC")->getResult();
  }

  public function handleDeleteFotolive($fotoliveId)
  {
    $em = EnvironmentManager::getEntityManager();

    /** @var Fotolive $fotolive */
    $fotolive = $em->find("Slack\Models\Fotolive", $fotoliveId);
    if ($fotolive == null) {
      $this->flashMessage("Fotolive nenalezeno");
      return;
    }

    if (file_exists($fotolive->getPathToFoto())) {
      unlink($fotolive->getPathToFoto());
    }
    if (file_exists($fotolive->getPathToThumbnail())) {
      unlink($fotolive->getPathToThumbnail());
    }

    $em->remove($fotolive);
    $em->flush();

    $this->invalidateControl("fotoliveImages");
    $this->flashMessage("Fotolive smazáno");
  }

  public function handleSetVideoTags($videoId, $tags)
  {
    $tagy = explode(',', $tags);
    $this->videoHasTag->removeVideos($videoId);
    foreach ($tagy as $tag) {
      //echo "videoId:".$videoId." tag:".$tag."\n";
      $this->videoHasTag->addVideoHasTag($videoId, $tag);
    }
    $this->flashMessage("Tagy uloženy.");
  }

  public function renderEditTag($tagName)
  {
    $em = EnvironmentManager::getEntityManager();
    $tag = $em->find('Slack\Models\Tag', $tagName);
    $hodnoty = array(
        'action' => 'edit',
        'name' => $tag->getName(),
        'caption' => $tag->getCaption()
    );
    $this['tagForm']->setDefaults($hodnoty);
  }

  public function renderTags()
  {
    $em = EnvironmentManager::getEntityManager();
    $this->template->tags = $em->getRepository("Slack\Models\Tag")->findAll();
  }

  public function renderEditVideo($video)
  {
    $em = EnvironmentManager::getEntityManager();
    $this->template->video = null;
    if ($video != null) {
      $video = $em->find("Slack\Models\Video", $video);

      $this->template->video = $video;
      if ($this->template->video != null) {
        $tags = array();
        foreach ($video->getTags() as $tag) {
          $tags[] = $tag->getName();
        }
        $hodnoty = array(
            'action' => 'edit',
            'videoId' => $video->getId(),
            'name' => $video->getNazev(),
            'address' => $video->getAdresa(),
            'tags' => $tags,
            'text' => $video->getPopis()
        );
        $this['videoAddForm']->setDefaults($hodnoty);
      }
    }
  }

  public function handleRemoveVideo($id)
  {
    if ($this->video->delete($id) != null) {
      $this->flashMessage('Video smazáno', 'success');
      $this->redrawControl('videosTable');
    } else {
      $this->flashMessage('Video se nepodařilo smazat.', 'danger');
    }
  }

  public function renderResources()
  {

  }

  public function renderVideos()
  {
    $em = EnvironmentManager::getEntityManager();
    $this->template->videos = $em->createQuery("SELECT v FROM Slack\Models\Video v ORDER BY v.datum DESC")->getResult();
  }

  public function videoTags()
  {

  }

  public function renderPages()
  {
    $this->template->pages = $this->page->findAll();
  }

  public function renderEditPage($name)
  {
    $page = $this->page->find($name);
    $this->template->page = $page;
    if ($this->template->page != null) {
      $hodnoty = array(
          'action' => 'edit',
          'name' => $page->name,
          'caption' => $page->caption,
          'text' => $page->text
      );
      $this['editPageForm']->setDefaults($hodnoty);
    }
  }

  public function renderEditArticle($articleId)
  {
    $em = EnvironmentManager::getEntityManager();
    $article = ($articleId != null ? $em->find("Slack\Models\Article", $articleId) : null);
    $this->template->article = $article;
    if ($this->template->article != null) {
      /* pokud existuje name tak předvyplň form daty */
      $tags = array();
      foreach ($article->getTags() as $tag) {
        $tags[] = $tag->getName();
      }
      $hodnoty = array(
          'action' => 'edit',
          'articleId' => $article->getId(),
          'name' => $article->getNazev(),
          'tags' => $tags,
          'public' => $article->getViditelnost(),
          'caption' => $article->getPopis(),
          'text' => $article->getText()
      );
      $this['editArticleForm']->setDefaults($hodnoty);
    }
    /* jinak se vytváří nová stránk */
  }

  public function renderElfinderData()
  {
    $opts = array(
        'locale' => '',
        'roots' => array(
            array(
                'driver' => 'LocalFileSystem',
                'path' => 'media',
                'URL' => '/media'
            ),
            array(
                'driver' => 'LocalFileSystem',
                'path' => 'data/banners',
                'URL' => '/data/banners'
            )
        )
    );

    // run elFinder
    $elfinder = new elFinder($opts);
    $connector = new elFinderConnector($elfinder);
    $connector->run();
    //phpinfo();
    $this->terminate();
  }

  public function actionEvents()
  {
    if (!$this->getUser()->isLoggedIn() || !$this->getUser()->isAllowed('Admin:events')) {
      $this->flashMessage('Nemáte oprávnění pro tuto stránku.', 'error');
      $this->redirect('Highlines:');
    }
  }

  public function renderEvents()
  {
    $this->template->events = $this->event->findAll()->order('start');
  }

  public function actionOddily()
  {

  }

  public function renderOddily()
  {
    $this->template->highlines = $this->highline->findAll()->where('highline_oddily_id', 0)->order('id');
  }

  public function actionRekordy()
  {
    if (!$this->getUser()->isLoggedIn() || !$this->getUser()->isAllowed('Admin:rekordy', 'edit')) {
      $this->flashMessage('Na požadovanou operaci nemáte oprávnění.', 'error');
      $this->redirect('Highlines:');
    }
  }

  public function renderRekordy()
  {
    $polh = $this->promenna->find('pol_highline');
    $poll = $this->promenna->find('pol_longline');
    $highh = $this->promenna->find('high_highline');
    $highl = $this->promenna->find('high_longline');

    $hodnoty = array(
        'pol_highline_WR' => $polh->hodnota,
        'pol_highline_WR_info' => $polh->hodnota2,
        'pol_highline_CR' => $polh->hodnota3,
        'pol_highline_CR_info' => $polh->hodnota4,
        'pol_longline_WR' => $poll->hodnota,
        'pol_longline_WR_info' => $poll->hodnota2,
        'pol_longline_CR' => $poll->hodnota3,
        'pol_longline_CR_info' => $poll->hodnota4,
        'high_highline_WR' => $highh->hodnota,
        'high_highline_WR_info' => $highh->hodnota2,
        'high_highline_CR' => $highh->hodnota3,
        'high_highline_CR_info' => $highh->hodnota4,
        'high_longline_WR' => $highl->hodnota,
        'high_longline_WR_info' => $highl->hodnota2,
        'high_longline_CR' => $highl->hodnota3,
        'high_longline_CR_info' => $highl->hodnota4
    );
    $this['rekordyForm']->setDefaults($hodnoty);
  }

  public function renderDefault()
  {
    $this->template->articles = EnvironmentManager::getEntityManager()->createQuery("SELECT a FROM Slack\Models\Article a ORDER BY a.datum DESC")->getResult();
  }

  public function renderLonglinersclub()
  {
    $em = EnvironmentManager::getEntityManager();
    $this->template->longliners100 = $em->createQuery("SELECT l FROM Slack\Models\Longliner l WHERE l.oddil=:oddil ORDER BY l.datum ASC")->setParameter("oddil", "100+")->getResult();
    $this->template->longliners200 = $em->createQuery("SELECT l FROM Slack\Models\Longliner l WHERE l.oddil=:oddil ORDER BY l.datum ASC")->setParameter("oddil", "200+")->getResult();
  }

  /*
   * ************** SIGNALY ***************
   */

  public function handleSetOddil($highline_id, $oddil)
  {
    $h = $this->highline->setOddil($highline_id, $oddil);
    $this->flashMessage('Highline ' . $h->jmeno . ' byla vložena do oddílu ' . $oddil, 'success');
    $this->redirect('this');
  }

  public function handleDelEvent($id_e)
  {
    $this->event->delete($id_e);
    $this->flashMessage('Událost smazána.', 'success');
    $this->redirect('this');
  }

  public function handleDeleteUser($user)
  {
    $em = EnvironmentManager::getEntityManager();
    $userObj = $em->find("Slack\Models\User", $user);
    if ($userObj == null) {
      $this->flashMessage('Uživatel nenalezen.', 'danger');
      return;
    }
    $userObj->setEnabled(false);
    $em->flush();
    $this->flashMessage('Uživatel smazán.', 'success');
    $this->invalidateControl("AdminUsersList");
    $this->redirect("Admin:users");
  }

  /*
   * ************** FORMULARE ***************
   */

  protected function createComponentRekordyForm()
  {
    $form = new Form();
    $form->addGroup('Highline (polyester)');
    $form->addText('pol_highline_WR', 'Highline (svět):', 40, 3);
    $form->addTextArea('pol_highline_WR_info', 'Info (svět):', 40, 5);
    $form->addText('pol_highline_CR', 'Highline (ČR):', 40, 3);
    $form->addTextArea('pol_highline_CR_info', 'Info (ČR):', 40, 5);

    $form->addGroup('Longline (polyester)');
    $form->addText('pol_longline_WR', 'Longline (svět):', 40, 3);
    $form->addTextArea('pol_longline_WR_info', 'Info (svět):', 40, 5);
    $form->addText('pol_longline_CR', 'Longline (ČR):', 40, 3);
    $form->addTextArea('pol_longline_CR_info', 'Info (ČR):', 40, 5);

    $form->addGroup('Highline (high-tec)');
    $form->addText('high_highline_WR', 'Highline (svět):', 40, 3);
    $form->addTextArea('high_highline_WR_info', 'Info (svět):', 40, 5);
    $form->addText('high_highline_CR', 'Highline (ČR):', 40, 3);
    $form->addTextArea('high_highline_CR_info', 'Info (ČR):', 40, 5);

    $form->addGroup('Longline (high-tec)');
    $form->addText('high_longline_WR', 'Longline (svět):', 40, 3);
    $form->addTextArea('high_longline_WR_info', 'Info (svět):', 40, 5);
    $form->addText('high_longline_CR', 'Longline (ČR):', 40, 3);
    $form->addTextArea('high_longline_CR_info', 'Info (ČR):', 40, 5);

    $form->addGroup();
    $form->addSubmit('save', 'Uložit změny');
    $form->onSuccess[] = $this->rekordyFormSubmitted;
    return $form;
  }

  protected function createComponentEditPageForm()
  {
    $form = new Form();
    $form->elementPrototype->addAttributes(array('class' => 'form-horizontal'));

    $renderer = $form->getRenderer();
    $renderer->wrappers['controls']['container'] = 'dl';
    $renderer->wrappers['pair']['container'] = "div class='form-group'";
    $renderer->wrappers['label']['container'] = "label class='col-sm-2 control-label'";
    $renderer->wrappers['control']['container'] = "div class='col-sm-10'";

    $form->addHidden('action');
    $form->addText('name', 'Název:', 40, 100)->setAttribute("class", "form-control");
    $form->addText('caption', "Popisek:", 40, 100)->setAttribute("class", "form-control");
    $form->addTextArea('text', 'Text')->setAttribute("class", "form-control");
    $form->addSubmit('send', 'Uložit změny')->setAttribute("class", "btn btn-default");

    $form->onSuccess[] = $this->editPageFormSubmitted;
    return $form;
  }

  protected function createComponentVideoAddForm()
  {
    $em = EnvironmentManager::getEntityManager();
    $tagsObj = $em->getRepository("Slack\Models\Tag")->findAll();
    $tags = array();
    foreach ($tagsObj as $tag) {
      $tags[$tag->getName()] = $tag->getName();
    }

    $form = new Form();
    $form->elementPrototype->addAttributes(array('class' => 'form-horizontal'));

    $renderer = $form->getRenderer();
    $renderer->wrappers['controls']['container'] = 'dl';
    $renderer->wrappers['pair']['container'] = "div class='form-group'";
    $renderer->wrappers['label']['container'] = "label class='col-sm-2 control-label'";
    $renderer->wrappers['control']['container'] = "div class='col-sm-10'";

    $form->addHidden('action');
    $form->addHidden('videoId');
    $form->addText('name', 'Název:', 40, 30);
    $form->addText('address', 'Adresa:', 40);
    $form->addMultiSelect("tags", "Tagy", $tags);
    $form->addUpload('thumb', 'Náhled:');
    $form->addTextArea('text', 'Popis:');


    $form->addSubmit('save', 'Uložit změny');
    $form->onSuccess[] = array($this, 'videoAddFormSubmitted');
    return $form;
  }

  public function videoAddFormSubmitted(Form $form)
  {
    $val = $form->values;
    $em = EnvironmentManager::getEntityManager();

    if ($val->action == 'edit') {
      $video = $em->find("Slack\Models\Video", $val->videoId);
    } else {
      $video = new Video2();
      $video->setDatum(new DateTime());
    }
    $video->setAdresa($val->address);
    $video->setNazev($val->name);
    $video->setPopis($val->text);
    $video->setTyp('nase');

    $video->clearTags();
    foreach ($val->tags as $tag) {
      $video->addTag($tag);
    }
    $em->persist($video);
    $em->flush();


    $foto = $val->thumb;
    if ($foto->isOk()) {
      $path = DATA_DIR . DS . 'video' . DS . 'video_' . $video->getId() . '.jpg';

      // ulož obrazek
      $foto->move($path);

      // vytvoreni nahledu
      $image = Image::fromFile($path);
      $image->resize(200, 100, Image::SHRINK_ONLY);
      $image->save($path, 80, Image::JPEG);
    }


    $this->flashMessage("Změny uloženy", FLASH_INFO);
    //$this->redirect('Admin:videos');
  }

  protected function createComponentLonglinerAddForm()
  {
    $em = EnvironmentManager::getEntityManager();

    $oddily = new Collection();
    $oddily->put("100+", "100+");
    $oddily->put("200+", "200+");
    $oddily->put("300+", "300+");

    $users = new Collection();
    $users->put("", "Uživatel nemá registraci.");
    foreach ($em->createQuery("SELECT u FROM Slack\Models\User u WHERE u.enabled=1 ORDER BY u.nick ASC")->getResult() as $user) {
      $users->put($user->getId(), $user->getNick() . " - " . $user->getJmeno() . " " . $user->getPrijmeni());
    }

    $form = new Form2();

    $form->addText('date', 'Datum:', 40, 30)
        ->setAttribute("class", "form-control datepicker")
        ->setAttribute("autocomplete", "off");
    $form->addText('long', 'Délka:', 40)->setAttribute("class", "form-control");
    $form->addText("place", "Místo")->setAttribute("class", "form-control");
    $form->addText('name', 'Jméno:')->setAttribute("class", "form-control");
    $form->addSelect('user', 'Registrovaný uživatel:', $users->toArray())->setAttribute("class", "form-control");
    $form->addSelect('oddil', 'Oddíl:', $oddily->toArray())->setAttribute("class", "form-control");


    $form->addSubmit('save', 'Uložit');
    $form->onSuccess[] = array($this, 'longlinerAddFormSubmitted');
    return $form;
  }

  public function longlinerAddFormSubmitted(Form $form)
  {
    $val = $form->getValues();

    $em = EnvironmentManager::getEntityManager();
    $longliner = new Longliner();
    $longliner->setDelka($val->long);
    $longliner->setDatum(new DateTime($val->date));
    $longliner->setJmeno($val->name);
    if ($val->user != null) {
      $user = $em->find("Slack\Models\User", $val->user);
      $longliner->setUser($user);
    }
    $longliner->setMisto($val->place);
    $longliner->setOddil($val->oddil);

    $em->persist($longliner);
    $em->flush();

    $this->flashMessage("Změny uloženy", 'success');
    //$this->redirect('Admin:videos');
  }

  protected function createComponentEditArticleForm()
  {
    $em = EnvironmentManager::getEntityManager();
    $form = new Form();
    $form->elementPrototype->addAttributes(array('class' => 'form-horizontal'));

    $renderer = $form->getRenderer();
    $renderer->wrappers['controls']['container'] = 'dl';
    $renderer->wrappers['pair']['container'] = "div class='form-group'";
    $renderer->wrappers['label']['container'] = "label class='col-sm-2 control-label'";
    $renderer->wrappers['control']['container'] = "div class='col-sm-10'";

    $tagsObj = $em->getRepository("Slack\Models\Tag")->findAll();
    $tags = array();
    foreach ($tagsObj as $tag) {
      $tags[$tag->getName()] = $tag->getName();
    }

    $form->addHidden('action');
    $form->addHidden('articleId');
    $form->addText('name', 'Název:', 40, 100)->setAttribute("class", "form-control");
    $form->addText('caption', "Popisek:", 40, 100)->setAttribute("class", "form-control");
    $form->addUpload('thumb', "Náhled:")->setAttribute("class", "form-control");
    $form->addMultiSelect('tags', "Tagy:", $tags, 10)->setAttribute("class", "form-control");
    $form->addTextArea('text', 'Text')->setAttribute("class", "form-control");
    $form->addCheckbox('public', "Veřejný")->setAttribute("class", "form-control");
    $form->addSubmit('send', 'Uložit změny')->setAttribute("class", "btn btn-default");

    $form->onSuccess[] = $this->editArticleFormSubmitted;
    return $form;
  }

  protected function createComponentTagForm()
  {
    $form = new Form();
    $form->elementPrototype->addAttributes(array('class' => 'form-horizontal'));

    $renderer = $form->getRenderer();
    $renderer->wrappers['controls']['container'] = 'dl';
    $renderer->wrappers['pair']['container'] = "div class='form-group'";
    $renderer->wrappers['label']['container'] = "label class='col-sm-2 control-label'";
    $renderer->wrappers['control']['container'] = "div class='col-sm-10'";

    $form->addHidden('action')->setAttribute("class", "form-control");
    $form->addText('name', "Tag:", 40, 100)->setAttribute("class", "form-control");
    $form->addText('caption', 'Popisek')->setAttribute("class", "form-control");
    $form->addUpload('tagFoto', 'Fotka')->setAttribute("class", "form-control");
    $form->addSubmit('send', 'Uložit tag')->setAttribute("class", "btn btn-default");

    $form->onSuccess[] = $this->newTagSubmitted;
    return $form;
  }

  protected function createComponentFotoliveForm()
  {
    $form = new Form();
    $form->elementPrototype->addAttributes(array('class' => 'form-horizontal'));

    $renderer = $form->getRenderer();
    $renderer->wrappers['controls']['container'] = 'dl';
    $renderer->wrappers['pair']['container'] = "div class='form-group'";
    $renderer->wrappers['label']['container'] = "label class='col-sm-2 control-label'";
    $renderer->wrappers['control']['container'] = "div class='col-sm-10'";

    $form->addText('caption', 'Popisek')->setAttribute("class", "form-control");
    $form->addText('fromDate', 'Zobrazit od datumu')
        ->setAttribute("class", "form-control datepicker")
        ->setAttribute("autocomplete", "off");
    $form->addUpload('foto', 'Fotka')->setAttribute("class", "form-control");
    $form->addSubmit('send', 'Změnit fotolive')->setAttribute("class", "btn btn-default");

    $form->onSuccess[] = $this->editFotoliveSubmitted;
    return $form;
  }

  /*
   * ************** FORMULARE - ZPRACOVANI ***************
   */

  public function rekordyFormSubmitted(Form $form)
  {
    if ($this->getUser()->isLoggedIn() && $this->getUser()->isAllowed('Admin:rekordy', 'edit')) {
      $val = $form->values;
      $this->promenna->update('pol_highline', $val->pol_highline_WR, $val->pol_highline_WR_info, $val->pol_highline_CR, $val->pol_highline_CR_info);
      $this->promenna->update('pol_longline', $val->pol_longline_WR, $val->pol_longline_WR_info, $val->pol_longline_CR, $val->pol_longline_CR_info);
      $this->promenna->update('high_highline', $val->high_highline_WR, $val->high_highline_WR_info, $val->high_highline_CR, $val->high_highline_CR_info);
      $this->promenna->update('high_longline', $val->high_longline_WR, $val->high_longline_WR_info, $val->high_longline_CR, $val->high_longline_CR_info);
      $this->flashMessage('Změny uloženy.', 'success');
      $this->redirect('this');
    } else {
      $this->flashMessage('Na požadovanou operaci nemáte oprávnění.', 'error');
      $this->redirect('Highlines:');
    }
  }

  public function newTagSubmitted(Form $form)
  {
    $em = EnvironmentManager::getEntityManager();
    $val = $form->values;
    if ($val->action == 'edit') {
      $tag = $em->find("Slack\Models\Tag", $val->name);
      if ($tag == null) {
        $this->flashMessage('Tag "' . $val->name . '" nenalezen. Při úpravě tagu nelze změnit jeho název.', 'danger');
        $this->redirect('Admin:Tags');
      }
    } else {
      $tag = new Tag2();
      $tag->setName($val->name);
    }
    $tag->setCaption($val->caption);

    $foto = $val->tagFoto;
    if ($foto->isOk()) {
      $path = DATA_DIR . DS . 'tags' . DS;

      // ulož obrazek
      $foto->move($path . $tag->getName() . '.jpg');

      // vytvoreni nahledu
      $image = Image::fromFile($path . $tag->getName() . '.jpg');
      $image->resize(130, 100, Image::SHRINK_ONLY);
      $image->save($path . $tag->getName() . '.jpg', 80, Image::JPEG);
    }

    $em->persist($tag);
    $em->flush();

    $this->flashMessage('Změny uloženy.', 'success');
    $this->redirect('Admin:Tags');
  }

  public function editPageFormSubmitted(Form $form)
  {

    $val = $form->values;
    if ($val->action == 'edit') {
      $this->page->update($val->name, $val->caption, $val->text);
    } else {
      $this->page->createPage($val->name, $val->caption, $val->text);
    }
    $this->flashMessage('Změny uloženy.', 'success');
    $this->redirect('Admin:pages');
  }

  public function editArticleFormSubmitted(Form $form)
  {
    $em = EnvironmentManager::getEntityManager();
    $val = $form->values;
    if ($val->action == 'edit') {
      $article = $em->find("Slack\Models\Article", $val->articleId);
    } else {
      $article = new Article();
      $article->setDatum(new DateTime());
      $article->setAlbum(Strings::webalize($val->name));
      $user = $em->find("Slack\Models\User", $this->user->id);
      $article->setUzivatel($user);
    }
    $article->setText($val->text);
    $article->setNazev($val->name);
    $article->setPopis($val->caption);
    $article->setViditelnost($val->public);

    $article->clearTags();
    foreach ($val->tags as $tag) {
      $tagObj = $em->find("Slack\Models\Tag", $tag);
      $article->addTag($tagObj);
    }

    if ($val->action != 'edit') {
      $em->persist($article);
    }
    $em->flush();

    $thumb = $val->thumb;
    if ($thumb->isOk()) {
      $path = DATA_DIR . DS . 'clanky' . DS . 'thumbnails' . DS;
      $fileName = 'nahled_' . $article->getId() . '.jpg';

      // ulož obrazek
      $thumb->move($path . $fileName);

      // vytvoreni nahledu
      $image = Image::fromFile($path . $fileName);
      $image->resize(200, 150, Image::SHRINK_ONLY);
      $image->save($path . $fileName, 80, Image::JPEG);
    }

    $this->flashMessage('Změny uloženy.', 'success');
    $this->redirect('Admin:');
  }

  public function editFotoliveSubmitted(Form $form)
  {
    $em = EnvironmentManager::getEntityManager();
    $val = $form->values;

    $fotolive = new Fotolive();
    $fotolive->setFromDate(new DateTime($val->fromDate));
    $fotolive->setPopis($val->caption);
    $fotolive->setFotka("neni potreba");
    $em->persist($fotolive);
    $em->flush();

    /** @var FileUpload $foto */
    $foto = $val->foto;
    if ($foto->isImage() && $foto->isOk()) {
      $image = $foto->toImage();
      if ($image->getWidth() > 2500) {
        $image->resize(2500, null);
      }
      $path = DATA_DIR . DS . 'fotolive' . DS;
      $filepath = $path . 'fotolive_' . $fotolive->getId() . '_full.jpg';
      $image->save($filepath);
      $optimizer = (new OptimizerFactory())->get();
      $optimizer->optimize($filepath);

      $thumbFilepath = $path . 'fotolive_' . $fotolive->getId() . '.jpg';
      $image->resize(300, null);
      $image->save($thumbFilepath);
      $optimizer->optimize($thumbFilepath);
    }

    $this->flashMessage('Fotolive změněno.', 'success');
    $this->redirect('Admin:fotolive');
  }
}
