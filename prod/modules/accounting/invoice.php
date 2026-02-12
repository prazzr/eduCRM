<?php
/**
 * Invoice View/Download Page
 * Displays or downloads a PDF invoice for a student fee
 */

require_once '../../app/bootstrap.php';

requireLogin();

$fee_id = isset($_GET['fee_id']) ? (int) $_GET['fee_id'] : 0;
$action = $_GET['action'] ?? 'view'; // view or download

if (!$fee_id) {
    die("Invalid Invoice ID");
}

// Initialize service
$invoiceService = new \EduCRM\Services\InvoiceService($pdo);

// Get invoice data
$invoiceData = $invoiceService->generateInvoice($fee_id);

if (!$invoiceData) {
    die("Invoice not found");
}

// Security check: Only admin/accountant or the student themselves can view
$isAdmin = hasRole('admin') || hasRole('accountant');
$isOwner = hasRole('student') && $invoiceData['student']['id'] == $_SESSION['user_id'];

if (!$isAdmin && !$isOwner) {
    die("Unauthorized access");
}

// Generate PDF content
$pdfContent = $invoiceService->generatePDF($invoiceData);

// Handle action
if ($action === 'download') {
    // Force download
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $invoiceData['invoice_number'] . '.html"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
} else {
    // Display for printing
    header('Content-Type: text/html; charset=UTF-8');
    echo $pdfContent;

    // Add print button
    echo '<script>
        // Add print button
        var printBtn = document.createElement("button");
        printBtn.innerHTML = "🖨️ Print Invoice";
        printBtn.style.cssText = "position:fixed;top:20px;right:20px;padding:10px 20px;background:#4f46e5;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;z-index:1000;";
        printBtn.onclick = function() { window.print(); };
        document.body.appendChild(printBtn);
        
        // Add download button
        var dlBtn = document.createElement("a");
        dlBtn.innerHTML = "📥 Download";
        dlBtn.href = "?fee_id=' . $fee_id . '&action=download";
        dlBtn.style.cssText = "position:fixed;top:20px;right:160px;padding:10px 20px;background:#16a34a;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;text-decoration:none;z-index:1000;";
        document.body.appendChild(dlBtn);
        
        // Add back button
        var backBtn = document.createElement("a");
        backBtn.innerHTML = "← Back";
        backBtn.href = "student_ledger.php?student_id=' . $invoiceData['student']['id'] . '";
        backBtn.style.cssText = "position:fixed;top:20px;left:20px;padding:10px 20px;background:#64748b;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;text-decoration:none;z-index:1000;";
        document.body.appendChild(backBtn);
    </script>';

    // Add print styles
    echo '<style>
        @media print {
            button, a[href*="action=download"], a[href*="student_ledger"] { display: none !important; }
        }
    </style>';
}
