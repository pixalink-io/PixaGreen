# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## WhatsApp Manager - Laravel Application

This is a Laravel 12 application that manages WhatsApp Web Multi-Device containers through Docker, providing a Filament admin panel and API proxy system.

## Common Development Commands

### Setup and Development
```bash
# Install dependencies and start full development environment
composer dev

# Manual setup (alternative to composer dev)
php artisan serve
php artisan queue:listen --tries=1
npm run dev

# Build frontend assets
npm run build

# Run tests
composer test
```

### WhatsApp Instance Management
```bash
# Sync container status with database
php artisan whatsapp:sync-status

# Create admin user for Filament panel
php artisan make:filament-user
```

### Docker Operations
```bash
# Start the application stack
docker-compose up -d

# View container logs
docker logs whatsapp-{instance_id}
```

## Architecture Overview

### Core System
- **Laravel Framework**: Main application (Port 8000)
- **Filament Admin Panel**: Web GUI at `/admin` for managing WhatsApp instances
- **Docker Integration**: Automatically manages WhatsApp Web containers (Ports 3000-3100)
- **API Proxy System**: Routes WhatsApp API calls to appropriate containers

### Request Flow
```
Client Request → Laravel App → WhatsAppProxyMiddleware → Docker Container (WhatsApp Web)
```

### Key Components

#### Models
- **WhatsAppInstance** (`app/Models/WhatsAppInstance.php`): Represents WhatsApp instances with container metadata

#### Services  
- **DockerService** (`app/Services/DockerService.php`): Manages Docker container lifecycle
  - Container creation/start/stop/removal
  - Port allocation (3000-3100 range)
  - Health checks and status monitoring

#### Middleware
- **WhatsAppProxyMiddleware** (`app/Http/Middleware/WhatsAppProxyMiddleware.php`): Routes `/api/instance/{id}/*` calls to container ports

#### Commands
- **SyncContainerStatus** (`app/Console/Commands/SyncContainerStatus.php`): Monitors container health and syncs database status

#### Filament Resources
- **WhatsAppInstanceResource** (`app/Filament/Resources/WhatsAppInstanceResource.php`): Admin panel interface for instance management

## API Structure

### Instance Management API
- `GET/POST /api/whatsapp` - List/create instances
- `GET/PUT/DELETE /api/whatsapp/{id}` - Instance CRUD operations  
- `POST /api/whatsapp/{id}/start` - Start instance
- `POST /api/whatsapp/{id}/stop` - Stop instance
- `GET /api/whatsapp/{id}/status` - Get status

### WhatsApp API Proxy
- `/api/instance/{id}/*` - Forwards all calls to WhatsApp container on port 300X
- Example: `/api/instance/1/send/message` → `http://localhost:3001/send/message`

## Docker Configuration

### WhatsApp Containers
- **Base Image**: `aldinokemal2104/go-whatsapp-web-multidevice:latest`
- **Port Range**: 3000-3100 (allocated dynamically)
- **Container Names**: `whatsapp-{instance_id}`
- **Environment**: Webhook URL configurable per instance

### Requirements
- Docker socket access: `/var/run/docker.sock:/var/run/docker.sock`
- Ports 3000-3100 available for WhatsApp containers
- Port 8000 for Laravel application

## Database

### Main Table: `whatsapp_instances`
- `id`, `name`, `container_id`, `port`, `status`, `webhook_url`, `config`, `last_activity`
- SQLite by default (`database/database.sqlite`)

## Development Notes

### Container Lifecycle
1. **Creation**: DockerService allocates port and creates container
2. **Management**: Start/stop operations update database status
3. **Monitoring**: Scheduler runs status sync every 60 seconds
4. **Health Checks**: API endpoint `/api/health` on each container

### Status Values
- `running` - Container running and healthy
- `stopped` - Container stopped
- `creating` - Container being created
- `error` - Container failed or unhealthy

### Port Management
- DockerService automatically finds available ports in 3000-3100 range
- Port conflicts prevented by checking both database and system ports