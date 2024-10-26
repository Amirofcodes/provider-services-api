<?php

namespace App\Controller;

use App\Entity\Provider;
use App\Repository\ProviderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api')]
class ProviderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProviderRepository $providerRepository,
        private SerializerInterface $serializer,
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
        $provider = $this->serializer->deserialize($request->getContent(), Provider::class, 'json');

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        // Invalidate the providers list cache when a new provider is added
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
        $updatedProvider = $this->serializer->deserialize($request->getContent(), Provider::class, 'json');

        $provider->setName($updatedProvider->getName());
        $provider->setEmail($updatedProvider->getEmail());
        $provider->setPhone($updatedProvider->getPhone());
        $provider->setAddress($updatedProvider->getAddress());
        $provider->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Invalidate the providers list cache when a provider is updated
        $this->cache->invalidateTags(['providers_tag']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/providers/{id}', name: 'delete_provider', methods: ['DELETE'])]
    public function delete(Provider $provider): JsonResponse
    {
        $this->entityManager->remove($provider);
        $this->entityManager->flush();

        // Invalidate the providers list cache when a provider is deleted
        $this->cache->invalidateTags(['providers_tag']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
