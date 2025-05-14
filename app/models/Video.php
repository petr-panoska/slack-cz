<?php

namespace Slack\Models;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;
use Slack\EnvironmentManager;

/**
 * @Entity
 * @Table(name="videa")
 */
class Video extends Object
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
    private $adresa;

    /**
     * @Column(type="string")
     */
    private $objekt;

    /**
     * @Column(type="string")
     */
    private $typ;

    /**
     * @Column(type="string")
     */
    private $popis;

    /**
     * @Column(type="integer")
     */
    private $uzivatel_id;

    /**
     * @Column(type="datetime")
     */
    private $datum;

    /**
     * @ManyToMany(targetEntity="Tag", cascade={"persist"})
     * @JoinTable(name="slack_videoHasTag",
     *      joinColumns={@JoinColumn(name="video", referencedColumnName="id")},
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

    public function getAdresa()
    {
        return $this->adresa;
    }

    public function getObjekt()
    {
        return $this->objekt;
    }

    public function getTyp()
    {
        return $this->typ;
    }

    public function getPopis()
    {
        return $this->popis;
    }

    public function getUzivatel_id()
    {
        return $this->uzivatel_id;
    }

    public function getDatum()
    {
        return $this->datum;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setNazev($nazev)
    {
        $this->nazev = $nazev;
    }

    public function setAdresa($adresa)
    {
        $this->adresa = $adresa;
    }

    public function setObjekt($objekt)
    {
        $this->objekt = $objekt;
    }

    public function setTyp($typ)
    {
        $this->typ = $typ;
    }

    public function setPopis($popis)
    {
        $this->popis = $popis;
    }

    public function setUzivatel_id($uzivatel_id)
    {
        $this->uzivatel_id = $uzivatel_id;
    }

    public function setDatum($datum)
    {
        $this->datum = $datum;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function setTags($tags)
    {
        $this->tags = $tags;
    }

    public function clearTags()
    {
        $this->tags->clear();
    }

    public function addTag($tag)
    {
        $em = EnvironmentManager::getEntityManager();
        $this->tags->add($em->find("Slack\Models\Tag", $tag));
    }

    public function getThumbnailUrl()
    {
        return 'data/video' . DS . 'video_' . $this->id . '.jpg';
    }

}
