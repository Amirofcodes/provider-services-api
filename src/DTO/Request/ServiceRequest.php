<?php

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ServiceRequest
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Name must be at least {{ limit }} characters',
        maxMessage: 'Name cannot exceed {{ limit }} characters'
    )]
    private ?string $name = null;

    #[Assert\NotBlank(message: 'Description is required')]
    private ?string $description = null;

    #[Assert\NotBlank(message: 'Price is required')]
    #[Assert\Type(
        type: 'float',
        message: 'Price must be a number'
    )]
    #[Assert\GreaterThan(
        value: 0,
        message: 'Price must be greater than zero'
    )]
    private ?float $price = null;

    #[Assert\NotNull(message: 'Provider ID is required')]
    private ?int $providerId = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getProviderId(): ?int
    {
        return $this->providerId;
    }

    public function setProviderId(?int $providerId): self
    {
        $this->providerId = $providerId;
        return $this;
    }
}
