<?php

use Nette\Utils\Strings;

class CalendarPresenter extends BasePresenter
{

  /**
   * @var HighlinesBook\Event
   */
  private $event;
  /**
   * @var HighlinesBook\Gps
   */
  private $gps;

  protected function startup()
  {
    parent::startup();
    $this->event = $this->context->event;
    $this->gps = $this->context->gps;
  }

  /** Vrácení českého názvu měsíce
   * @param int 1-12
   * @return string
   * @copyright Jakub Vrána, http://php.vrana.cz/
   */
  public function cesky_mesic($mesic)
  {
    static $nazvy = array(1 => 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen', 'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec');
    $date = new DateTime($mesic);
    $i = (int)$date->format('m');
    return $nazvy[$i];
  }

  /** Vrácení českého názvu dne v týdnu
   * @param int 0-6, 0 neděle
   * @return string
   * @copyright Jakub Vrána, http://php.vrana.cz/
   */
  public function cesky_den($den)
  {
    static $nazvy = array('neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota');
    $date = new DateTime($den);
    return $nazvy[$date->format('w')];
  }

  public function truncate($text, $length)
  {
    return Strings::truncate($text, $length);
  }

  public function renderEvents()
  {
    $this->template->events = $this->event->findAll()->order('start');
  }

  public function renderEvent($id)
  {
    $this->template->event = $this->event->find($id);
  }
}
