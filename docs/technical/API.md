# eduCRM REST API Documentation

## Base URL
```
http://localhost/CRM/api/v1/
```

## Authentication

All endpoints except `/auth/login` require a Bearer token.

### Login
```http
POST /auth/login.php
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "name": "System Admin",
      "email": "admin@example.com",
      "roles": ["admin"]
    }
  }
}
```

---

## Using Authentication

Include the token in the Authorization header:
```http
Authorization: Bearer <your_token>
```

---

## Endpoints

### Dashboard

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/dashboard/index.php` | Get role-based KPIs | All authenticated |

**Response includes:**
- **Admin/Counselor:** Lead stats, inquiry count, students, visa pipeline, tasks, appointments
- **Teacher:** Assigned classes, today's rosters
- **Student:** Classes, visa status, fee balance, attendance, tasks
- **Accountant:** Revenue, outstanding balance, overdue invoices

---

### Inquiries

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/inquiries/index.php` | List inquiries | Admin, Counselor |
| GET | `/inquiries/index.php?id={id}` | Get single inquiry | Admin, Counselor |
| POST | `/inquiries/index.php` | Create inquiry | Admin, Counselor |
| PUT | `/inquiries/index.php?id={id}` | Update inquiry | Admin, Counselor |
| DELETE | `/inquiries/index.php?id={id}` | Delete inquiry | Admin only |

**Query Parameters (GET list):**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 20, max: 100)
- `status` - Filter by status (new, contacted, converted, closed)
- `priority` - Filter by priority (hot, warm, cold)
- `search` - Search in name, email, phone

**Create/Update Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "intended_country": "Australia",
  "intended_course": "IT",
  "education_level": "bachelors",
  "assigned_to": 2
}
```

---

### Students

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/students/index.php` | List students | Admin, Counselor |
| GET | `/students/index.php?id={id}` | Get student profile | Admin, Counselor, Self |

**Query Parameters (GET list):**
- `page` - Page number
- `per_page` - Items per page
- `search` - Search in name, email
- `country` - Filter by country

**Profile Response includes:**
- Basic info (name, email, phone, country)
- Enrollments (classes)
- Visa status
- Test scores (IELTS, PTE, SAT)
- Financial summary (fees, paid, balance)

---

## Response Format

### Success
```json
{
  "success": true,
  "data": { ... }
}
```

### Success (Paginated)
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "total": 100,
    "page": 1,
    "per_page": 20,
    "total_pages": 5
  }
}
```

### Error
```json
{
  "success": false,
  "error": "Error message"
}
```

---

## Error Codes

| Code | Meaning |
|------|---------|
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Missing/invalid token |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found |
| 405 | Method Not Allowed |
| 500 | Server Error |

---

## Rate Limiting

API endpoints are rate-limited to prevent abuse. Default limits:
- 100 requests per minute per user

---

*Last Updated: January 4, 2026*
