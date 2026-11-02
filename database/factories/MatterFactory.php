<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatterFactory extends Factory
{
    protected $model = Matter::class;

    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Land dispute', 'Employment claim', 'Contract review', 'Debt recovery', 'Probate']).' – '.fake()->lastName(),
            'client_id' => Client::factory(),
            'priority' => 'normal',
            'is_restricted' => false,
        ];
    }

    /** Status set directly for test fixtures only; real changes go through MatterLifecycle. */
    public function status(string $status): static
    {
        return $this->afterMaking(fn (Matter $m) => $m->status = $status);
    }

    public function active(): static
    {
        return $this->status('active')->afterMaking(fn (Matter $m) => $m->opened_at = now()->subMonth());
    }

    public function restricted(): static
    {
        return $this->state(['is_restricted' => true]);
    }

    public function ledBy(User $user): static
    {
        return $this->state(['lead_user_id' => $user->id]);
    }

    /** @param array<int, string> $members user id => role */
    public function withTeam(array $members): static
    {
        return $this->afterCreating(function (Matter $matter) use ($members) {
            foreach ($members as $userId => $role) {
                $matter->assignments()->create(['user_id' => $userId, 'role' => $role]);
            }
        });
    }
}
