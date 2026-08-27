<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'photo', 'account_number'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    public function transfersSent(): HasMany
    {
        return $this->hasMany(Transfer::class, 'sender_id');
    }

    public function transfersReceived(): HasMany
    {
        return $this->hasMany(Transfer::class, 'receiver_id');
    }

    public function frequentPayees(): HasMany
    {
        return $this->hasMany(FrequentPayee::class, 'user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->orderBy('created_at', 'desc');
    }

    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->unread()->count();
    }

    /**
     * Generate a unique 10-digit account number.
     */
    public static function generateAccountNumber(): string
    {
        do {
            $number = str_pad(random_int(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
        } while (static::where('account_number', $number)->exists());

        return $number;
    }
}
