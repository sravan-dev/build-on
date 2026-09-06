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
 * The auth token is a bearer credential for the attendance API, so it is only
 * ever written to disk through EncryptedSharedPreferences, whose key lives in
 * the Android Keystore.
 *
 * If the Keystore is unavailable the session is held in memory for the lifetime
 * of the process instead. That fails closed: the employee signs in again after
 * the app is killed, but a device where encryption cannot be trusted never gets
 * a plaintext token written to it. Falling back to plain preferences would hand
 * an attacker the downgrade simply by making the Keystore fail.
 */
class Session(context: Context) {

    private val store: Store = openStore(context)

    var token: String?
        get() = store.get(KEY_TOKEN)
        set(value) = store.put(KEY_TOKEN, value)

    var name: String
        get() = store.get(KEY_NAME) ?: ""
        set(value) = store.put(KEY_NAME, value)

    var empId: String
        get() = store.get(KEY_EMP_ID) ?: ""
        set(value) = store.put(KEY_EMP_ID, value)

    val isSignedIn: Boolean get() = !token.isNullOrBlank()

    /** True when the Keystore was unavailable and nothing is being persisted. */
    val isMemoryOnly: Boolean get() = store is MemoryStore

    fun save(token: String, employee: Api.Employee) {
        this.token = token
        this.name = employee.name
        this.empId = employee.empId
    }

    fun clear() = store.clear()

    // ---------------------------------------------------------------- storage

    private interface Store {
        fun get(key: String): String?
        fun put(key: String, value: String?)
        fun clear()
    }

    private class PrefsStore(private val prefs: SharedPreferences) : Store {
        override fun get(key: String): String? = prefs.getString(key, null)
        override fun put(key: String, value: String?) = prefs.edit().apply {
            if (value == null) remove(key) else putString(key, value)
        }.apply()

        override fun clear() = prefs.edit().clear().apply()
    }

    /** Survives only while the process lives; nothing reaches disk. */
    private class MemoryStore : Store {
        private val values = mutableMapOf<String, String>()
        override fun get(key: String): String? = values[key]
        override fun put(key: String, value: String?) {
            if (value == null) values.remove(key) else values[key] = value
        }

        override fun clear() = values.clear()
    }

    private companion object {
        const val ENCRYPTED_FILE = "buildon_session_secure"
        const val LEGACY_PLAIN_FILE = "buildon_session"
        const val KEY_TOKEN = "token"
        const val KEY_NAME = "name"
        const val KEY_EMP_ID = "emp_id"

        fun openStore(context: Context): Store {
            // Version 1 of this app stored the token in a plaintext preferences
            // file. Upgrading installs still have it on disk, so remove it
            // whichever way this session ends up being stored.
            purgeLegacyPlaintext(context)

            return try {
                val masterKey = MasterKey.Builder(context)
                    .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                    .build()

                PrefsStore(
                    EncryptedSharedPreferences.create(
                        context,
                        ENCRYPTED_FILE,
                        masterKey,
                        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
                    )
                )
            } catch (e: Exception) {
                Log.w("Session", "Keystore unavailable; session kept in memory only", e)
                MemoryStore()
            }
        }

        fun purgeLegacyPlaintext(context: Context) {
            try {
                // Blank the contents first: deleteSharedPreferences only unlinks the
                // file, and a device that refuses the delete would otherwise keep
                // the old token readable.
                context.getSharedPreferences(LEGACY_PLAIN_FILE, Context.MODE_PRIVATE)
                    .edit().clear().commit()
                context.deleteSharedPreferences(LEGACY_PLAIN_FILE)
            } catch (e: Exception) {
                Log.w("Session", "Could not remove legacy plaintext session", e)
            }
        }
    }
}
