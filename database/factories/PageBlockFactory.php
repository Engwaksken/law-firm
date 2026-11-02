<?php
namespace Database\Factories;
use App\Models\PageBlock; use App\Models\Page; use Illuminate\Database\Eloquent\Factories\Factory;
class PageBlockFactory extends Factory { protected $model=PageBlock::class; public function definition(): array{return ['page_id'=>Page::factory(),'type'=>'text','content'=>['text'=>fake()->paragraph()],'sort_order'=>0,'is_active'=>true];} }
