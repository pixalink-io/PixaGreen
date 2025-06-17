<?php

use App\Models\WhatsAppInstance;
use App\Services\DockerService;

beforeEach(function () {
    $this->dockerService = $this->mock(DockerService::class);
});

describe('SyncContainerStatus Command', function () {
    it('exits early when docker is not running', function () {
        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->once()
            ->andReturn(false);

        $this->artisan('whatsapp:sync-status')
            ->expectsOutputToContain('Docker daemon is not running. Please start Docker service.')
            ->expectsOutputToContain('Commands to start Docker:')
            ->expectsOutputToContain('- Linux/macOS: sudo systemctl start docker or start Docker Desktop')
            ->expectsOutputToContain('- Windows: Start Docker Desktop')
            ->expectsOutputToContain('- Verify with: docker info')
            ->assertExitCode(1);
    });

    it('syncs status for instances with containers', function () {
        // Create instances with container IDs
        $instance1 = WhatsAppInstance::create([
            'name' => 'Instance 1',
            'container_id' => 'container1',
            'status' => 'stopped',
        ]);

        $instance2 = WhatsAppInstance::create([
            'name' => 'Instance 2',
            'container_id' => 'container2',
            'status' => 'error',
        ]);

        // Create instance without container ID (should be ignored)
        WhatsAppInstance::create([
            'name' => 'Instance 3',
            'container_id' => null,
            'status' => 'stopped',
        ]);

        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->once()
            ->andReturn(true);

        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($instance1)
            ->once()
            ->andReturn('running');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($instance1)
            ->once()
            ->andReturn(true);

        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($instance2)
            ->once()
            ->andReturn('running');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($instance2)
            ->once()
            ->andReturn(true);

        $this->artisan('whatsapp:sync-status')
            ->expectsOutputToContain('Syncing status for 2 instances...')
            ->expectsOutputToContain('Updated Instance 1: stopped -> running')
            ->expectsOutputToContain('Updated Instance 2: error -> running')
            ->expectsOutputToContain('Status sync completed.')
            ->assertExitCode(0);

        // Verify database was updated
        expect($instance1->fresh()->status)->toBe('running');
        expect($instance2->fresh()->status)->toBe('running');
    });

    it('handles different container statuses correctly', function () {
        $runningInstance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'container_id' => 'running_container',
            'status' => 'running',
        ]);

        $exitedInstance = WhatsAppInstance::create([
            'name' => 'Exited Instance',
            'container_id' => 'exited_container',
            'status' => 'running',
        ]);

        $createdInstance = WhatsAppInstance::create([
            'name' => 'Created Instance',
            'container_id' => 'created_container',
            'status' => 'error',
        ]);

        $errorInstance = WhatsAppInstance::create([
            'name' => 'Error Instance',
            'container_id' => 'error_container',
            'status' => 'running',
        ]);

        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->once()
            ->andReturn(true);

        // Running container with healthy status
        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($runningInstance)
            ->once()
            ->andReturn('running');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($runningInstance)
            ->once()
            ->andReturn(true);

        // Exited container
        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($exitedInstance)
            ->once()
            ->andReturn('exited');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($exitedInstance)
            ->once()
            ->andReturn(false);

        // Created container
        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($createdInstance)
            ->once()
            ->andReturn('created');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($createdInstance)
            ->once()
            ->andReturn(false);

        // Error container
        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($errorInstance)
            ->once()
            ->andReturn('unknown');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($errorInstance)
            ->once()
            ->andReturn(false);

        $this->artisan('whatsapp:sync-status')
            ->expectsOutputToContain('Updated Exited Instance: running -> stopped')
            ->expectsOutputToContain('Updated Created Instance: error -> creating')
            ->expectsOutputToContain('Updated Error Instance: running -> error')
            ->assertExitCode(0);

        // Verify status updates
        expect($runningInstance->fresh()->status)->toBe('running');
        expect($exitedInstance->fresh()->status)->toBe('stopped');
        expect($createdInstance->fresh()->status)->toBe('creating');
        expect($errorInstance->fresh()->status)->toBe('error');
    });

    it('handles unhealthy running containers', function () {
        $unhealthyInstance = WhatsAppInstance::create([
            'name' => 'Unhealthy Instance',
            'container_id' => 'unhealthy_container',
            'status' => 'running',
        ]);

        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->once()
            ->andReturn(true);

        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($unhealthyInstance)
            ->once()
            ->andReturn('running');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($unhealthyInstance)
            ->once()
            ->andReturn(false);

        $this->artisan('whatsapp:sync-status')
            ->expectsOutputToContain('Updated Unhealthy Instance: running -> error')
            ->assertExitCode(0);

        expect($unhealthyInstance->fresh()->status)->toBe('error');
    });

    it('handles docker unavailable status', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'container_id' => 'test_container',
            'status' => 'running',
        ]);

        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->once()
            ->andReturn(true);

        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($instance)
            ->once()
            ->andReturn('docker_unavailable');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($instance)
            ->once()
            ->andReturn(false);

        $this->artisan('whatsapp:sync-status')
            ->expectsOutputToContain('Updated Test Instance: running -> docker_unavailable')
            ->assertExitCode(0);

        expect($instance->fresh()->status)->toBe('docker_unavailable');
    });

    it('skips instances that do not need status updates', function () {
        $runningInstance = WhatsAppInstance::create([
            'name' => 'Already Running',
            'container_id' => 'running_container',
            'status' => 'running',
        ]);

        $stoppedInstance = WhatsAppInstance::create([
            'name' => 'Already Stopped',
            'container_id' => 'stopped_container',
            'status' => 'stopped',
        ]);

        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->once()
            ->andReturn(true);

        // Running instance stays running
        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($runningInstance)
            ->once()
            ->andReturn('running');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($runningInstance)
            ->once()
            ->andReturn(true);

        // Stopped instance stays stopped
        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($stoppedInstance)
            ->once()
            ->andReturn('exited');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($stoppedInstance)
            ->once()
            ->andReturn(false);

        $this->artisan('whatsapp:sync-status')
            ->expectsOutputToContain('Syncing status for 2 instances...')
            ->expectsOutputToContain('Status sync completed.')
            ->doesntExpectOutputToContain('Updated Already Running')
            ->doesntExpectOutputToContain('Updated Already Stopped')
            ->assertExitCode(0);

        // Verify status unchanged
        expect($runningInstance->fresh()->status)->toBe('running');
        expect($stoppedInstance->fresh()->status)->toBe('stopped');
    });

    it('handles empty instance list', function () {
        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->once()
            ->andReturn(true);

        $this->artisan('whatsapp:sync-status')
            ->expectsOutputToContain('Syncing status for 0 instances...')
            ->expectsOutputToContain('Status sync completed.')
            ->assertExitCode(0);
    });

    it('only processes instances with container ids', function () {
        // Create instance without container_id
        WhatsAppInstance::create([
            'name' => 'No Container',
            'container_id' => null,
            'status' => 'stopped',
        ]);

        // Create instance with empty string container_id
        WhatsAppInstance::create([
            'name' => 'Empty Container',
            'container_id' => '',
            'status' => 'stopped',
        ]);

        // Create instance with valid container_id
        $validInstance = WhatsAppInstance::create([
            'name' => 'Valid Container',
            'container_id' => 'valid_container',
            'status' => 'stopped',
        ]);

        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->once()
            ->andReturn(true);

        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->with($validInstance)
            ->once()
            ->andReturn('running');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->with($validInstance)
            ->once()
            ->andReturn(true);

        $this->artisan('whatsapp:sync-status')
            ->expectsOutputToContain('Syncing status for 1 instances...')
            ->assertExitCode(0);
    });
});
