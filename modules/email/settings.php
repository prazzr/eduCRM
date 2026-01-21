<?php
/**
 * Email Settings
 * Configure SMTP and email notification settings
 */

require_once '../../app/bootstrap.php';

requireLogin();
requireAdmin();

$pageDetails = ['title' => 'Email Settings'];

// Load current settings
function getEmailSettings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'email_%'");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'smtp_host' => $_POST['smtp_host'] ?? '',
        'smtp_port' => $_POST['smtp_port'] ?? '587',
        'smtp_username' => $_POST['smtp_username'] ?? '',
        'smtp_password' => $_POST['smtp_password'] ?? '',
        'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
        'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
        'smtp_from_name' => $_POST['smtp_from_name'] ?? 'EduCRM',
        'email_queue_enabled' => isset($_POST['email_queue_enabled']) ? 'true' : 'false',
    ];
    
    try {
        foreach ($settings as $key => $value) {
            // Don't update password if left blank (keep existing)
            if ($key === 'smtp_password' && empty($value)) {
                continue;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
        }
        
        redirectWithAlert('settings.php', 'Email settings saved successfully.', 'success');
    } catch (Exception $e) {
        redirectWithAlert('settings.php', 'Error saving settings: ' . $e->getMessage(), 'error');
    }
}

$settings = getEmailSettings($pdo);

require_once '../../templates/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Email Settings</h1>
        <p class="text-slate-500 mt-1 text-sm">Configure SMTP server and email notification preferences</p>
    </div>
    <a href="queue.php" class="btn btn-secondary">
        <?php echo \EduCRM\Services\NavigationService::getIcon('arrow-left', 16); ?> Back to Queue
    </a>
</div>

<?php renderFlashMessage(); ?>

<form method="POST" class="space-y-6">
    <!-- SMTP Configuration -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">SMTP Configuration</h2>
            <p class="text-sm text-slate-500">Configure your mail server for sending emails</p>
        </div>
        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">SMTP Host</label>
                    <input type="text" name="smtp_host" 
                           value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                           placeholder="smtp.gmail.com"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <p class="mt-1 text-xs text-slate-500">e.g., smtp.gmail.com, smtp.office365.com</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">SMTP Port</label>
                    <input type="number" name="smtp_port" 
                           value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <p class="mt-1 text-xs text-slate-500">Common ports: 587 (TLS), 465 (SSL), 25</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">SMTP Username</label>
                    <input type="text" name="smtp_username" 
                           value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                           placeholder="your-email@gmail.com"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">SMTP Password</label>
                    <input type="password" name="smtp_password" 
                           placeholder="<?php echo !empty($settings['smtp_password']) ? '••••••••' : 'Enter password'; ?>"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <p class="mt-1 text-xs text-slate-500">Leave blank to keep current password</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Encryption</label>
                <select name="smtp_encryption" 
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Sender Configuration -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">Sender Information</h2>
            <p class="text-sm text-slate-500">Configure default sender details for outgoing emails</p>
        </div>
        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">From Email</label>
                    <input type="email" name="smtp_from_email" 
                           value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>"
                           placeholder="noreply@yourcompany.com"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">From Name</label>
                    <input type="text" name="smtp_from_name" 
                           value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'EduCRM'); ?>"
                           placeholder="EduCRM"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Settings -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">Queue Settings</h2>
            <p class="text-sm text-slate-500">Configure email queue behavior</p>
        </div>
        <div class="p-6">
            <label class="flex items-center">
                <input type="checkbox" name="email_queue_enabled" 
                       <?php echo ($settings['email_queue_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>
                       class="h-4 w-4 text-primary focus:ring-primary border-slate-300 rounded">
                <span class="ml-3">
                    <span class="text-sm font-medium text-slate-800">Enable Email Queue</span>
                    <span class="block text-sm text-slate-500">When enabled, emails are queued and sent via cron job. When disabled, emails are sent immediately.</span>
                </span>
            </label>
        </div>
    </div>

    <!-- Test Email -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">Test Configuration</h2>
            <p class="text-sm text-slate-500">Send a test email to verify your SMTP settings</p>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4">
                <input type="email" id="test_email" 
                       placeholder="Enter test email address"
                       class="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                <button type="button" onclick="sendTestEmail()" class="btn btn-secondary">
                    <?php echo \EduCRM\Services\NavigationService::getIcon('mail', 16); ?> Send Test Email
                </button>
            </div>
            <div id="test_result" class="mt-3 hidden"></div>
        </div>
    </div>

    <!-- Submit -->
    <div class="flex justify-end gap-4">
        <a href="queue.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
</form>

<script>
function sendTestEmail() {
    const email = document.getElementById('test_email').value;
    const resultDiv = document.getElementById('test_result');
    
    if (!email) {
        resultDiv.className = 'mt-3 p-3 rounded-lg bg-amber-50 text-amber-800 border border-amber-200';
        resultDiv.textContent = 'Please enter an email address.';
        resultDiv.classList.remove('hidden');
        return;
    }
    
    resultDiv.className = 'mt-3 p-3 rounded-lg bg-slate-50 text-slate-700 border border-slate-200';
    resultDiv.textContent = 'Sending test email...';
    resultDiv.classList.remove('hidden');
    
    fetch('test_email.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({email: email})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.className = 'mt-3 p-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200';
            resultDiv.textContent = 'Test email sent successfully! Check your inbox.';
        } else {
            resultDiv.className = 'mt-3 p-3 rounded-lg bg-red-50 text-red-800 border border-red-200';
            resultDiv.textContent = 'Failed to send test email: ' + (data.error || 'Unknown error');
        }
    })
    .catch(error => {
        resultDiv.className = 'mt-3 p-3 rounded-lg bg-red-50 text-red-800 border border-red-200';
        resultDiv.textContent = 'Error: ' + error.message;
    });
}
</script>

<?php require_once '../../templates/footer.php'; ?>
