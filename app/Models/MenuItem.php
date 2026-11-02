<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\HasMany;
class MenuItem extends Model { use HasFactory; protected $fillable=['menu_id','parent_id','label','url','page_id','sort_order','is_active']; protected function casts(): array{return ['is_active'=>'boolean'];} public function menu(): BelongsTo{return $this->belongsTo(Menu::class);} public function parent(): BelongsTo{return $this->belongsTo(self::class,'parent_id');} public function children(): HasMany{return $this->hasMany(self::class,'parent_id')->orderBy('sort_order');} public function page(): BelongsTo{return $this->belongsTo(Page::class);} }
