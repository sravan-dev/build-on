package com.buildon.attendance.ui

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.buildon.attendance.data.Api
import kotlinx.coroutines.delay
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AttendanceScreen(
    name: String,
    status: Api.TodayStatus?,
    projects: List<Api.Project>,
    busy: Boolean,
    message: String?,
    error: String?,
    onClockIn: (Int?) -> Unit,
    onClockOut: () -> Unit,
    onStartBreak: () -> Unit,
    onEndBreak: (Int?) -> Unit,
    onSwitchSite: (Int?) -> Unit,
    onRefresh: () -> Unit,
    onSignOut: () -> Unit
) {
    var selectedProject by remember(projects) { mutableStateOf(projects.firstOrNull()) }
    val state = status?.state ?: "…"

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Attendance", fontWeight = FontWeight.SemiBold) },
                actions = {
                    IconButton(onClick = onRefresh, enabled = !busy) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                    IconButton(onClick = onSignOut) {
                        Icon(Icons.Default.Logout, contentDescription = "Sign out")
                    }
                }
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .padding(padding)
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 20.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(8.dp))

            Text(
                text = name.ifBlank { "Employee" }.toTitleCase(),
                style = MaterialTheme.typography.headlineMedium,
                color = MaterialTheme.colorScheme.onBackground,
                textAlign = TextAlign.Center,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
                lineHeight = 30.sp,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 10.dp)
            )

            Spacer(Modifier.height(14.dp))

            StatusCard(state = state, status = status)

            Spacer(Modifier.height(18.dp))

            if (projects.isNotEmpty() && status?.clockedOut != true) {
                ProjectPicker(
                    projects = projects,
                    selected = selectedProject,
                    enabled = !busy,
                    onSelect = { selectedProject = it }
                )
                Spacer(Modifier.height(14.dp))
            }

            when {
                status == null -> Unit

                !status.clockedIn -> PrimaryAction(
                    label = "Clock In",
                    icon = Icons.Default.Login,
                    enabled = !busy,
                    onClick = { onClockIn(selectedProject?.id) }
                )

                status.clockedOut -> Text(
                    "Shift complete for today.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(vertical = 20.dp)
                )

                status.onBreak -> PrimaryAction(
                    label = "End Break",
                    icon = Icons.Default.PlayArrow,
                    enabled = !busy,
                    onClick = { onEndBreak(selectedProject?.id) }
                )

                else -> {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        SecondaryAction(
                            label = "Break",
                            icon = Icons.Default.LocalCafe,
                            enabled = !busy,
                            modifier = Modifier.weight(1f),
                            onClick = onStartBreak
                        )
                        SecondaryAction(
                            label = "Switch Site",
                            icon = Icons.Default.SwapHoriz,
                            enabled = !busy && selectedProject != null,
                            modifier = Modifier.weight(1f),
                            onClick = { onSwitchSite(selectedProject?.id) }
                        )
                    }
                    Spacer(Modifier.height(12.dp))
                    PrimaryAction(
                        label = "Clock Out",
                        icon = Icons.Default.Logout,
                        enabled = !busy,
                        containerColor = MaterialTheme.colorScheme.error,
                        onClick = onClockOut
                    )
                }
            }

            if (busy) {
                Spacer(Modifier.height(18.dp))
                CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(26.dp))
            }

            message?.let {
                Spacer(Modifier.height(18.dp))
                Text(it, color = StatusWorking, style = MaterialTheme.typography.bodyMedium)
            }
            error?.let {
                Spacer(Modifier.height(18.dp))
                Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium)
            }

            Spacer(Modifier.height(28.dp))
        }
    }
}

@Composable
private fun StatusCard(state: String, status: Api.TodayStatus?) {
    val accent = when (state) {
        "Working" -> StatusWorking
        "On Break" -> StatusBreak
        else -> StatusOff
    }

    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 24.dp, horizontal = 20.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                "TODAY'S STATUS",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Spacer(Modifier.height(6.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(state, fontSize = 30.sp, fontWeight = FontWeight.Bold)
                if (state == "Working" || state == "On Break") {
                    Spacer(Modifier.width(10.dp))
                    PulsingDot(accent)
                }
            }

            // Live counter, seeded from the clock-in time reported by the server.
            status?.inTime?.let { inTime ->
                Spacer(Modifier.height(14.dp))
                WorkedTimer(inTime = inTime, running = state == "Working")
            }

            status?.workSite?.let { site ->
                Spacer(Modifier.height(14.dp))
                Surface(
                    color = MaterialTheme.colorScheme.surfaceVariant,
                    shape = MaterialTheme.shapes.medium
                ) {
                    Column(
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 10.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text(
                            "Currently at",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Text(site, color = MaterialTheme.colorScheme.primary, fontWeight = FontWeight.SemiBold)
                        status.inTime?.let {
                            Text(
                                "Since ${it.take(5)}",
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
            }
        }
    }
}

/**
 * Counts up from the clock-in time. Only the seconds are added on the device;
 * the starting point comes from the server, which owns the timesheet.
 */
@Composable
private fun WorkedTimer(inTime: String, running: Boolean) {
    val startSeconds = remember(inTime) { parseClock(inTime) }
    var elapsed by remember(inTime) { mutableLongStateOf(secondsSinceMidnight() - startSeconds) }

    LaunchedEffect(inTime, running) {
        while (running) {
            elapsed = secondsSinceMidnight() - startSeconds
            delay(1000)
        }
    }

    val safe = elapsed.coerceAtLeast(0)
    Text(
        text = String.format(
            Locale.US, "%02d:%02d:%02d",
            safe / 3600, (safe % 3600) / 60, safe % 60
        ),
        fontSize = 32.sp,
        fontWeight = FontWeight.Bold,
        color = if (running) StatusWorking else MaterialTheme.colorScheme.onSurfaceVariant
    )
}

private fun parseClock(hhmmss: String): Long {
    val parts = hhmmss.split(":")
    val h = parts.getOrNull(0)?.toLongOrNull() ?: 0
    val m = parts.getOrNull(1)?.toLongOrNull() ?: 0
    val s = parts.getOrNull(2)?.toLongOrNull() ?: 0
    return h * 3600 + m * 60 + s
}

private fun secondsSinceMidnight(): Long {
    val now = java.util.Calendar.getInstance()
    return (now.get(java.util.Calendar.HOUR_OF_DAY) * 3600 +
            now.get(java.util.Calendar.MINUTE) * 60 +
            now.get(java.util.Calendar.SECOND)).toLong()
}

@Composable
private fun PulsingDot(color: Color) {
    val transition = rememberInfiniteTransition(label = "pulse")
    val alpha by transition.animateFloat(
        initialValue = 0.35f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(900), RepeatMode.Reverse),
        label = "alpha"
    )
    Box(
        Modifier
            .size(12.dp)
            .clip(CircleShape)
            .background(color.copy(alpha = alpha))
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ProjectPicker(
    projects: List<Api.Project>,
    selected: Api.Project?,
    enabled: Boolean,
    onSelect: (Api.Project) -> Unit
) {
    var expanded by remember { mutableStateOf(false) }

    ExposedDropdownMenuBox(
        expanded = expanded,
        onExpandedChange = { if (enabled) expanded = !expanded }
    ) {
        OutlinedTextField(
            value = selected?.name ?: "Select site",
            onValueChange = {},
            readOnly = true,
            enabled = enabled,
            label = { Text("Work site") },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded) },
            modifier = Modifier
                .menuAnchor(MenuAnchorType.PrimaryNotEditable)
                .fillMaxWidth()
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            projects.forEach { project ->
                DropdownMenuItem(
                    text = { Text(project.name) },
                    onClick = {
                        onSelect(project)
                        expanded = false
                    }
                )
            }
        }
    }
}

@Composable
private fun PrimaryAction(
    label: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    enabled: Boolean,
    containerColor: Color = MaterialTheme.colorScheme.primary,
    onClick: () -> Unit
) {
    Button(
        onClick = onClick,
        enabled = enabled,
        colors = ButtonDefaults.buttonColors(containerColor = containerColor),
        modifier = Modifier
            .fillMaxWidth()
            .height(60.dp)
    ) {
        Icon(icon, contentDescription = null)
        Spacer(Modifier.width(10.dp))
        Text(label, fontSize = 17.sp, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
private fun SecondaryAction(
    label: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    enabled: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit
) {
    OutlinedButton(
        onClick = onClick,
        enabled = enabled,
        modifier = modifier.height(54.dp)
    ) {
        Icon(icon, contentDescription = null, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(8.dp))
        Text(label)
    }
}

/**
 * Names are stored in upper case ("VINOTHARAJAH THAVARAJAH"), which shouts on a
 * phone screen and is harder to read. Render them as "Vinotharajah Thavarajah"
 * without touching the stored value.
 */
private fun String.toTitleCase(): String = trim()
    .split(" ")
    .filter { it.isNotBlank() }
    .joinToString(" ") { word ->
        word.split("-").joinToString("-") { part ->
            if (part.isEmpty()) part
            else part.substring(0, 1).uppercase(Locale.getDefault()) +
                 part.substring(1).lowercase(Locale.getDefault())
        }
    }
