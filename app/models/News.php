<?php

namespace Slack\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="slack_news")
 */
class News extends Object
{

  /**
   * @Id
   * @Column(type="integer")
   */
  private $id;

  /**
   * @Column(type="string")
   */
  private $nazev;

  /**
   * @Column(type="datetime")
   */
  private $datum;

  /**
   * @Column(type="string")
   */
  private $typ;

  /**
   * @Column(type="string")
   */
  private $other;

  public function getId()
  {
    return $this->id;
  }

  public function getNazev()
  {
    return $this->nazev;
  }

  public function getDatum()
  {
    return $this->datum;
  }

  public function getTyp()
  {
    return $this->typ;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function setNazev($nazev)
  {
    $this->nazev = $nazev;
  }

  public function setDatum($datum)
  {
    $this->datum = $datum;
  }

  public function setTyp($typ)
  {
    $this->typ = $typ;
  }

  public function getOther()
  {
    return $this->other;
  }

  public function setOther($other)
  {
    $this->other = $other;
  }

  public function getTypeCaption()
  {
    switch ($this->typ) {
      case 'video':
        return "Video";
      case 'article':
        return "Článek";
      default:
        return '';
    }
  }

  public function getThumbnailUrl()
  {
    switch ($this->typ) {
      case 'video':
        return 'data/video' . DS . 'video_' . $this->id . '.jpg';
      case 'article':
        if (file_exists(DATA_DIR . DS . 'clanky' . DS . 'thumbnails' . DS . 'nahled_' . $this->id . '.jpg'))
          return 'data/clanky' . DS . 'thumbnails' . DS . 'nahled_' . $this->id . '.jpg';
        else
          return 'data/clanky' . DS . $this->other . DS . 'nahled.jpg';
      default:
        return 'zatim nevim';
    }
  }
}
