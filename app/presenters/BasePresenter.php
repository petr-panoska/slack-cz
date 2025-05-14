<?php

use HighlinesBook\Eshop\Product;
use HighlinesBook\Rating;
use Nette\Application\UI\Presenter;
use Nette\Forms\Form;
use Nette\Forms\Controls;
use Slack\EnvironmentManager;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Presenter
{

  protected function startup()
  {
    parent::startup();
    $this->template->hostUrl = $this->presenter->getHttpRequest()->getUrl()->getHost();
    $this->template->showNav = true;

    $em = EnvironmentManager::getEntityManager();
    /**
     * @var Product
     */
    $cssFiles = [];
    foreach (scandir(CSS_DIR) as $file) {
      if ($file == '.' || $file == '..' || substr($file, 0, 1) == '@')
        continue;
      $cssFiles[] = $file;
    }
    $jsFiles = [];
    foreach (scandir(JS_DIR) as $file) {
      if ($file == '.' || $file == '..' || substr($file, 0, 1) == '@')
        continue;
      $jsFiles[] = $file;
    }
    $this->template->cssFiles = $cssFiles;
    $this->template->jsFiles = $jsFiles;
    $product = $this->context->product;
    $this->template->fotolive = $em->createQuery("SELECT f FROM Slack\Models\Fotolive f WHERE f.fromDate <= :now ORDER BY f.fromDate DESC")->setMaxResults(1)->setParameter("now", new DateTime())->getOneOrNullResult();
    $this->template->product = $product->findAll()->where('del', '')->where('slack', 1)->order('RAND()')->limit(1);

    $this->template->newHighlines = $em->createQuery("SELECT h FROM Slack\Models\Highline h ORDER BY h.datum DESC")->setMaxResults(5)->getResult();
    $this->template->newAttempts = $em->createQuery("SELECT a FROM Slack\Models\HighlineAttempt a ORDER BY a.datum DESC")->setMaxResults(5)->getResult();
    $this->template->newAttemptsLong = $em->createQuery("SELECT a FROM Slack\Models\Longline a ORDER BY a.datum DESC")->setMaxResults(5)->getResult();
  }

  public function createComponentRating()
  {
    return new Rating();
  }

  public function strip($text)
  {
    return stripslashes(htmlspecialchars_decode($text));
  }

  protected function applyBootstrapThemeToForm(Form &$form)
  {
    $renderer = $form->getRenderer();
    $renderer->wrappers['controls']['container'] = NULL;
    $renderer->wrappers['pair']['container'] = 'div class=form-group';
    $renderer->wrappers['pair']['.error'] = 'has-error';
    $renderer->wrappers['control']['container'] = 'div class=col-sm-9';
    $renderer->wrappers['label']['container'] = 'div class="col-sm-3 control-label"';
    $renderer->wrappers['control']['description'] = 'span class=help-block';
    $renderer->wrappers['control']['errorcontainer'] = 'span class=help-block';

    $form->getElementPrototype()->class('form-horizontal');

    foreach ($form->getControls() as $control) {
      if ($control instanceof Controls\Button) {
        $control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-default');
        $usedPrimary = TRUE;

      } elseif ($control instanceof Controls\TextBase || $control instanceof Controls\SelectBox || $control instanceof Controls\MultiSelectBox) {
        $control->getControlPrototype()->addClass('form-control');

      } elseif ($control instanceof Controls\Checkbox || $control instanceof Controls\CheckboxList || $control instanceof Controls\RadioList) {
        $control->getSeparatorPrototype()->setName('div')->addClass($control->getControlPrototype()->type);
      }
    }
  }
}
