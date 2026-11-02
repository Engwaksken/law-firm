<?php
namespace Database\Factories;
use App\Models\Consultation; use App\Models\Client; use App\Models\Advocate; use App\Models\PracticeArea; use Illuminate\Database\Eloquent\Factories\Factory;
class ConsultationFactory extends Factory { protected $model=Consultation::class; public function definition(): array{return ['client_id'=>Client::factory(),'advocate_id'=>Advocate::factory(),'practice_area_id'=>PracticeArea::factory(),'name'=>fake()->name(),'email'=>fake()->safeEmail(),'phone'=>fake()->phoneNumber(),'message'=>fake()->paragraph(),'scheduled_at'=>fake()->optional()->dateTimeBetween('now','+1 month'),'status'=>'pending','internal_notes'=>null];} }
