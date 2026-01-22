# Phase 3 Implementation Plan: Advanced Features (3 Weeks)

> **Timeline**: February 20, 2026 - March 12, 2026  
> **Priority**: 🟢 High Impact, High Effort  
> **Goal**: Enterprise-grade features for automation, security, and scalability  
> **Prerequisites**: Phase 1 & Phase 2 completed successfully

---

## 📋 Overview

Phase 3 delivers advanced enterprise features that differentiate EduCRM from basic CRM solutions. These features require careful implementation due to their complexity and security implications.

| # | Feature | Estimated Effort | Modules | Status |
|---|---------|-----------------|---------|--------|
| 1 | Role-Based Access Control (RBAC) Enhancement | 4 days | All Modules | ⬜ Not Started |
| 2 | Advanced Automation Rules Engine | 5 days | Automate | ⬜ Not Started |
| 3 | Real-Time Notifications System | 4 days | System-wide | ⬜ Not Started |
| 4 | API Rate Limiting & Security | 3 days | API | ⬜ Not Started |
| 5 | Audit Logging & Compliance | 3 days | System-wide | ⬜ Not Started |

**Total Estimated Effort**: 19 working days (with 2 days buffer)

---

## 1️⃣ Role-Based Access Control (RBAC) Enhancement

### 1.1 Problem Statement
Current permission system has limitations:
- Hard-coded role checks in PHP files
- No granular permissions (all or nothing per module)
- Cannot create custom roles
- No field-level access control
- Branch managers can't customize team permissions

### 1.2 Current State Analysis

```php
// Current approach (scattered throughout codebase)
if (!hasRole('admin')) {
    redirectWithAlert('/dashboard.php', 'Access denied', 'error');
}

// Or checking multiple roles
if (!in_array($_SESSION['role'], ['admin', 'counselor', 'branch_manager'])) {
    // Block access
}
```

### 1.3 Target State

```php
// New approach - permission-based
if (!can('students.edit')) {
    throw new AuthorizationException('Cannot edit students');
}

// With resource ownership check
if (!can('students.edit', $student)) {
    throw new AuthorizationException('Cannot edit this student');
}

// Field-level control
$visibleFields = getFieldPermissions('students', 'view');
```

### 1.4 Database Schema

**File**: `database/migrations/add_rbac_tables.sql`

```sql
-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    module VARCHAR(50),
    action ENUM('view', 'create', 'edit', 'delete', 'export', 'import', 'approve') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Roles table (extends existing or replaces)
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100),
    description VARCHAR(255),
    is_system BOOLEAN DEFAULT FALSE, -- Cannot be deleted
    branch_id INT NULL, -- NULL = global role
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
);

-- Role-Permission pivot table
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- User-Role pivot table (users can have multiple roles)
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Field-level permissions (optional granularity)
CREATE TABLE IF NOT EXISTS field_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    entity VARCHAR(50) NOT NULL, -- 'students', 'inquiries', etc.
    field_name VARCHAR(100) NOT NULL,
    can_view BOOLEAN DEFAULT TRUE,
    can_edit BOOLEAN DEFAULT TRUE,
    UNIQUE KEY unique_field_perm (role_id, entity, field_name),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Seed default permissions
INSERT INTO permissions (name, description, module, action) VALUES
-- Dashboard
('dashboard.view', 'View dashboard', 'dashboard', 'view'),
('dashboard.analytics', 'View analytics widgets', 'dashboard', 'view'),

-- Inquiries
('inquiries.view', 'View inquiries list', 'inquiries', 'view'),
('inquiries.view_all', 'View all inquiries (not just assigned)', 'inquiries', 'view'),
('inquiries.create', 'Create new inquiries', 'inquiries', 'create'),
('inquiries.edit', 'Edit inquiries', 'inquiries', 'edit'),
('inquiries.delete', 'Delete inquiries', 'inquiries', 'delete'),
('inquiries.export', 'Export inquiries', 'inquiries', 'export'),
('inquiries.import', 'Import inquiries', 'inquiries', 'import'),
('inquiries.assign', 'Assign inquiries to counselors', 'inquiries', 'edit'),

-- Students
('students.view', 'View students list', 'students', 'view'),
('students.view_all', 'View all students', 'students', 'view'),
('students.create', 'Create new students', 'students', 'create'),
('students.edit', 'Edit students', 'students', 'edit'),
('students.delete', 'Delete students', 'students', 'delete'),
('students.export', 'Export students', 'students', 'export'),
('students.import', 'Import students', 'students', 'import'),

-- Applications
('applications.view', 'View applications', 'applications', 'view'),
('applications.create', 'Create applications', 'applications', 'create'),
('applications.edit', 'Edit applications', 'applications', 'edit'),
('applications.delete', 'Delete applications', 'applications', 'delete'),
('applications.approve', 'Approve/reject applications', 'applications', 'approve'),

-- Documents
('documents.view', 'View documents', 'documents', 'view'),
('documents.upload', 'Upload documents', 'documents', 'create'),
('documents.delete', 'Delete documents', 'documents', 'delete'),
('documents.download', 'Download documents', 'documents', 'view'),

-- Tasks
('tasks.view', 'View tasks', 'tasks', 'view'),
('tasks.view_all', 'View all tasks', 'tasks', 'view'),
('tasks.create', 'Create tasks', 'tasks', 'create'),
('tasks.edit', 'Edit tasks', 'tasks', 'edit'),
('tasks.delete', 'Delete tasks', 'tasks', 'delete'),
('tasks.assign', 'Assign tasks to others', 'tasks', 'edit'),

-- Appointments
('appointments.view', 'View appointments', 'appointments', 'view'),
('appointments.view_all', 'View all appointments', 'appointments', 'view'),
('appointments.create', 'Create appointments', 'appointments', 'create'),
('appointments.edit', 'Edit appointments', 'appointments', 'edit'),
('appointments.delete', 'Delete appointments', 'appointments', 'delete'),

-- Visa
('visa.view', 'View visa workflows', 'visa', 'view'),
('visa.create', 'Create visa applications', 'visa', 'create'),
('visa.edit', 'Edit visa applications', 'visa', 'edit'),
('visa.delete', 'Delete visa applications', 'visa', 'delete'),
('visa.approve', 'Approve visa stages', 'visa', 'approve'),

-- Accounting
('accounting.view', 'View accounting', 'accounting', 'view'),
('accounting.create_invoice', 'Create invoices', 'accounting', 'create'),
('accounting.record_payment', 'Record payments', 'accounting', 'create'),
('accounting.edit', 'Edit financial records', 'accounting', 'edit'),
('accounting.delete', 'Delete financial records', 'accounting', 'delete'),
('accounting.export', 'Export financial reports', 'accounting', 'export'),

-- Reports
('reports.view', 'View reports', 'reports', 'view'),
('reports.export', 'Export reports', 'reports', 'export'),
('reports.financial', 'View financial reports', 'reports', 'view'),

-- Messaging
('messaging.view', 'View messaging', 'messaging', 'view'),
('messaging.send', 'Send messages', 'messaging', 'create'),
('messaging.bulk', 'Send bulk messages', 'messaging', 'create'),
('messaging.templates', 'Manage templates', 'messaging', 'edit'),

-- Users
('users.view', 'View users', 'users', 'view'),
('users.create', 'Create users', 'users', 'create'),
('users.edit', 'Edit users', 'users', 'edit'),
('users.delete', 'Delete users', 'users', 'delete'),
('users.manage_roles', 'Manage user roles', 'users', 'edit'),

-- Branches
('branches.view', 'View branches', 'branches', 'view'),
('branches.create', 'Create branches', 'branches', 'create'),
('branches.edit', 'Edit branches', 'branches', 'edit'),
('branches.delete', 'Delete branches', 'branches', 'delete'),

-- System Settings
('settings.view', 'View settings', 'settings', 'view'),
('settings.edit', 'Edit settings', 'settings', 'edit'),
('settings.automation', 'Manage automation rules', 'settings', 'edit'),

-- Audit
('audit.view', 'View audit logs', 'audit', 'view'),
('audit.export', 'Export audit logs', 'audit', 'export');

-- Seed default roles
INSERT INTO roles (name, display_name, description, is_system) VALUES
('super_admin', 'Super Administrator', 'Full system access', TRUE),
('admin', 'Administrator', 'Administrative access', TRUE),
('branch_manager', 'Branch Manager', 'Branch-level management', TRUE),
('counselor', 'Counselor', 'Student counseling and management', TRUE),
('accountant', 'Accountant', 'Financial operations only', TRUE),
('viewer', 'Viewer', 'Read-only access', TRUE);

-- Assign all permissions to super_admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM roles WHERE name = 'super_admin'),
    id
FROM permissions;
```

### 1.5 RBAC Service

**File**: `app/Services/RBACService.php` (NEW)

```php
<?php
namespace EduCRM\Services;

class RBACService
{
    private $pdo;
    private static $permissionCache = [];
    private static $roleCache = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check if user has specific permission
     */
    public function can(int $userId, string $permission, $resource = null): bool
    {
        $permissions = $this->getUserPermissions($userId);
        
        // Check exact permission
        if (in_array($permission, $permissions)) {
            // If resource provided, check ownership
            if ($resource !== null) {
                return $this->checkResourceAccess($userId, $permission, $resource);
            }
            return true;
        }

        // Check for wildcard permissions (e.g., 'students.*')
        $module = explode('.', $permission)[0];
        if (in_array("{$module}.*", $permissions)) {
            return true;
        }

        // Check for super permission
        if (in_array('*', $permissions)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has any of the given permissions
     */
    public function canAny(int $userId, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($userId, $permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function canAll(int $userId, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->can($userId, $permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all permissions for a user
     */
    public function getUserPermissions(int $userId): array
    {
        $cacheKey = "user_{$userId}";
        
        if (isset(self::$permissionCache[$cacheKey])) {
            return self::$permissionCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT p.name
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        
        $permissions = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        self::$permissionCache[$cacheKey] = $permissions;
        
        return $permissions;
    }

    /**
     * Get all roles for a user
     */
    public function getUserRoles(int $userId): array
    {
        $cacheKey = "user_roles_{$userId}";
        
        if (isset(self::$roleCache[$cacheKey])) {
            return self::$roleCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare("
            SELECT r.id, r.name, r.display_name
            FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        
        $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        self::$roleCache[$cacheKey] = $roles;
        
        return $roles;
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(int $userId, string $roleName): bool
    {
        $roles = $this->getUserRoles($userId);
        return in_array($roleName, array_column($roles, 'name'));
    }

    /**
     * Assign role to user
     */
    public function assignRole(int $userId, int $roleId, int $assignedBy = null): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by, assigned_at)
            VALUES (?, ?, ?, NOW())
        ");
        $result = $stmt->execute([$userId, $roleId, $assignedBy]);
        
        // Clear cache
        $this->clearUserCache($userId);
        
        return $result;
    }

    /**
     * Remove role from user
     */
    public function removeRole(int $userId, int $roleId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
        $result = $stmt->execute([$userId, $roleId]);
        
        // Clear cache
        $this->clearUserCache($userId);
        
        return $result;
    }

    /**
     * Sync user roles (replace all)
     */
    public function syncRoles(int $userId, array $roleIds, int $assignedBy = null): bool
    {
        $this->pdo->beginTransaction();
        
        try {
            // Remove existing roles
            $this->pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
            
            // Add new roles
            $stmt = $this->pdo->prepare("
                INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            foreach ($roleIds as $roleId) {
                $stmt->execute([$userId, $roleId, $assignedBy]);
            }
            
            $this->pdo->commit();
            $this->clearUserCache($userId);
            
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get all available permissions grouped by module
     */
    public function getAllPermissions(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, name, description, module, action
            FROM permissions
            ORDER BY module, action
        ");
        
        $permissions = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $perm) {
            $permissions[$perm['module']][] = $perm;
        }
        
        return $permissions;
    }

    /**
     * Get role with its permissions
     */
    public function getRoleWithPermissions(int $roleId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$role) return null;

        $stmt = $this->pdo->prepare("
            SELECT p.*
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$roleId]);
        $role['permissions'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $role;
    }

    /**
     * Update role permissions
     */
    public function updateRolePermissions(int $roleId, array $permissionIds): bool
    {
        $this->pdo->beginTransaction();
        
        try {
            // Clear existing permissions
            $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
            
            // Add new permissions
            $stmt = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissionIds as $permId) {
                $stmt->execute([$roleId, $permId]);
            }
            
            $this->pdo->commit();
            
            // Clear all permission caches
            self::$permissionCache = [];
            
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Create new role
     */
    public function createRole(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO roles (name, display_name, description, branch_id, is_system)
            VALUES (?, ?, ?, ?, FALSE)
        ");
        $stmt->execute([
            $data['name'],
            $data['display_name'],
            $data['description'] ?? null,
            $data['branch_id'] ?? null
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Check resource-level access (ownership)
     */
    private function checkResourceAccess(int $userId, string $permission, $resource): bool
    {
        // If user has 'view_all' or admin permission, allow
        $module = explode('.', $permission)[0];
        if ($this->can($userId, "{$module}.view_all")) {
            return true;
        }

        // Check ownership based on resource type
        if (is_array($resource)) {
            // Check common ownership fields
            $ownerFields = ['user_id', 'assigned_to', 'counselor_id', 'created_by'];
            foreach ($ownerFields as $field) {
                if (isset($resource[$field]) && $resource[$field] == $userId) {
                    return true;
                }
            }
        } elseif (is_object($resource)) {
            // Object-based check
            if (method_exists($resource, 'isOwnedBy')) {
                return $resource->isOwnedBy($userId);
            }
        }

        return false;
    }

    /**
     * Get field-level permissions for a role/entity
     */
    public function getFieldPermissions(int $roleId, string $entity): array
    {
        $stmt = $this->pdo->prepare("
            SELECT field_name, can_view, can_edit
            FROM field_permissions
            WHERE role_id = ? AND entity = ?
        ");
        $stmt->execute([$roleId, $entity]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Clear user cache
     */
    private function clearUserCache(int $userId): void
    {
        unset(self::$permissionCache["user_{$userId}"]);
        unset(self::$roleCache["user_roles_{$userId}"]);
    }
}
```

### 1.6 Helper Functions

**File**: `app/Helpers/rbac.php` (NEW)

```php
<?php
/**
 * RBAC Helper Functions
 * These are globally available shortcuts for permission checks
 */

use EduCRM\Services\RBACService;

/**
 * Check if current user has permission
 */
function can(string $permission, $resource = null): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    global $pdo;
    $rbac = new RBACService($pdo);
    return $rbac->can($_SESSION['user_id'], $permission, $resource);
}

/**
 * Check if current user has any of the permissions
 */
function canAny(array $permissions): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    global $pdo;
    $rbac = new RBACService($pdo);
    return $rbac->canAny($_SESSION['user_id'], $permissions);
}

/**
 * Check if current user has all permissions
 */
function canAll(array $permissions): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    global $pdo;
    $rbac = new RBACService($pdo);
    return $rbac->canAll($_SESSION['user_id'], $permissions);
}

/**
 * Require permission or throw exception
 */
function requirePermission(string $permission, $resource = null): void
{
    if (!can($permission, $resource)) {
        throw new \EduCRM\Exceptions\AuthorizationException(
            "You don't have permission to perform this action"
        );
    }
}

/**
 * Require any of the permissions
 */
function requireAnyPermission(array $permissions): void
{
    if (!canAny($permissions)) {
        throw new \EduCRM\Exceptions\AuthorizationException(
            "You don't have permission to perform this action"
        );
    }
}

/**
 * Check if current user has specific role
 */
function hasRole(string $role): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    global $pdo;
    $rbac = new RBACService($pdo);
    return $rbac->hasRole($_SESSION['user_id'], $role);
}

/**
 * Get current user's permissions (for UI rendering)
 */
function getUserPermissions(): array
{
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    global $pdo;
    $rbac = new RBACService($pdo);
    return $rbac->getUserPermissions($_SESSION['user_id']);
}
```

### 1.7 Role Management UI

**File**: `modules/users/roles/list.php` (NEW)

```php
<?php
require_once '../../../app/bootstrap.php';
requireLogin();
requirePermission('users.manage_roles');

$pageDetails = ['title' => 'Manage Roles'];
require_once '../../../templates/header.php';

$rbac = new \EduCRM\Services\RBACService($pdo);

// Get all roles
$roles = $pdo->query("
    SELECT r.*, 
           (SELECT COUNT(*) FROM user_roles WHERE role_id = r.id) as user_count,
           (SELECT COUNT(*) FROM role_permissions WHERE role_id = r.id) as permission_count
    FROM roles r
    ORDER BY r.is_system DESC, r.name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Roles & Permissions</h1>
        <p class="text-slate-500 text-sm">Manage user roles and their permissions</p>
    </div>
    <a href="add.php" class="btn btn-primary">
        <?php echo getIcon('plus', 16); ?> Create Role
    </a>
</div>

<div class="card">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase">Users</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase">Permissions</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($roles as $role): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <span class="font-medium text-slate-800"><?php echo htmlspecialchars($role['display_name']); ?></span>
                            <span class="text-slate-400 text-sm ml-2">(<?php echo htmlspecialchars($role['name']); ?>)</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <?php echo htmlspecialchars($role['description'] ?? '-'); ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-700 text-sm">
                                <?php echo $role['user_count']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 rounded-full bg-primary-100 text-primary-700 text-sm">
                                <?php echo $role['permission_count']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($role['is_system']): ?>
                                <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-medium">
                                    System
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">
                                    Custom
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="edit.php?id=<?php echo $role['id']; ?>" 
                               class="text-primary-600 hover:text-primary-700 font-medium text-sm">
                                Edit Permissions
                            </a>
                            <?php if (!$role['is_system']): ?>
                                <a href="delete.php?id=<?php echo $role['id']; ?>" 
                                   onclick="return confirm('Delete this role?')"
                                   class="text-red-600 hover:text-red-700 font-medium text-sm ml-4">
                                    Delete
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../../templates/footer.php'; ?>
```

### 1.8 Implementation Steps

#### Day 1: Database & Service
1. [ ] Run RBAC migration script
2. [ ] Create `RBACService.php`
3. [ ] Create `rbac.php` helpers
4. [ ] Write unit tests

#### Day 2: Migration Script
1. [ ] Create script to migrate existing users to new roles
2. [ ] Map current role column to user_roles table
3. [ ] Test migration on staging

#### Day 3: UI for Role Management
1. [ ] Create `modules/users/roles/list.php`
2. [ ] Create `modules/users/roles/edit.php` (permissions grid)
3. [ ] Create `modules/users/roles/add.php`
4. [ ] Add role assignment to user edit page

#### Day 4: Replace Existing Checks
1. [ ] Create search/replace for `hasRole()` → `can()`
2. [ ] Update all module entry points
3. [ ] Test all permission scenarios
4. [ ] Update documentation

### 1.9 Testing Criteria

| Test Case | Expected Result |
|-----------|-----------------|
| User with no roles | Access denied to all modules |
| Admin role | Full access |
| Counselor viewing own student | Allowed |
| Counselor viewing other's student | Denied (unless has view_all) |
| Remove permission from role | Immediate effect |
| Create custom role | Works and assignable |
| Delete role with users | Users lose that role's permissions |

---

## 2️⃣ Advanced Automation Rules Engine

### 2.1 Problem Statement
Current automation is limited:
- Only basic triggers (status change, date)
- No complex conditions
- No multi-action workflows
- Cannot chain automations
- No scheduling options

### 2.2 Target Capabilities

| Capability | Description |
|------------|-------------|
| Complex Conditions | AND/OR grouping, field comparisons |
| Multiple Actions | Execute several actions per trigger |
| Delays | Wait X minutes/hours/days before action |
| Branching | If-then-else logic |
| Templates | Pre-built automation templates |
| Activity Log | Track all automation executions |

### 2.3 Database Schema

**File**: `database/migrations/add_automation_engine.sql`

```sql
-- Automation rules (enhanced)
CREATE TABLE IF NOT EXISTS automation_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    entity_type ENUM('inquiry', 'student', 'task', 'appointment', 'visa', 'payment') NOT NULL,
    trigger_type ENUM('create', 'update', 'delete', 'field_change', 'date', 'schedule', 'webhook') NOT NULL,
    trigger_config JSON, -- Field-specific triggers, cron schedule, etc.
    conditions JSON, -- Complex condition groups
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0, -- Higher = runs first
    execution_limit INT DEFAULT NULL, -- NULL = unlimited
    execution_count INT DEFAULT 0,
    last_executed_at DATETIME,
    created_by INT,
    branch_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
);

-- Automation actions
CREATE TABLE IF NOT EXISTS automation_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    action_type ENUM(
        'update_field', 'send_email', 'send_sms', 'send_notification',
        'create_task', 'assign_user', 'add_tag', 'remove_tag',
        'webhook', 'delay', 'condition' -- for branching
    ) NOT NULL,
    action_config JSON, -- Action-specific configuration
    sequence INT DEFAULT 0, -- Execution order
    delay_minutes INT DEFAULT 0, -- Delay before this action
    condition_branch ENUM('main', 'true', 'false') DEFAULT 'main',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE
);

-- Automation execution queue
CREATE TABLE IF NOT EXISTS automation_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    action_id INT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    payload JSON, -- Snapshot of entity data at trigger time
    scheduled_at DATETIME NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    started_at DATETIME,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES automation_actions(id) ON DELETE CASCADE,
    INDEX idx_scheduled (scheduled_at, status),
    INDEX idx_status (status)
);

-- Automation execution logs
CREATE TABLE IF NOT EXISTS automation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    rule_name VARCHAR(255),
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    trigger_type VARCHAR(50),
    actions_executed JSON, -- Array of action results
    status ENUM('success', 'partial', 'failed') NOT NULL,
    duration_ms INT,
    error_message TEXT,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE SET NULL,
    INDEX idx_rule_date (rule_id, executed_at),
    INDEX idx_entity (entity_type, entity_id)
);

-- Automation templates
CREATE TABLE IF NOT EXISTS automation_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    entity_type VARCHAR(50),
    template_config JSON, -- Full rule configuration
    is_public BOOLEAN DEFAULT TRUE,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default templates
INSERT INTO automation_templates (name, description, category, entity_type, template_config) VALUES
('Welcome Email', 'Send welcome email when inquiry is created', 'onboarding', 'inquiry', 
 '{"trigger_type":"create","actions":[{"type":"send_email","template":"welcome_inquiry"}]}'),
('Lead Score Update', 'Update priority based on lead score', 'lead_management', 'inquiry',
 '{"trigger_type":"field_change","trigger_config":{"field":"lead_score"},"conditions":[{"field":"lead_score","operator":">=","value":80}],"actions":[{"type":"update_field","field":"priority","value":"hot"}]}'),
('Overdue Task Reminder', 'Send reminder for overdue tasks', 'productivity', 'task',
 '{"trigger_type":"date","trigger_config":{"field":"due_date","when":"after"},"actions":[{"type":"send_notification","message":"Task overdue: {{title}}"}]}'),
('Auto-Assign Counselor', 'Round-robin counselor assignment', 'lead_management', 'inquiry',
 '{"trigger_type":"create","actions":[{"type":"assign_user","strategy":"round_robin","role":"counselor"}]}');
```

### 2.4 Automation Engine Service

**File**: `app/Services/AutomationEngine.php` (NEW)

```php
<?php
namespace EduCRM\Services;

class AutomationEngine
{
    private $pdo;
    private $actionHandlers = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->registerDefaultHandlers();
    }

    /**
     * Trigger automation check for an entity event
     */
    public function trigger(string $entityType, string $triggerType, array $entity, array $oldEntity = []): void
    {
        $rules = $this->getMatchingRules($entityType, $triggerType);
        
        foreach ($rules as $rule) {
            if ($this->evaluateConditions($rule['conditions'], $entity, $oldEntity)) {
                $this->queueActions($rule, $entity);
                $this->incrementExecutionCount($rule['id']);
            }
        }
    }

    /**
     * Get rules matching entity and trigger type
     */
    private function getMatchingRules(string $entityType, string $triggerType): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ar.*, 
                   (SELECT JSON_ARRAYAGG(
                       JSON_OBJECT('id', aa.id, 'type', aa.action_type, 'config', aa.action_config, 
                                   'sequence', aa.sequence, 'delay', aa.delay_minutes)
                   ) FROM automation_actions aa WHERE aa.rule_id = ar.id ORDER BY aa.sequence) as actions
            FROM automation_rules ar
            WHERE ar.entity_type = ? 
            AND ar.trigger_type = ?
            AND ar.is_active = TRUE
            AND (ar.execution_limit IS NULL OR ar.execution_count < ar.execution_limit)
            ORDER BY ar.priority DESC
        ");
        $stmt->execute([$entityType, $triggerType]);
        
        return array_map(function($rule) {
            $rule['conditions'] = json_decode($rule['conditions'], true) ?? [];
            $rule['trigger_config'] = json_decode($rule['trigger_config'], true) ?? [];
            $rule['actions'] = json_decode($rule['actions'], true) ?? [];
            return $rule;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Evaluate complex conditions
     */
    private function evaluateConditions(array $conditions, array $entity, array $oldEntity = []): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Handle grouped conditions (AND/OR)
        if (isset($conditions['operator'])) {
            $operator = strtoupper($conditions['operator']);
            $groups = $conditions['groups'] ?? [];
            
            if ($operator === 'AND') {
                foreach ($groups as $group) {
                    if (!$this->evaluateConditions($group, $entity, $oldEntity)) {
                        return false;
                    }
                }
                return true;
            } elseif ($operator === 'OR') {
                foreach ($groups as $group) {
                    if ($this->evaluateConditions($group, $entity, $oldEntity)) {
                        return true;
                    }
                }
                return false;
            }
        }

        // Single condition
        if (isset($conditions['field'])) {
            return $this->evaluateSingleCondition($conditions, $entity, $oldEntity);
        }

        // Array of conditions (implicit AND)
        foreach ($conditions as $condition) {
            if (!$this->evaluateSingleCondition($condition, $entity, $oldEntity)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateSingleCondition(array $condition, array $entity, array $oldEntity): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        
        $entityValue = $entity[$field] ?? null;
        $oldValue = $oldEntity[$field] ?? null;

        switch ($operator) {
            case '=':
            case '==':
            case 'equals':
                return $entityValue == $value;
                
            case '!=':
            case 'not_equals':
                return $entityValue != $value;
                
            case '>':
            case 'greater_than':
                return $entityValue > $value;
                
            case '>=':
            case 'greater_or_equal':
                return $entityValue >= $value;
                
            case '<':
            case 'less_than':
                return $entityValue < $value;
                
            case '<=':
            case 'less_or_equal':
                return $entityValue <= $value;
                
            case 'contains':
                return str_contains((string)$entityValue, (string)$value);
                
            case 'starts_with':
                return str_starts_with((string)$entityValue, (string)$value);
                
            case 'ends_with':
                return str_ends_with((string)$entityValue, (string)$value);
                
            case 'in':
                return in_array($entityValue, (array)$value);
                
            case 'not_in':
                return !in_array($entityValue, (array)$value);
                
            case 'is_empty':
                return empty($entityValue);
                
            case 'is_not_empty':
                return !empty($entityValue);
                
            case 'changed':
                return $entityValue !== $oldValue;
                
            case 'changed_to':
                return $entityValue !== $oldValue && $entityValue == $value;
                
            case 'changed_from':
                return $entityValue !== $oldValue && $oldValue == $value;
                
            default:
                return false;
        }
    }

    /**
     * Queue actions for execution
     */
    private function queueActions(array $rule, array $entity): void
    {
        $now = new \DateTime();
        
        foreach ($rule['actions'] as $action) {
            $scheduledAt = clone $now;
            
            if ($action['delay'] > 0) {
                $scheduledAt->modify("+{$action['delay']} minutes");
            }

            // For immediate actions, execute directly
            if ($action['delay'] === 0) {
                $this->executeAction($action, $entity, $rule);
            } else {
                // Queue for later
                $stmt = $this->pdo->prepare("
                    INSERT INTO automation_queue 
                    (rule_id, action_id, entity_type, entity_id, payload, scheduled_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $rule['id'],
                    $action['id'],
                    $rule['entity_type'],
                    $entity['id'],
                    json_encode($entity),
                    $scheduledAt->format('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * Execute a single action
     */
    public function executeAction(array $action, array $entity, array $rule): array
    {
        $startTime = microtime(true);
        $handler = $this->actionHandlers[$action['type']] ?? null;
        
        if (!$handler) {
            return [
                'success' => false,
                'error' => "Unknown action type: {$action['type']}"
            ];
        }

        try {
            $config = json_decode($action['config'] ?? '{}', true) ?? [];
            $result = $handler($entity, $config, $this->pdo);
            
            $duration = (int)((microtime(true) - $startTime) * 1000);
            
            return [
                'success' => true,
                'action_type' => $action['type'],
                'duration_ms' => $duration,
                'result' => $result
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'action_type' => $action['type'],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Register default action handlers
     */
    private function registerDefaultHandlers(): void
    {
        // Update field
        $this->actionHandlers['update_field'] = function($entity, $config, $pdo) {
            $table = $this->getTableForEntity($entity['_entity_type'] ?? 'inquiry');
            $field = $config['field'];
            $value = $this->interpolate($config['value'], $entity);
            
            $stmt = $pdo->prepare("UPDATE {$table} SET {$field} = ? WHERE id = ?");
            return $stmt->execute([$value, $entity['id']]);
        };

        // Send email
        $this->actionHandlers['send_email'] = function($entity, $config, $pdo) {
            $templateService = new TemplateService($pdo);
            $emailService = new EmailService($pdo);
            
            $template = $templateService->getByName($config['template']);
            $content = $templateService->render($template, $entity);
            
            return $emailService->send(
                $entity['email'],
                $content['subject'],
                $content['body']
            );
        };

        // Send SMS
        $this->actionHandlers['send_sms'] = function($entity, $config, $pdo) {
            $messagingService = new MessagingService($pdo);
            $message = $this->interpolate($config['message'], $entity);
            
            return $messagingService->sendSms($entity['phone'], $message);
        };

        // Send notification
        $this->actionHandlers['send_notification'] = function($entity, $config, $pdo) {
            $notificationService = new NotificationService($pdo);
            $message = $this->interpolate($config['message'], $entity);
            $userId = $config['user_id'] ?? $entity['assigned_to'] ?? $entity['user_id'];
            
            return $notificationService->create($userId, $message, $config['type'] ?? 'info');
        };

        // Create task
        $this->actionHandlers['create_task'] = function($entity, $config, $pdo) {
            $taskService = new TaskService($pdo);
            
            return $taskService->create([
                'title' => $this->interpolate($config['title'], $entity),
                'description' => $this->interpolate($config['description'] ?? '', $entity),
                'assigned_to' => $config['assigned_to'] ?? $entity['assigned_to'],
                'due_date' => $config['due_date'] ?? date('Y-m-d', strtotime('+' . ($config['due_days'] ?? 3) . ' days')),
                'priority' => $config['priority'] ?? 'medium',
                'related_entity' => $entity['_entity_type'] ?? 'inquiry',
                'related_id' => $entity['id']
            ]);
        };

        // Assign user (round-robin)
        $this->actionHandlers['assign_user'] = function($entity, $config, $pdo) {
            $role = $config['role'] ?? 'counselor';
            $branchId = $entity['branch_id'] ?? null;
            
            // Get counselors ordered by least assignments
            $sql = "SELECT u.id 
                    FROM users u 
                    WHERE u.status = 'active'";
            
            if ($branchId) {
                $sql .= " AND u.branch_id = " . (int)$branchId;
            }
            
            $sql .= " ORDER BY (
                SELECT COUNT(*) FROM inquiries WHERE assigned_to = u.id AND status NOT IN ('converted', 'lost')
            ) ASC LIMIT 1";
            
            $counselorId = $pdo->query($sql)->fetchColumn();
            
            if ($counselorId) {
                $table = $this->getTableForEntity($entity['_entity_type'] ?? 'inquiry');
                $stmt = $pdo->prepare("UPDATE {$table} SET assigned_to = ? WHERE id = ?");
                return $stmt->execute([$counselorId, $entity['id']]);
            }
            
            return false;
        };

        // Webhook
        $this->actionHandlers['webhook'] = function($entity, $config, $pdo) {
            $url = $config['url'];
            $method = strtoupper($config['method'] ?? 'POST');
            $headers = $config['headers'] ?? [];
            $body = $config['body'] ?? $entity;
            
            if (is_string($body)) {
                $body = $this->interpolate($body, $entity);
            }
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                ['Content-Type: application/json'],
                $headers
            ));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return [
                'http_code' => $httpCode,
                'response' => $response
            ];
        };
    }

    /**
     * Interpolate variables in string
     */
    private function interpolate(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($data) {
            return $data[$matches[1]] ?? $matches[0];
        }, $template);
    }

    /**
     * Get table name for entity type
     */
    private function getTableForEntity(string $entityType): string
    {
        return match($entityType) {
            'inquiry' => 'inquiries',
            'student' => 'users',
            'task' => 'tasks',
            'appointment' => 'appointments',
            'visa' => 'visa_workflows',
            'payment' => 'payments',
            default => $entityType
        };
    }

    /**
     * Increment rule execution count
     */
    private function incrementExecutionCount(int $ruleId): void
    {
        $this->pdo->prepare("
            UPDATE automation_rules 
            SET execution_count = execution_count + 1, last_executed_at = NOW()
            WHERE id = ?
        ")->execute([$ruleId]);
    }

    /**
     * Register custom action handler
     */
    public function registerHandler(string $type, callable $handler): void
    {
        $this->actionHandlers[$type] = $handler;
    }
}
```

### 2.5 Queue Processor Cron

**File**: `cron/automation_queue_processor.php` (UPDATE)

```php
<?php
/**
 * Process pending automation queue items
 * Run every minute: * * * * * php /path/to/cron/automation_queue_processor.php
 */

require_once __DIR__ . '/../app/bootstrap.php';

$engine = new \EduCRM\Services\AutomationEngine($pdo);

// Get pending items that are due
$stmt = $pdo->query("
    SELECT aq.*, ar.name as rule_name, aa.action_type, aa.action_config
    FROM automation_queue aq
    JOIN automation_rules ar ON aq.rule_id = ar.id
    JOIN automation_actions aa ON aq.action_id = aa.id
    WHERE aq.status = 'pending'
    AND aq.scheduled_at <= NOW()
    AND aq.attempts < aq.max_attempts
    ORDER BY aq.scheduled_at ASC
    LIMIT 50
");

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($items as $item) {
    // Mark as processing
    $pdo->prepare("UPDATE automation_queue SET status = 'processing', started_at = NOW(), attempts = attempts + 1 WHERE id = ?")->execute([$item['id']]);
    
    $entity = json_decode($item['payload'], true);
    $action = [
        'id' => $item['action_id'],
        'type' => $item['action_type'],
        'config' => $item['action_config']
    ];
    $rule = ['id' => $item['rule_id'], 'name' => $item['rule_name']];
    
    $result = $engine->executeAction($action, $entity, $rule);
    
    if ($result['success']) {
        $pdo->prepare("UPDATE automation_queue SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$item['id']]);
    } else {
        $status = $item['attempts'] + 1 >= $item['max_attempts'] ? 'failed' : 'pending';
        $pdo->prepare("UPDATE automation_queue SET status = ?, error_message = ? WHERE id = ?")->execute([$status, $result['error'], $item['id']]);
    }
    
    // Log execution
    $pdo->prepare("
        INSERT INTO automation_logs (rule_id, rule_name, entity_type, entity_id, trigger_type, actions_executed, status, duration_ms, error_message)
        VALUES (?, ?, ?, ?, 'queued', ?, ?, ?, ?)
    ")->execute([
        $item['rule_id'],
        $item['rule_name'],
        $item['entity_type'],
        $item['entity_id'],
        json_encode([$result]),
        $result['success'] ? 'success' : 'failed',
        $result['duration_ms'] ?? 0,
        $result['error'] ?? null
    ]);
}

echo "Processed " . count($items) . " queue items\n";
```

### 2.6 Implementation Steps

#### Day 1-2: Database & Core Engine
1. [ ] Run automation migration
2. [ ] Create `AutomationEngine.php`
3. [ ] Implement condition evaluator
4. [ ] Implement action handlers
5. [ ] Unit tests

#### Day 3: Queue Processing
1. [ ] Create/update queue processor cron
2. [ ] Implement retry logic
3. [ ] Add execution logging
4. [ ] Test delayed actions

#### Day 4: Integration Points
1. [ ] Add triggers to Inquiry create/update
2. [ ] Add triggers to Student create/update
3. [ ] Add triggers to Task create/update
4. [ ] Add triggers to Appointment create/update

#### Day 5: UI
1. [ ] Create automation rule builder UI
2. [ ] Create condition builder (drag-drop)
3. [ ] Create action configuration forms
4. [ ] Add template gallery
5. [ ] Add execution logs viewer

---

## 3️⃣ Real-Time Notifications System

### 3.1 Problem Statement
Current notification system:
- Requires page refresh
- No browser notifications
- No sound alerts
- Limited customization
- No notification grouping

### 3.2 Technical Approach

**Option 1**: Server-Sent Events (SSE) - Simpler, one-way
**Option 2**: WebSocket - Two-way, more complex
**Option 3**: Polling with ntfy.sh integration - Already configured

**Recommended**: SSE + ntfy.sh for push notifications

### 3.3 SSE Implementation

**File**: `api/v1/notifications/stream.php` (NEW)

```php
<?php
/**
 * Server-Sent Events endpoint for real-time notifications
 */

require_once __DIR__ . '/../../../app/bootstrap.php';

// Check auth via token
$token = $_GET['token'] ?? '';
$userId = validateNotificationToken($token);

if (!$userId) {
    http_response_code(401);
    exit;
}

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx

// Disable output buffering
while (ob_get_level()) ob_end_clean();

$lastId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;

function sendEvent($data, $event = 'notification', $id = null) {
    if ($id) echo "id: {$id}\n";
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Send initial connection event
sendEvent(['status' => 'connected', 'user_id' => $userId], 'connected');

// Keep connection alive
$checkInterval = 3; // seconds
$timeout = 30; // seconds

$startTime = time();
while (time() - $startTime < $timeout) {
    // Check for new notifications
    $stmt = $pdo->prepare("
        SELECT id, message, type, link, created_at
        FROM notifications
        WHERE user_id = ? AND id > ? AND is_read = FALSE
        ORDER BY id ASC
        LIMIT 10
    ");
    $stmt->execute([$userId, $lastId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($notifications as $notification) {
        sendEvent($notification, 'notification', $notification['id']);
        $lastId = $notification['id'];
    }
    
    // Check for other events (task assignments, etc.)
    $events = checkForEvents($pdo, $userId, $lastId);
    foreach ($events as $event) {
        sendEvent($event['data'], $event['type'], $event['id']);
    }
    
    // Heartbeat
    sendEvent(['time' => time()], 'heartbeat');
    
    if (connection_aborted()) break;
    
    sleep($checkInterval);
}

function validateNotificationToken($token): ?int {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT user_id FROM notification_tokens 
        WHERE token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    return $stmt->fetchColumn() ?: null;
}

function checkForEvents($pdo, $userId, $lastId): array {
    // Check for real-time events like task assignments, mentions, etc.
    return [];
}
```

### 3.4 Client-Side Integration

**File**: `public/assets/js/notifications.js` (NEW)

```javascript
class NotificationManager {
    constructor(options = {}) {
        this.userId = options.userId;
        this.token = options.token;
        this.onNotification = options.onNotification || this.defaultHandler;
        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
        
        this.init();
    }

    init() {
        this.connect();
        this.requestBrowserPermission();
        this.bindUI();
    }

    connect() {
        const url = `/api/v1/notifications/stream.php?token=${this.token}`;
        this.eventSource = new EventSource(url);

        this.eventSource.onopen = () => {
            console.log('Notification stream connected');
            this.reconnectAttempts = 0;
            this.updateConnectionStatus('connected');
        };

        this.eventSource.addEventListener('notification', (event) => {
            const notification = JSON.parse(event.data);
            this.handleNotification(notification);
        });

        this.eventSource.addEventListener('heartbeat', (event) => {
            // Connection is alive
        });

        this.eventSource.onerror = () => {
            this.updateConnectionStatus('disconnected');
            this.eventSource.close();
            this.attemptReconnect();
        };
    }

    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Reconnecting... attempt ${this.reconnectAttempts}`);
            setTimeout(() => this.connect(), this.reconnectDelay);
        } else {
            console.error('Max reconnection attempts reached');
            this.updateConnectionStatus('failed');
        }
    }

    handleNotification(notification) {
        // Update badge count
        this.incrementBadge();
        
        // Add to dropdown
        this.addToDropdown(notification);
        
        // Show browser notification
        if (Notification.permission === 'granted') {
            this.showBrowserNotification(notification);
        }
        
        // Play sound
        this.playSound();
        
        // Show toast
        this.showToast(notification);
        
        // Custom handler
        this.onNotification(notification);
    }

    showBrowserNotification(notification) {
        const n = new Notification('EduCRM', {
            body: notification.message,
            icon: '/public/assets/images/icon-192.png',
            tag: `notification-${notification.id}`,
            requireInteraction: notification.type === 'urgent'
        });

        n.onclick = () => {
            window.focus();
            if (notification.link) {
                window.location.href = notification.link;
            }
            n.close();
        };

        // Auto-close after 5 seconds (except urgent)
        if (notification.type !== 'urgent') {
            setTimeout(() => n.close(), 5000);
        }
    }

    showToast(notification) {
        const toast = document.createElement('div');
        toast.className = `notification-toast notification-${notification.type}`;
        toast.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    ${this.getIcon(notification.type)}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800">${notification.message}</p>
                    <p class="text-xs text-slate-500 mt-1">${this.timeAgo(notification.created_at)}</p>
                </div>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;
        
        document.getElementById('notification-container').appendChild(toast);
        
        // Animate in
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Auto-remove
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    playSound() {
        const sound = document.getElementById('notification-sound');
        if (sound && localStorage.getItem('notification-sound') !== 'off') {
            sound.currentTime = 0;
            sound.play().catch(() => {}); // Ignore autoplay restrictions
        }
    }

    incrementBadge() {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            const current = parseInt(badge.textContent) || 0;
            badge.textContent = current + 1;
            badge.classList.remove('hidden');
        }
    }

    addToDropdown(notification) {
        const list = document.getElementById('notification-list');
        if (!list) return;

        const item = document.createElement('a');
        item.href = notification.link || '#';
        item.className = 'notification-item unread';
        item.dataset.id = notification.id;
        item.innerHTML = `
            <div class="notification-icon ${notification.type}">
                ${this.getIcon(notification.type)}
            </div>
            <div class="notification-content">
                <p>${notification.message}</p>
                <span class="notification-time">${this.timeAgo(notification.created_at)}</span>
            </div>
        `;
        
        list.insertBefore(item, list.firstChild);

        // Remove "no notifications" message
        const emptyState = list.querySelector('.empty-state');
        if (emptyState) emptyState.remove();
    }

    requestBrowserPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    updateConnectionStatus(status) {
        const indicator = document.getElementById('connection-status');
        if (indicator) {
            indicator.className = `connection-${status}`;
            indicator.title = status.charAt(0).toUpperCase() + status.slice(1);
        }
    }

    getIcon(type) {
        const icons = {
            info: '<svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
            success: '<svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
            warning: '<svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
            error: '<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
            urgent: '<svg class="w-5 h-5 text-red-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>'
        };
        return icons[type] || icons.info;
    }

    timeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        return `${Math.floor(seconds / 86400)}d ago`;
    }

    bindUI() {
        // Mark as read on click
        document.addEventListener('click', (e) => {
            const item = e.target.closest('.notification-item');
            if (item && item.classList.contains('unread')) {
                this.markAsRead(item.dataset.id);
                item.classList.remove('unread');
            }
        });

        // Mark all as read
        const markAllBtn = document.getElementById('mark-all-read');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }
    }

    async markAsRead(id) {
        await fetch(`/api/v1/notifications/${id}/read`, { method: 'POST' });
    }

    async markAllAsRead() {
        await fetch('/api/v1/notifications/read-all', { method: 'POST' });
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.classList.remove('unread');
        });
        const badge = document.getElementById('notification-badge');
        if (badge) {
            badge.textContent = '0';
            badge.classList.add('hidden');
        }
    }

    defaultHandler(notification) {
        console.log('New notification:', notification);
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (window.EDUCRM_USER_ID) {
        window.notificationManager = new NotificationManager({
            userId: window.EDUCRM_USER_ID,
            token: window.EDUCRM_NOTIFICATION_TOKEN
        });
    }
});
```

### 3.5 Implementation Steps

#### Day 1-2: Backend
1. [ ] Create SSE endpoint
2. [ ] Add notification token system
3. [ ] Create notification service enhancements
4. [ ] Test SSE connection stability

#### Day 3: Frontend
1. [ ] Create `notifications.js`
2. [ ] Add toast component
3. [ ] Implement browser notifications
4. [ ] Add sound support

#### Day 4: Integration
1. [ ] Add to header template
2. [ ] Integrate with ntfy.sh for mobile
3. [ ] Add notification preferences UI
4. [ ] End-to-end testing

---

## 4️⃣ API Rate Limiting & Security

### 4.1 Implementation

**File**: `app/Middleware/RateLimitMiddleware.php` (ENHANCE)

```php
<?php
namespace EduCRM\Middleware;

class RateLimitMiddleware
{
    private $limits = [
        'default' => ['requests' => 100, 'window' => 60],
        'auth' => ['requests' => 5, 'window' => 300],
        'api' => ['requests' => 1000, 'window' => 3600],
        'export' => ['requests' => 10, 'window' => 3600],
        'upload' => ['requests' => 20, 'window' => 60],
    ];

    public function handle(string $type = 'default'): bool
    {
        $key = $this->getKey($type);
        $limit = $this->limits[$type] ?? $this->limits['default'];
        
        $current = $this->getCurrentCount($key);
        
        if ($current >= $limit['requests']) {
            $this->sendRateLimitHeaders($limit, $current, 0);
            http_response_code(429);
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'retry_after' => $this->getRetryAfter($key)
            ]);
            exit;
        }
        
        $this->increment($key, $limit['window']);
        $this->sendRateLimitHeaders($limit, $current + 1, $limit['requests'] - $current - 1);
        
        return true;
    }

    private function getKey(string $type): string
    {
        $identifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
        return "rate_limit:{$type}:{$identifier}";
    }

    private function getCurrentCount(string $key): int
    {
        $file = __DIR__ . "/../../storage/rate_limits/" . md5($key) . ".json";
        
        if (!file_exists($file)) {
            return 0;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if ($data['expires'] < time()) {
            unlink($file);
            return 0;
        }
        
        return $data['count'];
    }

    private function increment(string $key, int $window): void
    {
        $file = __DIR__ . "/../../storage/rate_limits/" . md5($key) . ".json";
        $count = $this->getCurrentCount($key) + 1;
        
        file_put_contents($file, json_encode([
            'count' => $count,
            'expires' => time() + $window,
            'key' => $key
        ]));
    }

    private function getRetryAfter(string $key): int
    {
        $file = __DIR__ . "/../../storage/rate_limits/" . md5($key) . ".json";
        
        if (!file_exists($file)) {
            return 0;
        }
        
        $data = json_decode(file_get_contents($file), true);
        return max(0, $data['expires'] - time());
    }

    private function sendRateLimitHeaders(array $limit, int $current, int $remaining): void
    {
        header("X-RateLimit-Limit: {$limit['requests']}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: " . (time() + $limit['window']));
    }
}
```

### 4.2 Additional Security Measures

**File**: `app/Middleware/SecurityMiddleware.php` (NEW)

```php
<?php
namespace EduCRM\Middleware;

class SecurityMiddleware
{
    public function handle(): void
    {
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;");
        
        // HTTPS enforcement (production)
        if (getenv('APP_ENV') === 'production' && empty($_SERVER['HTTPS'])) {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
            exit;
        }
        
        // CSRF protection
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            $this->validateCsrfToken();
        }
        
        // Input sanitization
        $this->sanitizeInput();
    }

    private function validateCsrfToken(): void
    {
        // Skip for API requests with JWT
        if (str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            return;
        }
        
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('CSRF token mismatch');
        }
    }

    private function sanitizeInput(): void
    {
        array_walk_recursive($_GET, [$this, 'sanitizeValue']);
        array_walk_recursive($_POST, [$this, 'sanitizeValue']);
    }

    private function sanitizeValue(&$value): void
    {
        if (is_string($value)) {
            $value = trim($value);
            // Remove null bytes
            $value = str_replace(chr(0), '', $value);
        }
    }
}
```

---

## 5️⃣ Audit Logging & Compliance

### 5.1 Database Schema

**File**: `database/migrations/add_audit_logging.sql`

```sql
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_name VARCHAR(255),
    action VARCHAR(50) NOT NULL, -- create, update, delete, view, export, login, logout
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    entity_name VARCHAR(255),
    old_values JSON,
    new_values JSON,
    changes JSON, -- Only changed fields
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    session_id VARCHAR(128),
    request_url VARCHAR(500),
    request_method VARCHAR(10),
    duration_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    INDEX idx_user_action_date (user_id, action, created_at)
) ENGINE=InnoDB;

-- Audit log retention policy (auto-cleanup)
CREATE EVENT IF NOT EXISTS audit_log_cleanup
ON SCHEDULE EVERY 1 DAY
DO DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### 5.2 Audit Service

**File**: `app/Services/AuditService.php` (NEW)

```php
<?php
namespace EduCRM\Services;

class AuditService
{
    private $pdo;
    private static $startTime;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function startRequest(): void
    {
        self::$startTime = microtime(true);
    }

    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $entityName = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $changes = null;
        if ($oldValues && $newValues) {
            $changes = $this->calculateChanges($oldValues, $newValues);
        }

        $duration = self::$startTime ? (int)((microtime(true) - self::$startTime) * 1000) : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs (
                user_id, user_name, action, entity_type, entity_id, entity_name,
                old_values, new_values, changes,
                ip_address, user_agent, session_id, request_url, request_method, duration_ms
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['user_name'] ?? 'System',
            $action,
            $entityType,
            $entityId,
            $entityName,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $changes ? json_encode($changes) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            session_id(),
            substr($_SERVER['REQUEST_URI'] ?? '', 0, 500),
            $_SERVER['REQUEST_METHOD'] ?? null,
            $duration
        ]);
    }

    private function calculateChanges(array $old, array $new): array
    {
        $changes = [];
        
        // Sensitive fields to mask
        $sensitiveFields = ['password', 'token', 'secret', 'api_key'];
        
        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $old) || $old[$key] !== $value) {
                $oldVal = $old[$key] ?? null;
                $newVal = $value;
                
                // Mask sensitive fields
                if (in_array($key, $sensitiveFields)) {
                    $oldVal = $oldVal ? '[REDACTED]' : null;
                    $newVal = $newVal ? '[REDACTED]' : null;
                }
                
                $changes[$key] = [
                    'old' => $oldVal,
                    'new' => $newVal
                ];
            }
        }
        
        return $changes;
    }

    public function getEntityHistory(string $entityType, int $entityId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM audit_logs
            WHERE entity_type = ? AND entity_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$entityType, $entityId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getUserActivity(int $userId, string $startDate = null, string $endDate = null): array
    {
        $sql = "SELECT * FROM audit_logs WHERE user_id = ?";
        $params = [$userId];
        
        if ($startDate) {
            $sql .= " AND created_at >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND created_at <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 500";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSecurityEvents(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM audit_logs
            WHERE action IN ('login', 'logout', 'failed_login', 'password_change', 'permission_change', 'export')
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

### 5.3 Audit Log UI

**File**: `modules/reports/audit.php` (NEW)

```php
<?php
require_once '../../app/bootstrap.php';
requireLogin();
requirePermission('audit.view');

$pageDetails = ['title' => 'Audit Logs'];
require_once '../../templates/header.php';

$auditService = new \EduCRM\Services\AuditService($pdo);

// Filters
$filters = [
    'user_id' => $_GET['user_id'] ?? null,
    'action' => $_GET['action'] ?? null,
    'entity_type' => $_GET['entity_type'] ?? null,
    'start_date' => $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days')),
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
];

// Build query
$where = ['1=1'];
$params = [];

if ($filters['user_id']) {
    $where[] = 'user_id = ?';
    $params[] = $filters['user_id'];
}
if ($filters['action']) {
    $where[] = 'action = ?';
    $params[] = $filters['action'];
}
if ($filters['entity_type']) {
    $where[] = 'entity_type = ?';
    $params[] = $filters['entity_type'];
}
if ($filters['start_date']) {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $filters['start_date'];
}
if ($filters['end_date']) {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $filters['end_date'];
}

$sql = "SELECT * FROM audit_logs WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$users = $pdo->query("SELECT DISTINCT user_id, user_name FROM audit_logs WHERE user_id IS NOT NULL ORDER BY user_name")->fetchAll();
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$entities = $pdo->query("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Audit Logs</h1>
        <p class="text-slate-500 text-sm">Track all system activities and changes</p>
    </div>
    <?php if (can('audit.export')): ?>
    <a href="audit_export.php?<?php echo http_build_query($filters); ?>" class="btn btn-secondary">
        <?php echo getIcon('download', 16); ?> Export CSV
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4">
        <select name="user_id" class="form-input w-40">
            <option value="">All Users</option>
            <?php foreach ($users as $u): ?>
                <option value="<?php echo $u['user_id']; ?>" <?php echo $filters['user_id'] == $u['user_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['user_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select name="action" class="form-input w-32">
            <option value="">All Actions</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?php echo $a; ?>" <?php echo $filters['action'] === $a ? 'selected' : ''; ?>>
                    <?php echo ucfirst($a); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select name="entity_type" class="form-input w-36">
            <option value="">All Entities</option>
            <?php foreach ($entities as $e): ?>
                <option value="<?php echo $e; ?>" <?php echo $filters['entity_type'] === $e ? 'selected' : ''; ?>>
                    <?php echo ucfirst($e); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <input type="date" name="start_date" value="<?php echo $filters['start_date']; ?>" class="form-input w-36">
        <input type="date" name="end_date" value="<?php echo $filters['end_date']; ?>" class="form-input w-36">
        
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="audit.php" class="btn btn-secondary">Reset</a>
    </form>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Action</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Entity</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Changes</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($logs as $log): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <?php echo date('M j, g:i A', strtotime($log['created_at'])); ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="font-medium text-slate-800"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                            $actionColors = [
                                'create' => 'bg-green-100 text-green-700',
                                'update' => 'bg-blue-100 text-blue-700',
                                'delete' => 'bg-red-100 text-red-700',
                                'view' => 'bg-slate-100 text-slate-700',
                                'export' => 'bg-purple-100 text-purple-700',
                                'login' => 'bg-teal-100 text-teal-700',
                                'logout' => 'bg-orange-100 text-orange-700',
                            ];
                            $color = $actionColors[$log['action']] ?? 'bg-slate-100 text-slate-700';
                            ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $color; ?>">
                                <?php echo ucfirst($log['action']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="text-slate-600"><?php echo ucfirst($log['entity_type']); ?></span>
                            <?php if ($log['entity_name']): ?>
                                <br><span class="text-slate-400 text-xs"><?php echo htmlspecialchars($log['entity_name']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php if ($log['changes']): ?>
                                <?php $changes = json_decode($log['changes'], true); ?>
                                <button onclick="showChanges(<?php echo htmlspecialchars(json_encode($changes)); ?>)" 
                                        class="text-primary-600 hover:text-primary-700 text-xs">
                                    <?php echo count($changes); ?> field(s) changed
                                </button>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            <?php echo $log['ip_address']; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Changes Modal -->
<div id="changes-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[80vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-slate-800">Field Changes</h3>
            <button onclick="document.getElementById('changes-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="changes-content" class="p-6">
            <!-- Changes will be inserted here -->
        </div>
    </div>
</div>

<script>
function showChanges(changes) {
    const content = document.getElementById('changes-content');
    let html = '<table class="w-full text-sm"><thead><tr><th class="text-left pb-2 border-b">Field</th><th class="text-left pb-2 border-b">Old Value</th><th class="text-left pb-2 border-b">New Value</th></tr></thead><tbody>';
    
    for (const [field, values] of Object.entries(changes)) {
        html += `<tr>
            <td class="py-2 font-medium">${field}</td>
            <td class="py-2 text-red-600">${values.old ?? '<em>empty</em>'}</td>
            <td class="py-2 text-green-600">${values.new ?? '<em>empty</em>'}</td>
        </tr>`;
    }
    
    html += '</tbody></table>';
    content.innerHTML = html;
    document.getElementById('changes-modal').classList.remove('hidden');
}
</script>

<?php require_once '../../templates/footer.php'; ?>
```

---

## 📊 Phase 3 Summary

### Files to Create

| File | Description |
|------|-------------|
| `app/Services/RBACService.php` | Role-based access control |
| `app/Helpers/rbac.php` | Permission helper functions |
| `app/Services/AutomationEngine.php` | Automation rules engine |
| `app/Services/AuditService.php` | Audit logging |
| `app/Middleware/SecurityMiddleware.php` | Security headers & CSRF |
| `api/v1/notifications/stream.php` | SSE notification stream |
| `public/assets/js/notifications.js` | Real-time notification client |
| `modules/users/roles/list.php` | Role management UI |
| `modules/users/roles/edit.php` | Permission editor |
| `modules/reports/audit.php` | Audit log viewer |
| Database migrations (5 files) | Schema changes |

### Estimated Timeline

| Week | Days | Tasks |
|------|------|-------|
| Week 1 | Day 1-4 | RBAC Enhancement |
| Week 2 | Day 1-5 | Automation Engine |
| Week 3 | Day 1-4 | Real-Time Notifications |
| Week 3 | Day 5-7 | API Security + Audit Logging |

### Success Metrics

| Metric | Target |
|--------|--------|
| Permission check overhead | < 5ms |
| Automation execution time | < 500ms per rule |
| SSE connection stability | 99%+ uptime |
| API rate limit false positives | < 0.1% |
| Audit log query time | < 100ms |

### Security Improvements

- ✅ Granular permission system
- ✅ CSRF protection
- ✅ Security headers
- ✅ Rate limiting
- ✅ Comprehensive audit trails
- ✅ Input sanitization

---

## 🚀 Post-Phase 3: Production Readiness

After completing Phase 3:

1. **Security Audit** - Third-party penetration testing
2. **Performance Testing** - Load testing with production data volumes
3. **Documentation** - Complete API documentation with Swagger
4. **Training** - User training sessions for new features
5. **Backup Strategy** - Automated backups and disaster recovery plan
6. **Monitoring** - Set up application monitoring (errors, performance)

---

*Document Version: 1.0*  
*Created: January 22, 2026*  
*Author: Development Team*  
*Prerequisites: Phase 1 & Phase 2 Completed*
