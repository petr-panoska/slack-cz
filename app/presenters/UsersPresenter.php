<?php

use Slack\Dao\HighlineAttemptDao;
use Slack\Dao\UserDao;
use Slack\EnvironmentManager;
use Slack\Models\Components\Collection\Collection;
use Slack\Models\Form;
use Slack\Models\Longline;

class UsersPresenter extends BasePresenter
{
  /**
   * @var UserDao
   */
  private $userDao;

  /**
   * @var HighlineAttemptDao
   */
  private $highlineAttemptDao;

  public function injectUserDao(UserDao $dao)
  {
    $this->userDao = $dao;
  }

  public function injectHighlineAttemptDao(HighlineAttemptDao $dao)
  {
    $this->highlineAttemptDao = $dao;
  }

  public function renderDefault()
  {
    $this->template->users = $this->userDao->findEnabled();
  }

  public function renderDetail($user, $tab = 'profil')
  {
    $em = EnvironmentManager::getEntityManager();
    $userObject = $em->find("Slack\Models\User", $user);
    if (is_null($userObject)) {
      $this->error('Uživatel nenalezen.');
    }
    $this->template->uzivatel = $userObject;
    $this->template->tab = $tab;
    if ($tab == 'longline') {
      $this->template->longlineAttempts = $em->getRepository(Longline::class)->findBy(['uzivatel' => $user]);
    } else if ($tab == 'highline') {
      $this->template->highlineAttempts = $this->highlineAttemptDao->findAllForUser($user);
    }
  }

  protected function createComponentNewLonglineForm()
  {
    $form = new Form();

    $styles = new Collection();
    $styles->put("-", "-");
    $styles->put("one way", "one way");
    $styles->put("fm", "fm");
    $styles->put("OS", "OS");
    $styles->put("OS, fm", "OS, fm");
    $styles->put("OS FM", "OS FM");

    $form->addText('date', 'Datum:', 40, 100)
        ->setAttribute("class", "form-control datepicker")
        ->setAttribute("autocomplete", "off");
    $form->addText('long', "Délka [m]:", 40, 100)->setAttribute("class", "form-control")
        ->addRule(Form::INTEGER, 'Musí být celé číslo.');
    $form->addText('place', 'Místo:', 40, 100)->setAttribute("class", "form-control");
    $form->addSelect('style', 'Styl:', $styles->toArray())->setAttribute("class", "form-control");
    if (!$this->user->isLoggedIn()) {
      $form->addText('text2', "Opište obrázek:")->setAttribute("class", "form-control");
    }
    $form->addSubmit('send', 'Odeslat')->setAttribute("class", "btn btn-default");

    $form->onSuccess[] = array($this, 'newLonglineFormSubmitted');
    return $form;
  }

  public function newLonglineFormSubmitted(Form $form)
  {
    $params = $this->request->getParameters();
    $user = $params["user"];
    $val = $form->values;
    $em = EnvironmentManager::getEntityManager();
    if ($this->user->isLoggedIn() && $this->user->id == $user) {
      $userObj = EnvironmentManager::getEntityManager()->find("Slack\Models\User", $user);
      $longline = new Longline();
      $longline->setUzivatel($userObj);
      $longline->setDelka($val->long);
      $longline->setMisto($val->place);
      $longline->setStyl($val->style);
      $longline->setDatum(new DateTime($val->date));
      $em->persist($longline);
      $em->flush();
      $this->flashMessage("Nový přechod longline byl přidán.");
      $this->redirect('Users:detail', ['user' => $userObj->getId(), 'tab' => 'longline']);
    } else {
      $this->flashMessage("Přechod nebyl přidán.");
    }
  }
}
