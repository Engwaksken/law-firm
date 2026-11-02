<?php
namespace Database\Factories;
use App\Models\Article; use App\Models\User; use Illuminate\Database\Eloquent\Factories\Factory; use Illuminate\Support\Str;
class ArticleFactory extends Factory { protected $model=Article::class; public function definition(): array{$title=fake()->sentence(6);return ['author_id'=>User::factory(),'title'=>$title,'slug'=>Str::slug($title).'-'.fake()->unique()->numberBetween(1,999999),'excerpt'=>fake()->paragraph(),'body'=>fake()->paragraphs(4,true),'status'=>'draft','featured_image'=>null,'meta_title'=>null,'meta_description'=>null,'published_at'=>null];} }
