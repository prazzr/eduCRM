<?php

class NavigationService
{
    public static function getMenuItems($role)
    {
        $base = BASE_URL;
        $items = [];

        // Dashboard is for everyone
        $items[] = ['label' => 'Dashboard', 'url' => $base];

        // Phase 1: Tasks (for everyone)
        $items[] = ['label' => 'Tasks', 'url' => $base . 'modules/tasks/list.php'];

        // Inquiry/CRM Links
        if (in_array($role, ['admin', 'counselor'])) {
            // Phase 1: Appointments (admin/counselor only)
            $items[] = ['label' => 'Appointments', 'url' => $base . 'modules/appointments/list.php'];

            $items[] = ['label' => 'Inquiries', 'url' => $base . 'modules/inquiries/list.php'];
            $items[] = ['label' => 'Students', 'url' => $base . 'modules/students/list.php'];
            $items[] = ['label' => 'Apps Tracker', 'url' => $base . 'modules/applications/tracker.php'];
            $items[] = ['label' => 'Visa Tracking', 'url' => $base . 'modules/visa/list.php'];
            $items[] = ['label' => 'Partners', 'url' => $base . 'modules/partners/list.php'];
        }

        // Admin Only
        if ($role === 'admin') {
            $items[] = ['label' => 'Users', 'url' => $base . 'modules/users/list.php'];
        }

        // LMS Classes
        if (in_array($role, ['admin', 'teacher', 'student'])) {
            $items[] = ['label' => 'Classes', 'url' => $base . 'modules/lms/classes.php'];
        }

        // LMS Management
        if (in_array($role, ['admin', 'teacher'])) {
            $items[] = ['label' => 'LMS', 'url' => $base . 'modules/lms/courses.php'];
        }

        // Accounting
        if (in_array($role, ['admin', 'accountant'])) {
            $items[] = ['label' => 'Accounting', 'url' => $base . 'modules/accounting/ledger.php'];
        }

        // Reports & Analytics (Phase 2D)
        if (in_array($role, ['admin', 'counselor'])) {
            $items[] = ['label' => 'Reports', 'url' => $base . 'modules/reports/dashboard.php'];
        }

        // Documents (Phase 3A)
        if (in_array($role, ['admin', 'counselor'])) {
            $items[] = ['label' => 'Documents', 'url' => $base . 'modules/documents/manage.php'];
        }

        // Messaging (Phase 3B)
        if (in_array($role, ['admin', 'counselor'])) {
            $items[] = ['label' => 'Messaging', 'url' => $base . 'modules/messaging/gateways.php'];
        }

        // Student Profile
        if ($role === 'student' && isset($_SESSION['user_id'])) {
            $items[] = ['label' => 'My Profile', 'url' => $base . 'modules/students/profile.php?id=' . $_SESSION['user_id']];
        }

        return $items;
    }
}
