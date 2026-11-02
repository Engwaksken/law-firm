<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PageBlock extends Model { use HasFactory; protected $fillable=['page_id','type','content','sort_order','is_active']; protected function casts(): array{return ['content'=>'array','is_active'=>'boolean'];} public function page(): BelongsTo{return $this->belongsTo(Page::class);} }
