<?php
namespace App\Entity\Old;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`highline`')]
class Highline
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: HighlineOddily::class)]
    #[ORM\JoinColumn(name: 'typ', referencedColumnName: 'id', nullable: true)]
    private $typ;

    #[ORM\ManyToOne(targetEntity: Uzivatel::class)]
    #[ORM\JoinColumn(name: 'uzivatel_id', referencedColumnName: 'id', nullable: true)]
    private $uzivatel;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $jmeno;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $delka;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $vyska;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $stat;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $oblast;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $kraj;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $gps;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $hodnoceni;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $kotveni;

    #[ORM\Column(type: 'text', nullable: true)]
    private $info;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $pointOneInfo;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $pointTwoInfo;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $autor;

    #[ORM\Column(type: 'date', nullable: true)]
    private $datum;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 12, nullable: true)]
    private $lat;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 12, nullable: true)]
    private $lng;

    #[ORM\ManyToOne(targetEntity: Gps::class)]
    #[ORM\JoinColumn(name: 'parking_id', referencedColumnName: 'id', nullable: true)]
    private $parking;

    #[ORM\ManyToOne(targetEntity: Gps::class)]
    #[ORM\JoinColumn(name: 'point1_id', referencedColumnName: 'id', nullable: true)]
    private $point1;

    #[ORM\ManyToOne(targetEntity: Gps::class)]
    #[ORM\JoinColumn(name: 'point2_id', referencedColumnName: 'id', nullable: true)]
    private $point2;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $nameHistory;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $timeApproach;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $timeTensioning;

    #[ORM\OneToMany(mappedBy: 'highline', targetEntity: HighlineOddily::class)]
    private $highlineOddily;

    public function getId()
    {
        return $this->id;
    }
    public function getTyp()
    {
        return $this->typ;
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
    public function getPointOneInfo()
    {
        return $this->pointOneInfo;
    }
    public function getPointTwoInfo()
    {
        return $this->pointTwoInfo;
    }
    public function getAutor()
    {
        return $this->autor;
    }
    public function getDatum()
    {
        return $this->datum;
    }
    public function getLat()
    {
        return $this->lat;
    }
    public function getLng()
    {
        return $this->lng;
    }
    public function getParking()
    {
        return $this->parking;
    }
    public function getPoint1()
    {
        return $this->point1;
    }
    public function getPoint2()
    {
        return $this->point2;
    }
    public function getNameHistory()
    {
        return $this->nameHistory;
    }
    public function getTimeApproach()
    {
        return $this->timeApproach;
    }
    public function getTimeTensioning()
    {
        return $this->timeTensioning;
    }
}
