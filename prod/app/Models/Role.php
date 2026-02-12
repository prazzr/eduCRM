<?php
declare(strict_types=1);

namespace EduCRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role Model
 * 
 * @property int $id
 * @property string $name
 * @property string $description
 */
class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = ['name', 'description'];

    /**
     * Users with this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_roles',
            'role_id',
            'user_id'
        );
    }
}
