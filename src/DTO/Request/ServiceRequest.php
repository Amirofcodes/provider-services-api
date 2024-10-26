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
    #[Assert\Type(type: 'string', message: 'Price must be a string')]
    #[Assert\Regex(
        pattern: '/^\d+\.\d{2}$/',
        message: 'Price must be a valid number with exactly 2 decimal places'
    )]
    private ?string $price = null;

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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        // Validate format before setting
        if (!preg_match('/^\d+\.\d{2}$/', $price)) {
            throw new \InvalidArgumentException('Price must be in format "XXX.XX"');
        }
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
