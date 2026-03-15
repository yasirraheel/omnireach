<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingDomain extends Model
{
    use Filterable;

    protected $fillable = [
        'uid',
        'user_id',
        'domain',
        'status',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = str_unique();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmin($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'pending' => 'warning',
            'inactive' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get the tracking base URL for a user.
     * Returns the user's verified tracking domain or falls back to app URL.
     */
    public static function getTrackingBaseUrl(?int $userId = null): string
    {
        $domain = null;

        if ($userId) {
            $domain = static::where('user_id', $userId)
                ->where('status', 'active')
                ->first();
        }

        if (!$domain) {
            $domain = static::whereNull('user_id')
                ->where('status', 'active')
                ->first();
        }

        if ($domain) {
            $scheme = request()->isSecure() ? 'https' : 'http';
            return "{$scheme}://{$domain->domain}";
        }

        return config('app.url');
    }

    /**
     * Verify CNAME DNS record points to this app.
     */
    public function verifyCname(): bool
    {
        try {
            $appDomain = parse_url(config('app.url'), PHP_URL_HOST);
            $records = dns_get_record($this->domain, DNS_CNAME);

            if (empty($records)) {
                return false;
            }

            foreach ($records as $record) {
                $target = rtrim($record['target'] ?? '', '.');
                if (strcasecmp($target, $appDomain) === 0) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
