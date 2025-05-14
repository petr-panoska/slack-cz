<?php


/**
 * Captcha presenter.
 */
class CaptchaPresenter extends Presenter
{

	public function renderShow($key = "")
	{
		$captcha = new Captcha;
		if ($captcha->loadState($key)) {
			$captcha->send();
		} else {
			$this->getHttpResponse()->setCode(IHttpResponse::S404_NOT_FOUND);
		}

		$this->terminate();
	}

}
