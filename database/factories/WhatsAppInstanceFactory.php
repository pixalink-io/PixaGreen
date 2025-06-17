<?php

namespace Database\Factories;

use App\Models\WhatsAppInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppInstance>
 */
class WhatsAppInstanceFactory extends Factory
{
    protected $model = WhatsAppInstance::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company().' WhatsApp Instance',
            'container_id' => $this->faker->optional()->regexify('[a-f0-9]{64}'),
            'port' => $this->faker->optional()->numberBetween(3000, 3100),
            'status' => $this->faker->randomElement(['running', 'stopped', 'creating', 'error']),
            'webhook_url' => $this->faker->optional()->url(),
            'webhook_secret' => $this->faker->optional()->sha256(),
            'config' => $this->faker->optional()->passthrough([
                'timeout' => $this->faker->numberBetween(10, 60),
                'retry_attempts' => $this->faker->numberBetween(1, 5),
                'auto_reply' => $this->faker->boolean(),
            ]),
            'last_activity' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the instance is running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'container_id' => $this->faker->regexify('[a-f0-9]{64}'),
            'port' => $this->faker->numberBetween(3000, 3100),
        ]);
    }

    /**
     * Indicate that the instance is stopped.
     */
    public function stopped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'stopped',
        ]);
    }

    /**
     * Indicate that the instance is being created.
     */
    public function creating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'creating',
            'container_id' => null,
            'port' => null,
        ]);
    }

    /**
     * Indicate that the instance has an error.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
        ]);
    }

    /**
     * Indicate that the instance has a webhook configured.
     */
    public function withWebhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_url' => $this->faker->url(),
            'webhook_secret' => $this->faker->sha256(),
        ]);
    }

    /**
     * Indicate that the instance has no container.
     */
    public function withoutContainer(): static
    {
        return $this->state(fn (array $attributes) => [
            'container_id' => null,
            'port' => null,
        ]);
    }
}
