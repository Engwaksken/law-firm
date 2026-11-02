<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatterAssignment extends Model
{
    use Auditable;

    protected $fillable = ['matter_id', 'user_id', 'role', 'assigned_by'];

    public function matter(): BelongsTo { return $this->belongsTo(Matter::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
