<?php

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProviderRequest extends ProviderRequest
{
    // Inherits all validation from ProviderRequest
    // We could add update-specific validation here if needed
}
