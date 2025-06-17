<?php

use App\Models\WhatsAppInstance;
use App\Services\DockerService;

beforeEach(function () {
    $this->dockerService = $this->mock(DockerService::class);
});

describe('WhatsAppInstance API', function () {
    it('can list all instances', function () {
        WhatsAppInstance::create(['name' => 'Instance 1', 'port' => 3001]);
        WhatsAppInstance::create(['name' => 'Instance 2', 'port' => 3002]);
        WhatsAppInstance::create(['name' => 'Instance 3', 'port' => 3003]);

        $response = $this->getJson('/api/whatsapp');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    });

    it('can create a new instance', function () {
        $this->dockerService
            ->shouldReceive('createContainer')
            ->once()
            ->andReturn([
                'container_id' => 'container123',
                'port' => 3001,
                'name' => 'whatsapp-1',
            ]);

        $response = $this->postJson('/api/whatsapp', [
            'name' => 'Test Instance',
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'instance' => [
                    'id',
                    'name',
                    'webhook_url',
                    'status',
                    'created_at',
                    'updated_at',
                ],
                'container' => [
                    'container_id',
                    'port',
                    'name',
                ],
            ]);

        $this->assertDatabaseHas('whatsapp_instances', [
            'name' => 'Test Instance',
            'webhook_url' => 'https://example.com/webhook',
            'status' => 'creating',
        ]);
    });

    it('validates required fields when creating instance', function () {
        $response = $this->postJson('/api/whatsapp', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating instance', function () {
        WhatsAppInstance::create(['name' => 'Existing Instance', 'port' => 3001]);

        $response = $this->postJson('/api/whatsapp', [
            'name' => 'Existing Instance',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('validates webhook url format', function () {
        $response = $this->postJson('/api/whatsapp', [
            'name' => 'Test Instance',
            'webhook_url' => 'invalid-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['webhook_url']);
    });

    it('handles docker service exceptions during creation', function () {
        $this->dockerService
            ->shouldReceive('createContainer')
            ->once()
            ->andThrow(new Exception('Docker service error'));

        $response = $this->postJson('/api/whatsapp', [
            'name' => 'Test Instance',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to create instance',
                'message' => 'Docker service error',
            ]);
    });

    it('can show a specific instance', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'status' => 'running',
        ]);

        $response = $this->getJson("/api/whatsapp/{$instance->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $instance->id,
                'name' => 'Test Instance',
                'status' => 'running',
            ]);
    });

    it('returns 404 for non-existent instance', function () {
        $response = $this->getJson('/api/whatsapp/999');

        $response->assertStatus(404);
    });

    it('can update an instance', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Original Name',
            'port' => 3001,
            'webhook_url' => 'https://original.com/webhook',
        ]);

        $response = $this->putJson("/api/whatsapp/{$instance->id}", [
            'name' => 'Updated Name',
            'webhook_url' => 'https://updated.com/webhook',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Name',
                'webhook_url' => 'https://updated.com/webhook',
            ]);

        $this->assertDatabaseHas('whatsapp_instances', [
            'id' => $instance->id,
            'name' => 'Updated Name',
            'webhook_url' => 'https://updated.com/webhook',
        ]);
    });

    it('validates unique name when updating instance', function () {
        $instance1 = WhatsAppInstance::create(['name' => 'Instance 1', 'port' => 3001]);
        $instance2 = WhatsAppInstance::create(['name' => 'Instance 2', 'port' => 3002]);

        $response = $this->putJson("/api/whatsapp/{$instance2->id}", [
            'name' => 'Instance 1',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating instance with same name', function () {
        $instance = WhatsAppInstance::create(['name' => 'Test Instance', 'port' => 3001]);

        $response = $this->putJson("/api/whatsapp/{$instance->id}", [
            'name' => 'Test Instance',
        ]);

        $response->assertStatus(200);
    });

    it('can delete an instance', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'container_id' => 'container123',
        ]);

        $this->dockerService
            ->shouldReceive('removeContainer')
            ->once()
            ->with($instance)
            ->andReturn(true);

        $response = $this->deleteJson("/api/whatsapp/{$instance->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Instance deleted successfully',
            ]);

        $this->assertDatabaseMissing('whatsapp_instances', [
            'id' => $instance->id,
        ]);
    });

    it('handles docker service exceptions during deletion', function () {
        $instance = WhatsAppInstance::create(['name' => 'Test Instance', 'port' => 3001]);

        $this->dockerService
            ->shouldReceive('removeContainer')
            ->once()
            ->andThrow(new Exception('Docker service error'));

        $response = $this->deleteJson("/api/whatsapp/{$instance->id}");

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to delete instance',
                'message' => 'Docker service error',
            ]);
    });

    it('can start an instance', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'container_id' => 'container123',
            'status' => 'stopped',
        ]);

        $this->dockerService
            ->shouldReceive('startContainer')
            ->once()
            ->with($instance)
            ->andReturn(true);

        $response = $this->postJson("/api/whatsapp/{$instance->id}/start");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Instance started successfully',
            ]);
    });

    it('handles start failure', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'container_id' => 'container123',
        ]);

        $this->dockerService
            ->shouldReceive('startContainer')
            ->once()
            ->with($instance)
            ->andReturn(false);

        $response = $this->postJson("/api/whatsapp/{$instance->id}/start");

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to start instance',
            ]);
    });

    it('handles start exceptions', function () {
        $instance = WhatsAppInstance::create(['name' => 'Test Instance', 'port' => 3001]);

        $this->dockerService
            ->shouldReceive('startContainer')
            ->once()
            ->andThrow(new Exception('Docker start error'));

        $response = $this->postJson("/api/whatsapp/{$instance->id}/start");

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to start instance',
                'message' => 'Docker start error',
            ]);
    });

    it('can stop an instance', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'container_id' => 'container123',
            'status' => 'running',
        ]);

        $this->dockerService
            ->shouldReceive('stopContainer')
            ->once()
            ->with($instance)
            ->andReturn(true);

        $response = $this->postJson("/api/whatsapp/{$instance->id}/stop");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Instance stopped successfully',
            ]);
    });

    it('handles stop failure', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'container_id' => 'container123',
        ]);

        $this->dockerService
            ->shouldReceive('stopContainer')
            ->once()
            ->with($instance)
            ->andReturn(false);

        $response = $this->postJson("/api/whatsapp/{$instance->id}/stop");

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to stop instance',
            ]);
    });

    it('handles stop exceptions', function () {
        $instance = WhatsAppInstance::create(['name' => 'Test Instance', 'port' => 3001]);

        $this->dockerService
            ->shouldReceive('stopContainer')
            ->once()
            ->andThrow(new Exception('Docker stop error'));

        $response = $this->postJson("/api/whatsapp/{$instance->id}/stop");

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to stop instance',
                'message' => 'Docker stop error',
            ]);
    });

    it('can get instance status', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'status' => 'running',
            'port' => 3001,
            'last_activity' => now(),
        ]);

        $this->dockerService
            ->shouldReceive('getContainerStatus')
            ->once()
            ->with($instance)
            ->andReturn('running');

        $this->dockerService
            ->shouldReceive('isContainerHealthy')
            ->once()
            ->with($instance)
            ->andReturn(true);

        $response = $this->getJson("/api/whatsapp/{$instance->id}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'instance_status',
                'docker_status',
                'is_healthy',
                'api_url',
                'last_activity',
            ])
            ->assertJson([
                'instance_status' => 'running',
                'docker_status' => 'running',
                'is_healthy' => true,
                'api_url' => 'http://localhost:3001',
            ]);
    });

    it('can handle partial updates', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Original Name',
            'port' => 3001,
            'webhook_url' => 'https://original.com/webhook',
        ]);

        $response = $this->putJson("/api/whatsapp/{$instance->id}", [
            'webhook_url' => 'https://updated.com/webhook',
        ]);

        $response->assertStatus(200);

        $fresh = $instance->fresh();
        expect($fresh->name)->toBe('Original Name');
        expect($fresh->webhook_url)->toBe('https://updated.com/webhook');
    });
});
