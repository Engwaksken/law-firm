<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->sentence(),
            'due_at' => now()->addDays(3),
            'priority' => 'normal',
            'status' => 'open',
        ];
    }

    public function overdue(): static
    {
        return $this->state(['due_at' => now()->subDays(2)]);
    }
}
