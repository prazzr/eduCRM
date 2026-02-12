<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageDetails['title']) ? $pageDetails['title'] . ' - EduCRM' : 'EduCRM'; ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (Local Build) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/tailwind.css">

    <!-- Professional Theme -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/sidebar.css?v=<?php echo time(); ?>_fixed">

    <!-- Alpine.js - Lightweight Reactivity -->
    <script defer src="<?php echo BASE_URL; ?>public/assets/js/alpine-components.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Searchable Dropdown Component -->
    <script src="<?php echo BASE_URL; ?>public/assets/js/searchable-dropdown.js"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body>
    <?php
    if (isLoggedIn()):
        require_once dirname(__DIR__) . '/app/Services/NavigationService.php';
        require_once dirname(__DIR__) . '/app/Services/NotificationService.php';

        // Notifications
        $notifService = new \EduCRM\Services\NotificationService($pdo, $_SESSION['user_id']);
        if (isset($_GET['mark_read'])) {
            $notifService->markAllRead();
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }
        $notifications = $notifService->getUnread();

        // Navigation
        $menuGroups = \EduCRM\Services\NavigationService::getGroupedMenuItems($_SESSION['role'] ?? 'guest');
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $userInitials = strtoupper(substr($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'U', 0, 2));
        ?>

        <div class="app-container" x-data="sidebar()">
            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar" :class="{ 'open': open }">
                <div class="sidebar-header">
                    <a href="<?php echo BASE_URL; ?>" class="sidebar-logo">
                        <div class="logo-mark">E</div>
                        <span class="logo-text">EduCRM</span>
                    </a>
                </div>

                <nav class="sidebar-nav">
                    <?php foreach ($menuGroups as $sectionKey => $section): ?>
                        <div class="nav-section">
                            <?php if (!empty($section['title'])): ?>
                                <div class="nav-section-label"><?php echo $section['title']; ?></div>
                            <?php endif; ?>
                            <?php foreach ($section['items'] as $item):
                                $itemPath = parse_url($item['url'], PHP_URL_PATH);
                                $isActive = ($itemPath === $currentPath);

                                // Check for sub-pages (same module directory)
                                // Only apply this "smart match" if the menu item is a main list page (list.php or index.php)
                                // This prevents overlap for modules sharing a folder (like LMS classes.php & courses.php)
                                $filename = basename($itemPath);
                                if (!$isActive && strpos($itemPath, '/modules/') !== false && ($filename === 'list.php' || $filename === 'index.php')) {
                                    $itemDir = dirname($itemPath);
                                    // Ensure directory matches and current path starts with it
                                    if (str_starts_with($currentPath, $itemDir . '/')) {
                                        $isActive = true;
                                    }
                                }

                                // Special case for Dashboard
                                if ($item['url'] === BASE_URL && ($currentPath === '/' || $currentPath === '/CRM/' || $currentPath === '/CRM/index.php')) {
                                    $isActive = true;
                                }
                                ?>
                                <a href="<?php echo htmlspecialchars($item['url']); ?>"
                                    class="nav-link <?php echo $isActive ? 'active' : ''; ?>">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon($item['icon'], 18); ?>
                                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </nav>

                <div class="sidebar-footer">
                    <div class="user-card">
                        <div class="user-avatar"><?php echo $userInitials; ?></div>
                        <div class="user-info">
                            <div class="user-name">
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'User'); ?>
                            </div>
                            <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                        </div>
                        <div class="user-actions">
                            <a href="<?php echo BASE_URL; ?>modules/users/change_password.php" class="user-action-btn"
                                title="Change Password">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('key', 16); ?>
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/users/notifications.php" class="user-action-btn"
                                title="Notification Settings">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('bell', 16); ?>
                            </a>
                            <a href="<?php echo BASE_URL; ?>logout.php" class="user-action-btn" title="Logout">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('log-out', 16); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="sidebar-overlay" @click="close()" x-show="open" x-transition.opacity></div>

            <!-- Main -->
            <main class="main-content">
                <header class="top-header">
                    <div class="header-left">
                        <button class="mobile-toggle" @click="toggle()">
                            <?php echo \EduCRM\Services\NavigationService::getIcon('menu', 20); ?>
                        </button>
                    </div>

                    <div class="header-right">
                        <?php if (hasRole('admin') || hasRole('counselor') || hasRole('branch_manager')): ?>
                            <a href="<?php echo BASE_URL; ?>modules/inquiries/add.php" class="header-btn">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?>
                                New Inquiry
                            </a>
                        <?php endif; ?>

                        <!-- Notification Dropdown -->
                        <div x-data="notificationDropdown({ 
                            initial: <?php echo json_encode(array_map(function ($n) {
                                return ['id' => $n['id'], 'message' => $n['message'], 'created_at' => $n['created_at'] ?? ''];
                            }, array_slice($notifications, 0, 5))); ?>,
                            markReadUrl: '?mark_read=1',
                            reloadOnMarkRead: true
                        })" class="relative" @keydown.escape.window="close()">
                            <button @click="toggle()" class="icon-btn" title="Notifications">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('bell', 18); ?>
                                <?php if (count($notifications) > 0): ?>
                                    <span class="badge"><?php echo count($notifications); ?></span>
                                <?php endif; ?>
                            </button>

                            <!-- Dropdown Panel -->
                            <div x-show="open" x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 transform scale-100"
                                x-transition:leave-end="opacity-0 transform scale-95" @click.outside="close()"
                                class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-slate-200 z-50 overflow-hidden">

                                <!-- Header -->
                                <div
                                    class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                                    <span class="font-semibold text-slate-700">Notifications</span>
                                    <span x-show="unreadCount > 0"
                                        class="bg-primary-100 text-primary-700 text-xs font-bold px-2 py-0.5 rounded-full"
                                        x-text="unreadCount + ' new'"></span>
                                </div>

                                <!-- Notification List -->
                                <div class="max-h-72 overflow-y-auto">
                                    <template x-if="notifications.length > 0">
                                        <div>
                                            <template x-for="notif in notifications" :key="notif.id">
                                                <div
                                                    class="px-4 py-3 border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                                    <p class="text-sm text-slate-700" x-text="notif.message"></p>
                                                    <p class="text-xs text-slate-400 mt-1"
                                                        x-text="notif.created_at || 'Just now'"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="notifications.length === 0">
                                        <div class="px-4 py-6 text-center text-slate-400 text-sm">
                                            No new notifications
                                        </div>
                                    </template>
                                </div>

                                <!-- Footer -->
                                <div x-show="notifications.length > 0"
                                    class="px-4 py-3 bg-slate-50 border-t border-slate-200">
                                    <button @click="markAllRead()" :disabled="loading"
                                        class="w-full text-center text-sm text-primary-600 hover:text-primary-700 font-medium">
                                        <span x-show="!loading">Mark all as read</span>
                                        <span x-show="loading">Marking...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <?php if (count($notifications) > 0): ?>
                    <div class="alert-bar">
                        <?php echo \EduCRM\Services\NavigationService::getIcon('bell', 16); ?>
                        <span><?php echo htmlspecialchars($notifications[0]['message']); ?></span>
                        <?php if (count($notifications) > 1): ?>
                            <span style="color: var(--text-muted);">+<?php echo count($notifications) - 1; ?> more</span>
                        <?php endif; ?>
                        <a href="?mark_read=1" class="dismiss">Dismiss</a>
                    </div>
                <?php endif; ?>

                <div class="content-wrapper">

                <?php else: ?>
                    <div class="login-page">
                    <?php endif; ?>

                    <script>
                        // Sidebar toggle is now handled by Alpine.js sidebar() component
                        // This fallback is for pages where Alpine.js may not be fully loaded
                        if (typeof Alpine === 'undefined') {
                            window.toggleSidebar = function () {
                                document.getElementById('sidebar')?.classList.toggle('open');
                            }
                        }
                    </script>