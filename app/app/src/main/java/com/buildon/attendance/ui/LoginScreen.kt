package com.buildon.attendance.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.buildon.attendance.R

@Composable
fun LoginScreen(
    busy: Boolean,
    error: String?,
    onSignIn: (empId: String, password: String) -> Unit
) {
    var empId by rememberSaveable { mutableStateOf("") }
    // Deliberately NOT rememberSaveable: saved instance state is persisted by the
    // system and would put the password on disk. A rotation clearing the field is
    // a fair trade.
    var password by remember { mutableStateOf("") }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 28.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Image(
            painter = painterResource(R.drawable.logo_buildon),
            contentDescription = "Buildon Trading & Contracting",
            modifier = Modifier
                .fillMaxWidth(0.62f)
                .padding(bottom = 40.dp)
        )

        OutlinedTextField(
            value = empId,
            onValueChange = { empId = it.trim() },
            label = { Text("Employee ID") },
            placeholder = { Text("BUE101") },
            singleLine = true,
            enabled = !busy,
            keyboardOptions = KeyboardOptions(
                capitalization = KeyboardCapitalization.Characters,
                imeAction = ImeAction.Next
            ),
            modifier = Modifier.fillMaxWidth()
        )

        Spacer(Modifier.height(14.dp))

        OutlinedTextField(
            value = password,
            onValueChange = { password = it },
            label = { Text("Password") },
            singleLine = true,
            enabled = !busy,
            visualTransformation = PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
            modifier = Modifier.fillMaxWidth()
        )

        if (error != null) {
            Spacer(Modifier.height(16.dp))
            Text(
                text = error,
                color = MaterialTheme.colorScheme.error,
                style = MaterialTheme.typography.bodyMedium,
                textAlign = TextAlign.Center
            )
        }

        Spacer(Modifier.height(28.dp))

        Button(
            onClick = { onSignIn(empId, password) },
            enabled = !busy && empId.isNotBlank() && password.isNotBlank(),
            modifier = Modifier
                .fillMaxWidth()
                .height(54.dp)
        ) {
            if (busy) {
                CircularProgressIndicator(
                    modifier = Modifier.size(22.dp),
                    strokeWidth = 2.dp,
                    color = MaterialTheme.colorScheme.onPrimary
                )
            } else {
                Text("Sign In", style = MaterialTheme.typography.titleMedium)
            }
        }
    }
}
