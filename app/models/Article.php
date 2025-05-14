<?php

namespace Slack\Models;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="clanky")
 */
class Article extends Object
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
  private $nazev;

  /**
   * @Column(type="string")
   */
  private $popis;

  /**
   * @Column(type="string")
   */
  private $text;

  /**
   * @Column(type="string")
   */
  private $album;

  /**
   * @Column(type="datetime")
   */
  private $datum;

  /**
   * @ManyToOne(targetEntity="User")
   * @JoinColumn(name="uzivatel", referencedColumnName="id")
   */
  private $uzivatel;

  /**
   * @Column(type="boolean")
   */
  private $viditelnost;

  /**
   * @ManyToMany(targetEntity="Tag", cascade={"persist"})
   * @JoinTable(name="slack_articleHasTag",
   *      joinColumns={@JoinColumn(name="article", referencedColumnName="id")},
   *      inverseJoinColumns={@JoinColumn(name="tag", referencedColumnName="name")}
   *      )
   * @var ArrayCollection Description
   */
  private $tags;

  function __construct()
  {
    $this->tags = new ArrayCollection();
  }

  public function getId()
  {
    return $this->id;
  }

  public function getNazev()
  {
    return $this->nazev;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function setNazev($nazev)
  {
    $this->nazev = $nazev;
  }

  public function getPopis()
  {
    return $this->popis;
  }

  public function getText()
  {
    return $this->text;
  }

  public function getAlbum()
  {
    return $this->album;
  }

  public function getDatum()
  {
    return $this->datum;
  }

  public function getViditelnost()
  {
    return $this->viditelnost;
  }

  public function setPopis($popis)
  {
    $this->popis = $popis;
  }

  public function setText($text)
  {
    $this->text = $text;
  }

  public function setAlbum($album)
  {
    $this->album = $album;
  }

  public function setDatum($datum)
  {
    $this->datum = $datum;
  }

  public function setViditelnost($viditelnost)
  {
    $this->viditelnost = $viditelnost;
  }

  public function getTags()
  {
    return $this->tags;
  }

  public function setTags(ArrayCollection $tags)
  {
    $this->tags = $tags;
  }

  public function clearTags()
  {
    $this->tags->clear();
  }

  public function addTag(Tag $tag)
  {
    $this->tags->add($tag);
  }

  public function getUzivatel()
  {
    return $this->uzivatel;
  }

  public function setUzivatel($uzivatel)
  {
    $this->uzivatel = $uzivatel;
  }

  public function getThumbnailUrl()
  {
    if (file_exists(DATA_DIR . DS . 'clanky' . DS . 'thumbnails' . DS . 'nahled_' . $this->id . '.jpg')) {
      return 'data/clanky/thumbnails/nahled_' . $this->getId() . '.jpg';
    } else {
      return 'clanky/' . $this->getAlbum() . DS . 'nahled.jpg';
    }
  }

}
