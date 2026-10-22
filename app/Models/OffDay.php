<?php

namespace App\Models;

use App\Support\Axcess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'type',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return Axcess::label('off_day_type', $this->type);
    }
}
