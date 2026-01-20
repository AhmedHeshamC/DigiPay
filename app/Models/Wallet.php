<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'owner_name',
        'email',
        'balance',
        'currency',
    ];

    protected $casts = [
        'balance' => 'decimal:4',
    ];
}
