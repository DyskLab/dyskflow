<?php

namespace App\Models;

use App\Support\Totp;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['role_id', 'name', 'email', 'password', 'locale', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'totp_secret', 'totp_recovery_codes'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'totp_secret' => 'encrypted',
            'totp_recovery_codes' => 'encrypted:array',
            'totp_confirmed_at' => 'datetime',
        ];
    }

    public function hasTotp(): bool
    {
        return $this->totp_confirmed_at !== null;
    }

    /** Accepts a current code once, or an unused recovery code, which is then spent. */
    public function passesTotp(string $input): bool
    {
        $input = strtolower(trim($input));

        try {
            $step = Totp::verify((string) $this->totp_secret, $input, $this->totp_last_step);
            if ($step !== null) {
                $this->forceFill(['totp_last_step' => $step])->save();

                return true;
            }

            $codes = $this->totp_recovery_codes ?? [];
            $hash = hash('sha256', $input);
            if (! in_array($hash, $codes, true)) {
                return false;
            }
            $this->forceFill(['totp_recovery_codes' => array_values(array_diff($codes, [$hash]))])->save();

            return true;
        } catch (DecryptException) {
            // Encrypted with another APP_KEY, for example after restoring a backup on a new site.
            return false;
        }
    }

    /** Replaces the recovery codes. Only hashes are kept, so the plain codes are shown just this once. */
    public function newRecoveryCodes(): array
    {
        $codes = collect(range(1, 8))->map(fn () => bin2hex(random_bytes(3)).'-'.bin2hex(random_bytes(3)))->all();
        $this->forceFill(['totp_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes)])->save();

        return $codes;
    }

    public function disableTotp(): void
    {
        $this->forceFill(['totp_secret' => null, 'totp_recovery_codes' => null, 'totp_confirmed_at' => null, 'totp_last_step' => null])->save();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasCapability(string $capability): bool
    {
        $capabilities = $this->role?->capabilities ?? [];

        return in_array('*', $capabilities, true) || in_array($capability, $capabilities, true);
    }
}
