<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfficeFactory extends Factory
{
    protected $model = Office::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Office',
            'code' => strtoupper(fake()->unique()->bothify('OF-###')),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'country' => 'Uganda',
            'is_head_office' => false,
            'is_active' => true,
        ];
    }
}
