<?php

namespace App\Http\Middleware;

use App\Models\WhatsAppInstance;
use Closure;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppProxyMiddleware
{
    public function __construct(private HttpClient $http) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        if (! str_starts_with($path, 'api/instance/')) {
            return $next($request);
        }

        $segments = explode('/', $path);

        if (count($segments) < 3) {
            return response()->json(['error' => 'Invalid API path'], 400);
        }

        $instanceId = $segments[2];
        $whatsappPath = implode('/', array_slice($segments, 3));

        $instance = WhatsAppInstance::find($instanceId);

        if (! $instance) {
            return response()->json(['error' => 'Instance not found'], 404);
        }

        if (! $instance->isRunning()) {
            return response()->json(['error' => 'Instance is not running'], 503);
        }

        try {
            $targetUrl = $instance->getApiUrl().'/'.$whatsappPath;

            $queryParams = $request->query();
            if (! empty($queryParams)) {
                $targetUrl .= '?'.http_build_query($queryParams);
            }

            $response = $this->http->send(
                $request->method(),
                $targetUrl,
                [
                    'headers' => $this->getForwardHeaders($request),
                    'body' => $request->getContent(),
                    'timeout' => 30,
                ]
            );

            $instance->update(['last_activity' => now()]);

            return response(
                $response->body(),
                $response->status(),
                $response->headers()
            );

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Proxy request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function getForwardHeaders(Request $request): array
    {
        $forwardHeaders = [
            'Content-Type',
            'Authorization',
            'Accept',
            'User-Agent',
        ];

        $headers = [];
        foreach ($forwardHeaders as $header) {
            if ($request->hasHeader($header)) {
                $headers[$header] = $request->header($header);
            }
        }

        return $headers;
    }
}
