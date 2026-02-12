<?php
declare(strict_types=1);

namespace EduCRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Branch Model
 * 
 * @property int $id
 * @property string $name
 * @property string $address
 * @property string $phone
 */
class Branch extends Model
{
    protected $table = 'branches';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = ['name', 'address', 'phone', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Users in this branch
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Students in this branch
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'branch_id');
    }
}
