# Buildon Attendance — Android

Minimal employee attendance app for Buildon Trading & Contracting W.L.L.
Talks to the existing `api.php` on the live site; there is no separate backend.

    app/                     this Gradle project
      app/src/main/java/com/buildon/attendance/
        MainActivity.kt      screen switching, one place where state lives
        data/Api.kt          the six endpoints this app uses
        data/Session.kt      remembers the signed-in employee
        ui/Theme.kt          colours sampled from logo.png
        ui/LoginScreen.kt
        ui/AttendanceScreen.kt

## Build

Needs JDK 17 and the Android SDK (platform 35).

    cd app
    gradle assembleDebug

The APK lands in `app/build/outputs/apk/debug/app-debug.apk`. Install with:

    adb install -r app/build/outputs/apk/debug/app-debug.apk

`local.properties` points at the SDK and is gitignored — recreate it on another
machine with `sdk.dir=/path/to/Android/Sdk`.

## Server it talks to

Set once, in `app/build.gradle.kts`:

    buildConfigField("String", "API_BASE", "\"https://login.buildonqatar.com/api.php\"")

## What it does

Sign in with employee ID and password, then: clock in (optionally choosing a
site), start and end a break, switch site, clock out. The status card shows the
current state, the site, and a live counter since clock-in.

The counter's starting point comes from the server's `in_time`, not the phone's
idea of when the shift began — device clocks drift, and this is a timesheet.

## Endpoints used

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `?endpoint=login` | POST | `{emp_id, password}` → token + employee |
| `?endpoint=attendance_today` | GET | current state, site, break flag |
| `?endpoint=projects` | GET | site list for the picker |
| `?endpoint=attendance` | POST | `{action: clock_in\|clock_out, project_id}` |
| `?endpoint=start_break` | POST | begin a break |
| `?endpoint=end_break` | POST | `{project_id}` resume work |
| `?endpoint=switch_site` | POST | `{project_id, is_offsite, note}` |

All except login send `Authorization: Bearer <token>`.

## Not included

No offline queue: every action needs a connection, and failures are shown
rather than retried silently — a clock-in that only exists on the phone is
worse than one that visibly failed. No push notifications, no leave screens,
no payslips, though the API exposes `leave_apply`, `leave_history` and
`salary_info` if those are wanted later.
