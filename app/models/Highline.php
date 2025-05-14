<?php

namespace Slack\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="highline")
 */
class Highline extends Object
{

  /**
   * @Id
   * @GeneratedValue
   * @Column(type="integer")
   */
  private $id;

  /**
   * @ManyToOne(targetEntity="User")
   * @JoinColumn(name="uzivatel_id", referencedColumnName="id")
   */
  private $uzivatel;

  /**
   * @Column(type="string")
   */
  private $jmeno;

  /**
   * @Column(type="string")
   */
  private $delka;

  /**
   * @Column(type="string")
   */
  private $vyska;

  /**
   * @Column(type="string")
   */
  private $stat;

  /**
   * @Column(type="string")
   */
  private $oblast;

  /**
   * @Column(type="string")
   */
  private $kraj;

  /**
   * @Column(type="integer")
   */
  private $hodnoceni;

  /**
   * @Column(type="string")
   */
  private $kotveni;

  /**
   * @Column(type="string")
   */
  private $info;

  /**
   * @Column(type="string")
   */
  private $pointOneInfo;

  /**
   * @Column(type="string")
   */
  private $pointTwoInfo;

  /**
   * @Column(type="string")
   */
  private $autor;

  /**
   * @Column(type="string")
   */
  private $nameHistory;

  /**
   * @Column(type="date")
   */
  private $datum;

  /**
   * @Column(type="integer")
   */
  private $typ;

  /**
   * @Column(type="integer")
   */
  private $timeApproach;

  /**
   * @Column(type="integer")
   */
  private $timeTensioning;

  /**
   * @ManyToOne(targetEntity="Gps", cascade={"PERSIST"})
   * @JoinColumn(name="parking_id", referencedColumnName="id")
   * @var Gps
   */
  private $parking;

  /**
   * @ManyToOne(targetEntity="Gps", fetch="EAGER", cascade={"PERSIST"})
   * @JoinColumn(name="point1_id", referencedColumnName="id")
   * @var Gps
   */
  private $point1;

  /**
   * @ManyToOne(targetEntity="Gps", cascade={"PERSIST"})
   * @JoinColumn(name="point2_id", referencedColumnName="id")
   * @var Gps
   */
  private $point2;

  /**
   * @OneToMany(targetEntity="HighlineAttempt", mappedBy="highline")
   */
  private $attempts;

  /**
   * @OneToMany(targetEntity="HighlinePhoto", mappedBy="highline")
   */
  private $photos;

  function getNameHistory()
  {
    return $this->nameHistory;
  }

  function setNameHistory($nameHistory)
  {
    $this->nameHistory = $nameHistory;
  }

  function getPointOneInfo()
  {
    return $this->pointOneInfo;
  }

  function getPointTwoInfo()
  {
    return $this->pointTwoInfo;
  }

  function setPointOneInfo($pointOneInfo)
  {
    $this->pointOneInfo = $pointOneInfo;
  }

  function setPointTwoInfo($pointTwoInfo)
  {
    $this->pointTwoInfo = $pointTwoInfo;
  }

  public function getTyp()
  {
    return $this->typ;
  }

  public function setTyp($typ)
  {
    $this->typ = $typ;
  }

  public function getId()
  {
    return $this->id;
  }

  public function getUzivatel()
  {
    return $this->uzivatel;
  }

  public function getJmeno()
  {
    return $this->jmeno;
  }

  public function getDelka()
  {
    return $this->delka;
  }

  public function getVyska()
  {
    return $this->vyska;
  }

  public function getStat()
  {
    return $this->stat;
  }

  public function getOblast()
  {
    return $this->oblast;
  }

  public function getKraj()
  {
    return $this->kraj;
  }

  public function getGps()
  {
    return $this->gps;
  }

  public function getHodnoceni()
  {
    return $this->hodnoceni;
  }

  public function getKotveni()
  {
    return $this->kotveni;
  }

  public function getInfo()
  {
    return $this->info;
  }

  public function getAutor()
  {
    return $this->autor;
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

  public function setJmeno($jmeno)
  {
    $this->jmeno = $jmeno;
  }

  public function setDelka($delka)
  {
    $this->delka = $delka;
  }

  public function setVyska($vyska)
  {
    $this->vyska = $vyska;
  }

  public function setStat($stat)
  {
    $this->stat = $stat;
  }

  public function setOblast($oblast)
  {
    $this->oblast = $oblast;
  }

  public function setKraj($kraj)
  {
    $this->kraj = $kraj;
  }

  public function setGps($gps)
  {
    $this->gps = $gps;
  }

  public function setHodnoceni($hodnoceni)
  {
    $this->hodnoceni = $hodnoceni;
  }

  public function setKotveni($kotveni)
  {
    $this->kotveni = $kotveni;
  }

  public function setInfo($info)
  {
    $this->info = $info;
  }

  public function setAutor($autor)
  {
    $this->autor = $autor;
  }

  public function setDatum($datum)
  {
    $this->datum = $datum;
  }

  public function getLng()
  {
    return $this->lng;
  }

  public function setLng($lng)
  {
    $this->lng = $lng;
  }

  public function getLat()
  {
    return $this->lat;
  }

  public function setLat($lat)
  {
    $this->lat = $lat;
  }

  public function getAttempts()
  {
    return $this->attempts;
  }

  public function setAttempts($attempts)
  {
    $this->attempts = $attempts;
  }

  public function getPhotos()
  {
    return $this->photos;
  }

  public function setPhotos($photos)
  {
    $this->photos = $photos;
  }

  function getParking()
  {
    return $this->parking;
  }

  function getPoint1()
  {
    return $this->point1;
  }

  function getPoint2()
  {
    return $this->point2;
  }

  function setParking(Gps $parking)
  {
    $this->parking = $parking;
  }

  function setPoint1(Gps $point1)
  {
    $this->point1 = $point1;
  }

  function setPoint2(Gps $point2)
  {
    $this->point2 = $point2;
  }

  function getTimeApproach()
  {
    return $this->timeApproach;
  }

  function getTimeTensioning()
  {
    return $this->timeTensioning;
  }

  function setTimeApproach($timeApproach)
  {
    $this->timeApproach = $timeApproach;
  }

  function setTimeTensioning($timeTensioning)
  {
    $this->timeTensioning = $timeTensioning;
  }

}
