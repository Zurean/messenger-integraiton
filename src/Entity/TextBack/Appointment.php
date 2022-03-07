<?php

namespace App\Entity\TextBack;

use App\Entity\Brand;
use App\Entity\City;
use App\Entity\Generation;
use App\Entity\Maintenance\Maintenance;
use App\Entity\Model;
use App\Entity\Specification;
use App\Repository\TextBack\AppointmentRepository;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

/**
 * @ORM\Entity(repositoryClass=AppointmentRepository::class)
 * @ORM\Table(name="textback_appointment")
 * @ORM\HasLifecycleCallbacks
 */
class Appointment
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $channel;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $channelId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $chatId;

    /**
     * @ORM\ManyToOne(targetEntity=City::class)
     */
    private ?City $city = null;

    /**
     * @ORM\ManyToOne(targetEntity=Brand::class)
     */
    private ?Brand $brand = null;

    /**
     * @ORM\ManyToOne(targetEntity=Model::class)
     */
    private ?Model $model = null;

    /**
     * @ORM\ManyToOne(targetEntity=Generation::class)
     */
    private ?Generation $generation = null;

    /**
     * @ORM\ManyToOne(targetEntity=Specification::class)
     */
    private ?Specification $specification = null;

    /**
     * @ORM\ManyToOne(targetEntity=Maintenance::class)
     */
    private ?Maintenance $maintenance = null;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $isFinal = null;

    /**
     * @ORM\Column(type="simple_array", nullable=true)
     */
    private array $hashes = [];

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(?string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function getChannelId(): ?int
    {
        return $this->channelId;
    }

    public function setChannelId(?int $channelId): self
    {
        $this->channelId = $channelId;

        return $this;
    }

    public function getChatId(): ?string
    {
        return $this->chatId;
    }

    public function setChatId(?string $chatId): self
    {
        $this->chatId = $chatId;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function setModel(?Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getGeneration(): ?Generation
    {
        return $this->generation;
    }

    public function setGeneration(?Generation $generation): self
    {
        $this->generation = $generation;

        return $this;
    }

    public function getSpecification(): ?Specification
    {
        return $this->specification;
    }

    public function setSpecification(?Specification $specification): self
    {
        $this->specification = $specification;

        return $this;
    }

    public function getMaintenance(): ?Maintenance
    {
        return $this->maintenance;
    }

    public function setMaintenance(?Maintenance $maintenance): self
    {
        $this->maintenance = $maintenance;

        return $this;
    }

    public function getIsFinal(): ?bool
    {
        return $this->isFinal;
    }

    public function setIsFinal(?bool $isFinal): self
    {
        $this->isFinal = $isFinal;

        return $this;
    }

    public function getHashes(): ?array
    {
        return $this->hashes;
    }

    public function addHash(string $hash): self
    {
        $this->hashes[] = $hash;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @ORM\PrePersist
     */
    public function onPrePersist(): void
    {
        $this->setCreatedAt(new DateTimeImmutable());
    }
}
