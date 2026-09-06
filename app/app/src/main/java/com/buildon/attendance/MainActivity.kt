package com.buildon.attendance

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import com.buildon.attendance.data.Api
import com.buildon.attendance.data.Session
import com.buildon.attendance.ui.AttendanceScreen
import com.buildon.attendance.ui.BuildonTheme
import com.buildon.attendance.ui.LoginScreen
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val session = Session(applicationContext)

        setContent {
            BuildonTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    AttendanceApp(session)
                }
            }
        }
    }
}

@Composable
private fun AttendanceApp(session: Session) {
    val scope = rememberCoroutineScope()

    var signedIn by remember { mutableStateOf(session.isSignedIn) }
    var name by remember { mutableStateOf(session.name) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var message by remember { mutableStateOf<String?>(null) }
    var status by remember { mutableStateOf<Api.TodayStatus?>(null) }
    var projects by remember { mutableStateOf<List<Api.Project>>(emptyList()) }

    /** Runs an API call, funnelling failures into the one error slot the UI shows. */
    fun run(successMessage: String? = null, block: suspend (String) -> Unit) {
        val token = session.token
        if (token.isNullOrBlank()) {
            signedIn = false
            return
        }
        scope.launch {
            busy = true
            error = null
            message = null
            try {
                block(token)
                status = Api.today(token)
                message = successMessage
            } catch (e: Exception) {
                val text = e.message ?: "Something went wrong."
                // An expired or rejected token should return to the sign-in screen
                // rather than leaving the employee tapping a dead button.
                if (text.contains("token", ignoreCase = true) ||
                    text.contains("unauthor", ignoreCase = true)
                ) {
                    session.clear()
                    signedIn = false
                }
                error = text
            } finally {
                busy = false
            }
        }
    }

    // Load today's state whenever we become signed in.
    LaunchedEffect(signedIn) {
        if (!signedIn) return@LaunchedEffect
        val token = session.token ?: return@LaunchedEffect
        busy = true
        try {
            status = Api.today(token)
            projects = Api.projects(token)
        } catch (e: Exception) {
            error = e.message
        } finally {
            busy = false
        }
    }

    if (!signedIn) {
        LoginScreen(
            busy = busy,
            error = error,
            onSignIn = { empId, password ->
                scope.launch {
                    busy = true
                    error = null
                    try {
                        val (token, employee) = Api.login(empId, password)
                        session.save(token, employee)
                        name = employee.name
                        signedIn = true
                    } catch (e: Exception) {
                        error = e.message ?: "Sign in failed."
                    } finally {
                        busy = false
                    }
                }
            }
        )
    } else {
        AttendanceScreen(
            name = name,
            status = status,
            projects = projects,
            busy = busy,
            message = message,
            error = error,
            onClockIn = { projectId -> run("Clocked in") { Api.clockIn(it, projectId) } },
            onClockOut = { run("Clocked out") { Api.clockOut(it) } },
            onStartBreak = { run("Break started") { Api.startBreak(it) } },
            onEndBreak = { projectId -> run("Back to work") { Api.endBreak(it, projectId) } },
            onSwitchSite = { projectId ->
                run("Site switched") { Api.switchSite(it, projectId, isOffsite = false) }
            },
            onRefresh = { run { } },
            onSignOut = {
                session.clear()
                status = null
                projects = emptyList()
                signedIn = false
            },
            memoryOnly = session.isMemoryOnly
        )
    }
}
