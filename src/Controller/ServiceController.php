<?php

namespace App\Controller;

use App\DTO\Request\ServiceRequest;
use App\DTO\Request\UpdateServiceRequest;
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
use Symfony\Contracts\Cache\ItemInterface;
use App\Exception\ValidationException;
use App\Exception\ResourceNotFoundException;
use App\Exception\BusinessLogicException;
use App\Trait\LoggerTrait;

#[Route('/api')]
class ServiceController extends AbstractController
{
    use LoggerTrait;

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
        try {
            $this->logInfo('Fetching all services from cache or database');
            $cacheKey = 'services_list';

            $jsonServices = $this->cache->get($cacheKey, function (ItemInterface $item) {
                $this->logDebug('Cache miss for services list, fetching from database');
                $item->expiresAfter(3600);
                $item->tag(['services_tag']);

                $services = $this->serviceRepository->findAll();
                return $this->serializer->serialize($services, 'json', ['groups' => 'service:read']);
            });

            return new JsonResponse($jsonServices, Response::HTTP_OK, [], true);
        } catch (\Exception $e) {
            $this->logError('Error fetching services', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error fetching services: ' . $e->getMessage());
        }
    }

    #[Route('/services', name: 'create_service', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $this->logInfo('Creating new service');
            $content = json_decode($request->getContent(), true);

            // Check price format before deserialization
            if (isset($content['price']) && !is_numeric($content['price'])) {
                $this->logError('Invalid price format provided', ['price' => $content['price']]);
                throw new ValidationException([
                    ['property' => 'price', 'message' => 'Price must be a valid number']
                ]);
            }

            // Deserialize into DTO
            $serviceRequest = $this->serializer->deserialize(
                $request->getContent(),
                ServiceRequest::class,
                'json'
            );

            $violations = $this->validator->validate($serviceRequest);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = [
                        'property' => $violation->getPropertyPath(),
                        'message' => $violation->getMessage()
                    ];
                }
                $this->logError('Validation failed for service creation', ['errors' => $errors]);
                throw new ValidationException($errors);
            }

            // Find provider
            $provider = $this->providerRepository->find($serviceRequest->getProviderId());
            if (!$provider) {
                $this->logError('Provider not found for service creation', [
                    'providerId' => $serviceRequest->getProviderId()
                ]);
                throw new ResourceNotFoundException('Provider', $serviceRequest->getProviderId());
            }

            // Create service
            $service = new Service();
            $service->setName($serviceRequest->getName());
            $service->setDescription($serviceRequest->getDescription());
            $service->setPrice($serviceRequest->getPrice());
            $service->setProvider($provider);

            $this->entityManager->persist($service);
            $this->entityManager->flush();

            $this->logInfo('Service created successfully', [
                'serviceId' => $service->getId(),
                'providerId' => $provider->getId()
            ]);

            $this->cache->invalidateTags(['services_tag', 'providers_tag']);

            return new JsonResponse(
                $this->serializer->serialize($service, 'json', ['groups' => 'service:read']),
                Response::HTTP_CREATED,
                [],
                true
            );
        } catch (ValidationException | ResourceNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Unexpected error creating service', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error creating service: ' . $e->getMessage());
        }
    }

    #[Route('/services/{id}', name: 'update_service', methods: ['PUT'])]
    public function update(Request $request, ?Service $service = null): JsonResponse
    {
        if (!$service) {
            $this->logError('Service not found for update', ['id' => $request->attributes->get('id')]);
            throw new ResourceNotFoundException('Service', $request->attributes->get('id'));
        }

        try {
            $this->logInfo('Updating service', [
                'serviceId' => $service->getId(),
                'providerId' => $service->getProvider()->getId()
            ]);

            $updateRequest = $this->serializer->deserialize(
                $request->getContent(),
                UpdateServiceRequest::class,
                'json'
            );

            $violations = $this->validator->validate($updateRequest);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = [
                        'property' => $violation->getPropertyPath(),
                        'message' => $violation->getMessage()
                    ];
                }
                $this->logError('Validation failed for service update', [
                    'serviceId' => $service->getId(),
                    'errors' => $errors
                ]);
                throw new ValidationException($errors);
            }

            $service->setName($updateRequest->getName());
            $service->setDescription($updateRequest->getDescription());
            $service->setPrice($updateRequest->getPrice());

            $this->entityManager->flush();

            $this->logInfo('Service updated successfully', [
                'serviceId' => $service->getId(),
                'providerId' => $service->getProvider()->getId()
            ]);

            $this->cache->invalidateTags(['services_tag', 'providers_tag']);

            return new JsonResponse(
                $this->serializer->serialize($service, 'json', ['groups' => 'service:read']),
                Response::HTTP_OK,
                [],
                true
            );
        } catch (ValidationException | BusinessLogicException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Unexpected error updating service', [
                'serviceId' => $service->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error updating service: ' . $e->getMessage());
        }
    }

    #[Route('/services/{id}', name: 'delete_service', methods: ['DELETE'])]
    public function delete(?Service $service = null): JsonResponse
    {
        if (!$service) {
            $this->logError('Service not found for deletion', ['id' => 'unknown']);
            throw new ResourceNotFoundException('Service', 'id');
        }

        try {
            $this->logInfo('Deleting service', [
                'serviceId' => $service->getId(),
                'providerId' => $service->getProvider()->getId()
            ]);

            $this->entityManager->remove($service);
            $this->entityManager->flush();

            $this->logInfo('Service deleted successfully', [
                'serviceId' => $service->getId(),
                'providerId' => $service->getProvider()->getId()
            ]);

            $this->cache->invalidateTags(['services_tag', 'providers_tag']);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            $this->logError('Error deleting service', [
                'serviceId' => $service->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error deleting service: ' . $e->getMessage());
        }
    }
}
