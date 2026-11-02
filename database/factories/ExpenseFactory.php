<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'user_id' => User::factory(),
            'incurred_on' => now()->subDays(2)->toDateString(),
            'category' => 'court_fees',
            'description' => 'Filing fees',
            'amount' => '150000.00',
            'currency' => 'UGX',
            'billable' => true,
        ];
    }

    public function approved(): static
    {
        return $this->afterCreating(fn (Expense $e) => $e->forceFill(['status' => 'approved', 'approved_at' => now()])->saveQuietly());
    }
}
