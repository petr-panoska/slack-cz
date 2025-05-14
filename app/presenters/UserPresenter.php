<?php

use HighlinesBook\Authenticator;
use HighlinesBook\Uzivatel;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Security as NS;
use Slack\Dao\UserDao;
use Slack\EnvironmentManager;
use Slack\Models\User;
use Slack\Service\CaptchaValidator;
use Slack\Service\PhotoManager;

/**
 * Description of UserPresenter
 *
 * @author Vejvis
 */
class UserPresenter extends BasePresenter
{
  /** @var UserDao */
  private $userDao;

  /** @var EnvironmentManager */
  private $environmentManager;

  /** @var Uzivatel */
  private $uzivatel;

  /** @var Authenticator */
  private $authenticator;

  /** @var CaptchaValidator $captchaValidator */
  private $captchaValidator;

  /** @var PhotoManager $photoManager */
  private $photoManager;

  public function injectDao(UserDao $dao)
  {
    $this->userDao = $dao;
  }

  public function injectEM(EnvironmentManager $manager)
  {
    $this->environmentManager = $manager;
  }

  public function injectCaptchaValidator(CaptchaValidator $captchaValidator)
  {
    $this->captchaValidator = $captchaValidator;
  }

  public function injectPhotoManager(PhotoManager $photoManager)
  {
    $this->photoManager = $photoManager;
  }

  protected function startup()
  {
    parent::startup();
    $this->uzivatel = $this->context->uzivatel;
    $this->authenticator = $this->context->authenticator;
  }

  public function renderEdit($id)
  {
    /** @var User $user */
    $user = $this->environmentManager->getLoggedUser();

    if ($user === null || $user->getId() !== intval($id)) {
      $this->flashMessage('Pro tuto operaci nemáte oprávnění.', 'warning');
      $this->redirect('Homepage:');
    }
    $this->template->editedUser = $user;
    $this['editForm']->setDefaults($user->toArray());
  }

  protected function createComponentEditForm()
  {
    $form = new Form();
    $form->addText('jmeno', 'Jméno')
        ->setRequired(true);
    $form->addText('prijmeni', 'Příjmení')
        ->setRequired(true);
    $form->addRadioList('pohlavi', 'Pohlaví', [
        'm' => 'Muž',
        'f' => 'Žena'
    ]);
    $form->addText('mesto', 'Město');
    $form->addText('rok_nar', 'Rok narození')
        ->setType('number')
        ->addRule(Form::LENGTH, 'Rok musí být 4 místné číslo.', 4);
    $form->addText('email', 'Email')
        ->setType('email')
        ->setRequired(true);
    $form->addText('telefon', 'Telefon');
    $form->addUpload('photo', 'Fotka')
        ->setAttribute("class", "file");
    $form->addHidden('id');
    $form->addSubmit('submit', 'Uložit');
    $form->onSuccess[] = [$this, 'editFormSucceded'];
    $this->applyBootstrapThemeToForm($form);
    return $form;
  }

  public function editFormSucceded(Form $form)
  {
    $values = $form->getValues();
    $user = $this->getUser();

    if ($user->isLoggedIn() && $user->getId() === intval($values->id)) {
      $userEntity = $this->userDao->find($user->getId());
      if ($this->userDao->findByEmail($values->email) !== null && strcmp($userEntity->getEmail(), $values->email) !== 0) {
        $form['email']->addError('Zadaný email již existuje.');
      } else {
        $userEntity->setJmeno($values->jmeno);
        $userEntity->setPrijmeni($values->prijmeni);
        $userEntity->setMesto($values->mesto);
        $userEntity->setRok_nar($values->rok_nar);
        $userEntity->setEmail($values->email);
        $userEntity->setTelefon($values->telefon);
        $userEntity->setPohlavi($values->pohlavi);

        /** @var FileUpload $photo */
        $photo = $values->photo;
        if (!is_null($photo) && $photo->isOk() && $photo->isImage()) {
          $this->photoManager->saveProfilePhoto($user->getId(), $photo, true);
        }

        $em = EnvironmentManager::getEntityManager();
        $em->persist($userEntity);
        $em->flush();
        $this->flashMessage('Údaje uloženy.', 'success');
        $this->redirect('Users:detail', $this->user->getId());
      }
    }
  }

  protected function createComponentPasswordForm()
  {
    $form = new Form();
    $form->addPassword('oldPassword', 'Staré heslo:', 30)
        ->addRule(Form::FILLED, 'Je nutné zadat staré heslo.');
    $form->addPassword('newPassword', 'Nové heslo:', 30)
        ->addRule(Form::MIN_LENGTH, 'Nové heslo musí mít alespoň %d znaků.', 6);
    $form->addPassword('confirmPassword', 'Potvrzení hesla:', 30)
        ->addRule(Form::FILLED, 'Nové heslo je nutné zadat ještě jednou pro potvrzení.')
        ->addRule(Form::EQUAL, 'Zadaná hesla se musejí shodovat.', $form['newPassword']);
    $form->addSubmit('submit', 'Změnit heslo');
    $form->onSuccess[] = $this->passwordFormSubmitted;
    $this->applyBootstrapThemeToForm($form);
    return $form;
  }

  public function passwordFormSubmitted(Form $form)
  {
    $values = $form->getValues();
    $user = $this->getUser();
    $userEntity = $this->userDao->find($user->getId());
    try {
      $this->authenticator->authenticate(array($userEntity->getNick(), $values->oldPassword));
      $userEntity->setHeslo(md5($values->newPassword));
      $em = EnvironmentManager::getEntityManager();
      $em->persist($userEntity);
      $em->flush();
      $this->flashMessage('Heslo bylo změněno.', 'success');
      $this->redirect('Users:detail', $user->getId());
    } catch (NS\AuthenticationException $e) {
      $form->addError('Zadané heslo není správné.');
    }
  }

  protected function createComponentResetPasswordForm()
  {
    $form = new Form();
    $form->addText('nickOrEmail')
        ->setRequired(true);
    $form->addSubmit('submit', 'Odeslat')
        ->setAttribute('class', 'btn btn-primary');
    $form->onSuccess[] = [$this, 'resetPasswordFormSucceeded'];
    return $form;
  }

  public function resetPasswordFormSucceeded(Form $form)
  {
    $nickOrEmail = $form->getValues()->nickOrEmail;
    /** @var User $userByNick */
    $userByNick = $this->userDao->findByNick($nickOrEmail);
    if (!is_null($userByNick)) {
      $this->template->foundedUser = $userByNick;
      $this['confirmResetPasswordForm']->setDefaults(['userId' => $userByNick->getId()]);
    } else {
      $userByEmail = $this->userDao->findByEmail($nickOrEmail);
      if (!is_null($userByEmail)) {
        if (is_array($userByEmail)) {
          $this->template->userHasMultipleNicks = true;
          $this->template->users = $userByEmail;
        } else {
          $this->template->foundedUser = $userByEmail;
          $this['confirmResetPasswordForm']->setDefaults(['userId' => $userByEmail->getId()]);
        }
      } else {
        $form->addError('Zadanému textu neodpovídá žádná přezdívka ani email.');
      }
    }
  }

  public function createComponentConfirmResetPasswordForm()
  {
    $form = new Form();
    $form->getElementPrototype()->addAttributes(['class' => 'js-form']);
    $form->addSubmit('submit', 'Poslat nové heslo')
        ->setAttribute('class', 'js-submit btn btn-primary')
        ->setDisabled(true);
    $form->addHidden('userId');
    $form->addHidden('captchaResponse')
        ->setAttribute('class', 'js-captcha');
    $form->onSuccess[] = [$this, 'confirmResetPasswordFormSucceeded'];
    return $form;
  }

  public function confirmResetPasswordFormSucceeded(Form $form)
  {
    $values = $form->getValues();
    if ($this->captchaValidator->validate($values->captchaResponse)) {

      $user = $this->userDao->find($values->userId);
      $newPassword = bin2hex(openssl_random_pseudo_bytes(10));
      $user->setHeslo(md5($newPassword));

      $em = EnvironmentManager::getEntityManager();
      $em->merge($user);
      $em->flush();

      try {
        $template = new \Nette\Templating\FileTemplate(__DIR__ . '/../templates/email/resetPassword.latte');
        $template->registerFilter(new \Nette\Latte\Engine);
        $template->registerHelperLoader('Nette\Templating\Helpers::loader');
        $template->control = $template->_control = $this;
        $template->password = $newPassword;

        $mail = new \Nette\Mail\Message();
        $mail
            ->setFrom('Slack.cz <panda09823@gmail.com>')
            ->addTo($user->getEmail())
            ->addBcc('panda098@centrum.cz')
            ->setSubject('Slack.cz - Obnova hesla')
            ->setHtmlBody($template);

        $mailer = new \Nette\Mail\SendmailMailer();
        $mailer->send($mail);
        $this->flashMessage("Nové heslo bylo odesláno na email " . $user->getEmail());

//        $headers = "Reply-To: Slack.cz <no-reply@slack.cz>\r\n";
//        $headers .= "Return-Path: Slack.cz <no-reply@slack.cz>\r\n";
//        $headers .= "From: Slack.cz <no-reply@slack.cz>\r\n";
//        $headers .= "Organization: Slack.cz\r\n";
//        $headers .= "MIME-Version: 1.0\r\n";
//        $headers .= "Content-type: text/plain; charset=iso-8859-1\r\n";
//        $headers .= "X-Priority: 3\r\n";
//        $headers .= "X-Mailer: PHP" . phpversion() . "\r\n";
//        $result = mail($user->getEmail(), 'Slack.cz - Obnova hesla', $newPassword, $headers);
//        if ($result) {
//          $this->flashMessage("Nové heslo bylo odesláno na email " . $user->getEmail());
//        } else {
//          $this->flashMessage("Obnova hesla se nezdařila :-(", 'danger');
//        }

      } catch (Exception $e) {
        $this->flashMessage("Obnova hesla se nezdařila :-(", 'danger');
      }
      $this->redirect('Homepage:');
    } else {
      $form->addError('Captcha ověření selhalo.');
    }
  }
}
