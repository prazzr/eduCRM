<?php
declare(strict_types=1);

/**
 * Global Helper Functions
 * 
 * Centralized location for all procedural helper functions.
 * Loaded by app/bootstrap.php
 */

use EduCRM\Helpers\RequestHelper;

// ============================================================================
// USER & AUTH HELPER FUNCTIONS
// ============================================================================

/**
 * Get UserHelper singleton instance for easy access
 */
function users(): \EduCRM\Helpers\UserHelper
{
    global $pdo;
    return \EduCRM\Helpers\UserHelper::getInstance($pdo);
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Require login or redirect
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

/**
 * Check if user has specific role(s)
 * Supports checking against a single role or array of roles
 */
function hasRole(string|array $role): bool
{
    $userRoles = $_SESSION['roles'] ?? [];
    if (isset($_SESSION['role'])) {
        $userRoles[] = $_SESSION['role'];
    }

    if (is_array($role)) {
        foreach ($role as $r) {
            if (in_array($r, $userRoles)) {
                return true;
            }
        }
        return false;
    }

    return in_array($role, $userRoles);
}

/**
 * Require specific roles or abort
 */
function requireRoles(array $allowed_roles): void
{
    requireLogin();

    foreach ($allowed_roles as $role) {
        if (hasRole($role)) {
            return;
        }
    }

    die("Unauthorized access.");
}

function requireAnyRole(array $roles, string $message = 'Access denied'): void
{
    requireLogin();
    if (!hasRole($roles)) {
        RequestHelper::abortWithError($message, 403);
    }
}

function requireAdmin(): void
{
    requireRoles(['admin']);
}

function requireAdminOrCounselor(): void
{
    requireRoles(['admin', 'counselor']);
}

function requireAdminOrTeacher(): void
{
    requireRoles(['admin', 'teacher']);
}

function requireBranchManager(): void
{
    requireRoles(['admin', 'branch_manager']);
}

function requireAnalyticsAccess(): void
{
    requireRoles(['admin', 'branch_manager', 'accountant']);
}

function requireFinanceAccess(): void
{
    requireRoles(['admin', 'branch_manager', 'accountant']);
}

function requireLMSManagementAccess(): void
{
    requireRoles(['admin', 'branch_manager', 'counselor', 'accountant']);
}

function requireCRMAccess(): void
{
    requireRoles(['admin', 'branch_manager', 'counselor', 'accountant']);
}

function requireAdminCounselorOrBranchManager(): void
{
    requireRoles(['admin', 'counselor', 'branch_manager']);
}

/**
 * Require that user is NOT a student (staff only)
 */
function requireStaff(): void
{
    requireLogin();
    if (hasRole('student') && !hasRole('admin') && !hasRole('counselor') && !hasRole('teacher')) {
        RequestHelper::abortWithError('Staff access required', 403);
    }
}

function requireStaffMember(): void
{
    requireLogin();
    if (hasRole('student') && !hasRole('admin') && !hasRole('counselor') && !hasRole('teacher') && !hasRole('branch_manager') && !hasRole('accountant')) {
        die("Access denied. Staff members only.");
    }
}

// ============================================================================
// DATA & LOOKUP FUNCTIONS
// ============================================================================

/**
 * Get LookupCacheService singleton
 */
function lookups(): \EduCRM\Services\LookupCacheService
{
    global $pdo;
    return \EduCRM\Services\LookupCacheService::getInstance($pdo);
}

/**
 * Get CrudHelper instance
 */
function crud(): \EduCRM\Helpers\CrudHelper
{
    global $pdo;
    static $instance = null;
    if ($instance === null) {
        $instance = new \EduCRM\Helpers\CrudHelper($pdo);
    }
    return $instance;
}

function getCountries(): array
{
    return lookups()->getAll('countries');
}

function getEducationLevels(): array
{
    return lookups()->getAll('education_levels');
}

function getPriorityLevels(): array
{
    return lookups()->getAll('priority_levels');
}

function getInquiryStatuses(): array
{
    return lookups()->getAll('inquiry_statuses');
}

function getVisaStages(): array
{
    return lookups()->getAll('visa_stages');
}

// ============================================================================
// SECURITY & CSRF FUNCTIONS
// ============================================================================

/**
 * Generate CSRF token if not exists
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output CSRF hidden input field
 */
function csrf_field(): void
{
    $token = csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Verify CSRF token from POST request
 */
function csrf_verify(bool $regenerate = false): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';

    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }

    $valid = hash_equals($_SESSION['csrf_token'], $token);

    if ($valid && $regenerate) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $valid;
}

/**
 * Require valid CSRF token or die
 */
function csrf_require(string $errorMessage = 'Invalid security token. Please try again.', ?string $redirectUrl = null): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!csrf_verify()) {
        if ($redirectUrl) {
            $_SESSION['error'] = $errorMessage;
            header("Location: $redirectUrl");
            exit;
        }
        http_response_code(403);
        die($errorMessage);
    }
}

function validateCsrf(bool $dieOnFail = true): bool
{
    if (!csrf_verify()) {
        if ($dieOnFail) {
            RequestHelper::abortWithError('Security validation failed. Please refresh and try again.', 403);
        }
        return false;
    }
    return true;
}

function generateSecurePassword($length = 12): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function sanitize($data): string
{
    return htmlspecialchars(strip_tags(trim((string) $data)));
}

// ============================================================================
// LOGGING & SYSTEM FUNCTIONS
// ============================================================================

function logAction($action, $details = ''): void
{
    global $pdo;
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Check if table exists or handle error silently if logging fails
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (Throwable $e) {
        // Silent fail
    }
}

function getBasePath(string $file): string
{
    $rootDir = realpath(APP_ROOT);
    $fileDir = realpath(dirname($file));
    $levels = substr_count(str_replace($rootDir, '', $fileDir), DIRECTORY_SEPARATOR);
    return str_repeat('../', $levels);
}

function requireIdParam(string $param = 'id'): int
{
    $id = isset($_GET[$param]) ? (int) $_GET[$param] : 0;
    if ($id <= 0) {
        die("Invalid ID");
    }
    return $id;
}

// ============================================================================
// UI & DISPLAY FUNCTIONS
// ============================================================================

function renderSelectOptions($items, $valueField, $labelField, $selectedValue = null): void
{
    foreach ($items as $item) {
        $selected = ($item[$valueField] == $selectedValue) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars((string) $item[$valueField]) . '" ' . $selected . '>' . htmlspecialchars((string) $item[$labelField]) . '</option>';
    }
}

function redirectWithAlert($url, $message, $type = 'success'): void
{
    $_SESSION['flash_msg'] = ['message' => $message, 'type' => $type];
    header("Location: " . $url);
    exit;
}

function renderFlashMessage(): void
{
    if (isset($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        $message = is_array($msg) ? $msg['message'] : $msg;
        $type = is_array($msg) ? ($msg['type'] ?? 'success') : 'success';

        $classMap = [
            'success' => 'alert-success',
            'warning' => 'alert-warning',
            'danger' => 'alert-danger'
        ];
        $alertClass = $classMap[$type] ?? 'alert-success';

        echo '<div class="alert-bar ' . $alertClass . '" style="margin-bottom: 20px; border-radius: 6px;">';
        echo '<span>' . $message . '</span>';
        echo '<a href="#" class="dismiss" onclick="this.parentElement.style.display=\'none\'; return false;">Dismiss</a>';
        echo '</div>';
        unset($_SESSION['flash_msg']);
    }
}
