<?php
// FORCE ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Deep Debug Dashboard</h1>";

try {
    // 1. Bootstrap
    echo "<h2>1. Bootstrap...</h2>";
    require_once 'app/bootstrap.php';
    echo "<p style='color:green'>OK</p>";

    // 2. Session
    echo "<h2>2. Session...</h2>";
    if (!isset($_SESSION['user_id'])) {
        // Mock login for debugging if not logged in
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin'; // Assume admin for test
        $_SESSION['name'] = 'Debug User';
        echo "<p style='color:orange'>Mocked Login as Admin (ID: 1)</p>";
    } else {
        echo "<p style='color:green'>Logged in as " . $_SESSION['role'] . " (ID: " . $_SESSION['user_id'] . ")</p>";
    }

    // 3. Service Initialization
    echo "<h2>3. Init DashboardService...</h2>";
    $dashboardService = new \EduCRM\Services\DashboardService($pdo, $_SESSION['user_id'], $_SESSION['role']);
    echo "<p style='color:green'>OK</p>";

    // 4. Run Queries (Simulate index.php)
    echo "<h2>4. Running Queries...</h2>";

    echo "Getting New Inquiries... ";
    $newInquiries = $dashboardService->getNewInquiriesCount();
    echo "<span style='color:green'>OK ($newInquiries)</span><br>";

    echo "Getting Active Classes... ";
    $activeClasses = $dashboardService->getActiveClassesCount();
    echo "<span style='color:green'>OK ($activeClasses)</span><br>";

    echo "Getting Pending Tasks... ";
    $pendingTasks = $dashboardService->getPendingTasksCount();
    echo "<span style='color:green'>OK ($pendingTasks)</span><br>";

    // 5. Check Header
    echo "<h2>5. Test Header Include...</h2>";
    $pageDetails = ['title' => 'Debug Mode'];
    ob_start(); // Buffer output
    require_once 'templates/header.php';
    ob_end_clean(); // Discard output
    echo "<p style='color:green'>Header included successfully (buffered).</p>";

    echo "<h1>✅ TEST COMPLETE - NO FATAL ERRORS</h1>";

} catch (Throwable $e) {
    echo "<div style='background:#fee2e2; padding:20px; border:1px solid #dc2626; color:#991b1b; white-space:pre-wrap;'>";
    echo "<h1>💥 CRASH DETECTED</h1>";
    echo "<strong>Type:</strong> " . get_class($e) . "\n";
    echo "<strong>Message:</strong> " . $e->getMessage() . "\n";
    echo "<strong>File:</strong> " . $e->getFile() . " : " . $e->getLine() . "\n";
    echo "<strong>Stack Trace:</strong>\n" . $e->getTraceAsString();
    echo "</div>";
}
?>