<?php

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ProviderRequest
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Name must be at least {{ limit }} characters',
        maxMessage: 'Name cannot exceed {{ limit }} characters'
    )]
    private ?string $name = null;

    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'The email {{ value }} is not a valid email.')]
    private ?string $email = null;

    #[Assert\NotBlank(message: 'Phone number is required')]
    #[Assert\Length(
        min: 10,
        max: 15,
        minMessage: 'Phone number must be at least {{ limit }} characters',
        maxMessage: 'Phone number cannot exceed {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^\+?[0-9]+$/',
        message: 'Phone number can only contain numbers and an optional + prefix'
    )]
    private ?string $phone = null;

    #[Assert\NotBlank(message: 'Address is required')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Address must be at least {{ limit }} characters',
        maxMessage: 'Address cannot exceed {{ limit }} characters'
    )]
    private ?string $address = null;

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
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }
    public function getPhone(): ?string
    {
        return $this->phone;
    }
    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }
    public function getAddress(): ?string
    {
        return $this->address;
    }
    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }
}
