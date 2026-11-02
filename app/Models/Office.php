<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'email', 'phone', 'address', 'city', 'country', 'is_head_office', 'is_active'];

    protected function casts(): array
    {
        return ['is_head_office' => 'boolean', 'is_active' => 'boolean'];
    }

    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
}
