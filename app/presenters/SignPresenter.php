<?php

use Nette\Http\FileUpload;
use Nette\Security\AuthenticationException;
use Slack\Dao\UserDao;
use Slack\EnvironmentManager;
use Slack\Models\Form;
use Slack\Models\User;
use Slack\Service\PhotoManager;

class SignPresenter extends BasePresenter
{
  /** @var UserDao $userDao * */
  private $userDao;

  private $environmentManager;

  /** @var PhotoManager */
  private $photoManager;

  public function injectDao(UserDao $dao)
  {
    $this->userDao = $dao;
  }

  public function injectEnvironmentManager(EnvironmentManager $manager)
  {
    $this->environmentManager = $manager;
  }

  public function injectPhotoManager(PhotoManager $photoManager)
  {
    $this->photoManager = $photoManager;
  }

  public function actionOut()
  {
    if ($this->getUser()->isLoggedIn()) {
      $this->getUser()->logout(true);
      $this->flashMessage('Byl jste odhlášen.', 'info');
    }
    $this->redirect('Homepage:page', 'vitejte');
  }

  protected function createComponentLoginForm()
  {
    $form = new Form();
    $form->addText('username', 'Přezdívka:')
        ->setRequired('Prosím zadejte přezdívku.');
    $form->addPassword('password', 'Heslo:')
        ->setRequired('Prosím zadejte heslo.');
    $form->addCheckbox('remember', 'Pamatuj si mne.');
    $form->addSubmit('submit', 'Přihlásit')
        ->setAttribute('class', 'btn btn-primary btn-block');
    $form->onSuccess[] = $this->signInFormSubmitted;
    return $form;
  }

  public function signInFormSubmitted($form)
  {
    try {
      $values = $form->getValues();
      if ($values->remember) {
        $this->getUser()->setExpiration('+ 14 days', FALSE);
      } else {
        $this->getUser()->setExpiration('+ 1 day', TRUE);
      }
      $this->getUser()->login($values->username, $values->password);
      $this->flashMessage('Byl jste úspěšně přihlášen.', 'success');
      $this->redirect('Users:detail', $this->getUser()->getId());
    } catch (AuthenticationException $e) {
      $this->flashMessage('Špatné uživatelské jméno nebo heslo.', 'danger');
    }
  }

  protected function createComponentRegisterForm()
  {
    $form = new Form();
    $form->addGroup(' Povinné ');
    $form->addText('nick', 'Přezdívka:', 40, 15)
        ->addRule(Form::FILLED, 'Je nutné zadat přezdívku.');
    $form->addPassword('password1', 'Heslo:', 40, 50)
        ->setRequired(true)
        ->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaků.', 6);
    $form->addPassword('password2', 'Heslo znovu:', 40, 50)
        ->addRule(Form::FILLED, 'Heslo je nutné zadat ještě jednou pro potvrzení.')
        ->addRule(Form::EQUAL, 'Zadná hesla se musejí shodovat.', $form['password1']);
    $form->addText('jmeno', 'Jméno:', 40, 30)
        ->addRule(Form::FILLED, 'Musíte zadat jméno.');
    $form->addText('prijmeni', 'Příjmení:', 40, 30)
        ->addRule(Form::FILLED, 'Musíte zadat příjmení.');
    $form->addText('email', 'E-mail:', 40, 30)
        ->setType('email')
        ->addRule(Form::FILLED, 'E-mail musí být zadán.');
    $form->addRadioList('pohlavi', 'Pohlaví', [
        'm' => 'Muž',
        'f' => 'Žena'
    ])
        ->addRule(Form::FILLED, 'Vyberte pohlaví.');
    $form->addGroup(' Doplňující ');
    $form->addText('rok_nar', 'Rok narození:', 40, 4)
        ->setType('number');
    $form->addText('telefon', 'Telefon:', 40, 13);
    $form->addText('mesto', 'Město:', 40, 50);
    $form->addUpload('foto', 'Profilová fotka:');
    $form->setCurrentGroup(NULL);
    $form->addSubmit('set', 'Registrovat');
    $form->onSuccess[] = $this->registerFormSubmitted;
    return $form;
  }

  public function registerFormSubmitted(Form $form)
  {
    $values = $form->values;

    if ($this->userDao->existUserByNick($values->nick)) {
      $form->addError('Uživatel s touto přezdívkou již existuje. Zvolte prosím jinou.');
    } elseif (!is_null($this->userDao->findByEmail($values->email))) {
      $form->addError('Uživatel s tímto emailem již existuje. Zvolte prosím jiný.');
    } else {
      $user = new User();
      $user->setHeslo(md5($values->password1));
      $user->setJmeno($values->jmeno);
      $user->setPrijmeni($values->prijmeni);
      $user->setEmail($values->email);
      $user->setNick($values->nick);
      $user->setPohlavi($values->pohlavi);

      $user->setEnabled(true);
      $user->setMesto($values->mesto);
      $user->setTelefon($values->telefon);
      if (!is_null($values->rok_nar) && strlen($values->rok_nar) === 4) {
        $user->setRok_nar($values->rok_nar);
      }

      $em = EnvironmentManager::getEntityManager();
      $em->persist($user);
      $em->flush();

      mkdir(USERS_DIR . DS . $user->getId(), 0777);

      /** @var FileUpload $foto */
      $foto = $values->foto;
      if (!is_null($foto) && $foto->isOk() && $foto->isImage()) {
        $this->photoManager->saveProfilePhoto($user->getId(), $foto);
      }

      $this->flashMessage('Registrace proběhla úspěšně.', 'success');
      $this->getUser()->login($values->nick, $values->password1);
      $this->redirect('Users:detail', $user->getId());
    }
  }
}
