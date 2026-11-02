<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'author_title' => fake()->jobTitle(),
            'quote' => fake()->sentence(18),
            'consent_obtained' => false,
            'is_approved' => false,
        ];
    }

    public function publishable(): static { return $this->state(['consent_obtained' => true, 'is_approved' => true]); }
}
