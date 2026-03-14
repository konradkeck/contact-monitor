<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = ['name', 'permissions'];

    protected $casts = ['permissions' => 'array'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $key): bool
    {
        return (bool) ($this->permissions[$key] ?? false);
    }

    // ── Default group definitions ──────────────────────────────────────────

    public static function defaultPermissions(): array
    {
        return [
            'Admin'    => self::adminPermissions(),
            'Analyst'  => self::analystPermissions(),
            'Viewer'   => self::viewerPermissions(),
        ];
    }

    public static function adminPermissions(): array
    {
        return [
            'browse_data'   => true,
            'data_write'    => true,
            'notes_write'   => true,
            'analyse'       => true,
            'configuration' => true,
        ];
    }

    public static function analystPermissions(): array
    {
        return [
            'browse_data'   => true,
            'data_write'    => false,
            'notes_write'   => true,
            'analyse'       => true,
            'configuration' => false,
        ];
    }

    public static function viewerPermissions(): array
    {
        return [
            'browse_data'   => true,
            'data_write'    => false,
            'notes_write'   => false,
            'analyse'       => false,
            'configuration' => false,
        ];
    }
}
