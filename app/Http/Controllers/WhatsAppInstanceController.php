<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppInstance;
use App\Services\DockerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppInstanceController extends Controller
{
    public function __construct(private DockerService $dockerService) {}

    public function index(): JsonResponse
    {
        $instances = WhatsAppInstance::all();

        return response()->json($instances);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'unique:whatsapp_instances'],
            'webhook_url' => ['nullable', 'url'],
        ]);

        try {
            $instance = WhatsAppInstance::create([
                'name' => $request->name,
                'webhook_url' => $request->webhook_url,
                'status' => 'creating',
            ]);

            $containerInfo = $this->dockerService->createContainer($instance);

            return response()->json([
                'instance' => $instance->fresh(),
                'container' => $containerInfo,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create instance',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(WhatsAppInstance $instance): JsonResponse
    {
        return response()->json($instance);
    }

    public function update(Request $request, WhatsAppInstance $instance): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'unique:whatsapp_instances,name,'.$instance->id],
            'webhook_url' => ['nullable', 'url'],
        ]);

        $instance->update($request->only(['name', 'webhook_url']));

        return response()->json($instance);
    }

    public function destroy(WhatsAppInstance $instance): JsonResponse
    {
        try {
            $this->dockerService->removeContainer($instance);
            $instance->delete();

            return response()->json(['message' => 'Instance deleted successfully']);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete instance',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function start(WhatsAppInstance $instance): JsonResponse
    {
        try {
            $success = $this->dockerService->startContainer($instance);

            if ($success) {
                return response()->json(['message' => 'Instance started successfully']);
            }

            return response()->json(['error' => 'Failed to start instance'], 500);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to start instance',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function stop(WhatsAppInstance $instance): JsonResponse
    {
        try {
            $success = $this->dockerService->stopContainer($instance);

            if ($success) {
                return response()->json(['message' => 'Instance stopped successfully']);
            }

            return response()->json(['error' => 'Failed to stop instance'], 500);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to stop instance',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function status(WhatsAppInstance $instance): JsonResponse
    {
        $dockerStatus = $this->dockerService->getContainerStatus($instance);
        $isHealthy = $this->dockerService->isContainerHealthy($instance);

        return response()->json([
            'instance_status' => $instance->status,
            'docker_status' => $dockerStatus,
            'is_healthy' => $isHealthy,
            'api_url' => $instance->getApiUrl(),
            'last_activity' => $instance->last_activity,
        ]);
    }
}
