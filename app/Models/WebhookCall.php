<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookCall extends Model
{
    protected $fillable = [
        'bank_provider',
        'payload',
        'status',
        'error_message',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];
}
