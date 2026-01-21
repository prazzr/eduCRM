<?php
declare(strict_types=1);

namespace EduCRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Student Model (extends User)
 * 
 * Represents students in the system (users with 'student' role)
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property int $branch_id
 */
class Student extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'branch_id',
        'is_active'
    ];

    protected $hidden = ['password'];

    /**
     * Boot the model to add student scope
     */
    protected static function boot()
    {
        parent::boot();

        // Only get users who are students (have student role)
        static::addGlobalScope('students', function ($query) {
            $query->whereHas('roles', function ($q) {
                $q->where('name', 'student');
            });
        });
    }

    /**
     * Student's branch
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Student's roles
     */
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles',
            'user_id',
            'role_id'
        );
    }

    /**
     * Student's class enrollments
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    /**
     * Student's tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }
}
