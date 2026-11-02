<?php

namespace Database\Factories;

use App\Models\Career;
use App\Models\CareerApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

class CareerApplicationFactory extends Factory
{
    protected $model = CareerApplication::class;

    public function definition(): array
    {
        return [
            'career_id' => Career::factory()->open(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'cover_letter' => fake()->paragraph(),
            'cv_path' => 'applications/test-cv.pdf',
            'cv_original_name' => 'cv.pdf',
            'status' => 'received',
            'privacy_accepted_at' => now(),
        ];
    }
}
