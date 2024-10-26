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
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

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
            $this->logInfo('Starting services fetch operation', [
                'action' => 'service_list_start',
                'cache_key' => 'services_list'
            ]);

            $cacheKey = 'services_list';

            $jsonServices = $this->cache->get($cacheKey, function (ItemInterface $item) {
                $this->logDebug('Cache miss occurred for services list', [
                    'action' => 'service_list_cache_miss',
                    'cache_config' => [
                        'expiration' => '3600 seconds',
                        'tags' => ['services_tag']
                    ]
                ]);

                $item->expiresAfter(3600);
                $item->tag(['services_tag']);

                $services = $this->serviceRepository->findAll();
                $count = count($services);

                $this->logDebug('Retrieved services from database', [
                    'action' => 'service_list_db_fetch',
                    'service_count' => $count,
                    'providers_count' => count(array_unique(array_map(fn($s) => $s->getProvider()->getId(), $services)))
                ]);

                return $this->serializer->serialize($services, 'json', ['groups' => 'service:read']);
            });

            $this->logInfo('Successfully retrieved services list', [
                'action' => 'service_list_success',
                'response_size' => strlen($jsonServices)
            ]);

            return new JsonResponse($jsonServices, Response::HTTP_OK, [], true);
        } catch (\Exception $e) {
            $this->logError('Failed to fetch services', [
                'action' => 'service_list_error',
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error fetching services: ' . $e->getMessage());
        }
    }

    #[Route('/services', name: 'create_service', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $this->logInfo('Starting service creation', [
                'action' => 'service_create_start',
                'content_length' => strlen($request->getContent())
            ]);

            $content = json_decode($request->getContent(), true);

            // Price validation pre-deserialization
            if (isset($content['price'])) {
                $this->logDebug('Validating price format', [
                    'action' => 'service_create_price_validation',
                    'price_value' => $content['price'],
                    'price_type' => gettype($content['price'])
                ]);

                if (!is_numeric($content['price'])) {
                    $this->logError('Invalid price format in service creation', [
                        'action' => 'service_create_price_error',
                        'invalid_price' => $content['price'],
                        'expected_type' => 'numeric'
                    ]);
                    throw new ValidationException([
                        ['property' => 'price', 'message' => 'Price must be a valid number']
                    ]);
                }
            }

            // Deserialize and validate
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
                        'message' => $violation->getMessage(),
                        'invalid_value' => $violation->getInvalidValue()
                    ];
                }
                $this->logError('Service creation validation failed', [
                    'action' => 'service_create_validation_error',
                    'validation_errors' => $errors,
                    'request_data' => [
                        'name' => $serviceRequest->getName(),
                        'description' => strlen($serviceRequest->getDescription()) > 50
                            ? substr($serviceRequest->getDescription(), 0, 47) . '...'
                            : $serviceRequest->getDescription(),
                        'price' => $serviceRequest->getPrice(),
                        'provider_id' => $serviceRequest->getProviderId()
                    ]
                ]);
                throw new ValidationException($errors);
            }

            // Find and validate provider
            $provider = $this->providerRepository->find($serviceRequest->getProviderId());
            if (!$provider) {
                $this->logError('Provider not found for service creation', [
                    'action' => 'service_create_provider_not_found',
                    'provider_id' => $serviceRequest->getProviderId(),
                    'service_data' => [
                        'name' => $serviceRequest->getName(),
                        'price' => $serviceRequest->getPrice()
                    ]
                ]);
                throw new ResourceNotFoundException('Provider', $serviceRequest->getProviderId());
            }

            $this->logDebug('Found provider for service creation', [
                'action' => 'service_create_provider_found',
                'provider' => [
                    'id' => $provider->getId(),
                    'name' => $provider->getName(),
                    'existing_services_count' => count($provider->getServices())
                ]
            ]);

            // Create service
            $service = new Service();
            $service->setName($serviceRequest->getName());
            $service->setDescription($serviceRequest->getDescription());
            $service->setPrice($serviceRequest->getPrice());
            $service->setProvider($provider);

            $this->entityManager->persist($service);
            $this->entityManager->flush();

            $this->logInfo('Service created successfully', [
                'action' => 'service_create_success',
                'service' => [
                    'id' => $service->getId(),
                    'name' => $service->getName(),
                    'price' => $service->getPrice(),
                ],
                'provider' => [
                    'id' => $provider->getId(),
                    'name' => $provider->getName()
                ]
            ]);

            $this->cache->invalidateTags(['services_tag', 'providers_tag']);
            $this->logDebug('Cache invalidated after service creation', [
                'action' => 'service_create_cache_invalidate',
                'tags' => ['services_tag', 'providers_tag']
            ]);

            return new JsonResponse(
                $this->serializer->serialize($service, 'json', ['groups' => 'service:read']),
                Response::HTTP_CREATED,
                [],
                true
            );
        } catch (ValidationException | ResourceNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Unexpected error during service creation', [
                'action' => 'service_create_error',
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error creating service: ' . $e->getMessage());
        }
    }

    #[Route('/services/{id}', name: 'update_service', methods: ['PUT'])]
    public function update(Request $request, ?Service $service = null): JsonResponse
    {
        if (!$service) {
            $this->logError('Service update attempted for non-existent service', [
                'action' => 'service_update_not_found',
                'requested_id' => $request->attributes->get('id'),
                'request_method' => $request->getMethod(),
                'request_path' => $request->getPathInfo()
            ]);
            throw new ResourceNotFoundException('Service', (string)$request->attributes->get('id'));
        }

        try {
            $this->logInfo('Starting service update operation', [
                'action' => 'service_update_start',
                'service' => [
                    'id' => $service->getId(),
                    'current_name' => $service->getName(),
                    'current_price' => $service->getPrice(),
                    'provider_id' => $service->getProvider()->getId()
                ],
                'content_length' => strlen($request->getContent())
            ]);

            try {
                $updateRequest = $this->serializer->deserialize(
                    $request->getContent(),
                    UpdateServiceRequest::class,
                    'json'
                );
            } catch (NotNormalizableValueException $e) {
                $this->logError('Service update data type validation failed', [
                    'action' => 'service_update_type_error',
                    'service_id' => $service->getId(),
                    'error_details' => [
                        'property' => $e->getPath(),
                        'expected_types' => $e->getExpectedTypes(),
                        'current_type' => $e->getCurrentType(),
                        // getValue() is not available, we'll use the raw data
                        'raw_data' => json_decode($request->getContent(), true)
                    ]
                ]);
                throw new ValidationException([
                    [
                        'property' => $e->getPath(),
                        'message' => sprintf(
                            'Invalid type for %s. Expected %s, got %s',
                            $e->getPath(),
                            implode('|', $e->getExpectedTypes()),
                            $e->getCurrentType()
                        )
                    ]
                ]);
            }

            $violations = $this->validator->validate($updateRequest);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = [
                        'property' => $violation->getPropertyPath(),
                        'message' => $violation->getMessage(),
                        'invalid_value' => $violation->getInvalidValue()
                    ];
                }
                $this->logError('Service update validation failed', [
                    'action' => 'service_update_validation_error',
                    'service_id' => $service->getId(),
                    'validation_errors' => $errors,
                    'request_data' => [
                        'name' => $updateRequest->getName(),
                        'description' => strlen($updateRequest->getDescription()) > 50
                            ? substr($updateRequest->getDescription(), 0, 47) . '...'
                            : $updateRequest->getDescription(),
                        'price' => $updateRequest->getPrice()
                    ]
                ]);
                throw new ValidationException($errors);
            }

            $this->logDebug('Updating service details', [
                'action' => 'service_update_changes',
                'service_id' => $service->getId(),
                'changes' => [
                    'name' => [
                        'from' => $service->getName(),
                        'to' => $updateRequest->getName()
                    ],
                    'description' => [
                        'from' => strlen($service->getDescription()) > 50
                            ? substr($service->getDescription(), 0, 47) . '...'
                            : $service->getDescription(),
                        'to' => strlen($updateRequest->getDescription()) > 50
                            ? substr($updateRequest->getDescription(), 0, 47) . '...'
                            : $updateRequest->getDescription()
                    ],
                    'price' => [
                        'from' => $service->getPrice(),
                        'to' => $updateRequest->getPrice()
                    ]
                ],
                'provider' => [
                    'id' => $service->getProvider()->getId(),
                    'name' => $service->getProvider()->getName()
                ]
            ]);

            $service->setName($updateRequest->getName());
            $service->setDescription($updateRequest->getDescription());
            $service->setPrice($updateRequest->getPrice());

            $this->entityManager->flush();

            $this->logInfo('Service updated successfully', [
                'action' => 'service_update_success',
                'service' => [
                    'id' => $service->getId(),
                    'updated_name' => $service->getName(),
                    'updated_price' => $service->getPrice()
                ],
                'provider' => [
                    'id' => $service->getProvider()->getId(),
                    'name' => $service->getProvider()->getName()
                ]
            ]);

            $this->cache->invalidateTags(['services_tag', 'providers_tag']);
            $this->logDebug('Cache invalidated after service update', [
                'action' => 'service_update_cache_invalidate',
                'tags' => ['services_tag', 'providers_tag']
            ]);

            return new JsonResponse(
                $this->serializer->serialize($service, 'json', ['groups' => 'service:read']),
                Response::HTTP_OK,
                [],
                true
            );
        } catch (ValidationException | BusinessLogicException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Unexpected error during service update', [
                'action' => 'service_update_error',
                'service_id' => $service->getId(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error updating service: ' . $e->getMessage());
        }
    }

    #[Route('/services/{id}', name: 'delete_service', methods: ['DELETE'])]
    public function delete(Request $request, ?Service $service = null): JsonResponse
    {
        if (!$service) {
            $this->logError('Service deletion attempted for non-existent service', [
                'action' => 'service_delete_not_found',
                'requested_id' => $request->attributes->get('id')
            ]);
            throw new ResourceNotFoundException('Service', (string)$request->attributes->get('id'));
        }

        try {
            $this->logInfo('Starting service deletion', [
                'action' => 'service_delete_start',
                'service' => [
                    'id' => $service->getId(),
                    'name' => $service->getName(),
                    'price' => $service->getPrice()
                ],
                'provider' => [
                    'id' => $service->getProvider()->getId(),
                    'name' => $service->getProvider()->getName(),
                    'remaining_services_count' => count($service->getProvider()->getServices()) - 1
                ]
            ]);

            // Store data for logging after deletion
            $serviceId = $service->getId();
            $serviceName = $service->getName();
            $providerId = $service->getProvider()->getId();
            $providerName = $service->getProvider()->getName();

            $this->entityManager->remove($service);
            $this->entityManager->flush();

            $this->logInfo('Service deleted successfully', [
                'action' => 'service_delete_success',
                'deleted_service' => [
                    'id' => $serviceId,
                    'name' => $serviceName
                ],
                'provider' => [
                    'id' => $providerId,
                    'name' => $providerName
                ]
            ]);

            $this->cache->invalidateTags(['services_tag', 'providers_tag']);
            $this->logDebug('Cache invalidated after service deletion', [
                'action' => 'service_delete_cache_invalidate',
                'tags' => ['services_tag', 'providers_tag'],
                'context' => [
                    'deleted_service_id' => $serviceId,
                    'affected_provider_id' => $providerId
                ]
            ]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            $this->logError('Error during service deletion', [
                'action' => 'service_delete_error',
                'service_id' => $service->getId(),
                'provider_id' => $service->getProvider()->getId(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error deleting service: ' . $e->getMessage());
        }
    }
}
