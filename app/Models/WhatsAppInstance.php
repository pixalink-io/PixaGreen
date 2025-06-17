<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppInstance extends Model
{
    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'name',
        'container_id',
        'port',
        'status',
        'webhook_url',
        'webhook_secret',
        'config',
        'last_activity',
    ];

    protected $casts = [
        'config' => 'array',
        'last_activity' => 'datetime',
    ];

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    public function getApiUrl(): string
    {
        return "http://localhost:{$this->port}";
    }
}
