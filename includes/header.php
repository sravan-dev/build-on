<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Buildon Accounts</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Font Awesome CSS from CDN (free) for reliable icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- DataTables CSS and JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <style>
        .bg-primary {
            background-color: #f07d00;
        }

        .bg-secondary {
            background-color: #f7a600;
        }

        .text-primary {
            color: #f07d00;
        }

        .text-secondary {
            color: #f7a600;
        }

        .border-primary {
            border-color: #f07d00;
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, #f07d00, #f7a600);
        }

        .bg-gradient-secondary {
            background: linear-gradient(135deg, #f7a600, #000000);
        }

        .shadow-primary {
            box-shadow: 0 4px 6px -1px rgba(240, 125, 0, 0.1), 0 2px 4px -1px rgba(240, 125, 0, 0.06);
        }

        .settings-page .bg-white {
            background: #ffffff !important;
            color: black !important;
        }

        .capitalize {
            text-transform: capitalize;
            color: black !important;
        }

        .text-white {
            --tw-text-opacity: 1;
            color: white !important;
            color: rgb(255 255 255 / var(--tw-text-opacity, 1));
        }
    </style>
    <link rel="stylesheet" href="assets/css/dark-mode.css" id="darkModeStyles" disabled>

</head>

<body>
    <!-- Mobile header with hamburger -->
    <div class="md:hidden bg-white text-gray-800 p-2 flex items-center justify-between border-b border-gray-200 fixed top-0 w-full z-50"
        style="<?php echo (!isset($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') === 'employee') ? 'display:none !important;' : ''; ?>">
        <div class="flex items-center">
            <?php 
            $currentPage = $_GET['page'] ?? 'dashboard';
            if ($currentPage !== 'dashboard'): 
            ?>
            <button onclick="goBack()" class="p-2 mr-2 rounded-md text-gray-700 bg-transparent hover:bg-gray-100">
                <i class="fas fa-arrow-left"></i>
            </button>
            <?php endif; ?>
            <button id="sidebarToggle" class="p-2 rounded-md text-gray-700 bg-transparent">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div id="mobile-qatar-time" class="text-sm font-medium text-gray-600 pr-2">--:--</div>
    </div>
    <!-- Spacer for fixed header -->
    <div class="md:hidden h-14" style="<?php echo (!isset($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') === 'employee') ? 'display:none !important;' : ''; ?>"></div>
    <script>
        function goBack() {
            // Check if there is history to go back to (internal nav), else go to dashboard
            if (document.referrer.indexOf(window.location.host) !== -1) {
                window.history.back();
            } else {
                window.location.href = 'index.php?page=dashboard';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('sidebarToggle');
            var sidebar = document.querySelector('.sidebar');
            // create overlay
            var overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            overlay.className = 'fixed inset-0 bg-black bg-opacity-40 z-30 hidden md:hidden';
            document.body.appendChild(overlay);
            if (!btn || !sidebar) return;
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            });
            // click outside to close (only when overlay is visible — i.e. mobile)
            document.addEventListener('click', function (ev) {
                // if overlay is hidden, assume desktop layout and do not auto-close
                if (overlay.classList.contains('hidden')) return;
                if (!sidebar.contains(ev.target) && !btn.contains(ev.target)) {
                    if (!sidebar.classList.contains('-translate-x-full')) {
                        sidebar.classList.add('-translate-x-full');
                        overlay.classList.add('hidden');
                    }
                }
            });
            overlay.addEventListener('click', function () {
                if (!sidebar.classList.contains('-translate-x-full')) {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('hidden');
                }
            });
            // theme toggle handlers
            var themeLight = document.getElementById('themeLight');
            var themeDark = document.getElementById('themeDark');
            function applyTheme(t) {
                var css = document.getElementById('darkModeStyles');
                if (t === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    if (css) css.removeAttribute('disabled');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                    if (css) css.setAttribute('disabled', 'true');
                }
                localStorage.setItem('buildon_theme', t);
            }
            if (themeLight && themeDark) {
                themeLight.addEventListener('click', function () { applyTheme('light'); });
                themeDark.addEventListener('click', function () { applyTheme('dark'); });
                var saved = localStorage.getItem('buildon_theme');
                if (saved === 'dark') applyTheme('dark');
            }
        });
    </script>