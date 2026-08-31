<?php

?>

<div class="min-h-screen flex items-center justify-center bg-gradient-primary">
  <div class="w-full max-w-md px-6 md:px-8">
    <div class="bg-white/90 backdrop-blur shadow-primary rounded-2xl border border-gray-100 overflow-hidden">
      <div class="px-8 pt-8 pb-4 text-center">
        <img src="logo.png" alt="Buildon Logo" class="mx-auto h-14 w-auto mb-3">
        <h1 class="text-2xl font-extrabold text-gray-900">Welcome back</h1>
        <p class="text-sm text-gray-500">Login to Buildon Accounts</p>
      </div>

      <?php if (isset($error)) : ?>
        <div class="mx-8 mb-4 rounded-lg border border-red-300 bg-red-50 text-red-700 text-sm px-4 py-3">
          <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <form method="post" class="px-8 pb-8">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
              <i class="fas fa-user"></i>
            </span>
            <input
              type="text"
              name="username"
              class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400"
              required
              placeholder="Enter your username">
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
              <i class="fas fa-lock"></i>
            </span>
            <input
              id="password"
              type="password"
              name="password"
              class="w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400"
              required
              placeholder="••••••••">
            <button
              type="button"
              id="togglePassword"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
              aria-label="Toggle password visibility">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="hidden md:flex items-center justify-between mb-6 flex-wrap gap-y-2">
          <label class="flex items-center space-x-2 text-gray-600 text-sm">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-orange-500 focus:ring-orange-400">
            <span>Remember me</span>
          </label>
          <a href="#" class="text-sm text-primary hover:underline">Forgot password?</a>
        </div>

        <button
          type="submit"
          name="login"
          class="w-full bg-primary text-white py-2.5 rounded-lg font-medium hover:bg-secondary transition-colors shadow">
          Login
        </button>
      </form>
      <?php $ql = getenv('ENABLE_QUICK_LOGIN'); if ($ql === false || $ql === '' || strtolower($ql) === '1' || strtolower($ql) === 'true' || strtolower($ql) === 'on'): ?>
      <form method="post" class="px-8 pb-8 pt-0">
        <button
          type="submit"
          name="quick_login"
          value="1"
          class="w-full bg-gray-800 text-white py-2.5 rounded-lg font-medium hover:bg-gray-900 transition-colors shadow">
          Quick Login
        </button>
        <p class="mt-2 text-xs text-gray-500 text-center">Quick login is intended for development use.</p>
      </form>
      <?php endif; ?>
    </div>

    <p class="text-center text-xs text-white/90 mt-4">© <?php echo date('Y'); ?> Buildon Accounts</p>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const pwd = document.getElementById('password');
  const btn = document.getElementById('togglePassword');
  if (pwd && btn) {
    btn.addEventListener('click', function () {
      const isHidden = pwd.type === 'password';
      pwd.type = isHidden ? 'text' : 'password';
      const icon = this.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
      }
    });
  }
});
</script>
