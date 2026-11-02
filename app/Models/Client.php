<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\HasMany;
class Client extends Model { use HasFactory,SoftDeletes; protected $fillable=['user_id','name','email','phone','company','notes']; public function user(): BelongsTo{return $this->belongsTo(User::class);} public function consultations(): HasMany{return $this->hasMany(Consultation::class);} }
