<?php

namespace Database\Factories;

use App\Models\Advocate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AdvocateFactory extends Factory
{
    protected $model = Advocate::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'title' => 'Advocate',
            'bio' => fake()->paragraph(),
            'email' => fake()->safeEmail(),
            'phone' => '+2567'.fake()->numerify('########'),
            'qualifications' => ['LLB', 'Dip. Legal Practice'],
            'memberships' => ['Uganda Law Society'],
            'is_active' => true,
            'is_public' => false,
            'accepts_consultations' => false,
            'sort_order' => 0,
        ];
    }

    /** Opted in to the website AND credentials verified: visible publicly. */
    public function published(): static
    {
        return $this->state(['is_public' => true])->afterMaking(fn (Advocate $a) => $a->credentials_verified_at = now());
    }

    public function acceptingConsultations(): static { return $this->state(['accepts_consultations' => true]); }
}
