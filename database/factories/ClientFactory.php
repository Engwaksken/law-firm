<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'type' => 'individual',
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+2567'.fake()->numerify('########'),
            'status' => 'active',
            'country' => 'Uganda',
        ];
    }

    public function corporate(): static
    {
        return $this->state(fn () => ['type' => 'corporate', 'name' => fake()->company().' Ltd', 'registration_number' => fake()->numerify('80020#######')]);
    }
}
