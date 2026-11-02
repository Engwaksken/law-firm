<?php

namespace Database\Factories;

use App\Models\Deadline;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeadlineFactory extends Factory
{
    protected $model = Deadline::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'title' => 'File written submissions',
            'type' => 'filing',
            'due_at' => now()->addDays(3)->setTime(17, 0),
        ];
    }
}
