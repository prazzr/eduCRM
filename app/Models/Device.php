<?php
declare(strict_types=1);

namespace EduCRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Device Model
 * 
 * Represents user device registrations for push notifications via ntfy.
 * Each device subscribes to a user-specific topic to receive notifications.
 * 
 * @property int $id
 * @property int $user_id
 * @property string $ntfy_topic
 * @property string $device_type
 * @property string $device_name
 * @property string $app_version
 * @property bool $is_active
 * @property string $last_active_at
 */
class Device extends Model
{
    protected $table = 'user_devices';
    protected $primaryKey = 'id';
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'user_id',
        'ntfy_topic',
        'device_type',
        'device_name',
        'app_version',
        'is_active',
        'last_active_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_active_at' => 'datetime'
    ];

    /**
     * Device types
     */
    const TYPE_IOS = 'ios';
    const TYPE_ANDROID = 'android';
    const TYPE_WEB = 'web';
    const TYPE_NTFY_ANDROID = 'ntfy_android';
    const TYPE_NTFY_IOS = 'ntfy_ios';
    const TYPE_NTFY_WEB = 'ntfy_web';

    /**
     * User who owns this device
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active devices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific device type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('device_type', $type);
    }

    /**
     * Scope for ntfy-enabled devices
     */
    public function scopeNtfyEnabled($query)
    {
        return $query->whereIn('device_type', [
            self::TYPE_NTFY_ANDROID,
            self::TYPE_NTFY_IOS,
            self::TYPE_NTFY_WEB
        ]);
    }

    /**
     * Update last active timestamp
     */
    public function updateLastActive(): void
    {
        $this->last_active_at = now();
        $this->save();
    }

    /**
     * Deactivate device (e.g., on logout)
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Activate device
     */
    public function activate(): void
    {
        $this->is_active = true;
        $this->last_active_at = now();
        $this->save();
    }

    /**
     * Get the ntfy subscription URL for this device
     * 
     * @return string Full subscription URL
     */
    public function getSubscriptionUrl(): string
    {
        $ntfyUrl = $_ENV['NTFY_URL'] ?? 'http://localhost:8090';
        return rtrim($ntfyUrl, '/') . '/' . $this->ntfy_topic;
    }
}
