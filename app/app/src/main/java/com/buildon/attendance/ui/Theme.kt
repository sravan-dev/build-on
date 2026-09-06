package com.buildon.attendance.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

// Sampled from logo.png: the amber of the mark's left half, the deeper orange of
// its right, and the grey of the "buildon" wordmark.
val BuildonAmber = Color(0xFFF5A623)
val BuildonOrange = Color(0xFFEE7B00)
val BuildonGrey = Color(0xFF58595B)
val BuildonInk = Color(0xFF2F3542)

val StatusWorking = Color(0xFF16A34A)
val StatusBreak = Color(0xFFD97706)
val StatusOff = Color(0xFF6B7280)

private val LightColors = lightColorScheme(
    primary = BuildonOrange,
    onPrimary = Color.White,
    primaryContainer = BuildonAmber,
    onPrimaryContainer = Color(0xFF3A2600),
    secondary = BuildonGrey,
    onSecondary = Color.White,
    background = Color(0xFFF4F5F7),
    onBackground = BuildonInk,
    surface = Color.White,
    onSurface = BuildonInk,
    surfaceVariant = Color(0xFFF0F1F3),
    onSurfaceVariant = BuildonGrey,
    error = Color(0xFFB91C1C)
)

private val DarkColors = darkColorScheme(
    primary = BuildonAmber,
    onPrimary = Color(0xFF2A1A00),
    primaryContainer = BuildonOrange,
    onPrimaryContainer = Color.White,
    secondary = Color(0xFFB9BBBE),
    background = Color(0xFF14161A),
    onBackground = Color(0xFFE7E8EA),
    surface = Color(0xFF1D2025),
    onSurface = Color(0xFFE7E8EA),
    surfaceVariant = Color(0xFF272B31),
    onSurfaceVariant = Color(0xFFB9BBBE),
    error = Color(0xFFF87171)
)

private val BuildonTypography = Typography(
    displayLarge = TextStyle(fontSize = 44.sp, fontWeight = FontWeight.Bold),
    headlineMedium = TextStyle(fontSize = 24.sp, fontWeight = FontWeight.Bold),
    titleMedium = TextStyle(fontSize = 16.sp, fontWeight = FontWeight.SemiBold),
    bodyMedium = TextStyle(fontSize = 15.sp),
    labelSmall = TextStyle(fontSize = 12.sp, fontWeight = FontWeight.Medium)
)

@Composable
fun BuildonTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DarkColors else LightColors,
        typography = BuildonTypography,
        content = content
    )
}
