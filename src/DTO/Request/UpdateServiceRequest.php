<?php

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateServiceRequest
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
    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Price must be a valid number with up to 2 decimal places'
    )]
    private ?string $price = null;

    // No providerId required for update as it's part of the existing service

    // Getters and setters
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
    public function setPrice(?string $price): self
    {
        $this->price = $price;
        return $this;
    }
}
