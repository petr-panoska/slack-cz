<?php
namespace App\Entity\Old;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`highline_oddily`')]
class HighlineOddily
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 25)]
    private $nazev;

    public function getId(): ?int
    {
        return $this->id;
    }
}
