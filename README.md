# Provider Services API

A RESTful API built with Symfony 6.4 following the 12-Factor App methodology for managing service providers and their services.

## Project Overview

This project is an implementation of a provider-services management system that allows:

- Management of service providers (CRUD operations)
- Management of services offered by providers (CRUD operations)
- Proper relationship handling between providers and services
- Data persistence using MySQL
- Cache management with Redis
- Email notifications for provider updates

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

3. Access the application

```bash
# The API will be available at:
http://localhost:8000/api
```

## API Endpoints

### Providers

- `GET /api/providers` - Get all providers
- `POST /api/providers` - Create a new provider
- `PUT /api/providers/{id}` - Update a provider
- `DELETE /api/providers/{id}` - Delete a provider

### Services

- `GET /api/services` - Get all services
- `POST /api/services` - Create a new service
- `PUT /api/services/{id}` - Update a service
- `DELETE /api/services/{id}` - Delete a service

## 12-Factor App Implementation

This application follows the 12-Factor App methodology:

1. **Codebase**

   - Single codebase tracked in Git
   - Multiple deploys through Docker

2. **Dependencies**

   - Explicitly declared in `composer.json`
   - Isolated through Docker containers

3. **Config**

   - Environment variables in `.env` files
   - Sensitive data protected and not in version control

4. **Backing Services**

   - MySQL database treated as attached resource
   - Redis for caching
   - Mailtrap for email notifications in testing

5. **Build, Release, Run**

   - Strict separation through Docker stages
   - Build process defined in Dockerfile
   - Runtime configuration in docker-compose.yml

6. **Processes**

   - Application runs as stateless process
   - Shared-nothing architecture

7. **Port Binding**

   - Service exported via port binding
   - Nginx handling HTTP requests

8. **Concurrency**

   - Process model implemented through PHP-FPM
   - Horizontal scalability ready

9. **Disposability**

   - Fast startup through Docker optimization
   - Graceful shutdown handling

10. **Dev/Prod Parity**

    - Docker ensuring environment consistency
    - Same dependencies across environments

11. **Logs**

    - Logging to stdout/stderr
    - Centralized logging through Docker

12. **Admin Processes**
    - Admin tasks as one-off processes
    - Symfony commands for maintenance tasks

## Project Structure

```
provider-services-api/
├── src/
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

## Running Tests

```bash
docker-compose exec app php bin/phpunit
```

## Development Commands

```bash
# Create database
docker-compose exec app php bin/console doctrine:database:create

# Run migrations
docker-compose exec app php bin/console doctrine:migrations:migrate

# Create new migration
docker-compose exec app php bin/console make:migration
```

## Git Strategy

### Initial Development Phase

The initial project setup and basic API implementation were developed on the main branch, including:

- Project initialization with Symfony 6.4
- Docker configuration
- Basic entity structure (Provider and Service)
- Initial API endpoints implementation
- Relationship handling and serialization

### Current Development Strategy - GitFlow Lite

From the implementation of additional features onwards, we adopted a more structured branching strategy:

```
main
  └── dev
       ├── feat/redis-cache
       ├── feat/input-validation
       └── feat/error-handling
```

#### Branch Structure

- `main`: Production-ready code
- `dev`: Development integration branch
- `feat/*`: Feature-specific branches

#### Workflow

1. Create feature branch from dev
2. Develop and test feature
3. Merge feature to dev
4. Test integration in dev
5. When release-ready, merge dev to main

This transition in Git strategy demonstrates the evolution from a basic setup to a more professional development workflow, better suited for team collaboration and production deployment.

## Contributing

This project was created as part of my studies at IT-Akademy. While it's primarily for educational purposes, feedback and suggestions are welcome.

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
