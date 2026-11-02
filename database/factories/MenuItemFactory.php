<?php
namespace Database\Factories;
use App\Models\MenuItem; use App\Models\Menu; use Illuminate\Database\Eloquent\Factories\Factory;
class MenuItemFactory extends Factory { protected $model=MenuItem::class; public function definition(): array{return ['menu_id'=>Menu::factory(),'parent_id'=>null,'label'=>fake()->words(2,true),'url'=>fake()->url(),'page_id'=>null,'sort_order'=>0,'is_active'=>true];} }
