<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Article extends Model { use HasFactory,SoftDeletes; protected $fillable=['author_id','title','slug','excerpt','body','status','featured_image','meta_title','meta_description','published_at']; protected function casts(): array{return ['published_at'=>'datetime'];} public function author(): BelongsTo{return $this->belongsTo(User::class,'author_id');} public function categories(): BelongsToMany{return $this->belongsToMany(ArticleCategory::class,'article_category','article_id','article_category_id');} }
