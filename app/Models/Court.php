<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    use Auditable, HasFactory;

    public const TYPES = ['court' => 'Court', 'tribunal' => 'Tribunal', 'arbitration' => 'Arbitral body', 'other' => 'Other'];

    protected $fillable = ['name', 'type', 'division', 'location', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function matters(): HasMany { return $this->hasMany(Matter::class); }
    public function hearings(): HasMany { return $this->hasMany(Hearing::class); }

    public function label(): string
    {
        return $this->name.($this->division ? " ({$this->division})" : '');
    }
}
