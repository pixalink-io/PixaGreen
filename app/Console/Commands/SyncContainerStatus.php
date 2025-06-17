<?php

namespace App\Console\Commands;

use App\Models\WhatsAppInstance;
use App\Services\DockerService;
use Illuminate\Console\Command;

class SyncContainerStatus extends Command
{
    protected $signature = 'whatsapp:sync-status';
    protected $description = 'Sync WhatsApp instance status with Docker containers';

    public function __construct(private DockerService $dockerService)
    {
        parent::__construct();
    }

    public function handle()
    {
        // Check if Docker is available before proceeding
        if (!$this->dockerService->isDockerRunning()) {
            $this->error('Docker daemon is not running. Please start Docker service.');
            $this->info('Commands to start Docker:');
            $this->info('- Linux/macOS: sudo systemctl start docker or start Docker Desktop');
            $this->info('- Windows: Start Docker Desktop');
            $this->info('- Verify with: docker info');
            return 1;
        }

        $instances = WhatsAppInstance::whereNotNull('container_id')->get();
        
        $this->info("Syncing status for {$instances->count()} instances...");
        
        foreach ($instances as $instance) {
            $dockerStatus = $this->dockerService->getContainerStatus($instance);
            $isHealthy = $this->dockerService->isContainerHealthy($instance);
            
            $newStatus = match ($dockerStatus) {
                'running' => $isHealthy ? 'running' : 'error',
                'exited' => 'stopped',
                'created' => 'creating',
                'docker_unavailable' => 'docker_unavailable',
                default => 'error'
            };
            
            if ($instance->status !== $newStatus) {
                $instance->update(['status' => $newStatus]);
                $this->line("Updated {$instance->name}: {$instance->status} -> {$newStatus}");
            }
        }
        
        $this->info('Status sync completed.');
        return 0;
    }
}
