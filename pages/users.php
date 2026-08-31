<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

require_role('superadmin');

$message = '';
$error = '';

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $employee_id = !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;

    if ($username && $password && $role) {
        try {
            // Check usage
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, employee_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $role, $employee_id]);
                $message = "User $username created successfully.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "All fields required.";
    }
}

// Handle Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $employee_id = !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;

    if ($username && $role && $id) {
        try {
            // Check if username exists for other users
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username already taken by another user.";
            } else {
                $sql = "UPDATE users SET username = ?, role = ?, employee_id = ?";
                $params = [$username, $role, $employee_id];

                if (!empty($password)) {
                    $sql .= ", password_hash = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }

                $sql .= " WHERE id = ?";
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = "User updated successfully.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id != $_SESSION['user_id']) { // Check not deleting self
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $message = "User deleted.";
    } else {
        $error = "Cannot delete your own account.";
    }
}

// Fetch Users
$users = $pdo->query("
    SELECT u.*, e.name as employee_name 
    FROM users u 
    LEFT JOIN employees e ON u.employee_id = e.id 
    ORDER BY u.created_at DESC
")->fetchAll();

// Fetch Employees for dropdown
$employees = $pdo->query("SELECT id, name FROM employees ORDER BY name")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
        <p class="text-gray-600 mt-2">Manage system logins and roles.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Add User Form -->
    <div class="md:col-span-1">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4" id="formTitle">Create New User</h3>
            <form method="post" id="userForm">
                <input type="hidden" name="user_id" id="user_id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" id="username"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1" id="password-label">Password</label>
                    <input type="password" name="password" id="password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" id="role" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="employee">Employee</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="accounts_manager">Accounts Manager</option>
                        <option value="driver">Driver</option>
                        <option value="superadmin">Super Admin</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Link to Employee (Optional)</label>
                    <select name="employee_id" id="employee_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">-- None --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Link to an employee record for attendance tracking.</p>
                </div>
                <div class="flex gap-2">
                    <button type="submit" name="add_user" id="submitBtn"
                        class="w-full bg-primary text-white py-2 rounded-md hover:bg-secondary">Create User</button>
                    <button type="button" id="cancelBtn" onclick="resetForm()"
                        class="w-full bg-gray-300 text-gray-700 py-2 rounded-md hover:bg-gray-400 hidden">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users List -->
    <div class="md:col-span-2">
        <div class="bg-white rounded-lg shadow-md overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Username</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Role</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Linked Employee</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="px-6 py-4 font-medium text-gray-900">
                                <?php echo htmlspecialchars($u['username']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-bold 
                                    <?php 
                                    if ($u['role'] === 'superadmin') echo 'bg-purple-100 text-purple-800';
                                    elseif ($u['role'] === 'supervisor') echo 'bg-blue-100 text-blue-800';
                                    elseif ($u['role'] === 'accounts_manager') echo 'bg-orange-100 text-orange-800';
                                    elseif ($u['role'] === 'driver') echo 'bg-yellow-100 text-yellow-800';
                                    else echo 'bg-green-100 text-green-800';
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $u['role'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500"><?php echo htmlspecialchars($u['employee_name'] ?? '-'); ?>
                            </td>
                            <td class="px-6 py-4">
                                <button
                                    onclick='editUser(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>)'
                                    class="text-blue-600 hover:text-blue-800 text-sm mr-2">Edit</button>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="index.php?page=users&delete=<?php echo $u['id']; ?>"
                                        onclick="return confirm('Delete user?')"
                                        class="text-red-600 hover:text-red-800 text-sm">Delete</a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">Current</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function editUser(user) {
        document.getElementById('user_id').value = user.id;
        document.getElementById('username').value = user.username;
        document.getElementById('role').value = user.role;
        document.getElementById('employee_id').value = user.employee_id || '';

        // Password is optional on edit
        document.getElementById('password').removeAttribute('required');
        document.getElementById('password-label').innerText = 'Password (leave blank to keep current)';

        document.getElementById('formTitle').innerText = 'Edit User';
        document.getElementById('submitBtn').innerText = 'Update User';
        document.getElementById('submitBtn').name = 'update_user';

        document.getElementById('cancelBtn').classList.remove('hidden');

        // Scroll to form
        document.getElementById('formTitle').scrollIntoView({ behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('userForm').reset();
        document.getElementById('user_id').value = '';

        document.getElementById('password').setAttribute('required', 'required');
        document.getElementById('password-label').innerText = 'Password';

        document.getElementById('formTitle').innerText = 'Create New User';
        document.getElementById('submitBtn').innerText = 'Create User';
        document.getElementById('submitBtn').name = 'add_user';

        document.getElementById('cancelBtn').classList.add('hidden');
    }
</script>