<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    public function definition(): array
    {
        return ['email' => fake()->unique()->safeEmail(), 'name' => fake()->name()];
    }

    public function confirmed(): static { return $this->state(['confirmed_at' => now()]); }
}
