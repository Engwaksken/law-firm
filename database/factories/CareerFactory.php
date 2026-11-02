<?php

namespace Database\Factories;

use App\Models\Career;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CareerFactory extends Factory
{
    protected $model = Career::class;

    public function definition(): array
    {
        $title = fake()->randomElement(['Associate Advocate', 'Legal Assistant', 'Paralegal', 'Legal Intern']);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'type' => 'job',
            'employment_type' => 'Full-time',
            'location' => 'Kampala',
            'summary' => fake()->sentence(),
            'description' => '<p>'.fake()->paragraphs(2, true).'</p>',
            'requirements' => '<ul><li>'.fake()->sentence().'</li></ul>',
            'status' => 'draft',
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()->subDay(), 'closes_at' => now()->addMonth()->toDateString()]);
    }

    public function closed(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()->subMonth(), 'closes_at' => now()->subDay()->toDateString()]);
    }
}
