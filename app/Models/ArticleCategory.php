<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class ArticleCategory extends Model { use HasFactory,SoftDeletes; protected $fillable=['name','slug','description']; public function articles(): BelongsToMany{return $this->belongsToMany(Article::class,'article_category');} }
