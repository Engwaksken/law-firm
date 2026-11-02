<?php

namespace App\Models;

use App\Support\NameNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictCheckParty extends Model
{
    public $timestamps = false;

    public const ROLES = ['prospective_client' => 'Prospective client', 'opposing' => 'Opposing party', 'related' => 'Related party', 'other' => 'Other'];

    protected $fillable = ['conflict_check_id', 'name', 'role'];

    protected static function booted(): void
    {
        static::saving(fn (self $p) => $p->normalized_name = NameNormalizer::normalize($p->name));
    }

    public function conflictCheck(): BelongsTo { return $this->belongsTo(ConflictCheck::class); }
}
