<?php

declare(strict_types=1);

namespace EduCRM\Services;

class NavigationService
{
    /**
     * Get menu items with Lucide SVG icons for sidebar navigation
     */
    public static function getMenuItems(string $role): array
    {
        $base = BASE_URL;
        $items = [];

        // Dashboard is for everyone
        $items[] = ['label' => 'Dashboard', 'url' => $base, 'icon' => 'home', 'section' => 'main'];

        // Phase 1: Tasks (for everyone)
        $items[] = ['label' => 'Tasks', 'url' => $base . 'modules/tasks/list.php', 'icon' => 'check-square', 'section' => 'main'];

        // Inquiry/CRM Links
        if (in_array($role, ['admin', 'counselor', 'branch_manager'])) {
            $items[] = ['label' => 'Appointments', 'url' => $base . 'modules/appointments/list.php', 'icon' => 'calendar', 'section' => 'crm'];
            $items[] = ['label' => 'Inquiries', 'url' => $base . 'modules/inquiries/list.php', 'icon' => 'inbox', 'section' => 'crm'];
            $items[] = ['label' => 'Students', 'url' => $base . 'modules/students/list.php', 'icon' => 'users', 'section' => 'crm'];
            $items[] = ['label' => 'Applications', 'url' => $base . 'modules/applications/tracker.php', 'icon' => 'file-text', 'section' => 'crm'];
            $items[] = ['label' => 'Visa Tracking', 'url' => $base . 'modules/visa/list.php', 'icon' => 'plane', 'section' => 'crm'];
            $items[] = ['label' => 'Partners', 'url' => $base . 'modules/partners/list.php', 'icon' => 'briefcase', 'section' => 'crm'];
        }

        // LMS
        if (in_array($role, ['admin', 'teacher', 'student', 'branch_manager'])) {
            $items[] = ['label' => 'Classes', 'url' => $base . 'modules/lms/classes.php', 'icon' => 'book-open', 'section' => 'lms'];
        }
        if (in_array($role, ['admin', 'teacher', 'branch_manager'])) {
            $items[] = ['label' => 'Courses', 'url' => $base . 'modules/lms/courses.php', 'icon' => 'graduation-cap', 'section' => 'lms'];
        }

        // Finance
        if (in_array($role, ['admin', 'accountant', 'branch_manager'])) {
            $items[] = ['label' => 'Accounting', 'url' => $base . 'modules/accounting/ledger.php', 'icon' => 'credit-card', 'section' => 'finance'];
        }

        // Reports
        if (in_array($role, ['admin', 'counselor', 'branch_manager'])) {
            $items[] = ['label' => 'Reports', 'url' => $base . 'modules/reports/dashboard.php', 'icon' => 'bar-chart-2', 'section' => 'reports'];
            $items[] = ['label' => 'Activity', 'url' => $base . 'modules/reports/activity.php', 'icon' => 'activity', 'section' => 'reports'];
        }

        // Templates (Moved from Tools) - Now explicitly under Tools
        if (in_array($role, ['admin', 'counselor', 'branch_manager'])) {
            $items[] = ['label' => 'Templates', 'url' => $base . 'modules/templates/index.php', 'icon' => 'layout', 'section' => 'tools'];
        }

        // Tools
        if (in_array($role, ['admin', 'counselor', 'branch_manager'])) {
            $items[] = ['label' => 'Documents', 'url' => $base . 'modules/documents/manage.php', 'icon' => 'folder', 'section' => 'tools'];
            $items[] = ['label' => 'Messaging', 'url' => $base . 'modules/messaging/gateways.php', 'icon' => 'message-circle', 'section' => 'tools'];
            $items[] = ['label' => 'Email Queue', 'url' => $base . 'modules/email/queue.php', 'icon' => 'mail', 'section' => 'tools'];
        }

        // Automate (Admin only)
        if ($role === 'admin') {
            $items[] = ['label' => 'Automate', 'url' => $base . 'modules/automate/workflows.php', 'icon' => 'zap', 'section' => 'tools'];
        }

        // Admin - Users visible to admin and branch_manager, Branches only for admin
        if (in_array($role, ['admin', 'branch_manager'])) {
            $items[] = ['label' => 'Users', 'url' => $base . 'modules/users/list.php', 'icon' => 'user', 'section' => 'admin'];
        }
        if ($role === 'admin') {
            $items[] = ['label' => 'Branches', 'url' => $base . 'modules/branches/list.php', 'icon' => 'building', 'section' => 'admin'];
        }

        // Student
        if ($role === 'student' && isset($_SESSION['user_id'])) {
            $items[] = ['label' => 'My Profile', 'url' => $base . 'modules/students/profile.php?id=' . $_SESSION['user_id'], 'icon' => 'user', 'section' => 'main'];
        }

        return $items;
    }

    /**
     * Get grouped menu items
     */
    public static function getGroupedMenuItems($role)
    {
        $items = self::getMenuItems($role);
        $grouped = [
            'main' => ['title' => '', 'items' => []],
            'crm' => ['title' => 'CRM', 'items' => []],
            'lms' => ['title' => 'Learning', 'items' => []],
            'finance' => ['title' => 'Finance', 'items' => []],
            'reports' => ['title' => 'Analytics', 'items' => []],
            'tools' => ['title' => 'Tools', 'items' => []],
            'admin' => ['title' => 'Settings', 'items' => []],
        ];

        foreach ($items as $item) {
            $section = $item['section'] ?? 'main';
            if (isset($grouped[$section])) {
                $grouped[$section]['items'][] = $item;
            }
        }

        return array_filter($grouped, fn($g) => !empty($g['items']));
    }

    /**
     * Get Lucide SVG icon by name
     */
    public static function getIcon($name, $size = 18)
    {
        $icons = [
            'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
            'check-square' => '<polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>',
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
            'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>',
            'plane' => '<path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"></path>',
            'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>',
            'book-open' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>',
            'graduation-cap' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c0 2 2 3 6 3s6-1 6-3v-5"></path>',
            'credit-card' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line>',
            'bar-chart-2' => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
            'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>',
            'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>',
            'message-circle' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>',
            'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline>',
            'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
            'building' => '<rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><path d="M9 22v-4h6v4"></path><path d="M8 6h.01"></path><path d="M16 6h.01"></path><path d="M12 6h.01"></path><path d="M12 10h.01"></path><path d="M12 14h.01"></path><path d="M16 10h.01"></path><path d="M16 14h.01"></path><path d="M8 10h.01"></path><path d="M8 14h.01"></path>',
            'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>',
            'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>',
            'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
            'search' => '<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>',
            'plus' => '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>',
            'menu' => '<line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>',
            'x' => '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
            'chevron-down' => '<polyline points="6 9 12 15 18 9"></polyline>',
            'trending-up' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline>',
            'trending-down' => '<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline>',
            'filter' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>',
            'fire' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path>',
            'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>',
            'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>',
            'trash' => '<polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>',
            'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>',
        ];

        $path = $icons[$name] ?? $icons['home'];
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }
}
