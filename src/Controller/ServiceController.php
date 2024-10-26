<?php

namespace App\Controller;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api')]
class ServiceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRepository $serviceRepository,
        private SerializerInterface $serializer
    ) {}

    #[Route('/services', name: 'get_services', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $services = $this->serviceRepository->findAll();
        $jsonServices = $this->serializer->serialize($services, 'json', ['groups' => 'service:read']);

        return new JsonResponse($jsonServices, Response::HTTP_OK, [], true);
    }

    #[Route('/services', name: 'create_service', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $service = $this->serializer->deserialize($request->getContent(), Service::class, 'json');

        $this->entityManager->persist($service);
        $this->entityManager->flush();

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
        $updatedService = $this->serializer->deserialize($request->getContent(), Service::class, 'json');

        $service->setName($updatedService->getName());
        $service->setDescription($updatedService->getDescription());
        $service->setPrice($updatedService->getPrice());
        $service->setProvider($updatedService->getProvider());

        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/services/{id}', name: 'delete_service', methods: ['DELETE'])]
    public function delete(Service $service): JsonResponse
    {
        $this->entityManager->remove($service);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
