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
 * @Table(name="longline")
 */
class Longline extends Object
{

  /**
   * @Id
   * @GeneratedValue
   * @Column(type="integer")
   */
  private $id;

  /**
   * @ManyToOne(targetEntity="User", inversedBy="longlines")
   * @JoinColumn(name="uzivatel", referencedColumnName="id")
   */
  private $uzivatel;

  /**
   * @Column(type="integer")
   */
  private $delka;

  /**
   * @Column(type="string")
   */
  private $misto;

  /**
   * @Column(type="string")
   */
  private $styl;

  /**
   * @Column(type="date")
   */
  private $datum;

  public function getId()
  {
    return $this->id;
  }

  public function getUzivatel()
  {
    return $this->uzivatel;
  }

  public function getDelka()
  {
    return $this->delka;
  }

  public function getMisto()
  {
    return $this->misto;
  }

  public function getStyl()
  {
    return $this->styl;
  }

  public function getDatum()
  {
    return $this->datum;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function setUzivatel($uzivatel)
  {
    $this->uzivatel = $uzivatel;
  }

  public function setDelka($delka)
  {
    $this->delka = $delka;
  }

  public function setMisto($misto)
  {
    $this->misto = $misto;
  }

  public function setStyl($styl)
  {
    $this->styl = $styl;
  }

  public function setDatum($datum)
  {
    $this->datum = $datum;
  }

}
