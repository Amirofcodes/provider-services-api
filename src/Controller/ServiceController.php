<?php

namespace App\Controller;

use App\DTO\Request\ServiceRequest;
use App\Entity\Service;
use App\Repository\ServiceRepository;
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
class ServiceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRepository $serviceRepository,
        private ProviderRepository $providerRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private TagAwareCacheInterface $cache
    ) {}

    #[Route('/services', name: 'get_services', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $cacheKey = 'services_list';

        $jsonServices = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(3600);
            $item->tag(['services_tag']);

            $services = $this->serviceRepository->findAll();
            return $this->serializer->serialize($services, 'json', ['groups' => 'service:read']);
        });

        return new JsonResponse($jsonServices, Response::HTTP_OK, [], true);
    }

    #[Route('/services', name: 'create_service', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Deserialize into DTO
        $serviceRequest = $this->serializer->deserialize(
            $request->getContent(),
            ServiceRequest::class,
            'json'
        );

        // Validate
        $violations = $this->validator->validate($serviceRequest);
        if (count($violations) > 0) {
            throw new ValidationFailedException($serviceRequest, $violations);
        }

        // Find provider
        $provider = $this->providerRepository->find($serviceRequest->getProviderId());
        if (!$provider) {
            return new JsonResponse(
                ['message' => 'Provider not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Create service
        $service = new Service();
        $service->setName($serviceRequest->getName());
        $service->setDescription($serviceRequest->getDescription());
        $service->setPrice($serviceRequest->getPrice());
        $service->setProvider($provider);

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cache->invalidateTags(['services_tag', 'providers_tag']);

        return new JsonResponse(
            $this->serializer->serialize($service, 'json', ['groups' => 'service:read']),
            Response::HTTP_CREATED,
            [],
            true
        );
    }

    #[Route('/services/{id}', name: 'update_service', methods: ['PUT'])]
    public function update(Request $request, Service $service): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Find the provider
        $providerId = $data['provider'] ?? null;
        $provider = $providerId ? $this->providerRepository->find($providerId) : null;

        if (!$provider) {
            return new JsonResponse(['error' => 'Provider not found'], Response::HTTP_BAD_REQUEST);
        }

        // Update the service fields
        $service->setName($data['name']);
        $service->setDescription($data['description']);
        $service->setPrice($data['price']);
        $service->setProvider($provider);
        $service->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Invalidate both services and providers cache as both are affected
        $this->cache->invalidateTags(['services_tag', 'providers_tag']);

        return new JsonResponse(
            $this->serializer->serialize($service, 'json', ['groups' => 'service:read']),
            Response::HTTP_OK,
            [],
            true
        );
    }

    #[Route('/services/{id}', name: 'delete_service', methods: ['DELETE'])]
    public function delete(Service $service): JsonResponse
    {
        $this->entityManager->remove($service);
        $this->entityManager->flush();

        // Invalidate both services and providers cache as both are affected
        $this->cache->invalidateTags(['services_tag', 'providers_tag']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
