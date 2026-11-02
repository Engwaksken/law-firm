<?php
namespace Database\Factories;
use App\Models\Media; use App\Models\User; use Illuminate\Database\Eloquent\Factories\Factory;
class MediaFactory extends Factory { protected $model=Media::class; public function definition(): array{return ['uploaded_by'=>User::factory(),'disk'=>'public','path'=>fake()->uuid().'.jpg','original_name'=>fake()->word().'.jpg','mime_type'=>'image/jpeg','size_bytes'=>fake()->numberBetween(1000,5000000),'alt_text'=>fake()->optional()->sentence(),'collection'=>'default'];} }
