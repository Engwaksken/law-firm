<?php

namespace Database\Factories;

use App\Models\Consultation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'message' => fake()->paragraph(),
            'consultation_type' => 'in_person',
            'preferred_date' => now()->addWeek()->toDateString(),
            'status' => 'pending',
            'privacy_accepted_at' => now(),
        ];
    }
}
