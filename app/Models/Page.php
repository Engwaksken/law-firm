<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\HasMany;
class Page extends Model { use HasFactory,SoftDeletes; protected $fillable=['title','slug','status','meta_title','meta_description','published_at']; protected function casts(): array{return ['published_at'=>'datetime'];} public function blocks(): HasMany{return $this->hasMany(PageBlock::class)->orderBy('sort_order');} public function menuItems(): HasMany{return $this->hasMany(MenuItem::class);} }
