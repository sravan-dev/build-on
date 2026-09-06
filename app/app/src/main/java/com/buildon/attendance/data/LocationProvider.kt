package com.buildon.attendance.data

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationManager
import androidx.core.content.ContextCompat
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import kotlin.coroutines.resume

/**
 * Where the worker is, for the site geofence.
 *
 * Uses the platform LocationManager rather than Play Services: the app is
 * side-loaded onto whatever handsets the crew carry, and a dependency on Google
 * Play being present and current is a support problem on a construction site.
 */
class LocationProvider(private val context: Context) {

    val hasPermission: Boolean
        get() = ContextCompat.checkSelfPermission(
            context, Manifest.permission.ACCESS_FINE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED ||
            ContextCompat.checkSelfPermission(
                context, Manifest.permission.ACCESS_COARSE_LOCATION
            ) == PackageManager.PERMISSION_GRANTED

    val isLocationEnabled: Boolean
        get() {
            val lm = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager
                ?: return false
            return lm.isProviderEnabled(LocationManager.GPS_PROVIDER) ||
                lm.isProviderEnabled(LocationManager.NETWORK_PROVIDER)
        }

    /**
     * A position, or null if one cannot be obtained in time.
     *
     * Tries the last known fix first — on a site the phone has usually had one
     * recently — then waits for a fresh one. The caller decides what a null
     * means; for a fenced site the server treats it as a refusal.
     */
    suspend fun current(timeoutMs: Long = 12_000): Location? = withContext(Dispatchers.IO) {
        if (!hasPermission || !isLocationEnabled) return@withContext null

        val lm = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager
            ?: return@withContext null

        val recent = lastKnown(lm)
        if (recent != null && System.currentTimeMillis() - recent.time < 60_000) {
            return@withContext recent
        }

        val fresh = withTimeoutOrNull(timeoutMs) { singleUpdate(lm) }
        fresh ?: recent
    }

    private fun lastKnown(lm: LocationManager): Location? {
        val providers = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
        var best: Location? = null
        for (p in providers) {
            val loc = try {
                @Suppress("MissingPermission")
                lm.getLastKnownLocation(p)
            } catch (e: SecurityException) {
                null
            }
            if (loc != null && (best == null || loc.time > best.time)) {
                best = loc
            }
        }
        return best
    }

    private suspend fun singleUpdate(lm: LocationManager): Location? =
        suspendCancellableCoroutine { cont ->
            val provider = when {
                lm.isProviderEnabled(LocationManager.GPS_PROVIDER) -> LocationManager.GPS_PROVIDER
                lm.isProviderEnabled(LocationManager.NETWORK_PROVIDER) -> LocationManager.NETWORK_PROVIDER
                else -> null
            }
            if (provider == null) {
                cont.resume(null)
                return@suspendCancellableCoroutine
            }

            val listener = object : android.location.LocationListener {
                override fun onLocationChanged(location: Location) {
                    if (cont.isActive) cont.resume(location)
                    try {
                        lm.removeUpdates(this)
                    } catch (e: SecurityException) {
                        // Nothing useful to do; the coroutine already has its value.
                    }
                }

                @Deprecated("Required on API < 29")
                override fun onStatusChanged(provider: String?, status: Int, extras: android.os.Bundle?) = Unit
                override fun onProviderEnabled(provider: String) = Unit
                override fun onProviderDisabled(provider: String) {
                    if (cont.isActive) cont.resume(null)
                }
            }

            try {
                @Suppress("MissingPermission")
                lm.requestLocationUpdates(provider, 0L, 0f, listener, context.mainLooper)
            } catch (e: SecurityException) {
                cont.resume(null)
                return@suspendCancellableCoroutine
            }

            cont.invokeOnCancellation {
                try {
                    lm.removeUpdates(listener)
                } catch (e: SecurityException) {
                    // Ignore: cancellation cleanup.
                }
            }
        }
}
