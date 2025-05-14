<?php

use Nette\Application\UI\Form;

/**
 * Captcha example.
 */
class ExamplePresenter extends BasePresenter
{

	public function renderDefault()
	{
		$this->template->form = $this->getComponent('form');
	}

	public function formSubmitted()
	{
		$this->flashMessage('OK');
		$this->redirect('default');
	}

	protected function createComponent($name)
	{
		switch ($name) {
		case 'form':
			class_exists('Captcha');
			$form = new Form($this, $name);
			$form->addCaptcha('captcha', 'Antispam')
				->setTextColor(Image::rgb(255, 0, 0))
				->addRule('Captcha::validateValid', 'Opište správně písmena z obrázku.');
			$form->addSubmit('send', 'Odeslat')
				->onClick[] = array($this, 'formSubmitted');
			return;

		default:
			parent::createComponent($name);
			return;
		}
	}
}
