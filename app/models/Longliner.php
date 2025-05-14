<?php

namespace Slack\Models;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="longlinersclub")
 */
class Longliner extends Object
{

    /**
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     */
    private $id;

    /**
     * @ManyToOne(targetEntity="User")
     * @JoinColumn(name="user", referencedColumnName="id")
     */
    private $user;

    /**
     * @Column(type="integer")
     */
    private $delka;

    /**
     * @Column(type="datetime")
     */
    private $datum;

    /**
     * @Column(type="integer")
     */
    private $poradi;

    /**
     * @Column(type="string")
     */
    private $misto;

    /**
     * @Column(type="string")
     */
    private $oddil;

    /**
     * @Column(type="string")
     */
    private $jmeno;

    public function getId()
    {
        return $this->id;
    }

    public function getDelka()
    {
        return $this->delka;
    }

    public function getDatum()
    {
        return $this->datum;
    }

    public function getPoradi()
    {
        return $this->poradi;
    }

    public function getMisto()
    {
        return $this->misto;
    }

    public function getOddil()
    {
        return $this->oddil;
    }

    public function getJmeno()
    {
        return $this->jmeno;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setDelka($delka)
    {
        $this->delka = $delka;
    }

    public function setDatum($datum)
    {
        $this->datum = $datum;
    }

    public function setPoradi($poradi)
    {
        $this->poradi = $poradi;
    }

    public function setMisto($misto)
    {
        $this->misto = $misto;
    }

    public function setOddil($oddil)
    {
        $this->oddil = $oddil;
    }

    public function setJmeno($jmeno)
    {
        $this->jmeno = $jmeno;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

}
