<?php

namespace Database\Factories;

use App\Enums\EmailProviderDriverType;
use App\Enums\EmailProviderStatus;
use App\Models\EmailProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailProvider>
 */
class EmailProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' (correo)',
            'driver' => EmailProviderDriverType::NullDriver,
            'credentials' => null,
            'connection_settings' => null,
            'status' => EmailProviderStatus::Activo,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'driver' => EmailProviderDriverType::Manual,
            'connection_settings' => [
                'imap_host' => 'imap.'.fake()->domainName(),
                'imap_port' => '993',
                'smtp_host' => 'smtp.'.fake()->domainName(),
                'smtp_port' => '587',
            ],
        ]);
    }
}
