<?php
namespace Database\Factories;
use App\Models\ActivityLog; use Illuminate\Database\Eloquent\Factories\Factory;
class ActivityLogFactory extends Factory { protected $model=ActivityLog::class; public function definition(): array{return ['user_id'=>null,'event'=>fake()->word(),'subject_type'=>null,'subject_id'=>null,'description'=>fake()->sentence(),'properties'=>['source'=>'factory'],'ip_address'=>fake()->ipv4(),'user_agent'=>fake()->userAgent()];} }
