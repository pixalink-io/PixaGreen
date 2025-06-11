<?php

namespace App\Services;

use App\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class DockerService
{
    private const IMAGE = 'aldinokemal2104/go-whatsapp-web-multidevice:latest';
    private const PORT_RANGE_START = 3000;
    private const PORT_RANGE_END = 3100;

    public function createContainer(WhatsAppInstance $instance): array
    {
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
            throw new \Exception("Failed to create container: " . $result->errorOutput());
        }

        $containerId = trim($result->output());

        $instance->update([
            'container_id' => $containerId,
            'port' => $port,
            'status' => 'running'
        ]);

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

        $result = Process::run(['docker', 'start', $instance->container_id]);

        if ($result->successful()) {
            $instance->update(['status' => 'running']);
            return true;
        }

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
}
