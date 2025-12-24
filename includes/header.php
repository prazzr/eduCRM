<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageDetails['title']) ? $pageDetails['title'] . ' - Education CRM' : 'Education CRM'; ?>
    </title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        .notify-badge {
            background: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>

<body>

    <?php
    if (isLoggedIn()):
        require_once 'notifications.php'; // Include helper
        $my_notifs = getNotifications($_SESSION['user_id']);
        ?>
        <nav class="navbar">
            <div class="logo">EduCRM</div>
            <div class="nav-links">
                <a href="<?php echo BASE_URL; ?>">Dashboard</a>

                <?php if (hasRole('admin') || hasRole('counselor')): ?>
                    <a href="<?php echo BASE_URL; ?>modules/inquiries/list.php">Inquiries</a>
                    <a href="<?php echo BASE_URL; ?>modules/students/list.php">Students</a>
                    <a href="<?php echo BASE_URL; ?>modules/applications/tracker.php">Apps Tracker</a>
                    <a href="<?php echo BASE_URL; ?>modules/visa/list.php">Visa Tracking</a>
                    <a href="<?php echo BASE_URL; ?>modules/partners/list.php">Partners</a>
                <?php endif; ?>

                <?php if (hasRole('admin')): ?>
                    <a href="<?php echo BASE_URL; ?>modules/users/list.php">Users</a>
                <?php endif; ?>

                <?php if (hasRole('admin') || hasRole('teacher') || hasRole('student')): ?>
                    <a href="<?php echo BASE_URL; ?>modules/lms/classes.php">Classes</a>
                <?php endif; ?>

                <?php if (hasRole('admin') || hasRole('teacher') || hasRole('student')): ?>
                    <a href="<?php echo BASE_URL; ?>modules/lms/courses.php">LMS</a>
                <?php endif; ?>

                <?php if (hasRole('admin') || hasRole('accountant') || hasRole('student')): ?>
                    <a href="<?php echo BASE_URL; ?>modules/accounting/ledger.php">Accounting</a>
                <?php endif; ?>

                <?php if (hasRole('student')): ?>
                    <a href="<?php echo BASE_URL; ?>modules/students/profile.php?id=<?php echo $_SESSION['user_id']; ?>">My
                        Profile</a>
                    <a href="<?php echo BASE_URL; ?>modules/documents/list.php">My Docs</a>
                <?php endif; ?>

                <!-- Notifications Dropdown (Simplified as link for now) -->
                <a href="?mark_read=1" style="position: relative;">
                    🔔
                    <?php if (count($my_notifs) > 0): ?>
                        <span class="notify-badge"><?php echo count($my_notifs); ?></span>
                    <?php endif; ?>
                </a>

                <a href="<?php echo BASE_URL; ?>modules/users/change_password.php"
                    style="font-size: 13px; margin-right: 10px;">Change Password</a>

                <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-secondary"
                    style="padding: 5px 15px; font-size: 13px;">Logout</a>
            </div>
        </nav>

        <?php if (count($my_notifs) > 0): ?>
            <div
                style="background: #fff; border-bottom: 1px solid #ddd; padding: 10px; text-align: center; font-size: 14px; color: #444;">
                Pending Alerts:
                <?php foreach ($my_notifs as $n): ?>
                    <span style="margin: 0 10px;">• <?php echo htmlspecialchars($n['message']); ?></span>
                <?php endforeach; ?>
                <a href="?mark_read=1" style="color: blue; font-size: 12px;">(Dismiss)</a>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <div class="container">