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
 * @Table(name="highline_foto")
 */
class HighlinePhoto extends Object
{

  /**
   * @Id
   * @GeneratedValue
   * @Column(type="integer")
   */
  private $id;

  /**
   * @ManyToOne(targetEntity="Highline", inversedBy="photos")
   * @JoinColumn(name="highline_id", referencedColumnName="id")
   */
  private $highline;

  /**
   * @Column(type="string")
   */
  private $text;

  /**
   * @Column(type="string")
   */
  private $file;

  public function getId()
  {
    return $this->id;
  }

  public function getHighline()
  {
    return $this->highline;
  }

  public function getText()
  {
    return $this->text;
  }

  public function getFile()
  {
    return $this->file;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function setHighline($highline)
  {
    $this->highline = $highline;
  }

  public function setText($text)
  {
    $this->text = $text;
  }

  public function setFile($file)
  {
    $this->file = $file;
  }

}
