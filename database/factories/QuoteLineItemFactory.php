<?php

namespace Database\Factories;

use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLineItem>
 */
class QuoteLineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'service_id' => null,
            'name' => 'Renglón '.fake()->word(),
            'description' => null,
            'category' => ServiceCategory::Other,
            'billing_frequency' => ServiceBillingFrequency::OneTime,
            'amount' => fake()->randomFloat(2, 1000, 40000),
        ];
    }
}
