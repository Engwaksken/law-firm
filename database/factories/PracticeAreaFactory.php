<?php

namespace Database\Factories;

use App\Models\PracticeArea;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PracticeAreaFactory extends Factory
{
    protected $model = PracticeArea::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true)).' Law';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'summary' => fake()->sentence(12),
            'description' => fake()->paragraph(),
            'body' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'faqs' => [['question' => 'How long does it take?', 'answer' => 'It depends on the matter.']],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static { return $this->state(['is_active' => false]); }

    public function featured(): static { return $this->state(['is_featured' => true]); }
}
