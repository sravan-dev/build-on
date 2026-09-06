package com.buildon.attendance.data

import com.buildon.attendance.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * Thin client over api.php. Deliberately HttpURLConnection + org.json rather than
 * Retrofit/Moshi: the surface is six endpoints and one envelope, and keeping the
 * dependency list short keeps the APK small and the build reproducible.
 *
 * Every response looks like:
 *   { "success": bool, "message": string, "data": {...}, "timestamp": "..." }
 */
object Api {

    class ApiException(message: String) : Exception(message)

    data class Employee(
        val name: String,
        val empId: String,
        val role: String
    )

    data class TodayStatus(
        val clockedIn: Boolean,
        val clockedOut: Boolean,
        val onBreak: Boolean,
        val inTime: String?,
        val outTime: String?,
        val workSite: String?,
        val projectId: Int?
    ) {
        /** What the employee is doing right now, for the headline. */
        val state: String
            get() = when {
                !clockedIn -> "Not Started"
                clockedOut -> "Completed"
                onBreak -> "On Break"
                else -> "Working"
            }
    }

    data class Project(val id: Int, val name: String)

    /** One stretch of work at one site. A day may have several. */
    data class SiteEntry(
        val id: Int,
        val siteName: String?,
        val timeIn: String?,
        val breakOut: String?,
        val breakIn: String?,
        val timeOut: String?,
        val hours: Double,
        val isOpen: Boolean,
        val onBreak: Boolean
    )

    /** Today across every site worked, plus what the worker may do next. */
    data class SiteDay(
        val entries: List<SiteEntry>,
        val openEntry: SiteEntry?,
        val sitesWorked: Int,
        val totalHours: Double,
        val breakHours: Double,
        val overtimeHours: Double,
        val canStartSite: Boolean,
        val canEndSite: Boolean,
        val canStartBreak: Boolean,
        val canEndBreak: Boolean
    ) {
        val state: String
            get() = when {
                openEntry == null && entries.isEmpty() -> "Not Started"
                openEntry == null -> "Between Sites"
                openEntry.onBreak -> "On Break"
                else -> "Working"
            }
    }

    private const val TIMEOUT_MS = 20_000

    private suspend fun call(
        endpoint: String,
        method: String,
        token: String? = null,
        body: JSONObject? = null
    ): JSONObject = withContext(Dispatchers.IO) {
        val url = URL("${BuildConfig.API_BASE}?endpoint=$endpoint")
        val conn = (url.openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = TIMEOUT_MS
            readTimeout = TIMEOUT_MS
            setRequestProperty("Accept", "application/json")
            token?.let { setRequestProperty("Authorization", "Bearer $it") }
            if (body != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json")
            }
        }

        try {
            if (body != null) {
                conn.outputStream.use { it.write(body.toString().toByteArray()) }
            }

            val stream = if (conn.responseCode in 200..299) conn.inputStream else conn.errorStream
            val text = stream?.bufferedReader()?.use(BufferedReader::readText).orEmpty()

            if (text.isBlank()) {
                throw ApiException("Server returned an empty response (HTTP ${conn.responseCode}).")
            }

            val json = try {
                JSONObject(text)
            } catch (e: Exception) {
                // A PHP fatal or an HTML error page rather than JSON.
                throw ApiException("Unexpected response from the server (HTTP ${conn.responseCode}).")
            }

            if (!json.optBoolean("success", false)) {
                throw ApiException(json.optString("message", "Request failed."))
            }
            json.optJSONObject("data") ?: JSONObject()
        } finally {
            conn.disconnect()
        }
    }

    suspend fun login(empId: String, password: String): Pair<String, Employee> {
        val data = call(
            endpoint = "login",
            method = "POST",
            body = JSONObject().put("emp_id", empId).put("password", password)
        )
        val token = data.optString("token")
        if (token.isBlank()) throw ApiException("Login succeeded but no token was returned.")

        val e = data.optJSONObject("employee") ?: JSONObject()
        return token to Employee(
            name = e.optString("name").ifBlank { empId },
            empId = e.optString("emp_id").ifBlank { empId },
            role = e.optString("role").ifBlank { "employee" }
        )
    }

    suspend fun today(token: String): TodayStatus {
        val d = call("attendance_today", "GET", token)
        return TodayStatus(
            clockedIn = d.optBoolean("clocked_in"),
            clockedOut = d.optBoolean("clocked_out"),
            onBreak = d.optBoolean("on_break"),
            inTime = d.optString("in_time").takeIf { it.isNotBlank() && it != "null" },
            outTime = d.optString("out_time").takeIf { it.isNotBlank() && it != "null" },
            workSite = d.optString("work_site").takeIf { it.isNotBlank() && it != "null" },
            projectId = d.optInt("project_id").takeIf { !d.isNull("project_id") && it > 0 }
        )
    }

    suspend fun projects(token: String): List<Project> {
        val d = call("projects", "GET", token)
        val arr = d.optJSONArray("projects") ?: return emptyList()
        return (0 until arr.length()).mapNotNull { i ->
            arr.optJSONObject(i)?.let { Project(it.optInt("id"), it.optString("name")) }
        }
    }

    suspend fun clockIn(token: String, projectId: Int?): String =
        call(
            "attendance", "POST", token,
            JSONObject().put("action", "clock_in").apply {
                if (projectId != null) put("project_id", projectId)
            }
        ).let { "Clocked in" }

    suspend fun clockOut(token: String): String =
        call("attendance", "POST", token, JSONObject().put("action", "clock_out"))
            .let { "Clocked out" }

    suspend fun startBreak(token: String): String =
        call("start_break", "POST", token, JSONObject()).let { "Break started" }

    suspend fun endBreak(token: String, projectId: Int?): String =
        call(
            "end_break", "POST", token,
            JSONObject().apply { if (projectId != null) put("project_id", projectId) }
        ).let { "Back to work" }

    suspend fun siteToday(token: String): SiteDay {
        val d = call("site_today", "GET", token)
        val arr = d.optJSONArray("entries")
        val entries = buildList {
            for (i in 0 until (arr?.length() ?: 0)) {
                val e = arr!!.optJSONObject(i) ?: continue
                add(
                    SiteEntry(
                        id = e.optInt("id"),
                        siteName = e.optString("site_name").takeIf { it.isNotBlank() && it != "null" },
                        timeIn = e.optString("time_in").takeIf { it.isNotBlank() && it != "null" },
                        breakOut = e.optString("break_out").takeIf { it.isNotBlank() && it != "null" },
                        breakIn = e.optString("break_in").takeIf { it.isNotBlank() && it != "null" },
                        timeOut = e.optString("time_out").takeIf { it.isNotBlank() && it != "null" },
                        hours = e.optDouble("working_hours", 0.0),
                        isOpen = e.optBoolean("is_open"),
                        onBreak = e.optBoolean("on_break")
                    )
                )
            }
        }
        return SiteDay(
            entries = entries,
            openEntry = entries.firstOrNull { it.isOpen },
            sitesWorked = d.optInt("sites_worked"),
            totalHours = d.optDouble("total_hours", 0.0),
            breakHours = d.optDouble("break_hours", 0.0),
            overtimeHours = d.optDouble("overtime_hours", 0.0),
            canStartSite = d.optBoolean("can_start_site"),
            canEndSite = d.optBoolean("can_end_site"),
            canStartBreak = d.optBoolean("can_start_break"),
            canEndBreak = d.optBoolean("can_end_break")
        )
    }

    suspend fun siteStart(token: String, projectId: Int?, siteName: String?) {
        call(
            "site_start", "POST", token,
            JSONObject().apply {
                if (projectId != null) put("project_id", projectId)
                if (!siteName.isNullOrBlank()) put("site_name", siteName)
            }
        )
    }

    suspend fun siteEnd(token: String) {
        call("site_end", "POST", token, JSONObject())
    }

    suspend fun siteBreak(token: String, start: Boolean) {
        call("site_break", "POST", token, JSONObject().put("action", if (start) "start" else "end"))
    }

    suspend fun switchSite(token: String, projectId: Int?, isOffsite: Boolean, note: String = ""): String =
        call(
            "switch_site", "POST", token,
            JSONObject()
                .put("is_offsite", isOffsite)
                .put("note", note)
                .apply { if (projectId != null) put("project_id", projectId) }
        ).let { "Site switched" }
}
