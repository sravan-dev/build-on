package com.buildon.attendance.data

import android.content.Context
import android.content.SharedPreferences

/**
 * Remembers the signed-in employee between launches so a worker clocking in at
 * 6am is not retyping credentials on a phone in the sun.
 */
class Session(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences("buildon_session", Context.MODE_PRIVATE)

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
        const val KEY_TOKEN = "token"
        const val KEY_NAME = "name"
        const val KEY_EMP_ID = "emp_id"
    }
}
