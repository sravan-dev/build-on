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
import java.util.Calendar
import java.util.Locale

/**
 * A working day is a list of site entries, not a single shift. Finishing at one
 * site leaves the day open, so the worker can start at the next one — which is
 * the whole point of multi-site attendance.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SiteDayScreen(
    name: String,
    day: Api.SiteDay?,
    projects: List<Api.Project>,
    busy: Boolean,
    message: String?,
    error: String?,
    memoryOnly: Boolean,
    onStartSite: (Int?, String?) -> Unit,
    onEndSite: () -> Unit,
    onBreak: (Boolean) -> Unit,
    onRefresh: () -> Unit,
    onSignOut: () -> Unit
) {
    var selectedProject by remember(projects) { mutableStateOf(projects.firstOrNull()) }
    val state = day?.state ?: "…"

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
                    .padding(horizontal = 16.dp, vertical = 12.dp)
            )

            StatusCard(state = state, day = day)

            Spacer(Modifier.height(16.dp))

            // Site picker only matters when about to start somewhere.
            if (day?.canStartSite == true && projects.isNotEmpty()) {
                ProjectPicker(
                    projects = projects,
                    selected = selectedProject,
                    enabled = !busy,
                    onSelect = { selectedProject = it }
                )
                Spacer(Modifier.height(12.dp))
            }

            when {
                day == null -> Unit

                day.canStartSite -> PrimaryAction(
                    label = if (day.entries.isEmpty()) "Start Work" else "Start Next Site",
                    icon = Icons.Default.AddLocationAlt,
                    enabled = !busy,
                    onClick = { onStartSite(selectedProject?.id, selectedProject?.name) }
                )

                day.canEndBreak -> PrimaryAction(
                    label = "End Break",
                    icon = Icons.Default.PlayArrow,
                    enabled = !busy,
                    onClick = { onBreak(false) }
                )

                else -> {
                    if (day.canStartBreak) {
                        SecondaryAction(
                            label = "Start Break",
                            icon = Icons.Default.LocalCafe,
                            enabled = !busy,
                            modifier = Modifier.fillMaxWidth(),
                            onClick = { onBreak(true) }
                        )
                        Spacer(Modifier.height(12.dp))
                    }
                    PrimaryAction(
                        label = "Finish This Site",
                        icon = Icons.Default.Logout,
                        enabled = !busy,
                        containerColor = MaterialTheme.colorScheme.error,
                        onClick = onEndSite
                    )
                }
            }

            if (busy) {
                Spacer(Modifier.height(16.dp))
                CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(26.dp))
            }

            message?.let {
                Spacer(Modifier.height(16.dp))
                Text(it, color = StatusWorking, style = MaterialTheme.typography.bodyMedium)
            }
            error?.let {
                Spacer(Modifier.height(16.dp))
                Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium, textAlign = TextAlign.Center)
            }

            // Today's sites, so the worker can see what has been recorded.
            if (day != null && day.entries.isNotEmpty()) {
                Spacer(Modifier.height(24.dp))
                SitesToday(day)
            }

            if (memoryOnly) {
                Spacer(Modifier.height(20.dp))
                Text(
                    "Secure storage is unavailable on this device, so you will need " +
                        "to sign in again after closing the app.",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center
                )
            }

            Spacer(Modifier.height(28.dp))
        }
    }
}

@Composable
private fun StatusCard(state: String, day: Api.SiteDay?) {
    val accent = when (state) {
        "Working" -> StatusWorking
        "On Break" -> StatusBreak
        "Between Sites" -> BuildonAmber
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
                .padding(vertical = 22.dp, horizontal = 20.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                "TODAY'S STATUS",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Spacer(Modifier.height(6.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(state, fontSize = 28.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
                if (state == "Working" || state == "On Break") {
                    Spacer(Modifier.width(10.dp))
                    PulsingDot(accent)
                }
            }

            // Counter for the current site; otherwise the day's recorded total.
            val open = day?.openEntry
            Spacer(Modifier.height(12.dp))
            if (open?.timeIn != null) {
                LiveTimer(startClock = open.timeIn, running = state == "Working")
            } else if (day != null) {
                Text(
                    formatHours(day.totalHours),
                    fontSize = 30.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            if (open?.siteName != null) {
                Spacer(Modifier.height(12.dp))
                Surface(color = MaterialTheme.colorScheme.surfaceVariant, shape = MaterialTheme.shapes.medium) {
                    Column(
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 10.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text(
                            "Currently at",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Text(open.siteName, color = MaterialTheme.colorScheme.primary, fontWeight = FontWeight.SemiBold)
                        open.timeIn?.let {
                            Text(
                                "Since ${it.take(5)}",
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
            }

            if (day != null && day.entries.isNotEmpty()) {
                Spacer(Modifier.height(14.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(24.dp)) {
                    Stat("Sites", day.sitesWorked.toString())
                    Stat("Total", formatHours(day.totalHours))
                    if (day.overtimeHours > 0) Stat("Overtime", formatHours(day.overtimeHours), BuildonOrange)
                }
            }
        }
    }
}

@Composable
private fun Stat(label: String, value: String, color: Color = MaterialTheme.colorScheme.onSurface) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value, fontWeight = FontWeight.Bold, color = color)
    }
}

@Composable
private fun SitesToday(day: Api.SiteDay) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
    ) {
        Column(Modifier.padding(16.dp)) {
            Text(
                "SITES TODAY",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Spacer(Modifier.height(10.dp))

            day.entries.forEach { e ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(Modifier.weight(1f)) {
                        Text(
                            e.siteName ?: "Unnamed site",
                            fontWeight = FontWeight.SemiBold,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(
                            buildString {
                                append(e.timeIn?.take(5) ?: "--:--")
                                append(" – ")
                                append(e.timeOut?.take(5) ?: "now")
                                if (e.breakOut != null) {
                                    append("   break ")
                                    append(e.breakOut.take(5))
                                    append("-")
                                    append(e.breakIn?.take(5) ?: "…")
                                }
                            },
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                    Text(
                        if (e.isOpen) "open" else formatHours(e.hours),
                        fontWeight = FontWeight.Bold,
                        color = if (e.isOpen) StatusWorking else MaterialTheme.colorScheme.onSurface
                    )
                }
            }

            HorizontalDivider(Modifier.padding(vertical = 8.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Total", fontWeight = FontWeight.Bold)
                Text(formatHours(day.totalHours), fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
            }
        }
    }
}

private fun formatHours(hours: Double): String {
    val total = (hours * 3600).toLong()
    return String.format(Locale.US, "%02d:%02d", total / 3600, (total % 3600) / 60)
}

/** Counts up from a HH:mm:ss clock time reported by the server. */
@Composable
private fun LiveTimer(startClock: String, running: Boolean) {
    val start = remember(startClock) { parseClock(startClock) }
    var elapsed by remember(startClock) { mutableLongStateOf(secondsSinceMidnight() - start) }

    LaunchedEffect(startClock, running) {
        while (running) {
            elapsed = secondsSinceMidnight() - start
            delay(1000)
        }
    }

    val safe = elapsed.coerceAtLeast(0)
    Text(
        text = String.format(Locale.US, "%02d:%02d:%02d", safe / 3600, (safe % 3600) / 60, safe % 60),
        fontSize = 32.sp,
        fontWeight = FontWeight.Bold,
        color = if (running) StatusWorking else MaterialTheme.colorScheme.onSurfaceVariant
    )
}

private fun parseClock(hhmmss: String): Long {
    val p = hhmmss.split(":")
    return (p.getOrNull(0)?.toLongOrNull() ?: 0) * 3600 +
        (p.getOrNull(1)?.toLongOrNull() ?: 0) * 60 +
        (p.getOrNull(2)?.toLongOrNull() ?: 0)
}

private fun secondsSinceMidnight(): Long {
    val now = Calendar.getInstance()
    return (now.get(Calendar.HOUR_OF_DAY) * 3600 +
        now.get(Calendar.MINUTE) * 60 +
        now.get(Calendar.SECOND)).toLong()
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

    ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { if (enabled) expanded = !expanded }) {
        OutlinedTextField(
            value = selected?.name ?: "Select site",
            onValueChange = {},
            readOnly = true,
            enabled = enabled,
            label = { Text("Site") },
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
    OutlinedButton(onClick = onClick, enabled = enabled, modifier = modifier.height(54.dp)) {
        Icon(icon, contentDescription = null, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(8.dp))
        Text(label)
    }
}
