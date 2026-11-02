<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
class ContactInquiry extends Model { use HasFactory,SoftDeletes; protected $fillable=['name','email','phone','subject','message','status','handled_at']; protected function casts(): array{return ['handled_at'=>'datetime'];} }
