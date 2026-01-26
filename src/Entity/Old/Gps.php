<?php
namespace App\Entity\Old;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`gps`')]
class Gps
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 10)]
    private $type;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6)]
    private $lat;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6)]
    private $lng;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getLat(): ?string
    {
        return $this->lat;
    }

    public function setLat($lat): self
    {
        $this->lat = $lat;
        return $this;
    }

    public function getLng(): ?string
    {
        return $this->lng;
    }

    public function setLng($lng): self
    {
        $this->lng = $lng;
        return $this;
    }
}
