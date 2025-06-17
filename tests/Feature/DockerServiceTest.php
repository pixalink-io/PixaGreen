<?php

use App\Models\WhatsAppInstance;
use App\Services\DockerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->dockerService = new DockerService;
    $this->instance = WhatsAppInstance::create([
        'name' => 'Test Instance',
        'port' => 3001,
        'status' => 'stopped',
    ]);
});

describe('DockerService', function () {
    it('can check if docker is running', function () {
        Process::fake([
            'docker info' => Process::result('Docker info output', 0),
        ]);

        expect($this->dockerService->isDockerRunning())->toBeTrue();
    });

    it('returns false when docker is not running', function () {
        Process::fake([
            '*' => Process::result('', 'Docker not running', 1),
        ]);

        expect($this->dockerService->isDockerRunning())->toBeFalse();
    });

    it('can check if image is available', function () {
        Process::fake([
            '*' => Process::result('sha256:abc123', 0),
        ]);

        expect($this->dockerService->isImageAvailable())->toBeTrue();
    });

    it('returns false when image is not available', function () {
        Process::fake([
            '*' => Process::result('', 0),
        ]);

        expect($this->dockerService->isImageAvailable())->toBeFalse();
    });

    it('can pull missing image', function () {
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('', 0)) // image not available
                ->push(Process::result('Successfully pulled', 0)), // pull succeeds
        ]);

        Log::spy();

        expect($this->dockerService->pullImageIfMissing())->toBeTrue();
        Log::shouldHaveReceived('info')->with('Pulling WhatsApp image: aldinokemal2104/go-whatsapp-web-multidevice:latest');
        Log::shouldHaveReceived('info')->with('Successfully pulled image: aldinokemal2104/go-whatsapp-web-multidevice:latest');
    });

    it('returns true when image already exists', function () {
        Process::fake([
            '*' => Process::result('sha256:abc123', 0),
        ]);

        expect($this->dockerService->pullImageIfMissing())->toBeTrue();
    });

    it('handles pull image failure', function () {
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('', 0)) // image not available
                ->push(Process::result('', 'Pull failed', 1)), // pull fails
        ]);

        expect($this->dockerService->pullImageIfMissing())->toBeFalse();
    });

    it('validates docker environment successfully', function () {
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('Docker info output', 0)) // docker running
                ->push(Process::result('sha256:abc123', 0)), // image available
        ]);

        expect(fn () => $this->dockerService->validateDockerEnvironment())->not->toThrow(Exception::class);
    });

    it('throws exception when docker is not running', function () {
        Process::fake([
            '*' => Process::result('', 'Docker not running', 1),
        ]);

        expect(fn () => $this->dockerService->validateDockerEnvironment())
            ->toThrow(Exception::class, 'Docker daemon is not running');
    });

    it('throws exception when image pull fails', function () {
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('Docker info output', 0)) // docker running
                ->push(Process::result('', 0)) // image not available
                ->push(Process::result('', 'Pull failed', 1)), // pull fails
        ]);

        expect(fn () => $this->dockerService->validateDockerEnvironment())
            ->toThrow(Exception::class, 'Failed to ensure WhatsApp image is available');
    });

    it('gets container status when container exists', function () {
        $this->instance->update(['container_id' => 'container123']);

        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('Docker info output', 0)) // docker running check
                ->push(Process::result('running', 0)), // container inspect
        ]);

        expect($this->dockerService->getContainerStatus($this->instance))->toBe('running');
    });

    it('returns not_created when container_id is null', function () {
        expect($this->dockerService->getContainerStatus($this->instance))->toBe('not_created');
    });

    it('returns docker_unavailable when docker is not running', function () {
        $this->instance->update(['container_id' => 'container123']);

        Process::fake([
            '*' => Process::result('', 'Docker not running', 1),
        ]);

        expect($this->dockerService->getContainerStatus($this->instance))->toBe('docker_unavailable');
    });

    it('returns error when container inspect fails', function () {
        $this->instance->update(['container_id' => 'container123']);

        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('Docker info output', 0)) // docker running
                ->push(Process::result('', 'Inspect failed', 1)), // inspect fails
        ]);

        expect($this->dockerService->getContainerStatus($this->instance))->toBe('error');
    });

    it('can stop container', function () {
        $this->instance->update(['container_id' => 'container123', 'status' => 'running']);

        Process::fake([
            '*' => Process::result('container123', 0),
        ]);

        expect($this->dockerService->stopContainer($this->instance))->toBeTrue();
        expect($this->instance->fresh()->status)->toBe('stopped');
    });

    it('returns false when stopping container without container_id', function () {
        expect($this->dockerService->stopContainer($this->instance))->toBeFalse();
    });

    it('returns false when stop command fails', function () {
        $this->instance->update(['container_id' => 'container123']);

        Process::fake([
            '*' => Process::result('', 'Stop failed', 1),
        ]);

        expect($this->dockerService->stopContainer($this->instance))->toBeFalse();
    });

    it('can start container', function () {
        $this->instance->update(['container_id' => 'container123', 'status' => 'stopped']);

        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('Docker info output', 0)) // docker running check
                ->push(Process::result('container123', 0)), // start command
        ]);

        expect($this->dockerService->startContainer($this->instance))->toBeTrue();
        expect($this->instance->fresh()->status)->toBe('running');
    });

    it('returns false when starting container without container_id', function () {
        expect($this->dockerService->startContainer($this->instance))->toBeFalse();
    });

    it('returns false when starting container and docker is not running', function () {
        $this->instance->update(['container_id' => 'container123']);

        Process::fake([
            '*' => Process::result('', 'Docker not running', 1),
        ]);

        expect($this->dockerService->startContainer($this->instance))->toBeFalse();
    });

    it('returns false when start command fails', function () {
        $this->instance->update(['container_id' => 'container123']);

        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('Docker info output', 0)) // docker running
                ->push(Process::result('', 'Start failed', 1)), // start fails
        ]);

        expect($this->dockerService->startContainer($this->instance))->toBeFalse();
    });

    it('can remove container', function () {
        $this->instance->update(['container_id' => 'container123', 'status' => 'running']);

        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('container123', 0)) // stop
                ->push(Process::result('container123', 0)), // remove
        ]);

        expect($this->dockerService->removeContainer($this->instance))->toBeTrue();

        $fresh = $this->instance->fresh();
        expect($fresh->container_id)->toBeNull();
        expect($fresh->status)->toBe('stopped');
    });

    it('returns false when removing container without container_id', function () {
        expect($this->dockerService->removeContainer($this->instance))->toBeFalse();
    });

    it('returns false when remove command fails', function () {
        $this->instance->update(['container_id' => 'container123']);

        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('container123', 0)) // stop succeeds
                ->push(Process::result('', 'Remove failed', 1)), // remove fails
        ]);

        expect($this->dockerService->removeContainer($this->instance))->toBeFalse();
    });

    it('checks container health via api', function () {
        $this->instance->update(['port' => 3001]);

        // Mock file_get_contents for health check
        expect($this->dockerService->isContainerHealthy($this->instance))->toBeFalse();
    });

    it('gets comprehensive docker status', function () {
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('Docker info output', 0)) // docker running
                ->push(Process::result('sha256:abc123', 0)), // image available
        ]);

        $status = $this->dockerService->getDockerStatus();

        expect($status)->toHaveKeys(['docker_running', 'image_available', 'image_name', 'port_range']);
        expect($status['docker_running'])->toBeTrue();
        expect($status['image_available'])->toBeTrue();
        expect($status['image_name'])->toBe('aldinokemal2104/go-whatsapp-web-multidevice:latest');
        expect($status['port_range'])->toBe('3000-3100');
    });

    it('creates container successfully', function () {
        // Mock available port by having no existing instances
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('Docker info output', 0)) // docker validation
                ->push(Process::result('sha256:abc123', 0)) // image check
                ->push(Process::result('container123', 0)), // container creation
        ]);

        $result = $this->dockerService->createContainer($this->instance);

        expect($result)->toHaveKeys(['container_id', 'port', 'name']);
        expect($result['container_id'])->toBe('container123');
        expect($result['port'])->toBeGreaterThan(2999); // Port should be allocated from range
        expect($result['name'])->toBe("whatsapp-{$this->instance->id}");

        $fresh = $this->instance->fresh();
        expect($fresh->container_id)->toBe('container123');
        expect($fresh->port)->toBeGreaterThan(2999); // Port should be allocated from range
        expect($fresh->status)->toBe('running');
    });

    it('throws exception when container creation fails', function () {
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('Docker info output', 0)) // docker validation
                ->push(Process::result('sha256:abc123', 0)) // image check
                ->push(Process::result('', 'Creation failed', 1)), // container creation fails
        ]);

        expect(fn () => $this->dockerService->createContainer($this->instance))
            ->toThrow(Exception::class, 'Failed to create container: Creation failed');
    });
});
