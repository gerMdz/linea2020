<?php

namespace App\Entity;

use App\Entity\Traits\IdentificadorTrait;
use App\Repository\TypeFixeRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=TypeFixeRepository::class)
 */
class TypeFixe
{
    use TimestampableEntity;
    use IdentificadorTrait;

    /**
     * @ORM\Id()
     * @ORM\Column(type="uuid", length=36)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=UuidGenerator::class)
     */
    private UuidInterface $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $description;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function __toString()
    {
        return $this->identificador;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
