<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactInquiry extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUSES = ['new' => 'New', 'in_progress' => 'In progress', 'handled' => 'Handled', 'spam' => 'Spam'];

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'status', 'handled_at', 'handled_by', 'privacy_accepted_at'];

    protected array $auditExclude = ['message'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime', 'privacy_accepted_at' => 'datetime'];
    }

    public function handler(): BelongsTo { return $this->belongsTo(User::class, 'handled_by'); }
}
