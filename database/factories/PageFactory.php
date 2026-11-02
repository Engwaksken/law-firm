<?php
namespace Database\Factories;
use App\Models\Page; use Illuminate\Database\Eloquent\Factories\Factory; use Illuminate\Support\Str;
class PageFactory extends Factory { protected $model=Page::class; public function definition(): array{$title=fake()->unique()->sentence(3);return ['title'=>$title,'slug'=>Str::slug($title),'status'=>'draft','meta_title'=>null,'meta_description'=>null,'published_at'=>null];} }
