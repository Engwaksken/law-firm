<?php
namespace Database\Factories;
use App\Models\PracticeArea; use Illuminate\Database\Eloquent\Factories\Factory; use Illuminate\Support\Str;
class PracticeAreaFactory extends Factory { protected $model=PracticeArea::class; public function definition(): array{$name=fake()->unique()->words(2,true);return ['name'=>$name,'slug'=>Str::slug($name),'description'=>fake()->paragraph(),'is_active'=>true,'sort_order'=>0];} }
