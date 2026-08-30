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
            'status' => EmailProviderStatus::Activo,
        ];
    }
}
