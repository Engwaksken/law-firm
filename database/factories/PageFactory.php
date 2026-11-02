<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(3), '.');

        return ['title' => $title, 'slug' => Str::slug($title), 'status' => 'draft', 'template' => 'default'];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()->subHour()]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()->addDay()]);
    }

    /** @param list<array{type: string, data: array}> $blocks */
    public function withBlocks(array $blocks): static
    {
        return $this->afterCreating(fn (Page $page) => $page->syncBlocks($blocks));
    }
}
