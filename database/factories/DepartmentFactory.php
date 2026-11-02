<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('DP-###')),
            'is_active' => true,
        ];
    }
}
