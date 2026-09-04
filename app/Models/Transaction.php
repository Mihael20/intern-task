<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_BUY = 'buy';
    public const TYPE_SELL = 'sell';

    protected $fillable = [
        'client_id',
        'type',
        'amount',
        'instrument',
        'quantity',
        'price',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}