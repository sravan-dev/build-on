# BuildOn Mobile API Documentation

**Version:** 1.0  
**Base URL:** `http://your-domain.com/api.php`  
**Content-Type:** `application/json`

---

## Table of Contents

1. [Authentication](#authentication)
2. [Endpoints](#endpoints)
   - [Login](#1-employee-login)
   - [Get Profile](#2-get-employee-profile)
   - [Mark Attendance](#3-mark-attendance)
   - [Attendance History](#4-get-attendance-history)
   - [Apply for Leave](#5-apply-for-leave)
   - [Leave History](#6-get-leave-history)
   - [Today's Attendance](#7-get-todays-attendance-status)
   - [Salary Info](#8-get-salary-info)
3. [Response Format](#response-format)
4. [Error Codes](#error-codes)
5. [Example Code (Android/Java)](#example-code-androidjava)

---

## Authentication

This API uses token-based authentication. After successful login, you'll receive a token that must be included in subsequent requests.

### Token Usage

Include the token in the `Authorization` header:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

### Token Expiration

Tokens expire after 30 days of inactivity. After expiration, users must login again.

---

## Endpoints

### 1. Employee Login

**Endpoint:** `?endpoint=login`  
**Method:** `POST`  
**Authentication:** Not required

#### Request Body

```json
{
  "emp_id": "EMP001",
  "password": "employee_password"
}
```

#### Success Response (200)

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "base64_encoded_token",
    "employee": {
      "id": 1,
      "emp_id": "EMP001",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "1234567890",
      "position": "Developer",
      "department": "IT",
      "basic_salary": 5000.00,
      "status": "active"
    }
  },
  "timestamp": "2025-12-16 22:49:25"
}
```

#### Error Response (401)

```json
{
  "success": false,
  "message": "Invalid credentials",
  "data": null,
  "timestamp": "2025-12-16 22:49:25"
}
```

---

### 2. Get Employee Profile

**Endpoint:** `?endpoint=profile`  
**Method:** `GET`  
**Authentication:** Required

#### Headers

```
Authorization: Bearer YOUR_TOKEN
```

#### Success Response (200)

```json
{
  "success": true,
  "message": "Profile retrieved successfully",
  "data": {
    "id": 1,
    "emp_id": "EMP001",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "1234567890",
    "position": "Developer",
    "department": "IT",
    "basic_salary": 5000.00,
    "join_date": "2024-01-01",
    "status": "active"
  },
  "timestamp": "2025-12-16 22:49:25"
}
```

---

### 3. Mark Attendance

Clock in or clock out for the day.

**Endpoint:** `?endpoint=attendance`  
**Method:** `POST`  
**Authentication:** Required

#### Request Body

```json
{
  "action": "clock_in"
}
```

or

```json
{
  "action": "clock_out"
}
```

#### Success Response - Clock In (200)

```json
{
  "success": true,
  "message": "Clocked in successfully",
  "data": {
    "date": "2025-12-16",
    "time": "09:00:00",
    "action": "clock_in"
  },
  "timestamp": "2025-12-16 09:00:00"
}
```

#### Success Response - Clock Out (200)

```json
{
  "success": true,
  "message": "Clocked out successfully",
  "data": {
    "date": "2025-12-16",
    "in_time": "09:00:00",
    "out_time": "17:30:00",
    "working_hours": 8.5,
    "action": "clock_out"
  },
  "timestamp": "2025-12-16 17:30:00"
}
```

#### Error Response (400)

```json
{
  "success": false,
  "message": "Already clocked in for today",
  "data": null,
  "timestamp": "2025-12-16 22:49:25"
}
```

---

### 4. Get Attendance History

**Endpoint:** `?endpoint=attendance_history`  
**Method:** `GET`  
**Authentication:** Required

#### Query Parameters

| Parameter | Type   | Required | Default      | Description                    |
|-----------|--------|----------|--------------|--------------------------------|
| month     | string | No       | Current month| Format: YYYY-MM (e.g., 2025-12)|
| limit     | int    | No       | 30           | Maximum records to return      |

#### Example Request

```
GET api.php?endpoint=attendance_history&month=2025-12&limit=30
Authorization: Bearer YOUR_TOKEN
```

#### Success Response (200)

```json
{
  "success": true,
  "message": "Attendance history retrieved",
  "data": {
    "month": "2025-12",
    "records": [
      {
        "id": 45,
        "employee_id": 1,
        "attendance_date": "2025-12-16",
        "in_time": "09:00:00",
        "out_time": "17:30:00",
        "working_hours": 8.5,
        "status": "present",
        "remarks": null
      },
      {
        "id": 44,
        "employee_id": 1,
        "attendance_date": "2025-12-15",
        "in_time": "08:45:00",
        "out_time": "17:00:00",
        "working_hours": 8.25,
        "status": "present",
        "remarks": null
      }
    ],
    "total": 2
  },
  "timestamp": "2025-12-16 22:49:25"
}
```

---

### 5. Apply for Leave

Submit a leave application.

**Endpoint:** `?endpoint=leave_apply`  
**Method:** `POST`  
**Authentication:** Required

#### Request Body

```json
{
  "leave_type": "Annual Leave",
  "start_date": "2025-12-20",
  "end_date": "2025-12-22",
  "reason": "Family vacation"
}
```

#### Leave Types

- Annual Leave
- Sick Leave
- Emergency Leave
- Unpaid Leave
- Casual Leave

#### Success Response (200)

```json
{
  "success": true,
  "message": "Leave application submitted successfully",
  "data": {
    "leave_type": "Annual Leave",
    "start_date": "2025-12-20",
    "end_date": "2025-12-22",
    "days": 3,
    "status": "pending"
  },
  "timestamp": "2025-12-16 22:49:25"
}
```

---

### 6. Get Leave History

**Endpoint:** `?endpoint=leave_history`  
**Method:** `GET`  
**Authentication:** Required

#### Query Parameters

| Parameter | Type | Required | Default | Description              |
|-----------|------|----------|---------|--------------------------|
| limit     | int  | No       | 50      | Maximum records to return|

#### Example Request

```
GET api.php?endpoint=leave_history&limit=20
Authorization: Bearer YOUR_TOKEN
```

#### Success Response (200)

```json
{
  "success": true,
  "message": "Leave history retrieved",
  "data": {
    "leaves": [
      {
        "id": 12,
        "employee_id": 1,
        "leave_type": "Annual Leave",
        "start_date": "2025-12-20",
        "end_date": "2025-12-22",
        "days": 3,
        "reason": "Family vacation",
        "status": "pending",
        "applied_date": "2025-12-16"
      },
      {
        "id": 11,
        "employee_id": 1,
        "leave_type": "Sick Leave",
        "start_date": "2025-11-10",
        "end_date": "2025-11-11",
        "days": 2,
        "reason": "Medical checkup",
        "status": "approved",
        "applied_date": "2025-11-08"
      }
    ],
    "total": 2
  },
  "timestamp": "2025-12-16 22:49:25"
}
```

---

### 7. Get Today's Attendance Status

Check if employee has clocked in/out today.

**Endpoint:** `?endpoint=attendance_today`  
**Method:** `GET`  
**Authentication:** Required

#### Success Response (200)

```json
{
  "success": true,
  "message": "Today's attendance status",
  "data": {
    "clocked_in": true,
    "clocked_out": false,
    "in_time": "09:00:00",
    "out_time": null,
    "working_hours": null,
    "status": "present"
  },
  "timestamp": "2025-12-16 14:30:00"
}
```

---

### 8. Get Salary Info

Retrieve employee salary information.

**Endpoint:** `?endpoint=salary_info`  
**Method:** `GET`  
**Authentication:** Required

#### Success Response (200)

```json
{
  "success": true,
  "message": "Salary information retrieved",
  "data": {
    "basic_salary": 5000.00,
    "allowances": 1000.00,
    "position": "Developer",
    "department": "IT"
  },
  "timestamp": "2025-12-16 22:49:25"
}
```

---

## Response Format

All API responses follow this standard format:

```json
{
  "success": true/false,
  "message": "Descriptive message",
  "data": { ... } or null,
  "timestamp": "2025-12-16 22:49:25"
}
```

### Fields

- **success** (boolean): `true` if request was successful, `false` otherwise
- **message** (string): Human-readable message describing the result
- **data** (object|null): Response data or `null` if error
- **timestamp** (string): Server timestamp when response was generated

---

## Error Codes

| HTTP Code | Meaning                |
|-----------|------------------------|
| 200       | Success                |
| 400       | Bad Request            |
| 401       | Unauthorized           |
| 404       | Not Found              |
| 405       | Method Not Allowed     |
| 500       | Internal Server Error  |

### Common Error Messages

- `"Authorization token required"` - Missing token in header
- `"Invalid or expired token"` - Token is invalid or expired
- `"Method not allowed. Use POST"` - Wrong HTTP method
- `"Employee ID and password are required"` - Missing required fields
- `"Invalid credentials"` - Wrong username or password

---

## Example Code (Android/Java)

### 1. Login Request

```java
import okhttp3.*;
import org.json.JSONObject;

public class ApiClient {
    private static final String BASE_URL = "http://your-domain.com/api.php";
    private OkHttpClient client = new OkHttpClient();
    
    public void login(String empId, String password) throws Exception {
        JSONObject jsonBody = new JSONObject();
        jsonBody.put("emp_id", empId);
        jsonBody.put("password", password);
        
        RequestBody body = RequestBody.create(
            jsonBody.toString(),
            MediaType.parse("application/json")
        );
        
        Request request = new Request.Builder()
            .url(BASE_URL + "?endpoint=login")
            .post(body)
            .build();
        
        Response response = client.newCall(request).execute();
        String responseBody = response.body().string();
        
        JSONObject jsonResponse = new JSONObject(responseBody);
        if (jsonResponse.getBoolean("success")) {
            String token = jsonResponse.getJSONObject("data").getString("token");
            // Save token to SharedPreferences
            saveToken(token);
        }
    }
}
```

### 2. Authenticated Request (Clock In)

```java
public void clockIn(String token) throws Exception {
    JSONObject jsonBody = new JSONObject();
    jsonBody.put("action", "clock_in");
    
    RequestBody body = RequestBody.create(
        jsonBody.toString(),
        MediaType.parse("application/json")
    );
    
    Request request = new Request.Builder()
        .url(BASE_URL + "?endpoint=attendance")
        .addHeader("Authorization", "Bearer " + token)
        .post(body)
        .build();
    
    Response response = client.newCall(request).execute();
    String responseBody = response.body().string();
    
    JSONObject jsonResponse = new JSONObject(responseBody);
    // Handle response
}
```

### 3. GET Request (Attendance History)

```java
public void getAttendanceHistory(String token, String month) throws Exception {
    Request request = new Request.Builder()
        .url(BASE_URL + "?endpoint=attendance_history&month=" + month + "&limit=30")
        .addHeader("Authorization", "Bearer " + token)
        .get()
        .build();
    
    Response response = client.newCall(request).execute();
    String responseBody = response.body().string();
    
    JSONObject jsonResponse = new JSONObject(responseBody);
    if (jsonResponse.getBoolean("success")) {
        JSONArray records = jsonResponse.getJSONObject("data").getJSONArray("records");
        // Process records
    }
}
```

---

## Security Recommendations

### Production Deployment

1. **Use HTTPS**: Always use SSL/TLS encryption
2. **Use JWT**: Replace the simple base64 token with proper JWT tokens
3. **Password Hashing**: Ensure all passwords are hashed with bcrypt/argon2
4. **Rate Limiting**: Implement rate limiting to prevent abuse
5. **Input Validation**: Validate all input data
6. **SQL Injection**: Use prepared statements (already implemented)
7. **CORS**: Configure CORS properly for your domain only

### Token Security

Store tokens securely in Android:
- Use `SharedPreferences` with encryption
- Or use Android Keystore for sensitive data
- Clear tokens on logout

---

## Testing the API

### Using cURL

```bash
# Login
curl -X POST http://your-domain.com/api.php?endpoint=login \
  -H "Content-Type: application/json" \
  -d '{"emp_id":"EMP001","password":"password123"}'

# Get Profile
curl -X GET http://your-domain.com/api.php?endpoint=profile \
  -H "Authorization: Bearer YOUR_TOKEN"

# Clock In
curl -X POST http://your-domain.com/api.php?endpoint=attendance \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"action":"clock_in"}'
```

### Using Postman

1. Import the endpoints into Postman
2. Set up environment variables for `base_url` and `token`
3. Test each endpoint with sample data

---

## Change Log

### Version 1.0 (2025-12-16)
- Initial release
- Employee login
- Attendance management
- Leave management
- Profile and salary info

---

## Support

For API support and bug reports, contact the BuildOn development team.

**Documentation Last Updated:** December 16, 2025
