<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'title' => 'Client meeting',
            'type' => 'client_meeting',
            'starts_at' => now()->addDays(1)->setTime(10, 0),
            'ends_at' => now()->addDays(1)->setTime(11, 0),
            'status' => 'scheduled',
        ];
    }
}
