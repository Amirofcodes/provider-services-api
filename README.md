# Provider Services API

A RESTful API built with Symfony 6.4 following the 12-Factor App methodology for managing service providers and their services.

## Project Overview

This project is an implementation of a provider-services management system that allows:

- Management of service providers (CRUD operations)
- Management of services offered by providers (CRUD operations)
- Data persistence using MySQL
- Cache management with Redis
- Email notifications for provider updates
- Comprehensive API documentation with OpenAPI/Swagger
- Administrative commands for system management

### Technical Stack

- Symfony 6.4
- PHP 8.3
- MySQL 8.0
- Redis (Alpine)
- Nginx
- Docker & Docker Compose

## Installation

1. Clone the repository

```bash
git clone https://github.com/Amirofcodes/provider-services-api.git
cd provider-services-api
```

2. Start the Docker containers

```bash
docker-compose up -d
```

3. Install dependencies

```bash
composer install
```

4. Run migrations

```bash
php bin/console doctrine:migrations:migrate
```

5. Access the application

```bash
# The API will be available at:
http://localhost:8000/api

# API Documentation is available at:
http://localhost:8000/api/doc
```

## API Documentation

The API documentation is available through Swagger UI at `/api/doc` and provides:

- Detailed endpoint descriptions
- Request/Response schemas
- Validation rules
- Error responses
- Example payloads

### Available Endpoints

#### Providers

- `GET /api/providers` - Get all providers
- `POST /api/providers` - Create a new provider
- `PUT /api/providers/{id}` - Update a provider
- `DELETE /api/providers/{id}` - Delete a provider

#### Services

- `GET /api/services` - Get all services
- `POST /api/services` - Create a new service
- `PUT /api/services/{id}` - Update a service
- `DELETE /api/services/{id}` - Delete a service

## Administrative Commands

The application provides several administrative commands for system management:

### Cache Management

```bash
# Clear all tagged cache
php bin/console app:cache:clear-tags --all

# Clear specific cache tags
php bin/console app:cache:clear-tags --tags providers_tag --tags services_tag
```

### System Statistics

```bash
# Generate statistics in table format
php bin/console app:stats:generate

# Generate statistics in JSON format
php bin/console app:stats:generate --format=json
```

Statistics include:

- Total number of providers
- Total number of services
- Average services per provider
- Number of providers without services
- Total value of all services

## Development Commands

```bash
# Create database
docker-compose exec app php bin/console doctrine:database:create

# Run migrations
docker-compose exec app php bin/console doctrine:migrations:migrate

# Create new migration
docker-compose exec app php bin/console make:migration

# Clear cache
docker-compose exec app php bin/console app:cache:clear-tags --all

# Generate system statistics
docker-compose exec app php bin/console app:stats:generate
```

## Features

### Implemented Features

- ✅ Complete CRUD operations for Providers and Services
- ✅ Data validation and error handling
- ✅ Redis caching with automatic invalidation
- ✅ Comprehensive logging system
- ✅ OpenAPI/Swagger documentation
- ✅ Docker containerization
- ✅ Environment-based configuration
- ✅ Administrative commands for system management

### Upcoming Features

- 🔄 Comprehensive test suite
- 🔄 Authentication and authorization
- 🔄 Performance optimizations

## 12-Factor App Implementation

This application follows the 12-Factor App methodology:

1. **Codebase** ✅

   - Single codebase tracked in Git
   - Multiple deploys through Docker

2. **Dependencies** ✅

   - Explicitly declared in `composer.json`
   - Isolated through Docker containers

3. **Config** ✅

   - Environment variables in `.env` files
   - Sensitive data protected

4. **Backing Services** ✅

   - MySQL database
   - Redis for caching
   - Mailtrap for email notifications

5. **Build, Release, Run** ✅

   - Strict separation through Docker stages
   - Build process defined in Dockerfile
   - Runtime configuration in docker-compose.yml

6. **Processes** ✅

   - Application runs as stateless process
   - Shared-nothing architecture

7. **Port Binding** ✅

   - Service exported via port binding
   - Nginx handling HTTP requests

8. **Concurrency** ✅

   - Process model implemented through PHP-FPM
   - Horizontal scalability ready

9. **Disposability** ✅

   - Fast startup through Docker optimization
   - Graceful shutdown handling

10. **Dev/Prod Parity** ✅

    - Docker ensuring environment consistency
    - Same dependencies across environments

11. **Logs** ✅

    - Logging to stdout/stderr
    - Centralized logging through Docker

12. **Admin Processes** ✅
    - Admin tasks as one-off processes
    - Symfony commands for maintenance tasks

## Project Structure

```
provider-services-api/
├── src/
│   ├── Command/
│   │   ├── CacheClearTagsCommand.php
│   │   └── GenerateStatsCommand.php
│   ├── Controller/
│   │   ├── ProviderController.php
│   │   └── ServiceController.php
│   ├── Entity/
│   │   ├── Provider.php
│   │   └── Service.php
│   └── Repository/
│       ├── ProviderRepository.php
│       └── ServiceRepository.php
├── docker/
│   └── nginx/
│       └── default.conf
├── .env
├── .gitignore
├── composer.json
├── docker-compose.yml
└── Dockerfile
```

## Author

Jaouad BOUDDEHBINE
Student at IT-Akademy
Contact: j.bouddehbine@it-students.fr

## Project Context

This project was developed as part of the final exercise at IT-Akademy, demonstrating practical implementation of:

- Symfony 6.4 framework
- RESTful API design
- Docker containerization
- 12-Factor App methodology
- Modern PHP development practices

The implementation focuses on creating a scalable, maintainable API that follows industry best practices while meeting specific educational requirements.

## License

This project is created for educational purposes as part of IT-Akademy's curriculum.
