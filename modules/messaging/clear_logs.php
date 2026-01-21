<?php
/**
 * Clear Messaging Logs Handler
 * Supports clearing all logs or per-gateway logs
 */

require_once '../../app/bootstrap.php';

requireLogin();
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $gatewayId = isset($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : 0;
    
    // Try to clear from database first
    try {
        if ($gatewayId > 0) {
            // Clear logs for specific gateway
            $stmt = $pdo->prepare("DELETE FROM messaging_logs WHERE gateway_id = ?");
            $stmt->execute([$gatewayId]);
        } else {
            // Clear all logs
            $pdo->exec("TRUNCATE TABLE messaging_logs");
        }
    } catch (PDOException $e) {
        // Table might not exist, continue to file clearing
    }
    
    // Also clear file-based logs
    $logFile = dirname(__DIR__, 2) . '/logs/messaging.log';
    
    if (file_exists($logFile)) {
        if ($gatewayId > 0) {
            // For gateway-specific clearing, we need to filter the log file
            $content = file_get_contents($logFile);
            $lines = array_filter(explode("\n", $content));
            
            // Get gateway provider name to filter
            $stmt = $pdo->prepare("SELECT provider FROM messaging_gateways WHERE id = ?");
            $stmt->execute([$gatewayId]);
            $gateway = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($gateway) {
                $providerName = $gateway['provider'];
                $filteredLines = [];
                foreach ($lines as $line) {
                    // Keep lines that don't match this gateway
                    if (stripos($line, $providerName) === false) {
                        $filteredLines[] = $line;
                    }
                }
                file_put_contents($logFile, implode("\n", $filteredLines));
            }
        } else {
            // Clear entire file
            file_put_contents($logFile, '');
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Logs cleared successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
