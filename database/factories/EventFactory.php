<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = rtrim(fake()->sentence(4), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'summary' => fake()->sentence(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'starts_at' => now()->addWeeks(2)->setTime(9, 0),
            'ends_at' => now()->addWeeks(2)->setTime(12, 0),
            'location' => 'Kampala',
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()->subDay()]);
    }

    public function past(): static
    {
        return $this->state(['starts_at' => now()->subMonth(), 'ends_at' => now()->subMonth()->addHours(3)]);
    }
}
