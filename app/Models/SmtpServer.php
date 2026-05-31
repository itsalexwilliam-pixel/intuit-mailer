<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SmtpServer extends Model
{
    protected $fillable = [
        'account_id',
        'name',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_email',
        'from_name',
        'reply_to_email',
        'reply_to_name',
        'is_active',
        'daily_limit',
        'priority',
        'last_used_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'daily_limit' => 'integer',
        'priority' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = encrypt($value);
    }

    public function getPasswordAttribute($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Normal Laravel encrypted payload (compatible with encrypt()).
        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            // Continue to legacy fallbacks below.
        }

        // Legacy/custom payload stored as JSON array:
        // ["iv","value","mac","tag"]
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            try {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && count($decoded) >= 3) {
                    $legacyPayload = [
                        'iv' => $decoded[0] ?? null,
                        'value' => $decoded[1] ?? null,
                        'mac' => $decoded[2] ?? null,
                        'tag' => $decoded[3] ?? '',
                    ];

                    return Crypt::decrypt(json_encode($legacyPayload));
                }
            } catch (\Throwable $e) {
                // Fall through to plaintext fallback.
            }
        }

        // Last fallback: treat as plaintext (for old manually inserted rows).
        return (string) $value;
    }

    public function maskedPassword(): string
    {
        return '******';
    }
}
