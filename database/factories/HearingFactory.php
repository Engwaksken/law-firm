<?php

namespace Database\Factories;

use App\Models\Hearing;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

class HearingFactory extends Factory
{
    protected $model = Hearing::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'type' => 'hearing',
            'starts_at' => now()->addDays(7)->setTime(9, 0),
            'status' => 'scheduled',
        ];
    }
}
