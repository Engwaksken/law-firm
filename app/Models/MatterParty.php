<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\NameNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatterParty extends Model
{
    use Auditable, HasFactory;

    public const TYPES = [
        'opposing' => 'Opposing party', 'opposing_counsel' => 'Opposing counsel', 'co_party' => 'Co-party',
        'witness' => 'Witness', 'related' => 'Related party', 'other' => 'Other',
    ];

    protected $fillable = ['matter_id', 'name', 'party_type', 'is_organization', 'contact', 'notes'];

    protected array $auditExclude = ['notes'];

    protected function casts(): array
    {
        return ['is_organization' => 'boolean', 'notes' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $p) => $p->normalized_name = NameNormalizer::normalize($p->name));
    }

    public function matter(): BelongsTo { return $this->belongsTo(Matter::class); }
}
