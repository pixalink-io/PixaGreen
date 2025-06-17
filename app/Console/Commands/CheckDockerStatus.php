<?php

namespace App\Console\Commands;

use App\Services\DockerService;
use Illuminate\Console\Command;

class CheckDockerStatus extends Command
{
    protected $signature = 'whatsapp:check-docker';
    protected $description = 'Check Docker daemon status and WhatsApp image availability';

    public function __construct(private DockerService $dockerService)
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Checking Docker environment status...');
        $this->newLine();

        $status = $this->dockerService->getDockerStatus();

        // Docker daemon status
        if ($status['docker_running']) {
            $this->info('✅ Docker daemon is running');
        } else {
            $this->error('❌ Docker daemon is not running');
            $this->warn('To start Docker:');
            $this->warn('- Linux/macOS: sudo systemctl start docker or start Docker Desktop');
            $this->warn('- Windows: Start Docker Desktop');
            $this->warn('- Verify with: docker info');
            $this->newLine();
        }

        // Image availability
        if ($status['image_available']) {
            $this->info('✅ WhatsApp image is available locally');
        } else {
            $this->warn('⚠️  WhatsApp image is not available locally');
            $this->info('Image will be pulled automatically when creating containers');
            $this->info('Or manually pull with: docker pull ' . $status['image_name']);
            $this->newLine();
        }

        // Configuration info
        $this->info('Configuration:');
        $this->line('- Image: ' . $status['image_name']);
        $this->line('- Port range: ' . $status['port_range']);

        $this->newLine();

        if ($status['docker_running'] && $status['image_available']) {
            $this->info('🎉 Docker environment is ready for WhatsApp instances!');
            return 0;
        } elseif ($status['docker_running']) {
            $this->warn('⚠️  Docker is running but image needs to be pulled');
            return 0;
        } else {
            $this->error('❌ Docker environment requires attention');
            return 1;
        }
    }
}