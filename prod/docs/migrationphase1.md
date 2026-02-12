# EduCRM Migration - Phase 1 Documentation

> **Phase**: Foundation Strengthening  
> **Version**: 1.0  
> **Date**: January 11, 2026  
> **Status**: In Progress

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

Phase 1 establishes the foundation for EduCRM's migration to Laravel by:

| Objective | Description | Priority |
|-----------|-------------|----------|
| **Centralized Routing** | Implement Slim Framework for URL routing | 🔴 High |
| **Front Controller** | Single entry point for all requests | 🔴 High |
| **Testing Infrastructure** | PHPUnit with service coverage | 🟡 Medium |
| **Coexistence** | Legacy modules continue working | 🔴 High |

---

## 2. Prerequisites

### System Requirements

```
PHP >= 8.0
Composer >= 2.0
Apache with mod_rewrite enabled
MySQL 8.0+
```

### Verify mod_rewrite

```apache
# In httpd.conf, ensure this line is uncommented:
LoadModule rewrite_module modules/mod_rewrite.so
```

---

## 3. Architecture Changes

### Before Phase 1

```
Browser Request
      │
      ▼
┌─────────────────────────────────────┐
│         Direct File Access          │
│  /modules/students/list.php         │
│  /modules/tasks/add.php             │
│  /api/v1/students/list.php          │
└─────────────────────────────────────┘
```

### After Phase 1

```
Browser Request
      │
      ├──────────────────────────┐
      ▼                          ▼
┌─────────────┐         ┌─────────────────────┐
│   Legacy    │         │   Slim Router       │
│   Modules   │         │   /public/index.php │
│   (still    │         │                     │
│   works)    │         │   /api/v1/*         │
└─────────────┘         └─────────────────────┘
                               │
                               ▼
                        ┌─────────────┐
                        │ Controllers │
                        │ (New)       │
                        └─────────────┘
```

---

## 4. Implementation Guide

### Step 1: Install Slim Framework

```bash
cd c:\xampp\htdocs\CRM
composer require slim/slim:^4.12 slim/psr7:^1.6 php-di/php-di:^7.0
```

**Expected output:**
```
- Installing slim/slim (4.12.x)
- Installing slim/psr7 (1.6.x)
- Installing php-di/php-di (7.0.x)
```

---

### Step 2: Create PHPUnit Configuration

Create `phpunit.xml` in project root:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         testdox="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">./app</directory>
        </include>
        <exclude>
            <directory>./app/Helpers</directory>
        </exclude>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_DATABASE" value="edu_crm_test"/>
    </php>
</phpunit>
```

---

### Step 3: Create Base Test Class

Create `tests/TestCase.php`:

```php
<?php
declare(strict_types=1);

namespace EduCRM\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use PDO;

abstract class TestCase extends PHPUnitTestCase
{
    protected static ?PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        // Initialize test database connection if needed
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                'mysql:host=localhost;dbname=edu_crm',
                'root',
                '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
    }

    protected function getPdo(): PDO
    {
        return self::$pdo;
    }
}
```

---

### Step 4: Create Base Controller

Create `app/Controllers/BaseController.php`:

```php
<?php
declare(strict_types=1);

namespace EduCRM\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController
{
    /**
     * Return JSON response
     */
    protected function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Return success response
     */
    protected function success(Response $response, $data = null, string $message = 'Success'): Response
    {
        return $this->json($response, [
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Return error response
     */
    protected function error(Response $response, string $message, int $status = 400): Response
    {
        return $this->json($response, [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $status
            ]
        ], $status);
    }
}
```

---

### Step 5: Create Apache Rewrite Rules

Create `public/.htaccess`:

```apache
RewriteEngine On

# Redirect to front controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

# Set environment
SetEnv APP_ENV production

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
```

---

### Step 6: Create Front Controller

Create `public/index.php`:

```php
<?php
declare(strict_types=1);

/**
 * EduCRM Slim Front Controller
 * Phase 1: Foundation Strengthening
 */

// Bootstrap application
require __DIR__ . '/../app/bootstrap.php';

use DI\Container;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Create Container
$container = new Container();
AppFactory::setContainer($container);

// Create App
$app = AppFactory::create();

// Add Error Middleware
$app->addErrorMiddleware(true, true, true);

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Load API Routes
require __DIR__ . '/../app/Routes/api.php';

// Health Check
$app->get('/health', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'status' => 'healthy',
        'version' => '1.0.0',
        'timestamp' => date('c')
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Run Application
$app->run();
```

---

### Step 7: Create API Routes

Create `app/Routes/api.php`:

```php
<?php
declare(strict_types=1);

/**
 * API Routes - Version 1
 * Migrated from legacy api/v1/
 */

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use EduCRM\Services\DashboardService;

/** @var App $app */

$app->group('/api/v1', function ($group) {
    
    // Priority Counts (migrated from api/priority_counts.php)
    $group->get('/priority-counts', function (Request $request, Response $response) {
        global $pdo;
        
        $dashboardService = new DashboardService($pdo);
        $stats = $dashboardService->getLeadPriorityStats();
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $stats
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Dashboard Stats
    $group->get('/dashboard/stats', function (Request $request, Response $response) {
        global $pdo;
        
        $dashboardService = new DashboardService($pdo);
        
        $data = [
            'total_students' => $dashboardService->getTotalStudentsCount(),
            'pending_tasks' => $dashboardService->getPendingTasksCount(),
            'hot_leads' => $dashboardService->getHotLeadsCount(),
            'active_visa' => $dashboardService->getActiveVisaProcessesCount(),
        ];
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    });
    
});
```

---

### Step 8: Create Service Unit Test

Create `tests/Unit/Services/DashboardServiceTest.php`:

```php
<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Services;

use EduCRM\Tests\TestCase;
use EduCRM\Services\DashboardService;

class DashboardServiceTest extends TestCase
{
    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardService($this->getPdo());
    }

    public function testGetTotalStudentsCountReturnsInteger(): void
    {
        $count = $this->service->getTotalStudentsCount();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testGetPendingTasksCountReturnsInteger(): void
    {
        $count = $this->service->getPendingTasksCount();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testGetHotLeadsCountReturnsInteger(): void
    {
        $count = $this->service->getHotLeadsCount();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testGetLeadPriorityStatsReturnsArray(): void
    {
        $stats = $this->service->getLeadPriorityStats();
        $this->assertIsArray($stats);
    }

    public function testGetRecentTasksReturnsArray(): void
    {
        $tasks = $this->service->getRecentTasks(5);
        $this->assertIsArray($tasks);
        $this->assertLessThanOrEqual(5, count($tasks));
    }
}
```

---

## 5. File Reference

### New Files Created

| File | Purpose |
|------|---------|
| `public/index.php` | Slim front controller |
| `public/.htaccess` | Apache rewrite rules |
| `app/Routes/api.php` | API route definitions |
| `app/Controllers/BaseController.php` | Base controller class |
| `phpunit.xml` | PHPUnit configuration |
| `tests/TestCase.php` | Base test class |
| `tests/Unit/Services/DashboardServiceTest.php` | Service unit tests |

### Modified Files

| File | Changes |
|------|---------|
| `composer.json` | Added Slim dependencies |

---

## 6. Testing

### Run All Tests

```bash
cd c:\xampp\htdocs\CRM
vendor\bin\phpunit
```

### Run Specific Test Suite

```bash
# Unit tests only
vendor\bin\phpunit --testsuite Unit

# With coverage report
vendor\bin\phpunit --coverage-html coverage
```

### Expected Test Output

```
PHPUnit 9.6.x

Dashboard Service Tests
 ✓ Get total students count returns integer
 ✓ Get pending tasks count returns integer
 ✓ Get hot leads count returns integer
 ✓ Get lead priority stats returns array
 ✓ Get recent tasks returns array

Time: 00:00.123, Memory: 10.00 MB

OK (5 tests, 10 assertions)
```

---

## 7. Troubleshooting

### Issue: 404 on /public/api/v1/*

**Cause**: mod_rewrite not enabled or AllowOverride not set.

**Solution**:
```apache
# In httpd.conf, find your DocumentRoot config and set:
<Directory "C:/xampp/htdocs">
    AllowOverride All
</Directory>
```

### Issue: Class not found errors

**Cause**: Composer autoload not updated.

**Solution**:
```bash
composer dump-autoload
```

### Issue: PDO connection failed in tests

**Cause**: Test database not configured.

**Solution**: Create `edu_crm_test` database or use the main database for initial testing.

---

## Completion Checklist

- [ ] Slim Framework installed via Composer
- [ ] PHPUnit configuration created
- [ ] Base test class created
- [ ] Base controller created
- [ ] Apache rewrite rules configured
- [ ] Front controller working
- [ ] API routes responding
- [ ] Service tests passing
- [ ] Legacy modules still functional

---

> **Next Phase**: Phase 2 - API-First Architecture  
> **Documentation**: See `docs/migrationplan.md` Section 5
