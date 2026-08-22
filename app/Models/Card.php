<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    protected $fillable = ['account_id', 'card_number', 'card_holder', 'expiry_date', 'card_type', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Return masked card number (last 4 digits only).
     */
    public function getMaskedNumberAttribute(): string
    {
        return '**** **** **** ' . substr($this->card_number, -4);
    }
}
