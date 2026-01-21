# EduCRM Migration - Phase 2 Documentation

> **Phase**: API-First Architecture  
> **Version**: 1.0  
> **Date**: January 11, 2026  
> **Status**: In Progress  
> **Duration**: 4-6 Weeks

---

## Table of Contents

1. [Objectives](#1-objectives)
2. [Prerequisites](#2-prerequisites)
3. [Architecture Changes](#3-architecture-changes)
4. [Implementation Guide](#4-implementation-guide)
5. [File Reference](#5-file-reference)
6. [Testing](#6-testing)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Objectives

Phase 2 establishes the API-first architecture by implementing:

| Objective | Description | Priority |
|-----------|-------------|----------|
| **Eloquent ORM** | Standalone Eloquent for database operations | 🔴 High |
| **JWT Authentication** | Stateless token-based auth for API | 🔴 High |
| **API v2 Endpoints** | Mobile-optimized API endpoints | 🔴 High |
| **Rate Limiting** | Protect API from abuse | 🟡 Medium |
| **API Documentation** | OpenAPI/Swagger docs | 🟡 Medium |

---

## 2. Prerequisites

### Installed Dependencies (Phase 1)
```
✅ Slim Framework 4.15.1
✅ PHP-DI 7.1.1
✅ slim/psr7 1.8.0
```

### New Dependencies (Phase 2)
```bash
# Eloquent ORM (standalone)
composer require illuminate/database illuminate/events

# JWT Authentication
composer require firebase/php-jwt
```

---

## 3. Architecture Changes

### Before Phase 2 (Current)
```
┌─────────────────────────────────────┐
│           Raw PDO Queries           │
│   $pdo->prepare("SELECT * FROM...")  │
└─────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│        Session-Based Auth           │
│    $_SESSION['user_id'] check       │
└─────────────────────────────────────┘
```

### After Phase 2
```
┌─────────────────────────────────────┐
│         Eloquent ORM Models         │
│   Student::with('enrollments')      │
└─────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│       JWT Token Authentication      │
│   Bearer eyJhbGciOiJIUzI1NiI...     │
└─────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│         API v2 Endpoints            │
│   /api/v2/students (JSON)           │
└─────────────────────────────────────┘
```

---

## 4. Implementation Guide

### Step 1: Install Eloquent ORM

```bash
cd c:\xampp\htdocs\CRM
php composer.phar require illuminate/database illuminate/events
```

---

### Step 2: Create Eloquent Bootstrap

Create `app/Database/Eloquent.php`:

```php
<?php
declare(strict_types=1);

namespace EduCRM\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

class Eloquent
{
    private static ?Capsule $capsule = null;

    public static function boot(): Capsule
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        self::$capsule = new Capsule;

        self::$capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $_ENV['DB_HOST'] ?? 'localhost',
            'database'  => $_ENV['DB_NAME'] ?? 'edu_crm',
            'username'  => $_ENV['DB_USER'] ?? 'root',
            'password'  => $_ENV['DB_PASS'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ]);

        self::$capsule->setEventDispatcher(
            new Dispatcher(new Container)
        );

        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();

        return self::$capsule;
    }

    public static function getCapsule(): ?Capsule
    {
        return self::$capsule;
    }
}
```

---

### Step 3: Create Eloquent Models

Create `app/Models/User.php`:

```php
<?php
declare(strict_types=1);

namespace EduCRM\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 
        'branch_id', 'is_active'
    ];

    protected $hidden = ['password'];

    public function roles()
    {
        return $this->belongsToMany(
            Role::class, 
            'user_roles', 
            'user_id', 
            'role_id'
        );
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
```

---

### Step 4: Install JWT Library

```bash
php composer.phar require firebase/php-jwt
```

---

### Step 5: Create JWT Authentication Service

Create `app/Services/JwtService.php`:

```php
<?php
declare(strict_types=1);

namespace EduCRM\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secretKey;
    private string $algorithm = 'HS256';
    private int $accessTokenTTL = 86400;    // 24 hours
    private int $refreshTokenTTL = 604800;  // 7 days

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?? $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    }

    public function generateTokens(int $userId, array $roles = []): array
    {
        $now = time();
        
        $accessPayload = [
            'iss' => 'educrm',
            'sub' => $userId,
            'roles' => $roles,
            'iat' => $now,
            'exp' => $now + $this->accessTokenTTL,
            'type' => 'access'
        ];

        $refreshPayload = [
            'iss' => 'educrm',
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->refreshTokenTTL,
            'type' => 'refresh'
        ];

        return [
            'access_token' => JWT::encode($accessPayload, $this->secretKey, $this->algorithm),
            'refresh_token' => JWT::encode($refreshPayload, $this->secretKey, $this->algorithm),
            'expires_in' => $this->accessTokenTTL,
            'token_type' => 'Bearer'
        ];
    }

    public function validateToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secretKey, $this->algorithm));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getUserIdFromToken(string $token): ?int
    {
        $payload = $this->validateToken($token);
        return $payload ? (int) $payload->sub : null;
    }
}
```

---

### Step 6: Create Auth Controller

Create `app/Controllers/Api/AuthController.php`:

```php
<?php
declare(strict_types=1);

namespace EduCRM\Controllers\Api;

use EduCRM\Controllers\BaseController;
use EduCRM\Services\JwtService;
use EduCRM\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends BaseController
{
    private JwtService $jwtService;

    public function __construct()
    {
        $this->jwtService = new JwtService();
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->error($response, 'Email and password required', 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !password_verify($password, $user->password)) {
            return $this->error($response, 'Invalid credentials', 401);
        }

        if (!$user->is_active) {
            return $this->error($response, 'Account is inactive', 403);
        }

        $roles = $user->roles->pluck('name')->toArray();
        $tokens = $this->jwtService->generateTokens($user->id, $roles);

        return $this->success($response, [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles
            ],
            'tokens' => $tokens
        ], 'Login successful');
    }

    public function refresh(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $refreshToken = $data['refresh_token'] ?? '';

        $payload = $this->jwtService->validateToken($refreshToken);

        if (!$payload || ($payload->type ?? '') !== 'refresh') {
            return $this->error($response, 'Invalid refresh token', 401);
        }

        $user = User::find($payload->sub);
        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        $roles = $user->roles->pluck('name')->toArray();
        $tokens = $this->jwtService->generateTokens($user->id, $roles);

        return $this->success($response, ['tokens' => $tokens]);
    }
}
```

---

### Step 7: Create JWT Middleware

Create `app/Middleware/JwtMiddleware.php`:

```php
<?php
declare(strict_types=1);

namespace EduCRM\Middleware;

use EduCRM\Services\JwtService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class JwtMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;

    public function __construct()
    {
        $this->jwtService = new JwtService();
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorizedResponse('No authorization header');
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Invalid authorization format');
        }

        $token = $matches[1];
        $payload = $this->jwtService->validateToken($token);

        if (!$payload) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        // Add user info to request attributes
        $request = $request->withAttribute('user_id', $payload->sub);
        $request = $request->withAttribute('user_roles', $payload->roles ?? []);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => ['message' => $message, 'code' => 'UNAUTHORIZED']
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
```

---

### Step 8: Create API v2 Routes

Update `app/Routes/api.php` to add v2 endpoints:

```php
// API v2 Routes (JWT Protected)
$app->group('/api/v2', function (RouteCollectorProxy $group) {
    
    // Public routes
    $group->post('/auth/login', [AuthController::class, 'login']);
    $group->post('/auth/refresh', [AuthController::class, 'refresh']);
    
})->add(new JwtMiddleware()); // Protected routes
```

---

## 5. File Reference

### New Files Created

| File | Purpose |
|------|---------|
| `app/Database/Eloquent.php` | Eloquent ORM bootstrap |
| `app/Models/User.php` | User Eloquent model |
| `app/Models/Student.php` | Student Eloquent model |
| `app/Models/Role.php` | Role Eloquent model |
| `app/Models/Branch.php` | Branch Eloquent model |
| `app/Services/JwtService.php` | JWT token generation/validation |
| `app/Controllers/Api/AuthController.php` | Authentication endpoints |
| `app/Middleware/JwtMiddleware.php` | JWT auth middleware |
| `tests/Unit/Services/JwtServiceTest.php` | JWT service tests |

---

## 6. Testing

### Run All Tests

```bash
php composer.phar require --dev firebase/php-jwt
C:\xampp\php\php.exe vendor\bin\phpunit --testdox
```

### Test JWT Authentication

```bash
# Login
curl -X POST http://localhost/CRM/public/api/v2/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@edu.crm","password":"password"}'

# Access protected endpoint
curl http://localhost/CRM/public/api/v2/students \
  -H "Authorization: Bearer <access_token>"
```

---

## 7. Troubleshooting

### Issue: Eloquent connection failed

**Solution**: Ensure `.env` has correct database credentials:
```
DB_HOST=localhost
DB_NAME=edu_crm
DB_USER=root
DB_PASS=
```

### Issue: JWT token invalid

**Solution**: Check `JWT_SECRET` in `.env` matches the one used for generation.

---

## Completion Checklist

- [ ] Install Eloquent ORM via Composer
- [ ] Create `app/Database/Eloquent.php` bootstrap
- [ ] Create Eloquent models (User, Student, Role, Branch)
- [ ] Install firebase/php-jwt
- [ ] Create `app/Services/JwtService.php`
- [ ] Create `app/Controllers/Api/AuthController.php`
- [ ] Create `app/Middleware/JwtMiddleware.php`
- [ ] Add API v2 routes
- [ ] Create JWT service tests
- [ ] Run tests and verify

---

> **Next Phase**: Phase 3 - Mobile Readiness  
> **Documentation**: See `docs/migrationplan.md` Section 6
