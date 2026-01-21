# ntfy Self-Hosted Push Notification Setup Guide

> **Version**: 1.0  
> **Date**: January 12, 2026  
> **Status**: Ready for Deployment

---

## Table of Contents

1. [Overview](#1-overview)
2. [Prerequisites](#2-prerequisites)
3. [Server Installation](#3-server-installation)
4. [Configuration](#4-configuration)
5. [Mobile App Setup](#5-mobile-app-setup)
6. [Testing](#6-testing)
7. [Production Deployment](#7-production-deployment)
8. [Troubleshooting](#8-troubleshooting)
9. [API Reference](#9-api-reference)

---

## 1. Overview

ntfy (pronounced "notify") is a simple HTTP-based pub-sub notification service. Unlike Firebase Cloud Messaging (FCM), ntfy is:

| Feature | ntfy | Firebase FCM |
|---------|------|--------------|
| **Self-Hosted** | ✅ Yes | ❌ No |
| **No Third-Party** | ✅ Complete control | ❌ Google dependency |
| **Setup Complexity** | ✅ Simple | ❌ Complex |
| **Cost** | ✅ Free | ⚠️ Free tier limits |
| **Privacy** | ✅ All data on your server | ❌ Data through Google |

### How It Works

```
┌─────────────────┐      HTTP POST      ┌─────────────────┐
│    EduCRM       │ ─────────────────> │   ntfy Server   │
│    Backend      │                     │  (Docker)       │
└─────────────────┘                     └────────┬────────┘
                                                 │
                                        ┌────────┴────────┐
                                        │                 │
                                ┌───────▼───┐     ┌───────▼───┐
                                │  Android  │     │    iOS    │
                                │  ntfy App │     │  ntfy App │
                                └───────────┘     └───────────┘
```

---

## 2. Prerequisites

### Required
- **Docker** and **Docker Compose** installed
- **Port 8090** available (or configure custom port)
- **PHP curl extension** enabled (already included in XAMPP)

### Recommended
- **Domain name** with SSL for production (e.g., `ntfy.yourdomain.com`)
- **Reverse proxy** (nginx/Apache) for SSL termination

### Check Docker Installation
```powershell
docker --version
docker-compose --version
```

---

## 3. Server Installation

### Step 1: Navigate to Project Directory
```powershell
cd c:\xampp\htdocs\CRM
```

### Step 2: Create Directories (if not exists)
```powershell
mkdir ntfy\config -Force
mkdir ntfy\cache -Force
```

### Step 3: Start ntfy Server
```powershell
docker-compose -f docker-compose.ntfy.yml up -d
```

### Step 4: Verify Server is Running
```powershell
# Check container status
docker ps | findstr ntfy

# Check health endpoint
curl http://localhost:8090/v1/health
```

Expected output:
```json
{"healthy":true}
```

### Step 5: Access Web UI
Open in browser: `http://localhost:8090`

---

## 4. Configuration

### Environment Variables

Add to your `.env` file:

```env
# ntfy Push Notifications (Self-Hosted)
NTFY_URL=http://localhost:8090
NTFY_ACCESS_TOKEN=
NTFY_TOPIC_PREFIX=educrm
```

| Variable | Description | Default |
|----------|-------------|---------|
| `NTFY_URL` | ntfy server URL | `http://localhost:8090` |
| `NTFY_ACCESS_TOKEN` | Optional auth token | (empty) |
| `NTFY_TOPIC_PREFIX` | Prefix for all topics | `educrm` |

### Server Configuration

Edit `ntfy/config/server.yml` for advanced settings:

```yaml
# Base URL - Important for mobile apps
base-url: "http://localhost:8090"

# For production with domain:
# base-url: "https://ntfy.yourdomain.com"

# Enable authentication (optional)
# auth-file: "/var/cache/ntfy/user.db"
# auth-default-access: "deny-all"
```

### Enable Authentication (Optional)

For production, you may want to require authentication:

1. Enable auth in `server.yml`:
   ```yaml
   auth-file: "/var/cache/ntfy/user.db"
   auth-default-access: "deny-all"
   ```

2. Create admin user:
   ```powershell
   docker exec -it educrm-ntfy ntfy user add --role=admin admin
   ```

3. Create access token:
   ```powershell
   docker exec -it educrm-ntfy ntfy token add admin
   ```

4. Add token to `.env`:
   ```env
   NTFY_ACCESS_TOKEN=tk_xxxxxxxxxxxxxxxx
   ```

---

## 5. Mobile App Setup

### Android

1. **Download ntfy app** from:
   - Google Play Store: Search "ntfy"
   - F-Droid: https://f-droid.org/packages/io.heckel.ntfy/
   - GitHub Releases: https://github.com/binwiederhier/ntfy/releases

2. **Add Self-Hosted Server**:
   - Open ntfy app
   - Go to Settings → Add server
   - Enter your server URL: `http://your-server-ip:8090`
   - (Optional) Add username/password if auth enabled

3. **Subscribe to Topic**:
   - Tap "+" button
   - Enter topic: `educrm-user-{your_user_id}` (e.g., `educrm-user-1`)
   - Tap Subscribe

### iOS

1. **Download ntfy app** from App Store

2. **Add Self-Hosted Server**:
   - Settings → Server URL
   - Enter: `http://your-server-ip:8090`

3. **Subscribe to Topic**:
   - Same as Android

### Web Browser

1. Open `http://localhost:8090` in browser
2. Enter topic name and click "Subscribe"
3. Enable browser notifications when prompted

---

## 6. Testing

### Test via Command Line

```powershell
# Send test notification
curl -d "Hello from EduCRM!" http://localhost:8090/educrm-test

# With title and priority
curl -H "Title: Test Alert" -H "Priority: high" -H "Tags: bell" -d "This is a test notification" http://localhost:8090/educrm-test
```

### Test via PHP

```php
<?php
require_once 'app/bootstrap.php';

use EduCRM\Services\PushNotificationService;

$pushService = new PushNotificationService();

// Test to specific topic
$result = $pushService->sendTestNotification('test');
var_dump($result);

// Test to user
$result = $pushService->sendToUser(1, 'Test Notification', 'Hello from EduCRM!');
var_dump($result);
```

### Test via API Endpoint

```powershell
# First, get JWT token via login
$token = "your-jwt-token"

# Send test notification
curl -X POST http://localhost/CRM/api/v2/devices/test-notification `
  -H "Authorization: Bearer $token"
```

### Run Unit Tests

```powershell
cd c:\xampp\htdocs\CRM
C:\xampp\php\php.exe vendor\bin\phpunit tests/Unit/Services/PushNotificationServiceTest.php --testdox
```

---

## 7. Production Deployment

### With HTTPS (Recommended)

1. **Set up reverse proxy** (nginx example):

   ```nginx
   server {
       listen 443 ssl;
       server_name ntfy.yourdomain.com;
       
       ssl_certificate /path/to/cert.pem;
       ssl_certificate_key /path/to/key.pem;
       
       location / {
           proxy_pass http://localhost:8090;
           proxy_http_version 1.1;
           proxy_set_header Upgrade $http_upgrade;
           proxy_set_header Connection "upgrade";
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
       }
   }
   ```

2. **Update configuration**:

   ```yaml
   # ntfy/config/server.yml
   base-url: "https://ntfy.yourdomain.com"
   behind-proxy: true
   ```

3. **Update .env**:
   
   ```env
   NTFY_URL=https://ntfy.yourdomain.com
   ```

### Docker Resource Limits

```yaml
# docker-compose.ntfy.yml
services:
  ntfy:
    # ... existing config ...
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 256M
```

---

## 8. Troubleshooting

### Issue: Container Won't Start

**Check logs:**
```powershell
docker logs educrm-ntfy
```

**Common fixes:**
- Ensure port 8090 is not in use
- Check file permissions on ntfy/cache directory

### Issue: Notifications Not Received on Mobile

1. **Verify server is accessible** from mobile device
2. **Check topic name** matches exactly
3. **Disable battery optimization** for ntfy app (Android)
4. **Enable background refresh** (iOS)

### Issue: PHP Can't Connect to ntfy

**Check curl:**
```php
<?php
$ch = curl_init('http://localhost:8090/v1/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

echo "Result: " . $result . "\n";
echo "Error: " . $error . "\n";
```

### Issue: Authentication Failed

**Verify token:**
```powershell
docker exec -it educrm-ntfy ntfy token list
```

**Test with curl:**
```powershell
curl -H "Authorization: Bearer tk_xxx" http://localhost:8090/test
```

---

## 9. API Reference

### Send Notification

```
POST /{topic}
```

**Headers:**
| Header | Description | Example |
|--------|-------------|---------|
| `Title` | Notification title | `New Task` |
| `Priority` | 1-5 (min to urgent) | `4` |
| `Tags` | Emoji tags (comma-separated) | `bell,warning` |
| `Click` | URL to open on tap | `https://...` |
| `Attach` | Attachment URL | `https://...` |
| `Authorization` | Bearer token | `Bearer tk_xxx` |

**Body:** Plain text message

**Example:**
```bash
curl -H "Title: Alert" \
     -H "Priority: 5" \
     -H "Tags: warning,skull" \
     -d "Critical error occurred!" \
     http://localhost:8090/educrm-admin
```

### PHP Service Methods

```php
use EduCRM\Services\PushNotificationService;

$push = new PushNotificationService();

// Send to topic
$push->send('topic-name', 'Title', 'Body', [
    'priority' => 4,
    'tags' => ['bell'],
    'click' => 'https://...'
]);

// Send to user (auto-generates topic)
$push->sendToUser($userId, 'Title', 'Body');

// Convenience methods
$push->sendTaskNotification($userId, $taskTitle, $taskId);
$push->sendAppointmentReminder($userId, $clientName, $time);
$push->sendInquiryNotification($userId, $inquiryName, $inquiryId);
```

---

## Useful Links

- **ntfy Documentation**: https://docs.ntfy.sh/
- **ntfy GitHub**: https://github.com/binwiederhier/ntfy
- **Docker Hub**: https://hub.docker.com/r/binwiederhier/ntfy
- **Android App**: https://play.google.com/store/apps/details?id=io.heckel.ntfy
- **iOS App**: https://apps.apple.com/app/ntfy/id1625396347

---

> **Need Help?** Contact your system administrator or refer to the official ntfy documentation.
