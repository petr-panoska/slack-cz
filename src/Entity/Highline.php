<?php

namespace App\Entity;

use App\Enum\HighlineType;
use App\Repository\HighlineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HighlineRepository::class)]
#[ORM\Table(name: 'highline')]
#[UniqueEntity(fields: ['name'], message: 'Highline s tímto názvem už existuje')]
class Highline
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 32, enumType: HighlineType::class)]
    private HighlineType $type = HighlineType::Unsorted;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $length = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $height = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    #[Assert\Range(min: -90, max: 90)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    #[Assert\Range(min: -180, max: 180)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?string $point1Latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?string $point1Longitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?string $point2Latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?string $point2Longitude = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $area = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 5)]
    private ?int $rating = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $anchoring = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $pointOneInfo = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $pointTwoInfo = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $firstAscentBy = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $firstAscentDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $nameHistory = null;

    #[ORM\Column(nullable: true)]
    private ?int $approachMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $tensioningMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $legacyId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getType(): HighlineType
    {
        return $this->type;
    }

    public function setType(HighlineType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function setLength(int $length): static
    {
        $this->length = $length;
        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getPoint1Latitude(): ?string
    {
        return $this->point1Latitude;
    }

    public function setPoint1Latitude(?string $point1Latitude): static
    {
        $this->point1Latitude = $point1Latitude;
        return $this;
    }

    public function getPoint1Longitude(): ?string
    {
        return $this->point1Longitude;
    }

    public function setPoint1Longitude(?string $point1Longitude): static
    {
        $this->point1Longitude = $point1Longitude;
        return $this;
    }

    public function getPoint2Latitude(): ?string
    {
        return $this->point2Latitude;
    }

    public function setPoint2Latitude(?string $point2Latitude): static
    {
        $this->point2Latitude = $point2Latitude;
        return $this;
    }

    public function getPoint2Longitude(): ?string
    {
        return $this->point2Longitude;
    }

    public function setPoint2Longitude(?string $point2Longitude): static
    {
        $this->point2Longitude = $point2Longitude;
        return $this;
    }

    public function hasPolyline(): bool
    {
        return $this->point1Latitude !== null
            && $this->point1Longitude !== null
            && $this->point2Latitude !== null
            && $this->point2Longitude !== null;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getArea(): ?string
    {
        return $this->area;
    }

    public function setArea(?string $area): static
    {
        $this->area = $area;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;
        return $this;
    }

    public function getAnchoring(): ?string
    {
        return $this->anchoring;
    }

    public function setAnchoring(?string $anchoring): static
    {
        $this->anchoring = $anchoring;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPointOneInfo(): ?string
    {
        return $this->pointOneInfo;
    }

    public function setPointOneInfo(?string $pointOneInfo): static
    {
        $this->pointOneInfo = $pointOneInfo;
        return $this;
    }

    public function getPointTwoInfo(): ?string
    {
        return $this->pointTwoInfo;
    }

    public function setPointTwoInfo(?string $pointTwoInfo): static
    {
        $this->pointTwoInfo = $pointTwoInfo;
        return $this;
    }

    public function getFirstAscentBy(): ?string
    {
        return $this->firstAscentBy;
    }

    public function setFirstAscentBy(?string $firstAscentBy): static
    {
        $this->firstAscentBy = $firstAscentBy;
        return $this;
    }

    public function getFirstAscentDate(): ?\DateTimeImmutable
    {
        return $this->firstAscentDate;
    }

    public function setFirstAscentDate(?\DateTimeImmutable $firstAscentDate): static
    {
        $this->firstAscentDate = $firstAscentDate;
        return $this;
    }

    public function getNameHistory(): ?string
    {
        return $this->nameHistory;
    }

    public function setNameHistory(?string $nameHistory): static
    {
        $this->nameHistory = $nameHistory;
        return $this;
    }

    public function getApproachMinutes(): ?int
    {
        return $this->approachMinutes;
    }

    public function setApproachMinutes(?int $approachMinutes): static
    {
        $this->approachMinutes = $approachMinutes;
        return $this;
    }

    public function getTensioningMinutes(): ?int
    {
        return $this->tensioningMinutes;
    }

    public function setTensioningMinutes(?int $tensioningMinutes): static
    {
        $this->tensioningMinutes = $tensioningMinutes;
        return $this;
    }

    public function getLegacyId(): ?int
    {
        return $this->legacyId;
    }

    public function setLegacyId(?int $legacyId): static
    {
        $this->legacyId = $legacyId;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCoverUrl(): ?string
    {
        if ($this->legacyId === null) {
            return null;
        }
        return sprintf('https://slack.cz/line/high/%d/foto.jpg', $this->legacyId);
    }
}
