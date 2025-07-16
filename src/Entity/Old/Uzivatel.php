<?php
namespace App\Entity\Old;

use App\Repository\Old\UzivatelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UzivatelRepository::class)]
#[ORM\Table(name: '`uzivatel`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_NICK', fields: ['nick'])]
class Uzivatel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "string", length: 15)]
    private $nick;

    #[ORM\Column(type: "string", length: 32)]
    private $heslo;

    #[ORM\Column(type: "string", length: 30)]
    private $jmeno;

    #[ORM\Column(type: "string", length: 30)]
    private $prijmeni;

    #[ORM\Column(type: "integer", nullable: true)]
    private $rok_nar;

    #[ORM\Column(type: "string", length: 30)]
    private $email;

    #[ORM\Column(type: "string", length: 30, nullable: true)]
    private $telefon;

    #[ORM\Column(type: "string", length: 50)]
    private $mesto;

    #[ORM\Column(type: "string", length: 1, nullable: true)]
    private $pohlavi;

    #[ORM\Column(type: "string", length: 45, options: ["default" => "guest"])]
    private $role = 'guest';

    #[ORM\Column(type: "boolean", options: ["default" => 1])]
    private $enabled = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNick(): ?string
    {
        return $this->nick;
    }

    public function getHeslo(): ?string
    {
        return $this->heslo;
    }

    public function getJmeno(): ?string
    {
        return $this->jmeno;
    }

    public function getPrijmeni(): ?string
    {
        return $this->prijmeni;
    }

    public function getRok_nar(): ?int
    {
        return $this->rok_nar;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getTelefon(): ?string
    {
        return $this->telefon;
    }

    public function getMesto(): ?string
    {
        return $this->mesto;
    }

    public function getPohlavi(): ?string
    {
        return $this->pohlavi;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
