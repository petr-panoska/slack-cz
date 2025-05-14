<?php

namespace Slack\Models\Forum;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="forum")
 */
class Message extends Object
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
    private $jmeno;

    /**
     * @Column(type="string")
     */
    private $email;

    /**
     * @Column(type="string")
     */
    private $www;

    /**
     * @Column(type="string")
     */
    private $text;

    /**
     * @Column(type="datetime")
     */
    private $datum;

    /**
     * @Column(type="string")
     */
    private $nick;

    /**
     * @ManyToOne(targetEntity="Slack\Models\User")
     * @JoinColumn(name="user", referencedColumnName="id")
     */
    private $user;

    /**
     * @Column(type="string")
     */
    private $ip;

    public function getId()
    {
        return $this->id;
    }

    public function getJmeno()
    {
        return $this->jmeno;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getWww()
    {
        return $this->www;
    }

    public function getText()
    {
        return $this->text;
    }

    public function getDatum()
    {
        return $this->datum;
    }

    public function getNick()
    {
        return $this->nick;
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setJmeno($jmeno)
    {
        $this->jmeno = $jmeno;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function setWww($www)
    {
        $this->www = $www;
    }

    public function setText($text)
    {
        $this->text = $text;
    }

    public function setDatum($datum)
    {
        $this->datum = $datum;
    }

    public function setNick($nick)
    {
        $this->nick = $nick;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
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
