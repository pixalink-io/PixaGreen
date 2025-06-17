<?php

use App\Models\WhatsAppInstance;

describe('WhatsAppInstance Model', function () {
    it('can create a whatsapp instance', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'status' => 'stopped',
        ]);

        expect($instance)->toBeInstanceOf(WhatsAppInstance::class);
        expect($instance->name)->toBe('Test Instance');
        expect($instance->status)->toBe('stopped');
    });

    it('has correct fillable attributes', function () {
        $fillable = [
            'name',
            'container_id',
            'port',
            'status',
            'webhook_url',
            'webhook_secret',
            'config',
            'last_activity',
        ];

        $instance = new WhatsAppInstance;
        expect($instance->getFillable())->toBe($fillable);
    });

    it('casts config to array', function () {
        $config = ['key' => 'value', 'timeout' => 30];

        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'config' => $config,
        ]);

        expect($instance->config)->toBeArray();
        expect($instance->config)->toBe($config);
    });

    it('casts last_activity to datetime', function () {
        $now = now();

        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'last_activity' => $now,
        ]);

        expect($instance->last_activity)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        expect($instance->last_activity->format('Y-m-d H:i:s'))->toBe($now->format('Y-m-d H:i:s'));
    });

    it('can check if instance is running', function () {
        $runningInstance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'port' => 3001,
            'status' => 'running',
        ]);

        $stoppedInstance = WhatsAppInstance::create([
            'name' => 'Stopped Instance',
            'port' => 3002,
            'status' => 'stopped',
        ]);

        expect($runningInstance->isRunning())->toBeTrue();
        expect($stoppedInstance->isRunning())->toBeFalse();
    });

    it('can check if instance is stopped', function () {
        $runningInstance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'port' => 3001,
            'status' => 'running',
        ]);

        $stoppedInstance = WhatsAppInstance::create([
            'name' => 'Stopped Instance',
            'port' => 3002,
            'status' => 'stopped',
        ]);

        expect($stoppedInstance->isStopped())->toBeTrue();
        expect($runningInstance->isStopped())->toBeFalse();
    });

    it('generates correct api url', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
        ]);

        expect($instance->getApiUrl())->toBe('http://localhost:3001');
    });

    it('handles different status values', function () {
        $statuses = ['running', 'stopped', 'creating', 'error'];

        foreach ($statuses as $index => $status) {
            $instance = WhatsAppInstance::create([
                'name' => "Instance with {$status} status",
                'port' => 3001 + $index,
                'status' => $status,
            ]);

            expect($instance->status)->toBe($status);
            expect($instance->isRunning())->toBe($status === 'running');
            expect($instance->isStopped())->toBe($status === 'stopped');
        }
    });

    it('can store webhook configuration', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Webhook Instance',
            'port' => 3001,
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => 'secret123',
        ]);

        expect($instance->webhook_url)->toBe('https://example.com/webhook');
        expect($instance->webhook_secret)->toBe('secret123');
    });

    it('can store container information', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Container Instance',
            'container_id' => 'container123',
            'port' => 3005,
        ]);

        expect($instance->container_id)->toBe('container123');
        expect($instance->port)->toBe(3005);
    });

    it('can be updated with new status', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Test Instance',
            'port' => 3001,
            'status' => 'stopped',
        ]);

        $instance->update(['status' => 'running']);

        expect($instance->fresh()->status)->toBe('running');
        expect($instance->isRunning())->toBeTrue();
    });

    it('can store complex config data', function () {
        $config = [
            'timeout' => 30,
            'retry_attempts' => 3,
            'features' => [
                'auto_reply' => true,
                'message_logging' => false,
            ],
            'webhooks' => [
                'message' => 'https://example.com/message',
                'status' => 'https://example.com/status',
            ],
        ];

        $instance = WhatsAppInstance::create([
            'name' => 'Complex Config Instance',
            'port' => 3001,
            'config' => $config,
        ]);

        expect($instance->config)->toBe($config);
        expect($instance->config['timeout'])->toBe(30);
        expect($instance->config['features']['auto_reply'])->toBeTrue();
        expect($instance->config['webhooks']['message'])->toBe('https://example.com/message');
    });

    it('uses correct table name', function () {
        $instance = new WhatsAppInstance;
        expect($instance->getTable())->toBe('whatsapp_instances');
    });

    it('can handle null values for optional fields', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Minimal Instance',
            'port' => 3001,
        ]);

        expect($instance->container_id)->toBeNull();
        expect($instance->port)->toBe(3001);
        expect($instance->webhook_url)->toBeNull();
        expect($instance->webhook_secret)->toBeNull();
        expect($instance->config)->toBeNull();
        expect($instance->last_activity)->toBeNull();
    });
});
