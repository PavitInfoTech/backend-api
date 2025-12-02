<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_name',
        'transaction_id',
        'gateway',
        'amount',
        'currency',
        'status',
        'type',
        'card_last_four',
        'card_brand',
        'description',
        'gateway_response',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    protected $hidden = [
        'gateway_response',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // subscriptions are no longer tracked separately, plan_name is stored on payment

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public static function generateTransactionId(): string
    {
        return 'TXN_' . strtoupper(bin2hex(random_bytes(12)));
    }
}
