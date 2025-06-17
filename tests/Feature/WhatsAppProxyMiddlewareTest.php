<?php

use App\Http\Middleware\WhatsAppProxyMiddleware;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->httpClient = $this->mock(HttpClient::class);
    $this->middleware = new WhatsAppProxyMiddleware($this->httpClient);
    $this->nextCallback = fn ($request) => response('next called');
});

describe('WhatsAppProxyMiddleware', function () {
    it('passes through non-api requests', function () {
        $request = Request::create('/admin/dashboard');

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->getContent())->toBe('next called');
    });

    it('passes through non-instance api requests', function () {
        $request = Request::create('/api/health');

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->getContent())->toBe('next called');
    });

    it('returns error for invalid api path', function () {
        $request = Request::create('/api/instance');

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(400);
        $content = json_decode($response->getContent(), true);
        expect($content['error'])->toBe('Invalid API path');
    });

    it('returns error for non-existent instance', function () {
        $request = Request::create('/api/instance/999/status');

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(404);
        $content = json_decode($response->getContent(), true);
        expect($content['error'])->toBe('Instance not found');
    });

    it('returns error for non-running instance', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Stopped Instance',
            'status' => 'stopped',
            'port' => 3001,
        ]);

        $request = Request::create("/api/instance/{$instance->id}/status");

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(503);
        $content = json_decode($response->getContent(), true);
        expect($content['error'])->toBe('Instance is not running');
    });

    it('proxies request to running instance', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'status' => 'running',
            'port' => 3001,
        ]);

        $request = Request::create("/api/instance/{$instance->id}/status", 'GET');

        $mockResponse = $this->mock(Response::class);
        $mockResponse->shouldReceive('body')->andReturn('{"status": "ok"}');
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('headers')->andReturn(['Content-Type' => 'application/json']);

        $this->httpClient
            ->shouldReceive('send')
            ->once()
            ->with(
                'GET',
                'http://localhost:3001/status',
                [
                    'headers' => [],
                    'body' => '',
                    'timeout' => 30,
                ]
            )
            ->andReturn($mockResponse);

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(200);
        expect($response->getContent())->toBe('{"status": "ok"}');

        // Verify last_activity was updated
        expect($instance->fresh()->last_activity)->not->toBeNull();
    });

    it('forwards query parameters', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'status' => 'running',
            'port' => 3001,
        ]);

        $request = Request::create("/api/instance/{$instance->id}/messages?limit=10&offset=20", 'GET');

        $mockResponse = $this->mock(Response::class);
        $mockResponse->shouldReceive('body')->andReturn('{"messages": []}');
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('headers')->andReturn([]);

        $this->httpClient
            ->shouldReceive('send')
            ->once()
            ->with(
                'GET',
                'http://localhost:3001/messages?limit=10&offset=20',
                [
                    'headers' => [],
                    'body' => '',
                    'timeout' => 30,
                ]
            )
            ->andReturn($mockResponse);

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(200);
    });

    it('forwards request headers', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'status' => 'running',
            'port' => 3001,
        ]);

        $request = Request::create("/api/instance/{$instance->id}/send", 'POST');
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Authorization', 'Bearer token123');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('User-Agent', 'Test Client');
        $request->headers->set('X-Custom-Header', 'should-not-forward');

        $mockResponse = $this->mock(Response::class);
        $mockResponse->shouldReceive('body')->andReturn('{"success": true}');
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('headers')->andReturn([]);

        $this->httpClient
            ->shouldReceive('send')
            ->once()
            ->with(
                'POST',
                'http://localhost:3001/send',
                [
                    'headers' => [
                        'Content-Type' => ['application/json'],
                        'Authorization' => ['Bearer token123'],
                        'Accept' => ['application/json'],
                        'User-Agent' => ['Test Client'],
                    ],
                    'body' => '',
                    'timeout' => 30,
                ]
            )
            ->andReturn($mockResponse);

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(200);
    });

    it('forwards request body for post requests', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'status' => 'running',
            'port' => 3001,
        ]);

        $requestBody = json_encode(['message' => 'Hello World']);
        $request = Request::create("/api/instance/{$instance->id}/send", 'POST', [], [], [], [], $requestBody);

        $mockResponse = $this->mock(Response::class);
        $mockResponse->shouldReceive('body')->andReturn('{"success": true}');
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('headers')->andReturn([]);

        $this->httpClient
            ->shouldReceive('send')
            ->once()
            ->with(
                'POST',
                'http://localhost:3001/send',
                [
                    'headers' => [],
                    'body' => $requestBody,
                    'timeout' => 30,
                ]
            )
            ->andReturn($mockResponse);

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(200);
    });

    it('handles different http methods', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'status' => 'running',
            'port' => 3001,
        ]);

        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $request = Request::create("/api/instance/{$instance->id}/endpoint", $method);

            $mockResponse = $this->mock(Response::class);
            $mockResponse->shouldReceive('body')->andReturn('{"success": true}');
            $mockResponse->shouldReceive('status')->andReturn(200);
            $mockResponse->shouldReceive('headers')->andReturn([]);

            $this->httpClient
                ->shouldReceive('send')
                ->once()
                ->with(
                    $method,
                    'http://localhost:3001/endpoint',
                    [
                        'headers' => [],
                        'body' => '',
                        'timeout' => 30,
                    ]
                )
                ->andReturn($mockResponse);

            $response = $this->middleware->handle($request, $this->nextCallback);

            expect($response->status())->toBe(200);
        }
    });

    it('handles nested api paths', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'status' => 'running',
            'port' => 3001,
        ]);

        $request = Request::create("/api/instance/{$instance->id}/messages/send/text", 'POST');

        $mockResponse = $this->mock(Response::class);
        $mockResponse->shouldReceive('body')->andReturn('{"success": true}');
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('headers')->andReturn([]);

        $this->httpClient
            ->shouldReceive('send')
            ->once()
            ->with(
                'POST',
                'http://localhost:3001/messages/send/text',
                [
                    'headers' => [],
                    'body' => '',
                    'timeout' => 30,
                ]
            )
            ->andReturn($mockResponse);

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(200);
    });

    it('handles proxy request exceptions', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'status' => 'running',
            'port' => 3001,
        ]);

        $request = Request::create("/api/instance/{$instance->id}/status", 'GET');

        $this->httpClient
            ->shouldReceive('send')
            ->once()
            ->andThrow(new Exception('Connection timeout'));

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(500);
        $content = json_decode($response->getContent(), true);
        expect($content['error'])->toBe('Proxy request failed');
        expect($content['message'])->toBe('Connection timeout');
    });

    it('forwards response headers from proxied request', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'status' => 'running',
            'port' => 3001,
        ]);

        $request = Request::create("/api/instance/{$instance->id}/status", 'GET');

        $mockResponse = $this->mock(Response::class);
        $mockResponse->shouldReceive('body')->andReturn('{"status": "ok"}');
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('headers')->andReturn([
            'Content-Type' => 'application/json',
            'X-RateLimit-Remaining' => '100',
        ]);

        $this->httpClient
            ->shouldReceive('send')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(200);
        expect($response->headers->get('Content-Type'))->toBe('application/json');
        expect($response->headers->get('X-RateLimit-Remaining'))->toBe('100');
    });

    it('forwards error status codes from proxied request', function () {
        $instance = WhatsAppInstance::create([
            'name' => 'Running Instance',
            'status' => 'running',
            'port' => 3001,
        ]);

        $request = Request::create("/api/instance/{$instance->id}/invalid-endpoint", 'GET');

        $mockResponse = $this->mock(Response::class);
        $mockResponse->shouldReceive('body')->andReturn('{"error": "Not found"}');
        $mockResponse->shouldReceive('status')->andReturn(404);
        $mockResponse->shouldReceive('headers')->andReturn([]);

        $this->httpClient
            ->shouldReceive('send')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->middleware->handle($request, $this->nextCallback);

        expect($response->status())->toBe(404);
        expect($response->getContent())->toBe('{"error": "Not found"}');
    });
});
