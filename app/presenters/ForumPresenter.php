<?php

use Nette\Application\UI\Form;
use Slack\EnvironmentManager;
use Slack\Models\Forum\Message;
use Slack\Service\CaptchaValidator;
use VisualPaginator\VisualPaginator;

class ForumPresenter extends BasePresenter
{

  /**
   * HighlinesBook\Forum
   */
  private $forum;

  /** @var CaptchaValidator $captchaValidator */
  private $captchaValidator;

  public function injectCaptchaValidator(CaptchaValidator $captchaValidator)
  {
    $this->captchaValidator = $captchaValidator;
  }

  protected function startup()
  {
    parent::startup();
    $this->forum = $this->context->forum;
  }

  public function renderDefault()
  {
    if ($this->user->isLoggedIn()) {
      $formDefaults = [
          'nick' => $this->user->getIdentity()->nick,
          'email' => $this->user->getIdentity()->email
      ];
      $this['forumMessageForm']->setDefaults($formDefaults);
    }

    $itemsPerPage = 20;
    $em = EnvironmentManager::getEntityManager();
    $vp = new VisualPaginator($this, 'vp');
    $vp->getPaginator()->setItemsPerPage($itemsPerPage);
    $count = $em->createQuery("SELECT COUNT(m) FROM Slack\Models\Forum\Message m")->getSingleScalarResult();
    $vp->getPaginator()->setItemCount(intval($count));


    $dql = "SELECT m FROM Slack\Models\Forum\Message m ORDER BY m.datum DESC";
    $result = $em->createQuery($dql)
        ->setFirstResult($vp->getPaginator()->getOffset())
        ->setMaxResults($itemsPerPage)
        ->getResult();
    $this->template->messages = $result;
  }

  protected function createComponentForumMessageForm()
  {
    $form = new Form();
    $form->getElementPrototype()->addAttributes(['class' => 'js-form form-horizontal']);

    $renderer = $form->getRenderer();
    $renderer->wrappers['controls']['container'] = 'dl';
    $renderer->wrappers['pair']['container'] = "div class='form-group'";
    $renderer->wrappers['label']['container'] = "label class='col-sm-2 control-label'";
    $renderer->wrappers['control']['container'] = "div class='col-sm-10'";

    $form->addText('nick', 'Jméno:', 40, 100)
        ->setAttribute("class", "form-control")
        ->setRequired(true);
    $form->addText('email', "E-mail:", 40, 100)->setAttribute("class", "form-control");
    $form->addTextArea('text', 'Text:')
        ->setAttribute("class", "form-control")
        ->setRequired(true);
    $form->addHidden('captchaResponse')
        ->setAttribute('class', 'js-captcha');
    $form->addSubmit('send', 'Odeslat')
        ->setAttribute("class", "js-submit btn btn-default")
        ->setDisabled(true);

    $form->onSuccess[] = array($this, 'newMessageFormSubmitted');
    return $form;
  }

  public function newMessageFormSubmitted(Form $form)
  {
    if ($form->isValid()) {
      $val = $form->values;
      if ($this->captchaValidator->validate($val->captchaResponse)) {
        $em = EnvironmentManager::getEntityManager();
        $message = new Message();
        $message->setJmeno($val->nick);
        $message->setEmail($val->email);
        $message->setText($val->text);
        $message->setDatum(new \DateTime());
        $message->setIp($_SERVER['REMOTE_ADDR']);

        if ($this->user->isLoggedIn()) {
          $message->setUser($em->find("Slack\Models\User", $this->user->id));
        }

        $em->persist($message);
        $em->flush();
        $this->flashMessage("Příspěvek přidán.");
        $this->redirect('Forum:');
      } else {
        $form->addError('Captcha ověření selhalo.');
      }
    } else {
      $this->flashMessage("Něco se pokazilo.");
    }

  }

  public function handleRemoveMessage($message)
  {
    if ($this->user->isInRole('admin')) {
      $em = EnvironmentManager::getEntityManager();
      $em->remove($em->find("Slack\Models\Forum\Message", $message));
      $em->flush();
      $this->flashMessage('Zpráva smazána', 'success');
      $this->redirect("Forum:");
    } else {
      $this->flashMessage('Na tuto operaci nemáte oprávnění', 'danger');
    }
  }
}
