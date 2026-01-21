# EduCRM Migration - Phase 3 Documentation

> **Phase**: Mobile Readiness  
> **Version**: 3.1 (ntfy Integration)  
> **Date**: January 12, 2026  
> **Status**: Complete  
> **Duration**: 3-4 Weeks

---

## Table of Contents

1. [Objectives](#1-objectives)
2. [Prerequisites](#2-prerequisites)
3. [Architecture](#3-architecture)
4. [Implementation Guide](#4-implementation-guide)
5. [File Reference](#5-file-reference)
6. [Testing](#6-testing)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Objectives

Phase 3 establishes mobile readiness with a **self-hosted push notification system**:

| Objective | Description | Status |
|-----------|-------------|--------|
| **Push Notifications** | Self-hosted ntfy integration | ✅ Complete |
| **Mobile API Endpoints** | Optimized endpoints for mobile apps | ✅ Complete |
| **Device Registration** | Topic-based subscription management | ✅ Complete |
| **SDK Documentation** | iOS/Android integration guide | ✅ Complete |

### Why ntfy Instead of Firebase?

| Feature | ntfy | Firebase FCM |
|---------|------|--------------|
| **Self-Hosted** | ✅ Full control | ❌ Google servers |
| **Privacy** | ✅ Data stays local | ❌ Through Google |
| **Cost** | ✅ Free, no limits | ⚠️ Free tier limits |
| **Setup** | ✅ Simple Docker | ❌ Complex SDK |
| **Dependency** | ✅ None | ❌ Google account |

---

## 2. Prerequisites

### Installed Dependencies
```
✅ Eloquent ORM v9.52.16
✅ firebase/php-jwt v7.0.2
✅ JWT Authentication
✅ API v2 Endpoints
✅ Docker & Docker Compose
```

### New Components (Phase 3.1)
```
✅ ntfy server (Docker container)
✅ Topic-based push notifications
✅ Mobile app subscription flow
```

### Required Setup
- Docker Desktop or Docker Engine installed
- Port 8090 available for ntfy server
- PHP curl extension enabled

---

## 3. Architecture

### Push Notification Flow

```
┌─────────────────────────────────────┐
│          Mobile Apps                │
│    iOS / Android ntfy App           │
└──────────────┬──────────────────────┘
               │ Subscribe to topic
               ▼
┌─────────────────────────────────────┐
│       ntfy Server (Docker)          │
│      http://localhost:8090          │
└──────────────┬──────────────────────┘
               ▲ HTTP POST /topic
               │
┌──────────────┴──────────────────────┐
│      PushNotificationService        │
│   EduCRM PHP Backend                │
└─────────────────────────────────────┘
```

### Topic Structure

Each user gets a unique topic for notifications:

```
Format: {prefix}-user-{user_id}
Example: educrm-user-123
```

This allows:
- Direct notification to specific users
- No need for device token management
- User subscribes once in ntfy app

---

## 4. Implementation Guide

### Step 1: Start ntfy Server

```powershell
cd c:\xampp\htdocs\CRM
docker-compose -f docker-compose.ntfy.yml up -d
```

Verify: `http://localhost:8090/v1/health`

### Step 2: Configure Environment

Add to `.env`:
```env
NTFY_URL=http://localhost:8090
NTFY_ACCESS_TOKEN=
NTFY_TOPIC_PREFIX=educrm
```

### Step 3: Device Registration Flow

**Client App:**
1. User logs in via API
2. Call `POST /api/v2/devices/register`
3. Receive ntfy topic and server URL
4. Subscribe to topic in ntfy app

**API Response:**
```json
{
  "success": true,
  "data": {
    "device_id": 1,
    "ntfy_topic": "educrm-user-123",
    "ntfy_url": "http://localhost:8090",
    "subscription_url": "http://localhost:8090/educrm-user-123",
    "message": "Device registered. Subscribe to the topic in ntfy app."
  }
}
```

### Step 4: Sending Notifications

**From PHP:**
```php
use EduCRM\Services\PushNotificationService;

$push = new PushNotificationService();

// Send to specific user
$push->sendToUser($userId, 'New Task', 'You have a new task assigned');

// Send task notification (with click action)
$push->sendTaskNotification($userId, 'Complete Report', $taskId);

// Send with options
$push->send('custom-topic', 'Alert', 'Message', [
    'priority' => 5,  // urgent
    'tags' => ['warning', 'skull'],
    'click' => 'https://yourapp.com/view/123'
]);
```

### Step 5: Mobile App Setup

**Android/iOS:**
1. Install ntfy app from Play Store / App Store
2. Add server: `http://your-server-ip:8090`
3. Subscribe to topic: `educrm-user-{your_id}`

See [docs/ntfy-setup.md](ntfy-setup.md) for detailed instructions.

---

## 5. File Reference

### New/Modified Files

| File | Purpose | Status |
|------|---------|--------|
| `docker-compose.ntfy.yml` | ntfy Docker configuration | ✅ New |
| `ntfy/config/server.yml` | ntfy server settings | ✅ New |
| `app/Services/PushNotificationService.php` | ntfy notification service | ✅ Modified |
| `app/Models/Device.php` | Device Eloquent model | ✅ Modified |
| `app/Controllers/Api/DeviceController.php` | Device registration API | ✅ Modified |
| `database/migrations/create_user_devices.sql` | Device table migration | ✅ Modified |
| `docs/ntfy-setup.md` | Deployment guide | ✅ New |
| `tests/Unit/Services/PushNotificationServiceTest.php` | Unit tests | ✅ Modified |
| `.env` / `.env.example` | Environment config | ✅ Modified |

### Database Schema

**user_devices table:**
```sql
CREATE TABLE user_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ntfy_topic VARCHAR(100) NOT NULL,
    device_type ENUM('ios', 'android', 'web', 'ntfy_android', 'ntfy_ios', 'ntfy_web'),
    device_name VARCHAR(255),
    app_version VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    last_active_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## 6. Testing

### Run Unit Tests

```powershell
cd c:\xampp\htdocs\CRM
C:\xampp\php\php.exe vendor\bin\phpunit tests/Unit/Services/PushNotificationServiceTest.php --testdox
```

### Test Notification via CLI

```powershell
# Simple notification
curl -d "Test from EduCRM" http://localhost:8090/educrm-test

# With all options
curl -H "Title: Alert" -H "Priority: high" -H "Tags: bell,warning" -d "Test message" http://localhost:8090/educrm-test
```

### Test via API

```powershell
# Register device
curl -X POST http://localhost/CRM/api/v2/devices/register `
  -H "Authorization: Bearer <token>" `
  -H "Content-Type: application/json" `
  -d '{"device_type":"ntfy_android","device_name":"Test Phone"}'

# Send test notification
curl -X POST http://localhost/CRM/api/v2/devices/test-notification `
  -H "Authorization: Bearer <token>"
```

---

## 7. Troubleshooting

### Issue: ntfy container won't start

**Check logs:**
```powershell
docker logs educrm-ntfy
```

**Common fixes:**
- Port 8090 already in use: Change port in docker-compose.ntfy.yml
- Permission issues: Ensure ntfy/cache is writable

### Issue: No notifications on mobile

1. Verify ntfy server is accessible from device network
2. Check topic name matches exactly (case-sensitive)
3. Ensure ntfy app has notification permissions
4. Disable battery optimization for ntfy app

### Issue: PHP connection errors

```php
// Debug script
$ch = curl_init('http://localhost:8090/v1/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
echo "Error: " . curl_error($ch) . "\n";
echo "Result: " . $result . "\n";
```

### Issue: Authentication required

If you enabled auth on ntfy:
1. Create access token: `docker exec educrm-ntfy ntfy token add admin`
2. Add to `.env`: `NTFY_ACCESS_TOKEN=tk_xxx`

---

## Completion Checklist

- [x] Create Docker Compose for ntfy
- [x] Create ntfy server configuration
- [x] Refactor PushNotificationService for ntfy
- [x] Update Device model
- [x] Update DeviceController
- [x] Update database migrations
- [x] Update environment configuration
- [x] Create ntfy setup documentation
- [x] Update unit tests
- [x] Update Phase 3 documentation

---

> **Next Phase**: Phase 4 - Laravel Migration (OPTIONAL)  
> **Documentation**: See `docs/migrationplan.md` Section 7
