<?php

namespace Slack\Models;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="fotolive")
 */
class Fotolive extends Object
{

  /**
   * @Id
   * @GeneratedValue
   * @Column(type="integer")
   */
  private $id;

  /**
   * @Column(type="string")
   */
  private $fotka;

  /**
   * @Column(type="string")
   */
  private $popis;

  /**
   * @Column(type="datetime")
   */
  private $fromDate;

  public function getFotka()
  {
    return $this->fotka;
  }

  public function getPopis()
  {
    return $this->popis;
  }

  public function setFotka($fotka)
  {
    $this->fotka = $fotka;
  }

  public function setPopis($popis)
  {
    $this->popis = $popis;
  }

  public function getId()
  {
    return $this->id;
  }

  public function getFromDate()
  {
    return $this->fromDate;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function setFromDate($fromDate)
  {
    $this->fromDate = $fromDate;
  }

  public function getFullFotoUrl()
  {
    return "/data/fotolive/fotolive_" . $this->id . "_full.jpg";
  }

  public function getFotoUrl()
  {
    return "/data/fotolive/fotolive_" . $this->id . ".jpg";
  }

  public function getPathToThumbnail()
  {
    return DATA_DIR . DS . "fotolive" . DS . "fotolive_" . $this->id . ".jpg";
  }

  public function getPathToFoto()
  {
    return DATA_DIR . DS . "fotolive" . DS . "fotolive_" . $this->id . "_full.jpg";
  }

}
