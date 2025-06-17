# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## WhatsApp Manager - Laravel Application

This is a Laravel 12 application that manages WhatsApp Web Multi-Device containers through Docker, providing a Filament admin panel and API proxy system. The goal is to evolve this into a comprehensive WhatsApp Business API service that can compete with services like Green API.

## Project Vision

**Current State**: Container management system for WhatsApp Web instances
**Target Goal**: Complete WhatsApp Business API service with developer-friendly features
**Competitive Focus**: Replace Green API with better pricing, documentation, and developer experience

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
# Check Docker daemon and image status
php artisan whatsapp:check-docker

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
  - Docker daemon validation and image management
  - Automatic WhatsApp image pulling

#### Middleware
- **WhatsAppProxyMiddleware** (`app/Http/Middleware/WhatsAppProxyMiddleware.php`): Routes `/api/instance/{id}/*` calls to container ports

#### Commands
- **SyncContainerStatus** (`app/Console/Commands/SyncContainerStatus.php`): Monitors container health and syncs database status
- **CheckDockerStatus** (`app/Console/Commands/CheckDockerStatus.php`): Validates Docker environment and image availability

#### Filament Resources
- **WhatsAppInstanceResource** (`app/Filament/Resources/WhatsAppInstanceResource.php`): Admin panel interface for instance management

## API Structure

### Instance Management API
- `GET/POST /api/whatsapp` - List/create instances
- `GET/PUT/DELETE /api/whatsapp/{id}` - Instance CRUD operations  
- `POST /api/whatsapp/{id}/start` - Start instance
- `POST /api/whatsapp/{id}/stop` - Stop instance
- `GET /api/whatsapp/{id}/status` - Get status

### WhatsApp API Proxy (Current)
- `/api/instance/{id}/*` - Forwards all calls to WhatsApp container on port 300X
- Example: `/api/instance/1/send/message` → `http://localhost:3001/send/message`

### Planned WhatsApp API Endpoints
**Authentication & Session Management**
- `POST /api/whatsapp/{id}/login` - Generate QR code or pairing code
- `POST /api/whatsapp/{id}/logout` - Disconnect from WhatsApp
- `GET /api/whatsapp/{id}/status` - Get connection status
- `POST /api/whatsapp/{id}/reconnect` - Reconnect to WhatsApp

**Message Operations**
- `POST /api/whatsapp/{id}/send/text` - Send text message
- `POST /api/whatsapp/{id}/send/image` - Send image with caption
- `POST /api/whatsapp/{id}/send/file` - Send document/file
- `POST /api/whatsapp/{id}/send/audio` - Send audio message
- `POST /api/whatsapp/{id}/send/video` - Send video message
- `POST /api/whatsapp/{id}/send/location` - Send location
- `POST /api/whatsapp/{id}/send/poll` - Send poll message
- `GET /api/whatsapp/{id}/messages` - Get message history

**Contact & Group Management**
- `GET /api/whatsapp/{id}/contacts` - List contacts
- `POST /api/whatsapp/{id}/contacts/check` - Validate phone number
- `GET /api/whatsapp/{id}/groups` - List groups
- `POST /api/whatsapp/{id}/groups` - Create group
- `POST /api/whatsapp/{id}/groups/{groupId}/participants` - Add/remove participants

**Webhook Configuration**
- `POST /api/whatsapp/{id}/webhooks` - Configure webhook URL
- `GET /api/whatsapp/{id}/webhooks` - Get webhook settings

## Docker Configuration

### WhatsApp Containers
- **Base Image**: `aldinokemal2104/go-whatsapp-web-multidevice:latest`
- **Port Range**: 3000-3100 (allocated dynamically)
- **Container Names**: `whatsapp-{instance_id}`
- **Environment**: Webhook URL configurable per instance

### Container API Capabilities
The WhatsApp containers provide a complete REST API (v6.0.0) with these endpoint groups:
- **App Endpoints**: Login/logout, pairing codes, device management
- **User Endpoints**: Profile info, avatar, privacy settings, contacts/groups
- **Send Endpoints**: Text, images, audio, files, videos, contacts, links, locations, polls
- **Message Endpoints**: Revoke, react, edit, mark as read, star messages
- **Group Endpoints**: Create/manage groups, participants, permissions
- **Webhook Support**: Real-time message notifications

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
- `docker_unavailable` - Docker daemon not accessible

### Port Management
- DockerService automatically finds available ports in 3000-3100 range
- Port conflicts prevented by checking both database and system ports
