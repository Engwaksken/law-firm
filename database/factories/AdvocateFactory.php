<?php
namespace Database\Factories;
use App\Models\Advocate; use App\Models\User; use Illuminate\Database\Eloquent\Factories\Factory; use Illuminate\Support\Str;
class AdvocateFactory extends Factory { protected $model=Advocate::class; public function definition(): array{$name=fake()->name();return ['user_id'=>User::factory(),'name'=>$name,'slug'=>Str::slug($name).'-'.fake()->unique()->numberBetween(1,999999),'title'=>fake()->jobTitle(),'bio'=>fake()->paragraph(),'email'=>fake()->safeEmail(),'phone'=>fake()->phoneNumber(),'is_active'=>true,'sort_order'=>0];} }
