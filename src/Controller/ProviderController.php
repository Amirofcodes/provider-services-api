<?php

namespace App\Controller;

use App\DTO\Request\ProviderRequest;
use App\DTO\Request\UpdateProviderRequest;
use App\Entity\Provider;
use App\Repository\ProviderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api')]
class ProviderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProviderRepository $providerRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private TagAwareCacheInterface $cache
    ) {}

    #[Route('/providers', name: 'get_providers', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $cacheKey = 'providers_list';

        $jsonProviders = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(3600);
            $item->tag(['providers_tag']);

            $providers = $this->providerRepository->findAll();
            return $this->serializer->serialize($providers, 'json', ['groups' => 'provider:read']);
        });

        return new JsonResponse($jsonProviders, Response::HTTP_OK, [], true);
    }

    #[Route('/providers', name: 'create_provider', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Deserialize into DTO
        $providerRequest = $this->serializer->deserialize(
            $request->getContent(),
            ProviderRequest::class,
            'json'
        );

        // Validate
        $violations = $this->validator->validate($providerRequest);
        if (count($violations) > 0) {
            throw new ValidationFailedException($providerRequest, $violations);
        }

        // Create provider
        $provider = new Provider();
        $provider->setName($providerRequest->getName());
        $provider->setEmail($providerRequest->getEmail());
        $provider->setPhone($providerRequest->getPhone());
        $provider->setAddress($providerRequest->getAddress());

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cache->invalidateTags(['providers_tag']);

        return new JsonResponse(
            $this->serializer->serialize($provider, 'json', ['groups' => 'provider:read']),
            Response::HTTP_CREATED,
            [],
            true
        );
    }

    #[Route('/providers/{id}', name: 'update_provider', methods: ['PUT'])]
    public function update(Request $request, Provider $provider): JsonResponse
    {
        // Deserialize into DTO
        $updateRequest = $this->serializer->deserialize(
            $request->getContent(),
            UpdateProviderRequest::class,
            'json'
        );

        // Validate
        $violations = $this->validator->validate($updateRequest);
        if (count($violations) > 0) {
            throw new ValidationFailedException($updateRequest, $violations);
        }

        // Update provider
        $provider->setName($updateRequest->getName());
        $provider->setEmail($updateRequest->getEmail());
        $provider->setPhone($updateRequest->getPhone());
        $provider->setAddress($updateRequest->getAddress());

        $this->entityManager->flush();

        // Invalidate cache
        $this->cache->invalidateTags(['providers_tag']);

        return new JsonResponse(
            $this->serializer->serialize($provider, 'json', ['groups' => 'provider:read']),
            Response::HTTP_OK,
            [],
            true
        );
    }
}
