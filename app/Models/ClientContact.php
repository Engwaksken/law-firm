<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\NameNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['client_id', 'name', 'position', 'email', 'phone', 'is_authorized_representative', 'is_primary'];

    protected function casts(): array
    {
        return ['is_authorized_representative' => 'boolean', 'is_primary' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $c) => $c->normalized_name = NameNormalizer::normalize($c->name));
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
}
