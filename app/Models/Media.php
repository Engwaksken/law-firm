<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Media extends Model { use HasFactory,SoftDeletes; protected $table='media'; protected $fillable=['uploaded_by','disk','path','original_name','mime_type','size_bytes','alt_text','collection']; protected function casts(): array{return ['size_bytes'=>'integer'];} public function uploader(): BelongsTo{return $this->belongsTo(User::class,'uploaded_by');} }
