<?php

use App\Services\DockerService;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->dockerService = $this->mock(DockerService::class);
});

describe('CheckDockerStatus Command', function () {
    it('shows success when docker is running and image is available', function () {
        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->once()
            ->andReturn([
                'docker_running' => true,
                'image_available' => true,
                'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                'port_range' => '3000-3100',
            ]);

        $this->artisan('whatsapp:check-docker')
            ->expectsOutputToContain('✅ Docker daemon is running')
            ->expectsOutputToContain('✅ WhatsApp image is available locally')
            ->expectsOutputToContain('🎉 Docker environment is ready for WhatsApp instances!')
            ->assertExitCode(0);
    });

    it('shows warning when docker is running but image is not available', function () {
        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->once()
            ->andReturn([
                'docker_running' => true,
                'image_available' => false,
                'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                'port_range' => '3000-3100',
            ]);

        $this->artisan('whatsapp:check-docker')
            ->expectsOutputToContain('✅ Docker daemon is running')
            ->expectsOutputToContain('⚠️  WhatsApp image is not available locally')
            ->expectsOutputToContain('⚠️  Docker is running but image needs to be pulled')
            ->assertExitCode(0);
    });

    it('shows error when docker is not running', function () {
        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->once()
            ->andReturn([
                'docker_running' => false,
                'image_available' => false,
                'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                'port_range' => '3000-3100',
            ]);

        $this->artisan('whatsapp:check-docker')
            ->expectsOutputToContain('❌ Docker daemon is not running')
            ->expectsOutputToContain('❌ Docker environment requires attention')
            ->expectsOutputToContain('💡 Use --start flag to attempt automatic Docker startup')
            ->assertExitCode(1);
    });

    it('displays configuration information', function () {
        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->once()
            ->andReturn([
                'docker_running' => true,
                'image_available' => true,
                'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                'port_range' => '3000-3100',
            ]);

        $this->artisan('whatsapp:check-docker')
            ->expectsOutputToContain('Configuration:')
            ->expectsOutputToContain('- Image: aldinokemal2104/go-whatsapp-web-multidevice:latest')
            ->expectsOutputToContain('- Port range: 3000-3100')
            ->assertExitCode(0);
    });

    it('provides docker start instructions when docker is not running', function () {
        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->once()
            ->andReturn([
                'docker_running' => false,
                'image_available' => false,
                'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                'port_range' => '3000-3100',
            ]);

        $this->artisan('whatsapp:check-docker')
            ->expectsOutputToContain('To start Docker:')
            ->expectsOutputToContain('- Linux/macOS: sudo systemctl start docker or start Docker Desktop')
            ->expectsOutputToContain('- Windows: Start Docker Desktop')
            ->expectsOutputToContain('- Verify with: docker info')
            ->assertExitCode(1);
    });

    it('attempts to start docker on macos when --start flag is used', function () {
        $this->markTestSkipped('Skipping this test temporarily');

        // Mock the OS as Darwin (macOS)
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('This test only runs on macOS');
        }

        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->twice()
            ->andReturn(
                [
                    'docker_running' => false,
                    'image_available' => false,
                    'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                    'port_range' => '3000-3100',
                ],
                [
                    'docker_running' => true,
                    'image_available' => false,
                    'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                    'port_range' => '3000-3100',
                ]
            );

        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->atLeast()
            ->once()
            ->andReturn(true);

        Process::fake([
            'open -a Docker' => Process::result('', 0),
        ]);

        $this->artisan('whatsapp:check-docker --start')
            ->expectsOutputToContain('Attempting to start Docker...')
            ->expectsOutputToContain('Starting Docker Desktop on macOS...')
            ->expectsOutputToContain('✅ Docker has been started successfully')
            ->expectsOutputToContain('⚠️  Docker is running but image needs to be pulled')
            ->assertExitCode(0);
    });

    it('handles failed docker start attempt', function () {
        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->once()
            ->andReturn([
                'docker_running' => false,
                'image_available' => false,
                'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                'port_range' => '3000-3100',
            ]);

        Process::fake([
            'open -a Docker' => Process::result('', 1), // Simulate failure
        ]);

        $this->artisan('whatsapp:check-docker --start')
            ->expectsOutputToContain('Attempting to start Docker...')
            ->expectsOutputToContain('❌ Failed to start Docker automatically')
            ->assertExitCode(1);
    });

    it('handles linux docker start with systemctl', function () {
        // Skip if not on Linux or if we can't mock PHP_OS_FAMILY
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('This test simulates Linux environment');
        }

        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->twice()
            ->andReturn(
                [
                    'docker_running' => false,
                    'image_available' => false,
                    'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                    'port_range' => '3000-3100',
                ],
                [
                    'docker_running' => true,
                    'image_available' => false,
                    'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                    'port_range' => '3000-3100',
                ]
            );

        $this->dockerService
            ->shouldReceive('isDockerRunning')
            ->atLeast()
            ->once()
            ->andReturn(true);

        Process::fake([
            'sudo systemctl start docker' => Process::result('', 0),
        ]);

        $this->artisan('whatsapp:check-docker --start')
            ->expectsOutputToContain('Starting Docker daemon on Linux...')
            ->expectsOutputToContain('✅ Docker has been started successfully')
            ->assertExitCode(0);
    });

    it('provides image pull instructions when image is not available', function () {
        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->once()
            ->andReturn([
                'docker_running' => true,
                'image_available' => false,
                'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                'port_range' => '3000-3100',
            ]);

        $this->artisan('whatsapp:check-docker')
            ->expectsOutputToContain('⚠️  WhatsApp image is not available locally')
            ->expectsOutputToContain('Image will be pulled automatically when creating containers')
            ->expectsOutputToContain('Or manually pull with: docker pull aldinokemal2104/go-whatsapp-web-multidevice:latest')
            ->assertExitCode(0);
    });

    it('handles exceptions during docker start gracefully', function () {
        $this->dockerService
            ->shouldReceive('getDockerStatus')
            ->once()
            ->andReturn([
                'docker_running' => false,
                'image_available' => false,
                'image_name' => 'aldinokemal2104/go-whatsapp-web-multidevice:latest',
                'port_range' => '3000-3100',
            ]);

        // Mock Process to throw an exception
        Process::fake([
            '*' => function () {
                throw new Exception('Process execution failed');
            },
        ]);

        $this->artisan('whatsapp:check-docker --start')
            ->expectsOutputToContain('Attempting to start Docker...')
            ->expectsOutputToContain('Error starting Docker: Process execution failed')
            ->expectsOutputToContain('❌ Failed to start Docker automatically')
            ->assertExitCode(1);
    });
});
