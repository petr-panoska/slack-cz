<?php

namespace Slack\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="highline_prechody")
 */
class HighlineAttempt extends Object
{

  /**
   * @Id
   * @GeneratedValue
   * @Column(type="integer")
   */
  private $id;

  /**
   * @ManyToOne(targetEntity="Highline", inversedBy="attempts")
   * @JoinColumn(name="highline_id", referencedColumnName="id")
   */
  private $highline;

  /**
   * @ManyToOne(targetEntity="User")
   * @JoinColumn(name="uzivatel_id", referencedColumnName="id")
   */
  private $uzivatel;

  /**
   * @Column(type="date")
   */
  private $datum;

  /**
   * @Column(type="string")
   */
  private $styl;

  /**
   * @Column(type="string")
   */
  private $poznamka;

  /**
   * @Column(type="integer")
   */
  private $hodnoceni;

  public function getId()
  {
    return $this->id;
  }

  public function getHighline()
  {
    return $this->highline;
  }

  public function getUzivatel()
  {
    return $this->uzivatel;
  }

  public function getDatum()
  {
    return $this->datum;
  }

  public function getStyl()
  {
    return $this->styl;
  }

  public function getPoznamka()
  {
    return $this->poznamka;
  }

  public function getHodnoceni()
  {
    return $this->hodnoceni;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function setHighline($highline)
  {
    $this->highline = $highline;
  }

  public function setUzivatel($uzivatel)
  {
    $this->uzivatel = $uzivatel;
  }

  public function setDatum($datum)
  {
    $this->datum = $datum;
  }

  public function setStyl($styl)
  {
    $this->styl = $styl;
  }

  public function setPoznamka($poznamka)
  {
    $this->poznamka = $poznamka;
  }

  public function setHodnoceni($hodnoceni)
  {
    $this->hodnoceni = $hodnoceni;
  }

}
