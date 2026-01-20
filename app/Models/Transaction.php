<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'type',
        'bank_reference',
        'bank_provider',
        'amount',
        'bank_transaction_time',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'bank_transaction_time' => 'datetime',
        'metadata' => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
