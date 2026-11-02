<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\BelongsToMany; use Illuminate\Database\Eloquent\Relations\HasMany;
class PracticeArea extends Model { use HasFactory,SoftDeletes; protected $fillable=['name','slug','description','is_active','sort_order']; protected function casts(): array{return ['is_active'=>'boolean'];} public function advocates(): BelongsToMany{return $this->belongsToMany(Advocate::class);} public function consultations(): HasMany{return $this->hasMany(Consultation::class);} }
