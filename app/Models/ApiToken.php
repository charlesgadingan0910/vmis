<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A single bearer token issued to the Driver mobile app for one device.
 * See the create_api_tokens_table migration's docblock for why this is a
 * small custom table rather than Laravel Sanctum.
 */
class ApiToken extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'device_name',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issues a new token for $user and returns the ONE AND ONLY time the
     * plain-text value is ever available — callers must hand it to the app
     * immediately, since only its hash is kept from this point on.
     *
     * @return array{plain: string, model: self}
     */
    public static function generate(User $user, ?string $deviceName = null): array
    {
        $plain = Str::random(64);

        $model = static::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'device_name' => $deviceName,
            // 1 year: long enough that a driver isn't repeatedly bounced
            // back to the login screen, short enough that a lost/retired
            // phone's access doesn't stay valid forever unnoticed.
            'expires_at' => now()->addYear(),
        ]);

        return ['plain' => $plain, 'model' => $model];
    }

    /**
     * Resolves a plain-text bearer token (as received in an Authorization
     * header) back to its row, or null if it doesn't exist or has expired.
     */
    public static function findValidByPlain(string $plain): ?self
    {
        $token = static::where('token_hash', hash('sha256', $plain))->first();

        if (! $token) {
            return null;
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }
}
