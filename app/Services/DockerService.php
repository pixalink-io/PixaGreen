<?php

namespace App\Services;

use App\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DockerService
{
    private const IMAGE = 'aldinokemal2104/go-whatsapp-web-multidevice:latest';
    private const PORT_RANGE_START = 3000;
    private const PORT_RANGE_END = 3100;

    public function createContainer(WhatsAppInstance $instance): array
    {
        $this->validateDockerEnvironment();
        
        $containerName = "whatsapp-{$instance->id}";
        $port = $this->getAvailablePort();

        $command = [
            'docker', 'run',
            '-d',
            '--name', $containerName,
            '-p', "{$port}:3000",
            '-e', 'WEBHOOK=' . ($instance->webhook_url ?? ''),
            self::IMAGE
        ];

        $result = Process::run($command);

        if ($result->failed()) {
            Log::error("Failed to create container for instance {$instance->id}: " . $result->errorOutput());
            throw new \Exception("Failed to create container: " . $result->errorOutput());
        }

        $containerId = trim($result->output());

        $instance->update([
            'container_id' => $containerId,
            'port' => $port,
            'status' => 'running'
        ]);

        Log::info("Created container {$containerName} for instance {$instance->id} on port {$port}");

        return [
            'container_id' => $containerId,
            'port' => $port,
            'name' => $containerName
        ];
    }

    public function stopContainer(WhatsAppInstance $instance): bool
    {
        if (!$instance->container_id) {
            return false;
        }

        $result = Process::run(['docker', 'stop', $instance->container_id]);

        if ($result->successful()) {
            $instance->update(['status' => 'stopped']);
            return true;
        }

        return false;
    }

    public function startContainer(WhatsAppInstance $instance): bool
    {
        if (!$instance->container_id) {
            return false;
        }

        if (!$this->isDockerRunning()) {
            Log::error("Cannot start container: Docker daemon is not running");
            return false;
        }

        $result = Process::run(['docker', 'start', $instance->container_id]);

        if ($result->successful()) {
            $instance->update(['status' => 'running']);
            Log::info("Started container for instance {$instance->id}");
            return true;
        }

        Log::error("Failed to start container for instance {$instance->id}: " . $result->errorOutput());
        return false;
    }

    public function removeContainer(WhatsAppInstance $instance): bool
    {
        if (!$instance->container_id) {
            return false;
        }

        $this->stopContainer($instance);
        $result = Process::run(['docker', 'rm', $instance->container_id]);

        if ($result->successful()) {
            $instance->update([
                'container_id' => null,
                'status' => 'stopped'
            ]);
            return true;
        }

        return false;
    }

    public function getContainerStatus(WhatsAppInstance $instance): string
    {
        if (!$instance->container_id) {
            return 'not_created';
        }

        if (!$this->isDockerRunning()) {
            return 'docker_unavailable';
        }

        $result = Process::run([
            'docker', 'inspect',
            '--format', '{{.State.Status}}',
            $instance->container_id
        ]);

        if ($result->failed()) {
            return 'error';
        }

        return trim($result->output());
    }

    public function isContainerHealthy(WhatsAppInstance $instance): bool
    {
        try {
            $response = file_get_contents($instance->getApiUrl() . '/api/health');
            return $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getAvailablePort(): int
    {
        $usedPorts = WhatsAppInstance::whereNotNull('port')->pluck('port')->toArray();

        for ($port = self::PORT_RANGE_START; $port <= self::PORT_RANGE_END; $port++) {
            if (!in_array($port, $usedPorts) && !$this->isPortInUse($port)) {
                return $port;
            }
        }

        throw new \Exception('No available ports in range');
    }

    private function isPortInUse(int $port): bool
    {
        $connection = @fsockopen('localhost', $port);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }

    /**
     * Check if Docker daemon is running and accessible
     */
    public function isDockerRunning(): bool
    {
        $result = Process::run(['docker', 'info']);
        return $result->successful();
    }

    /**
     * Check if the WhatsApp image is available locally
     */
    public function isImageAvailable(): bool
    {
        $result = Process::run(['docker', 'images', '-q', self::IMAGE]);
        return $result->successful() && !empty(trim($result->output()));
    }

    /**
     * Pull the WhatsApp image if it's not available locally
     */
    public function pullImageIfMissing(): bool
    {
        if ($this->isImageAvailable()) {
            return true;
        }

        Log::info("Pulling WhatsApp image: " . self::IMAGE);
        $result = Process::run(['docker', 'pull', self::IMAGE]);
        
        if ($result->failed()) {
            Log::error("Failed to pull image " . self::IMAGE . ": " . $result->errorOutput());
            return false;
        }

        Log::info("Successfully pulled image: " . self::IMAGE);
        return true;
    }

    /**
     * Validate that Docker environment is ready for operations
     */
    public function validateDockerEnvironment(): void
    {
        if (!$this->isDockerRunning()) {
            throw new \Exception(
                "Docker daemon is not running. Please start Docker service:\n" .
                "- On Linux/macOS: sudo systemctl start docker or start Docker Desktop\n" .
                "- On Windows: Start Docker Desktop\n" .
                "- Verify with: docker info"
            );
        }

        if (!$this->pullImageIfMissing()) {
            throw new \Exception(
                "Failed to ensure WhatsApp image is available. Please check:\n" .
                "- Internet connection for pulling image\n" .
                "- Docker Hub accessibility\n" .
                "- Manual pull: docker pull " . self::IMAGE
            );
        }
    }

    /**
     * Get comprehensive Docker environment status
     */
    public function getDockerStatus(): array
    {
        return [
            'docker_running' => $this->isDockerRunning(),
            'image_available' => $this->isImageAvailable(),
            'image_name' => self::IMAGE,
            'port_range' => self::PORT_RANGE_START . '-' . self::PORT_RANGE_END,
        ];
    }
}
