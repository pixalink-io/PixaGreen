# WhatsApp Manager

A Laravel application that automatically spins up Docker instances and forwards API calls to WhatsApp Web Multi-Device containers with path-based routing.

## Features

- **Auto Docker Management**: Automatically creates, starts, stops, and manages WhatsApp Web containers
- **Path-based API Routing**: Forward API calls to specific instances via `/api/instance/{id}/...`
- **Filament Admin Panel**: Web-based dashboard at `/admin` for managing instances
- **Real-time Monitoring**: Container health checks and status synchronization
- **RESTful API**: Complete API for programmatic instance management

## Quick Start

### Prerequisites

- Docker and Docker Compose
- PHP 8.3+
- Composer

### Installation

1. **Clone and setup**:
   ```bash
   cd whatsapp-manager
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Start the application**:
   ```bash
   docker-compose up -d
   ```

3. **Create admin user**:
   ```bash
   php artisan make:filament-user
   ```

4. **Access the application**:
   - Admin Panel: `http://localhost:8000/admin`
   - API: `http://localhost:8000/api`

## API Usage

### Instance Management

- `GET /api/whatsapp` - List all instances
- `POST /api/whatsapp` - Create new instance
- `GET /api/whatsapp/{id}` - Get instance details
- `PUT /api/whatsapp/{id}` - Update instance
- `DELETE /api/whatsapp/{id}` - Delete instance
- `POST /api/whatsapp/{id}/start` - Start instance
- `POST /api/whatsapp/{id}/stop` - Stop instance
- `GET /api/whatsapp/{id}/status` - Get instance status

### WhatsApp API Proxy

All WhatsApp Web API calls are proxied through:
```
/api/instance/{instance_id}/{whatsapp_endpoint}
```

Examples:
- `GET /api/instance/1/user/info` → forwards to container on port 3000
- `POST /api/instance/1/send/message` → sends message via instance 1
- `GET /api/instance/2/app/qr` → get QR code for instance 2

## Architecture

```
Laravel App (Port 8000)
├── Admin Panel (/admin) - Filament
├── API (/api/whatsapp) - Instance Management
├── Proxy (/api/instance/{id}/*) - WhatsApp API Proxy
└── Docker Containers (Ports 3000-3100)
    ├── WhatsApp Instance 1 (Port 3001)
    ├── WhatsApp Instance 2 (Port 3002)
    └── WhatsApp Instance N (Port 300N)
```

## Configuration

### Environment Variables

```env
# Database
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite

# WhatsApp Container Settings
WHATSAPP_PORT_RANGE_START=3000
WHATSAPP_PORT_RANGE_END=3100
WHATSAPP_IMAGE=aldinokemal2104/go-whatsapp-web-multidevice:latest
```

### Docker Configuration

The application requires access to Docker socket to manage containers:
```yaml
volumes:
  - /var/run/docker.sock:/var/run/docker.sock
```

## Monitoring

### Health Checks

The system continuously monitors:
- Container status (running/stopped/error)
- API health endpoints
- Last activity timestamps

### Status Sync

Run manual status sync:
```bash
php artisan whatsapp:sync-status
```

Automatic sync runs every 60 seconds via scheduler service.

## Development

### File Structure

```
app/
├── Models/WhatsAppInstance.php
├── Services/DockerService.php
├── Http/
│   ├── Controllers/WhatsAppInstanceController.php
│   └── Middleware/WhatsAppProxyMiddleware.php
├── Filament/Resources/WhatsAppInstanceResource.php
└── Console/Commands/SyncContainerStatus.php
```

### Key Components

1. **DockerService**: Manages Docker container lifecycle
2. **WhatsAppProxyMiddleware**: Routes API calls to correct container
3. **WhatsAppInstanceResource**: Filament admin interface
4. **SyncContainerStatus**: Monitors container health

## Security Notes

- Container management requires Docker socket access
- API endpoints should be secured with authentication in production
- Consider implementing rate limiting for proxy endpoints
- WhatsApp containers should use secure configurations

## Troubleshooting

### Common Issues

1. **Port conflicts**: Ensure ports 3000-3100 are available
2. **Docker permission**: Verify Docker socket permissions
3. **Container startup**: Check container logs for WhatsApp connection issues

### Logs

Check application logs:
```bash
tail -f storage/logs/laravel.log
```

Check container logs:
```bash
docker logs whatsapp-{instance_id}
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
