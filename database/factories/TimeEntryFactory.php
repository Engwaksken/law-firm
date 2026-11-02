<?php

namespace Database\Factories;

use App\Models\Matter;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'user_id' => User::factory(),
            'worked_on' => now()->subDays(3)->toDateString(),
            'minutes' => 90,
            'description' => fake()->randomElement(['Drafting plaint', 'Client meeting', 'Legal research', 'Court attendance', 'Reviewing contract']),
            'billable' => true,
            'rate' => '300000.00',
            'currency' => 'UGX',
        ];
    }

    public function nonBillable(): static { return $this->state(['billable' => false]); }
}
