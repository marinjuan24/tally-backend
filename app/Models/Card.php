<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    protected $fillable = ['account_id', 'card_number', 'cvv', 'card_holder', 'expiry_date', 'card_type', 'is_active'];

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

    /**
     * Generate a random 16-digit card number.
     */
    public static function generateCardNumber(): string
    {
        $number = '';
        for ($i = 0; $i < 16; $i++) {
            $number .= random_int(0, 9);
        }
        return $number;
    }

    /**
     * Generate a random 3-digit CVV.
     */
    public static function generateCvv(): string
    {
        return str_pad(random_int(100, 999), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate expiry date 3 years from now (MM/YY).
     */
    public static function generateExpiryDate(): string
    {
        $date = now()->addYears(3);
        return $date->format('m/y');
    }
}
