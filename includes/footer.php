</body>

</html>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var light = document.getElementById('themeLight');
    var dark = document.getElementById('themeDark');
    if (light) light.addEventListener('click', function () { document.documentElement.classList.remove('dark-mode'); });
    if (dark) dark.addEventListener('click', function () { document.documentElement.classList.add('dark-mode'); });

    var closeBtn = document.querySelector('.close-sidebar');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        var sidebar = document.querySelector('.sidebar');
        if (sidebar) sidebar.style.display = 'none';
      });
    }
  });
</script>

<!-- Fixed dark footer -->
<style>
  .app-fixed-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #0f1724;
    color: #e6eef8;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    z-index: 9999;
  }

  .app-fixed-footer .left,
  .app-fixed-footer .right {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .app-fixed-footer .status-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
  }

  @media (max-width: 768px) {
    .app-fixed-footer {
      display: none !important;
    }
  }
</style>
<div class="app-fixed-footer" style="<?php echo !isset($_SESSION['logged_in']) ? 'display:none;' : ''; ?>">
  <div class="left"><span id="db-status-dot" class="status-dot" style="background:#f59e0b"></span><span
      id="db-status-text">DB: checking...</span></div>
  <div class="right">
    <span id="qatar-time" style="margin-right: 15px; color: #f7a600;"><i class="fas fa-clock"
        style="margin-right: 5px;"></i><span id="qatar-time-text">--:--:--</span></span>
    <span id="app-version-text">Version: <?php echo htmlspecialchars(getenv('APP_VERSION') ?: '1.0.0'); ?></span>
  </div>
</div>

<script>
  // Check DB status via lightweight endpoint
  function updateDbStatus(up) {
    var dot = document.getElementById('db-status-dot');
    var txt = document.getElementById('db-status-text');
    if (!dot || !txt) return;
    if (up) { dot.style.background = '#10b981'; txt.textContent = 'DB: connected'; }
    else { dot.style.background = '#ef4444'; txt.textContent = 'DB: disconnected'; }
  }

  (function () {
    fetch('includes/db_status.php', { cache: 'no-store' }).then(function (r) {
      return r.json();
    }).then(function (j) {
      updateDbStatus(!!j.ok);
    }).catch(function () { updateDbStatus(false); });
  })();

  // Live Qatar Time Clock (UTC+3)
  function updateQatarTime() {
    var now = new Date();
    // Qatar is UTC+3
    var utc = now.getTime() + (now.getTimezoneOffset() * 60000);
    var qatarTime = new Date(utc + (3600000 * 3));

    var hours = qatarTime.getHours();
    var minutes = qatarTime.getMinutes();
    var seconds = qatarTime.getSeconds();
    var ampm = hours >= 12 ? 'PM' : 'AM';

    hours = hours % 12;
    hours = hours ? hours : 12; // 0 becomes 12

    var timeStr = hours.toString().padStart(2, '0') + ':' +
      minutes.toString().padStart(2, '0') + ':' +
      seconds.toString().padStart(2, '0') + ' ' + ampm + ' (Qatar)';

    var el = document.getElementById('qatar-time-text');
    if (el) el.textContent = timeStr;

    var elMobile = document.getElementById('mobile-qatar-time');
    if (elMobile) elMobile.textContent = timeStr;
  }

  // Update every second
  updateQatarTime();
  setInterval(updateQatarTime, 1000);
</script>