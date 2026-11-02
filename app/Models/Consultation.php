<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Consultation extends Model { use HasFactory,SoftDeletes; protected $fillable=['client_id','advocate_id','practice_area_id','name','email','phone','message','scheduled_at','status','internal_notes']; protected function casts(): array{return ['scheduled_at'=>'datetime'];} public function client(): BelongsTo{return $this->belongsTo(Client::class);} public function advocate(): BelongsTo{return $this->belongsTo(Advocate::class);} public function practiceArea(): BelongsTo{return $this->belongsTo(PracticeArea::class);} }
