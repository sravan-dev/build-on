package com.buildon.attendance.data

import android.content.Context
import android.content.SharedPreferences
import android.util.Log
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * Remembers the signed-in employee between launches so a worker clocking in at
 * 6am is not retyping credentials on a phone in the sun.
 *
 * The auth token is a bearer credential for the attendance API, so it is held in
 * EncryptedSharedPreferences: the file is encrypted with a key kept in the
 * Android Keystore, which means a rooted device or an extracted app-data backup
 * does not hand over a working token.
 *
 * A handful of devices have broken Keystore implementations and throw when
 * setting this up. Rather than leave the app unusable there, we fall back to
 * plain preferences and clear whatever was stored, so the failure costs a
 * re-login instead of silently downgrading a session that already exists.
 */
class Session(context: Context) {

    private val prefs: SharedPreferences = openPreferences(context)

    var token: String?
        get() = prefs.getString(KEY_TOKEN, null)
        set(value) = prefs.edit().apply {
            if (value == null) remove(KEY_TOKEN) else putString(KEY_TOKEN, value)
        }.apply()

    var name: String
        get() = prefs.getString(KEY_NAME, "") ?: ""
        set(value) = prefs.edit().putString(KEY_NAME, value).apply()

    var empId: String
        get() = prefs.getString(KEY_EMP_ID, "") ?: ""
        set(value) = prefs.edit().putString(KEY_EMP_ID, value).apply()

    val isSignedIn: Boolean get() = !token.isNullOrBlank()

    fun save(token: String, employee: Api.Employee) {
        this.token = token
        this.name = employee.name
        this.empId = employee.empId
    }

    fun clear() = prefs.edit().clear().apply()

    private companion object {
        const val ENCRYPTED_FILE = "buildon_session_secure"
        const val PLAIN_FILE = "buildon_session"
        const val KEY_TOKEN = "token"
        const val KEY_NAME = "name"
        const val KEY_EMP_ID = "emp_id"

        fun openPreferences(context: Context): SharedPreferences = try {
            val masterKey = MasterKey.Builder(context)
                .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                .build()

            EncryptedSharedPreferences.create(
                context,
                ENCRYPTED_FILE,
                masterKey,
                EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
            )
        } catch (e: Exception) {
            Log.w("Session", "Keystore unavailable, falling back to plain storage", e)
            context.getSharedPreferences(PLAIN_FILE, Context.MODE_PRIVATE)
                .also { it.edit().clear().apply() }
        }
    }
}
