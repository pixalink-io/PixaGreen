<?php

namespace App\Console\Commands;

use App\Services\DockerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class CheckDockerStatus extends Command
{
    protected $signature = 'whatsapp:check-docker {--start : Attempt to start Docker if not running}';

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

            if ($this->option('start')) {
                $this->info('Attempting to start Docker...');
                if ($this->startDocker()) {
                    $this->info('✅ Docker has been started successfully');
                    // Re-check status after starting
                    $status = $this->dockerService->getDockerStatus();
                } else {
                    $this->error('❌ Failed to start Docker automatically');
                    $this->showDockerStartInstructions();
                }
            } else {
                $this->showDockerStartInstructions();
                $this->info('💡 Use --start flag to attempt automatic Docker startup');
            }
            $this->newLine();
        }

        // Image availability
        if ($status['image_available']) {
            $this->info('✅ WhatsApp image is available locally');
        } else {
            $this->warn('⚠️  WhatsApp image is not available locally');
            $this->info('Image will be pulled automatically when creating containers');
            $this->info('Or manually pull with: docker pull '.$status['image_name']);
            $this->newLine();
        }

        // Configuration info
        $this->info('Configuration:');
        $this->line('- Image: '.$status['image_name']);
        $this->line('- Port range: '.$status['port_range']);

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

    private function startDocker(): bool
    {
        $os = PHP_OS_FAMILY;

        try {
            switch ($os) {
                case 'Darwin': // macOS
                    $this->info('Starting Docker Desktop on macOS...');
                    $result = Process::run(['open', '-a', 'Docker']);
                    if ($result->successful()) {
                        // Wait a bit for Docker to initialize
                        $this->info('Waiting for Docker to initialize...');
                        sleep(10);

                        // Check if Docker is now running with a timeout
                        for ($i = 0; $i < 30; $i++) {
                            if ($this->dockerService->isDockerRunning()) {
                                return true;
                            }
                            sleep(2);
                        }
                    }
                    break;

                case 'Linux':
                    $this->info('Starting Docker daemon on Linux...');
                    // Try systemctl first
                    $result = Process::run(['sudo', 'systemctl', 'start', 'docker']);
                    if ($result->successful()) {
                        sleep(3);

                        return $this->dockerService->isDockerRunning();
                    }

                    // Try service command as fallback
                    $result = Process::run(['sudo', 'service', 'docker', 'start']);
                    if ($result->successful()) {
                        sleep(3);

                        return $this->dockerService->isDockerRunning();
                    }
                    break;

                case 'Windows':
                    $this->info('Starting Docker Desktop on Windows...');
                    $result = Process::run(['powershell', '-Command', 'Start-Process', '"Docker Desktop"']);
                    if ($result->successful()) {
                        $this->info('Waiting for Docker Desktop to initialize...');
                        sleep(15);

                        // Check if Docker is now running with a timeout
                        for ($i = 0; $i < 30; $i++) {
                            if ($this->dockerService->isDockerRunning()) {
                                return true;
                            }
                            sleep(2);
                        }
                    }
                    break;

                default:
                    $this->warn("Automatic Docker startup not supported on {$os}");

                    return false;
            }
        } catch (\Exception $e) {
            $this->error('Error starting Docker: '.$e->getMessage());

            return false;
        }

        return false;
    }

    private function showDockerStartInstructions(): void
    {
        $this->warn('To start Docker:');
        $this->warn('- Linux/macOS: sudo systemctl start docker or start Docker Desktop');
        $this->warn('- Windows: Start Docker Desktop');
        $this->warn('- Verify with: docker info');
    }
}
