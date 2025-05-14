<?php

use HighlinesBook\DataTable;
use HighlinesBook\HighlineFoto;
use HighlinesBook\HighlinePrechod;
use ImageOptimizer\OptimizerFactory;
use Nette\Application\AbortException;
use Nette\Environment;
use Nette\Http\FileUpload;
use Nette\Image;
use Slack\Dao\HighlinesDao;
use Slack\EnvironmentManager;
use Slack\Models\Form;
use Slack\Models\Gps;
use Slack\Models\Highline;
use Slack\Models\HighlineAttempt;
use Slack\Models\LatLng;

class HighlinesPresenter extends BasePresenter
{
  /** @persistent array */
  public $filter;

  /**
   * @var HighlinePrechod
   */
  private $highlinePrechod;

  /**
   * @var HighlineFoto
   */
  private $highlineFoto;

  private $styly = [
      'OS fm' => 'OS FM',
      'OS, fm' => 'OS, fm',
      'OS' => 'OS',
      'fm' => 'fm',
      'AF' => 'AF',
      'one way' => 'one way',
      'solo' => 'solo',
      'swami' => 'swami',
  ];

  /**
   * @var HighlinesDao
   */
  private $dao;

  public function injectService(HighlinesDao $dao)
  {
    $this->dao = $dao;
  }

  protected function startup()
  {
    parent::startup();
    $this->highlinePrechod = $this->context->highlinePrechod;
    $this->highlineFoto = $this->context->highlineFoto;
  }

  public function renderDefault()
  {
    $this->template->isUserHighlineAuthor = count($this->dao->findAllForUser($this->getUser()->getId())) > 0;
    $this->template->highlinesWithGps = $this->dao->findAllWithGps();
    $this->template->highlineRoute = $this->link('Highlines:detail');
  }

  public function renderDetail($id, $tab = "info")
  {
    $this->template->showNav = false;
    if ($tab == 'attempts') {
      $this['newAttemptForm']->setDefaults(['highline_id' => $id]);
    }

    $highline = $this->dao->find($id);

    $this->template->point1 = json_encode([
        "lat" => $highline->getPoint1() ? $highline->getPoint1()->getLat() : null,
        "lng" => $highline->getPoint1() ? $highline->getPoint1()->getLng() : null
    ]);
    $this->template->point2 = json_encode([
        "lat" => $highline->getPoint2() ? $highline->getPoint2()->getLat() : null,
        "lng" => $highline->getPoint2() ? $highline->getPoint2()->getLng() : null
    ]);
    $this->template->parking = json_encode([
        "lat" => $highline->getParking() ? $highline->getParking()->getLat() : null,
        "lng" => $highline->getParking() ? $highline->getParking()->getLng() : null
    ]);
    $this->template->highline = $highline;

    //todo is this needed?
    $this->template->strom = strpos($highline->getKotveni(), "1") !== false;
    $this->template->obhoz = strpos($highline->getKotveni(), "2") !== false;
    $this->template->vrtano = strpos($highline->getKotveni(), "3") !== false;
    $this->template->omezeni = strpos($highline->getKotveni(), "4") !== false;

    $qb = EnvironmentManager::getEntityManager()->getRepository(HighlineAttempt::class)->createQueryBuilder('ha');
    $qb
        ->join('ha.highline', 'h')
        ->select('ha')
        ->where($qb->expr()->eq('h.id', ':id'))
        ->setParameter('id', $highline->getId())
        ->orderBy('ha.datum', 'desc')
        ->setMaxResults(5);
    $lastAttempts = $qb->getQuery()->getResult();
    $this->template->lastAttempts = $lastAttempts;
  }

  /**
   * @throws AbortException
   * @deprecated BC route alias
   */
  public function renderMaps()
  {
    $this->redirect('Highlines:default');
  }

  public function actionNew()
  {
    $this->template->highline = null;
  }

  public function renderList()
  {
    $this->template->highlines = $this->dao->findAllForUser($this->getUser()->getId());
  }

  public function renderEdit($id)
  {
    $highline = $this->dao->find($id);

    if (is_null($highline)) {
      $this->flashMessage("Highline nenalezena.");
      $this->redirect("Highlines:");
    }

    $point1 = new LatLng();
    if ($highline->getPoint1() != null) {
      $point1 = $highline->getPoint1()->getPosition();
    }

    $point2 = new LatLng();
    if ($highline->getPoint2() != null) {
      $point2 = $highline->getPoint2()->getPosition();
    }

    $parking = new LatLng();
    if ($highline->getParking() != null) {
      $parking = $highline->getParking()->getPosition();
    }

    $hodnoty = array(
        'jmeno' => $highline->getJmeno(),
        'vyska' => $highline->getVyska(),
        'delka' => $highline->getDelka(),
        'name_history' => $highline->getNameHistory(),
        'info' => $highline->getInfo(),
        'point_one_info' => $highline->getPointOneInfo(),
        'point_two_info' => $highline->getPointTwoInfo(),
        'gps_point1_lat' => $point1->getLat(),
        'gps_point1_lng' => $point1->getLng(),
        'gps_point2_lat' => $point2->getLat(),
        'gps_point2_lng' => $point2->getLng(),
        'gps_parking_lat' => $parking->getLat(),
        'gps_parking_lng' => $parking->getLng(),
        'hodnoceni' => $highline->getHodnoceni(),
        'casNapinani' => $highline->getTimeTensioning(),
        'casPristupu' => $highline->getTimeApproach(),
        'autor' => $highline->getAutor(),
        'datum' => $highline->getDatum() ? $highline->getDatum()->format("Y-m-d") : null,
        'highline' => $id,
    );
    $this['newHighlineForm']->setDefaults($hodnoty);
    $this->template->highline = $highline;
  }

  public function renderFoto($id)
  {
    $hl = $this->dao->find($id);
    $this->template->highline = $hl;
    $this->template->countPhotos = count($hl->getPhotos());
  }

  protected function createComponentNewFoto()
  {

    $form = new Form();
    $form->addGroup('Přidání fotky');
    $form->addText('text', 'Text:', 40, 150);
    $form->addUpload('file', 'Fotka:');

    $form->addSubmit('save', 'Uložit');
    $form->onSuccess[] = $this->newFotoFormSubmitted;
    return $form;
  }

  protected function createComponentNewHighlineForm()
  {
    $form = new Form();
    $form->addHidden('highline', '');
    $form->addText('jmeno', 'Název:', 40, 100)
        ->addRule(Form::FILLED, 'Je nutné zadat název highline.');
    $form->addText('vyska', 'Výška:', 40, 5)
        ->addRule(Form::NUMERIC, 'Výška musí být celé nezáporné číslo.')
        ->addRule(Form::FILLED, 'Je nutné zadat výšku highline.');
    $form->addText('delka', 'Délka:', 40, 3)
        ->addRule(Form::NUMERIC, 'Délka musí být celé nezáporné číslo.')
        ->addRule(Form::FILLED, 'Je nutné zadat délku highline.');
    $form->addText('casNapinani', 'Doba napínání [minuty]:', 40, 3)
        ->addRule(Form::FILLED);
    $form->addText('casPristupu', 'Doba přístupu z parkoviště [minuty]:', 40, 3)
        ->addRule(Form::FILLED);
    $form->addEditor('info', 'Informace:');
    $form->addTextArea('point_one_info', "Kotvení 1:");
    $form->addTextArea('point_two_info', "Kotvení 2:");
    $form->addTextArea('name_history', "Historie názvu:");
    $form->addHidden('gps_point1_lat', null);
    $form->addHidden('gps_point1_lng', null);
    $form->addHidden('gps_point2_lat', null);
    $form->addHidden('gps_point2_lng', null);
    $form->addHidden('gps_parking_lat', null);
    $form->addHidden('gps_parking_lng', null);

    $form->addUpload('foto', 'Fotka (max 2MB):')
        ->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 2MB.', 2000 * 1024)
        ->addCondition(Form::FILLED)
        ->addRule(Form::IMAGE, 'Fotka musí být JPEG, PNG nebo GIF.');

    $form->addDatePicker('datum', 'Datum 1. napnutí:');
    $form->addText('autor', 'Autor:', 40, 100);
    $form->addStarRating('hodnoceni', 'Hodnocení:');

    $form->addSubmit('save', 'Uložit');
    $form->onSuccess[] = array($this, 'newHighlineFormSubmitted');
    return $form;
  }

  public function newHighlineFormSubmitted(Form $form)
  {
    $user = Environment::getUser();

    if (!$user->isLoggedIn()) {
      $this->flashMessage('Pro tuto operaci musíte být přihlášen.', 'warning');
      $this->redirect('Highlines:');
    }
    $userEntity = EnvironmentManager::getEntityManager()->find("Slack\Models\User", Environment::getUser()->id);

    $formValues = $form->values;
    $isNewHighline = true;
    if ($formValues->highline == null) {
      $highline = new Highline();
    } else {
      $highline = $this->dao->find($formValues->highline);
      $isNewHighline = false;
    }

    if (!$isNewHighline && !in_array("admin", $user->getRoles()) && $user->getId() !== $highline->getUzivatel()->getId()) {
      $this->flashMessage('Pro tuto operaci nemáte oprávnění.', 'warning');
      $this->redirect('Highlines:');
    }

    $highline->setJmeno($formValues->jmeno);
    $highline->setVyska($formValues->vyska);
    $highline->setDelka($formValues->delka);
    $highline->setNameHistory($formValues->name_history);
    $highline->setInfo($formValues->info);
    $highline->setPointOneInfo($formValues->point_one_info);
    $highline->setPointTwoInfo($formValues->point_two_info);
    $highline->setTyp(0);
    $highline->setHodnoceni($formValues->hodnoceni);
    $highline->setTimeApproach($formValues->casPristupu);
    $highline->setTimeTensioning($formValues->casNapinani);
    $highline->setAutor($formValues->autor);
    if (DateTime::createFromFormat('Y-m-d', $formValues->datum) !== FALSE) {
      $highline->setDatum(new DateTime($formValues->datum));
    }
    if ($isNewHighline) {
      $highline->setUzivatel($userEntity);
    }

    $point1 = new LatLng($formValues->gps_point1_lat, $formValues->gps_point1_lng);
    $gpsPoint1 = $highline->getPoint1();
    if ($gpsPoint1 == null && !$point1->isEmpty()) {
      $gpsPoint1 = new Gps($point1->getLat(), $point1->getLng(), Gps::LINE_POINT);
      HighlinesDao::persist($gpsPoint1);
      $highline->setPoint1($gpsPoint1);
    } elseif ($gpsPoint1 != null && !$point1->isEmpty()) {
      $gpsPoint1->setLat($point1->getLat());
      $gpsPoint1->setLng($point1->getLng());
      $this->dao->update($gpsPoint1);
    }

    $point2 = new LatLng($formValues->gps_point2_lat, $formValues->gps_point2_lng);
    $gpsPoint2 = $highline->getPoint2();
    if ($gpsPoint2 == null && !$point2->isEmpty()) {
      $gpsPoint2 = new Gps($point2->getLat(), $point2->getLng(), Gps::LINE_POINT);
      HighlinesDao::persist($gpsPoint2);
      $highline->setPoint2($gpsPoint2);
    } elseif ($gpsPoint1 != null && !$point2->isEmpty()) {
      $gpsPoint2->setLat($point2->getLat());
      $gpsPoint2->setLng($point2->getLng());
      $this->dao->update($gpsPoint2);
    }

    $parking = new LatLng($formValues->gps_parking_lat, $formValues->gps_parking_lng);
    $gpsParking = $highline->getParking();
    if ($gpsParking == null && !$parking->isEmpty()) {
      $gpsParking = new Gps($parking->getLat(), $parking->getLng(), Gps::PARKING);
      HighlinesDao::persist($gpsParking);
      $highline->setParking($gpsParking);
    } elseif ($gpsParking != null && !$parking->isEmpty()) {
      $gpsParking->setLat($parking->getLat());
      $gpsParking->setLng($parking->getLng());
      $this->dao->update($gpsParking);
    }

    if ($isNewHighline) {
      HighlinesDao::persist($highline);
      mkdir(HIGHLINES_DIR . DS . $highline->getId(), 0777);
    } else {
      $this->dao->update($highline);
    }

//    $thumb = $formValues->thumb;
//    if ($thumb->isOk()) {
//      $thumb->move(HIGHLINE_DIR . DS . $highline->getId() . DS . "nahled.jpg");
//      $image = Image::fromFile(HIGHLINE_DIR . DS . $highline->getId() . DS . "nahled.jpg");
//      $image->resize(120, NULL, Image::SHRINK_ONLY);
//      $image->save(HIGHLINE_DIR . DS . $highline->getId() . DS . "nahled.jpg", 80, Image::JPEG);
//    } else {
//      $this->flashMessage('Náhled se nezdařilo nahrát na server. Nahrajte nový v editaci highline.', 'warning');
//    }

    /** @var FileUpload $foto */
    $foto = $formValues->foto;
    if ($foto->isImage()) {
      if ($foto->isOk()) {
        $image = $foto->toImage();
        if ($image->getWidth() > 2500) {
          $image->resize(2500, null);
        }
        $filepath = HIGHLINE_DIR . DS . $highline->getId() . DS . "foto.jpg";
        $image->save($filepath);
        $optimizer = (new OptimizerFactory())->get();
        $optimizer->optimize($filepath);
      } else {
        $this->flashMessage('Fotku se nezdařilo nahrát na server. Nahrajte novou v editaci highline.', 'warning');
      }
    }

    if ($isNewHighline) {
      $this->flashMessage('Highline byla úspěšně přidána.', 'success');
      $this->flashMessage('K highline můžete přidat 10 fotek, nejlépe kotvení. <i class="ml-10 fas fa-camera-retro"></i> <a href="' . $this->link('Highlines:detail', ['id' => $highline->getId(), 'tab' => 'gallery']) . '">Přidat fotky</a></i>', 'info');
    } else {
      $this->flashMessage('Změny uloženy.', 'success');
    }
    $this->redirect('Highlines:detail', array("id" => $highline->getId(), "tab" => "info"));
  }

  protected function createComponentNewAttemptForm()
  {
    $hodnoceni = array("1" => "1 hvězda", "2" => "2 hvězdy", "3" => "3 hvězdy", "4" => "4 hvězdy", "5" => "5 hvězd");
    $form = new Form();

    $form->addHidden('highline_id', '');
    $form->addText('poznamka', 'Poznámka:', 40, 100)->setAttribute("class", "form-control");
    $form->addText('datum', "Datum:")
        ->setAttribute("class", "form-control datepicker")
        ->setAttribute("autocomplete", "off");
    $form->addSelect('styl', 'Styl:', $this->styly)->setAttribute("class", "form-control");
    $form->addStarRating('hodnoceni', 'Hodnocení:');
    $form->addSubmit('add', 'Přidat')->setAttribute("class", "btn btn-default");
    $form->onSuccess[] = $this->newAttemptFormSubmitted;
    return $form;
  }

  public function newFotoFormSubmitted(Form $form)
  {
    $user = Environment::getUser();
    if ($user->isLoggedIn()) {
      $val = $form->values;
      $foto = $val->file;
      if ($foto->isOk()) {
        $path = WWW_DIR . '/line/high/' . $this->getParam('id') . '/foto/';

        // ulož obrazek
        $foto->move($path . $foto->getName());

        // zmensovani obrazku
        $image = Image::fromFile($path . $foto->getName());
        $image->resize(800, NULL, Image::SHRINK_ONLY);
        $image->save($path . $foto->getName(), 80, Image::JPEG);
        // vytvoreni nahledu
        $image = Image::fromFile($path . $foto->getName());
        $image->resize(130, 100, Image::SHRINK_ONLY);
        $image->save($path . 'thumb_' . $foto->getName(), 80, Image::JPEG);

        $this->highlineFoto->add($val->text, $foto->getName(), $this->getParam('id'));
        $this->flashMessage('Fotografie uložena.', 'success');

        $this->redirect('this');
      } else {

        $this->flashMessage('Neproslo isOk', 'warning');
        $this->redirect('this');
      }
    } else {
      $this->flashMessage('Musíte být přihlášen.', 'warning');
      $this->redirect('this');
    }
  }

  public function newAttemptFormSubmitted(Form $form)
  {
    $user = Environment::getUser();
    if ($user->isLoggedIn()) {
      $em = EnvironmentManager::getEntityManager();
      $val = $form->values;
      $loggedUser = EnvironmentManager::getEntityManager()->find("Slack\Models\User", $user->id);
      $highline = EnvironmentManager::getEntityManager()->find("Slack\Models\Highline", $val->highline_id);
      $attempt = new HighlineAttempt();
      $attempt->setHodnoceni($val->hodnoceni);
      $attempt->setDatum(new DateTime($val->datum));
      $attempt->setStyl($val->styl);
      $attempt->setPoznamka($val->poznamka);
      $attempt->setUzivatel($loggedUser);
      $attempt->setHighline($highline);
      $em->persist($attempt);
      $em->flush();
      $this->flashMessage('Přechod přidán.', 'success');
    } else {
      $this->flashMessage('Musíte být přihlášen.', 'error');
    }
    $this->redirect('Highlines:detail', array('id' => $this->getParam('id'), 'tab' => 'attempts'));
  }

  public function handleDeleteFoto($id_f)
  {
    $user = Environment::getUser();
    if ($user->isLoggedIn()) {
      if ($user->isInRole('admin')) {
        $file = $this->highlineFoto->find($id_f);
        $path = WWW_DIR . '/../line/high/' . $this->getParam('id') . '/foto/';
        if (is_file($path . $file->file)) {
          unlink($path . $file->file);
        }
        if (is_file($path . 'thumb_' . $file->file)) {
          unlink($path . 'thumb_' . $file->file);
        }
        $this->highlineFoto->delete($id_f);
        $this->flashMessage('Fotka smazána.', 'success');
        $this->invalidateControl('fotoEdit');
        $this->invalidateControl('flashMessages');
      } else {
        $this->flashMessage('Nemáte oprávnění na smazání přechodu.', 'error');
        $this->invalidateControl('flashMessages');
      }
    } else {
      $this->flashMessage('Nemáte oprávnění na smazání přechodu.', 'error');
      $this->invalidateControl('flashMessages');
    }
  }

  public function handleDeleteAttempt($id_p)
  {
    $user = Environment::getUser();
    if ($user->isLoggedIn()) {
      if ($user->getIdentity()->id == $this->highlinePrechod->find($id_p)->uzivatel_id || $user->isInRole('admin')) {
        $this->highlinePrechod->delete($id_p);
        $this->flashMessage('Přechod úspěšně smazán.', 'success');
        $this->invalidateControl('tablePrechody');
        $this->invalidateControl('flashMessages');
      } else {
        $this->flashMessage('Nemáte oprávnění na smazání přechodu.', 'error');
        $this->invalidateControl('flashMessages');
      }
    }
  }

  /**
   * @param $id_h
   * @todo
   */
  public function handleDeleteHighline($id_h)
  {
    $user = Environment::getUser();
    if ($user->isLoggedIn()) {
      $highline = $this->dao->find($id_h);
      if (!is_null($highline)) {
        if ($user->getIdentity()->id == $highline->getUzivatel()->getId()) {
//        $delHl = $this->highline->delete($id_h);
          EnvironmentManager::getEntityManager()->remove($highline);
          $this->flashMessage('Highline byla úspěšně smazána.', 'success');
//          $this->redrawControl('flashMessages');
        } else {
          $this->flashMessage('Nemáte oprávnění na smazání highline.', 'error');
//          $this->redrawControl('flashMessages');
        }
      }
    } else {
      $this->flashMessage('Pro smazání highline musíte být přihlášen', 'error');
//      $this->redrawControl('flashMessages');
    }
  }

  public function createComponentDataTable()
  {
    return new DataTable();
  }
}
