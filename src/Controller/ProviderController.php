<?php

namespace App\Controller;

use App\DTO\Request\ProviderRequest;
use App\DTO\Request\UpdateProviderRequest;
use App\Entity\Provider;
use App\Repository\ProviderRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
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
#[OA\Tag(name: 'Providers')]
class ProviderController extends AbstractController
{
    use LoggerTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProviderRepository $providerRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private TagAwareCacheInterface $cache
    ) {}

    #[Route('/providers', name: 'get_providers', methods: ['GET'])]
    #[OA\Get(
        path: '/api/providers',
        description: 'Retrieves the list of all providers',
        summary: 'Get all providers'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns all providers',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Provider')
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal server error',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function index(): JsonResponse
    {
        try {
            $this->logInfo('Starting providers fetch operation', [
                'action' => 'provider_list_start',
                'cache_key' => 'providers_list'
            ]);

            $cacheKey = 'providers_list';

            $jsonProviders = $this->cache->get($cacheKey, function (ItemInterface $item) {
                $this->logDebug('Cache miss occurred for providers list', [
                    'action' => 'provider_list_cache_miss',
                    'cache_config' => [
                        'expiration' => '3600 seconds',
                        'tags' => ['providers_tag']
                    ]
                ]);

                $item->expiresAfter(3600);
                $item->tag(['providers_tag']);

                $providers = $this->providerRepository->findAll();
                $count = count($providers);

                $this->logDebug('Retrieved providers from database', [
                    'action' => 'provider_list_db_fetch',
                    'provider_count' => $count
                ]);

                return $this->serializer->serialize($providers, 'json', ['groups' => 'provider:read']);
            });

            $this->logInfo('Successfully retrieved providers list', [
                'action' => 'provider_list_success',
                'response_size' => strlen($jsonProviders)
            ]);

            return new JsonResponse($jsonProviders, Response::HTTP_OK, [], true);
        } catch (\Exception $e) {
            $this->logError('Failed to fetch providers', [
                'action' => 'provider_list_error',
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error fetching providers: ' . $e->getMessage());
        }
    }

    #[Route('/providers', name: 'create_provider', methods: ['POST'])]
    #[OA\Post(
        path: '/api/providers',
        description: 'Creates a new provider',
        summary: 'Create a provider'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/Provider')
    )]
    #[OA\Response(
        response: 201,
        description: 'Provider successfully created',
        content: new OA\JsonContent(ref: '#/components/schemas/Provider')
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid input',
        content: new OA\JsonContent(ref: '#/components/responses/ValidationError')
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal server error',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $this->logInfo('Starting provider creation', [
                'action' => 'provider_create_start',
                'content_length' => strlen($request->getContent())
            ]);

            $providerRequest = $this->serializer->deserialize(
                $request->getContent(),
                ProviderRequest::class,
                'json'
            );

            // Validation
            $violations = $this->validator->validate($providerRequest);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = [
                        'property' => $violation->getPropertyPath(),
                        'message' => $violation->getMessage(),
                        'invalid_value' => $violation->getInvalidValue()
                    ];
                }
                $this->logError('Provider creation validation failed', [
                    'action' => 'provider_create_validation_error',
                    'validation_errors' => $errors,
                    'request_data' => [
                        'name' => $providerRequest->getName(),
                        'email' => $providerRequest->getEmail(),
                        // Mask sensitive data in logs
                        'phone' => substr($providerRequest->getPhone(), 0, 3) . '****' . substr($providerRequest->getPhone(), -4),
                    ]
                ]);
                throw new ValidationException($errors);
            }

            // Check duplicate email
            if ($this->providerRepository->findOneBy(['email' => $providerRequest->getEmail()])) {
                $this->logError('Duplicate email detected during provider creation', [
                    'action' => 'provider_create_duplicate_email',
                    'email' => $providerRequest->getEmail()
                ]);
                throw new BusinessLogicException('Email already exists', 'DUPLICATE_EMAIL');
            }

            $provider = new Provider();
            $provider->setName($providerRequest->getName());
            $provider->setEmail($providerRequest->getEmail());
            $provider->setPhone($providerRequest->getPhone());
            $provider->setAddress($providerRequest->getAddress());

            $this->entityManager->persist($provider);
            $this->entityManager->flush();

            $this->logInfo('Provider created successfully', [
                'action' => 'provider_create_success',
                'provider' => [
                    'id' => $provider->getId(),
                    'name' => $provider->getName(),
                    'email' => $provider->getEmail()
                ]
            ]);

            $this->cache->invalidateTags(['providers_tag']);
            $this->logDebug('Cache invalidated after provider creation', [
                'action' => 'provider_create_cache_invalidate',
                'tags' => ['providers_tag']
            ]);

            return new JsonResponse(
                $this->serializer->serialize($provider, 'json', ['groups' => 'provider:read']),
                Response::HTTP_CREATED,
                [],
                true
            );
        } catch (ValidationException | BusinessLogicException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Unexpected error during provider creation', [
                'action' => 'provider_create_error',
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error creating provider: ' . $e->getMessage());
        }
    }

    #[Route('/providers/{id}', name: 'update_provider', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/providers/{id}',
        description: 'Updates an existing provider',
        summary: 'Update a provider'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID of the provider to update'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/Provider')
    )]
    #[OA\Response(
        response: 200,
        description: 'Provider successfully updated',
        content: new OA\JsonContent(ref: '#/components/schemas/Provider')
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid input',
        content: new OA\JsonContent(ref: '#/components/responses/ValidationError')
    )]
    #[OA\Response(
        response: 404,
        description: 'Provider not found',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function update(Request $request, ?Provider $provider = null): JsonResponse
    {
        if (!$provider) {
            $this->logError('Provider update attempted for non-existent provider', [
                'action' => 'provider_update_not_found',
                'requested_id' => $request->attributes->get('id'),
                'request_method' => $request->getMethod(),
                'request_path' => $request->getPathInfo()
            ]);
            throw new ResourceNotFoundException('Provider', $request->attributes->get('id'));
        }

        try {
            $this->logInfo('Starting provider update operation', [
                'action' => 'provider_update_start',
                'provider' => [
                    'id' => $provider->getId(),
                    'current_name' => $provider->getName(),
                    'current_email' => $provider->getEmail()
                ],
                'content_length' => strlen($request->getContent())
            ]);

            $updateRequest = $this->serializer->deserialize(
                $request->getContent(),
                UpdateProviderRequest::class,
                'json'
            );

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
                $this->logError('Provider update validation failed', [
                    'action' => 'provider_update_validation_error',
                    'provider_id' => $provider->getId(),
                    'validation_errors' => $errors,
                    'request_data' => [
                        'name' => $updateRequest->getName(),
                        'email' => $updateRequest->getEmail(),
                        'phone' => substr($updateRequest->getPhone(), 0, 3) . '****' . substr($updateRequest->getPhone(), -4),
                    ]
                ]);
                throw new ValidationException($errors);
            }

            $existingProvider = $this->providerRepository->findOneBy(['email' => $updateRequest->getEmail()]);
            if ($existingProvider && $existingProvider->getId() !== $provider->getId()) {
                $this->logError('Duplicate email detected during provider update', [
                    'action' => 'provider_update_duplicate_email',
                    'provider_id' => $provider->getId(),
                    'conflicting_provider_id' => $existingProvider->getId(),
                    'requested_email' => $updateRequest->getEmail()
                ]);
                throw new BusinessLogicException('Email already exists', 'DUPLICATE_EMAIL');
            }

            $this->logDebug('Updating provider details', [
                'action' => 'provider_update_changes',
                'provider_id' => $provider->getId(),
                'changes' => [
                    'name' => [
                        'from' => $provider->getName(),
                        'to' => $updateRequest->getName()
                    ],
                    'email' => [
                        'from' => $provider->getEmail(),
                        'to' => $updateRequest->getEmail()
                    ],
                    'address' => [
                        'from' => $provider->getAddress(),
                        'to' => $updateRequest->getAddress()
                    ]
                ]
            ]);

            $provider->setName($updateRequest->getName());
            $provider->setEmail($updateRequest->getEmail());
            $provider->setPhone($updateRequest->getPhone());
            $provider->setAddress($updateRequest->getAddress());

            $this->entityManager->flush();

            $this->logInfo('Provider updated successfully', [
                'action' => 'provider_update_success',
                'provider' => [
                    'id' => $provider->getId(),
                    'updated_name' => $provider->getName(),
                    'updated_email' => $provider->getEmail()
                ]
            ]);

            $this->cache->invalidateTags(['providers_tag']);
            $this->logDebug('Cache invalidated after provider update', [
                'action' => 'provider_update_cache_invalidate',
                'tags' => ['providers_tag']
            ]);

            return new JsonResponse(
                $this->serializer->serialize($provider, 'json', ['groups' => 'provider:read']),
                Response::HTTP_OK,
                [],
                true
            );
        } catch (ValidationException | BusinessLogicException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Unexpected error during provider update', [
                'action' => 'provider_update_error',
                'provider_id' => $provider->getId(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error updating provider: ' . $e->getMessage());
        }
    }

    #[Route('/providers/{id}', name: 'delete_provider', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/providers/{id}',
        description: 'Deletes a provider',
        summary: 'Delete a provider'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID of the provider to delete'
    )]
    #[OA\Response(
        response: 204,
        description: 'Provider successfully deleted'
    )]
    #[OA\Response(
        response: 404,
        description: 'Provider not found',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function delete(?Provider $provider = null): JsonResponse
    {
        if (!$provider) {
            $this->logError('Provider deletion attempted for non-existent provider', [
                'action' => 'provider_delete_not_found',
                'requested_id' => 'unknown'
            ]);
            throw new ResourceNotFoundException('Provider', 'id');
        }

        try {
            $this->logInfo('Starting provider deletion', [
                'action' => 'provider_delete_start',
                'provider' => [
                    'id' => $provider->getId(),
                    'name' => $provider->getName(),
                    'email' => $provider->getEmail(),
                    'service_count' => count($provider->getServices())
                ]
            ]);

            $providerId = $provider->getId(); // Store for logging after deletion
            $providerName = $provider->getName();

            $this->entityManager->remove($provider);
            $this->entityManager->flush();

            $this->logInfo('Provider deleted successfully', [
                'action' => 'provider_delete_success',
                'deleted_provider' => [
                    'id' => $providerId,
                    'name' => $providerName
                ]
            ]);

            $this->cache->invalidateTags(['providers_tag']);
            $this->logDebug('Cache invalidated after provider deletion', [
                'action' => 'provider_delete_cache_invalidate',
                'tags' => ['providers_tag']
            ]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            $this->logError('Error during provider deletion', [
                'action' => 'provider_delete_error',
                'provider_id' => $provider->getId(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new BusinessLogicException('Error deleting provider: ' . $e->getMessage());
        }
    }
}
