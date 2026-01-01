<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageDetails['title']) ? $pageDetails['title'] . ' - Education CRM' : 'Education CRM'; ?>
    </title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Priority Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/priority.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        },
                        surface: '#ffffff',
                        background: '#f8fafc',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-slate-50 text-slate-900 font-sans">

    <?php
    if (isLoggedIn()):
        require_once 'services/NavigationService.php';
        require_once 'services/NotificationService.php';

        // Notification Logic
        $notifService = new NotificationService($pdo, $_SESSION['user_id']);
        if (isset($_GET['mark_read'])) {
            $notifService->markAllRead();
            $redirect = strtok($_SERVER["REQUEST_URI"], '?');
            header("Location: $redirect");
            exit;
        }
        $my_notifs = $notifService->getUnread();

        // Navigation Logic
        $menuItems = NavigationService::getMenuItems($_SESSION['role']);
        ?>
        <nav class="bg-white border-b border-slate-200 h-16 px-6 flex items-center justify-between sticky top-0 z-50">
            <div class="text-xl font-bold text-primary-600 tracking-tight">EduCRM</div>
            <div class="flex items-center gap-6 text-sm font-medium text-slate-600">
                <?php foreach ($menuItems as $item): ?>
                    <a href="<?php echo htmlspecialchars($item['url']); ?>" class="hover:text-primary-600 transition-colors">
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                <?php endforeach; ?>

                <!-- Notifications -->
                <a href="?mark_read=1" class="relative group">
                    <span class="text-lg">🔔</span>
                    <?php if (count($my_notifs) > 0): ?>
                        <span
                            class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full"><?php echo count($my_notifs); ?></span>
                    <?php endif; ?>
                </a>

                <div class="flex items-center gap-3 pl-4 border-l border-slate-200">
                    <a href="<?php echo BASE_URL; ?>modules/users/change_password.php"
                        class="text-xs text-slate-500 hover:text-slate-800">Change Password</a>

                    <a href="<?php echo BASE_URL; ?>logout.php"
                        class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs rounded hover:bg-slate-200 transition-colors">Logout</a>
                </div>
            </div>
        </nav>

        <?php if (count($my_notifs) > 0): ?>
            <div class="bg-white border-b border-slate-200 px-4 py-2 text-center text-sm text-slate-600">
                <span class="font-semibold text-primary-600">Alerts:</span>
                <?php foreach ($my_notifs as $n): ?>
                    <span class="mx-2">• <?php echo htmlspecialchars($n['message']); ?></span>
                <?php endforeach; ?>
                <a href="?mark_read=1" class="text-blue-600 hover:underline text-xs ml-2">(Dismiss)</a>
            </div>
        <?php endif; ?>

        <!-- Quick Actions Bar -->
        <?php if (hasRole('admin') || hasRole('counselor')): ?>
            <div class="quick-actions-bar">
                <a href="<?php echo BASE_URL; ?>modules/inquiries/add.php" class="btn-primary">+ New Inquiry</a>
                <a href="<?php echo BASE_URL; ?>modules/tasks/add.php" class="btn-primary">+ New Task</a>
                <a href="<?php echo BASE_URL; ?>modules/appointments/add.php" class="btn-primary">+ New Appointment</a>
                <input type="search" placeholder="Quick search..." class="quick-search" id="globalQuickSearch">
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <div class="max-w-7xl mx-auto p-6">