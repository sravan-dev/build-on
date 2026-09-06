package com.buildon.attendance

import android.Manifest
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.ui.platform.LocalContext
import com.buildon.attendance.data.LocationProvider
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import com.buildon.attendance.data.Api
import com.buildon.attendance.data.Session
import com.buildon.attendance.ui.SiteDayScreen
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
    var day by remember { mutableStateOf<Api.SiteDay?>(null) }
    var projects by remember { mutableStateOf<List<Api.Project>>(emptyList()) }

    // Geofencing: a fenced site can only be started from inside it, so the app
    // needs a position before it can even ask.
    val context = LocalContext.current
    val locations = remember { LocationProvider(context) }
    var hasLocationPermission by remember { mutableStateOf(locations.hasPermission) }
    var pendingStart by remember { mutableStateOf<Pair<Int?, String?>?>(null) }

    val permissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { granted ->
        hasLocationPermission = granted.values.any { it }
        if (!hasLocationPermission) {
            error = "Location permission is required to start work at a site."
            pendingStart = null
        }
    }

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
                day = Api.siteToday(token)
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
            day = Api.siteToday(token)
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
        SiteDayScreen(
            name = name,
            day = day,
            projects = projects,
            busy = busy,
            message = message,
            error = error,
            memoryOnly = session.isMemoryOnly,
            hasLocationPermission = hasLocationPermission,
            onStartSite = { projectId, siteName ->
                if (!locations.hasPermission) {
                    pendingStart = projectId to siteName
                    permissionLauncher.launch(
                        arrayOf(
                            Manifest.permission.ACCESS_FINE_LOCATION,
                            Manifest.permission.ACCESS_COARSE_LOCATION
                        )
                    )
                } else if (!locations.isLocationEnabled) {
                    error = "Turn location on to start work at a site."
                } else {
                    run("Started at site") { token ->
                        // The server decides whether this position is inside the
                        // fence; the app only supplies it. A null here becomes a
                        // refusal server-side for a fenced site, which is the
                        // behaviour we want rather than a silent bypass.
                        val fix = locations.current()
                        Api.siteStart(token, projectId, siteName, fix?.latitude, fix?.longitude)
                    }
                }
            },
            onEndSite = { run("Site finished") { Api.siteEnd(it) } },
            onBreak = { start ->
                run(if (start) "Break started" else "Back to work") { Api.siteBreak(it, start) }
            },
            onRefresh = { run { } },
            onSignOut = {
                session.clear()
                day = null
                projects = emptyList()
                signedIn = false
            }
        )
    }
}
