<?php

namespace Slack\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="uzivatel")
 */
class User extends Object
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
  private $nick;

  /**
   * @Column(type="string")
   */
  private $heslo;

  /**
   * @Column(type="string")
   */
  private $jmeno;

  /**
   * @Column(type="string")
   */
  private $prijmeni;

  /**
   * @Column(type="string")
   */
  private $mesto;

  /**
   * @Column(type="integer", nullable=true)
   */
  private $rok_nar;

  /**
   * @Column(type="string")
   */
  private $email;

  /**
   * @Column(type="string")
   */
  private $telefon;

  /**
   * @Column(type="string")
   */
  private $pohlavi;

  /**
   * @Column(type="boolean")
   */
  private $enabled;

  /**
   * @OneToMany(targetEntity="Longline", mappedBy="uzivatel")
   */
  private $longlines;

  function getLonglines()
  {
    return $this->longlines;
  }

  function setLonglines($longlines)
  {
    $this->longlines = $longlines;
  }

  public function getId()
  {
    return $this->id;
  }

  public function getNick()
  {
    return $this->nick;
  }

  public function getHeslo()
  {
    return $this->heslo;
  }

  public function getJmeno()
  {
    return $this->jmeno;
  }

  public function getPrijmeni()
  {
    return $this->prijmeni;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function setNick($nick)
  {
    $this->nick = $nick;
  }

  public function setHeslo($heslo)
  {
    $this->heslo = $heslo;
  }

  public function setJmeno($jmeno)
  {
    $this->jmeno = $jmeno;
  }

  public function setPrijmeni($prijmeni)
  {
    $this->prijmeni = $prijmeni;
  }

  public function getMesto()
  {
    return $this->mesto;
  }

  public function getRok_nar()
  {
    return $this->rok_nar;
  }

  public function setMesto($mesto)
  {
    $this->mesto = $mesto;
  }

  public function setRok_nar($rok_nar)
  {
    $this->rok_nar = $rok_nar;
  }

  public function getEmail($code = false)
  {
    return $code ? str_replace(".", "(tečka)", str_replace("@", "(zavináč)", $this->email)) : $this->email;
  }

  public function setEmail($email)
  {
    $this->email = $email;
  }

  public function getTelefon()
  {
    return $this->telefon;
  }

  public function setTelefon($telefon)
  {
    $this->telefon = $telefon;
  }

  public function getEnabled()
  {
    return $this->enabled;
  }

  public function setEnabled($enabled)
  {
    $this->enabled = $enabled;
  }

  public function getPohlavi()
  {
    return $this->pohlavi;
  }

  public function setPohlavi($pohlavi)
  {
    $this->pohlavi = $pohlavi;
  }

  public function fullFotoUrl()
  {
    $filePath = "uzivatel" . DS . $this->id . DS . "profil_foto_full.jpg";
    if (file_exists(WWW_DIR . DS . $filePath)) {
      return $filePath;
    } else {
      return "img/ico/profil.jpg";
    }
  }

  public function getThumbnailUrl()
  {
    $filePath = "uzivatel" . DS . $this->id . DS . "profil_foto.jpg";
    if (file_exists(WWW_DIR . DS . $filePath)) {
      return $filePath;
    } else {
      return "img/ico/profil.jpg";
    }
  }

  public function getFullFotoUrl()
  {
    return $this->fullFotoUrl();
  }

  public function toArray()
  {
    return [
        'id' => $this->id,
        'nick' => $this->nick,
        'jmeno' => $this->jmeno,
        'prijmeni' => $this->prijmeni,
        'pohlavi' => $this->pohlavi,
        'email' => $this->email,
        'rok_nar' => $this->rok_nar,
        'mesto' => $this->mesto,
        'telefon' => $this->telefon
    ];
  }
}
