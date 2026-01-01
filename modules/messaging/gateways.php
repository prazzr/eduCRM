<?php
require_once '../../config.php';
require_once '../../includes/services/MessagingFactory.php';

requireLogin();
requireAdmin();

MessagingFactory::init($pdo);

// Handle gateway actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_gateway') {
        $config = [
            'account_sid' => $_POST['account_sid'] ?? '',
            'auth_token' => $_POST['auth_token'] ?? '',
            'from_number' => $_POST['from_number'] ?? '',
            'host' => $_POST['host'] ?? '',
            'port' => $_POST['port'] ?? '',
            'system_id' => $_POST['system_id'] ?? '',
            'password' => $_POST['password'] ?? '',
            'system_type' => $_POST['system_type'] ?? '',
            'source_addr' => $_POST['source_addr'] ?? '',
            'gammu_path' => $_POST['gammu_path'] ?? '',
            'device' => $_POST['device'] ?? '',
            'connection' => $_POST['connection'] ?? '',
            'phone_number_id' => $_POST['phone_number_id'] ?? '',
            'access_token' => $_POST['access_token'] ?? '',
            'business_account_id' => $_POST['business_account_id'] ?? '',
            'api_key' => $_POST['api_key'] ?? '',
            'client_id' => $_POST['client_id'] ?? '',
            'auth_token' => $_POST['auth_token'] ?? '',
            'bot_name' => $_POST['bot_name'] ?? '',
            'bot_avatar' => $_POST['bot_avatar'] ?? ''
        ];

        // Remove empty values
        $config = array_filter($config);

        $stmt = $pdo->prepare("
            INSERT INTO messaging_gateways (name, type, provider, config, is_default, priority, daily_limit, cost_per_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['name'],
            $_POST['type'],
            $_POST['provider'],
            json_encode($config),
            isset($_POST['is_default']) ? 1 : 0,
            $_POST['priority'] ?? 0,
            $_POST['daily_limit'] ?? 1000,
            $_POST['cost_per_message'] ?? 0
        ]);

        $_SESSION['flash_message'] = 'Gateway added successfully';
        $_SESSION['flash_type'] = 'success';
        header('Location: gateways.php');
        exit;
    }
}

// Get all gateways
$stmt = $pdo->query("SELECT * FROM messaging_gateways ORDER BY priority DESC, is_default DESC");
$gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageDetails = ['title' => 'Messaging Gateways'];
require_once '../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">📡 Messaging Gateways</h1>
        <p class="text-slate-600 mt-1">Configure SMS, WhatsApp, and Viber gateways</p>
    </div>
    <button onclick="showAddModal()" class="btn">+ Add Gateway</button>
</div>

<?php renderFlashMessage(); ?>

<!-- Gateways Grid -->
<?php if (count($gateways) > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($gateways as $gateway):
            $config = json_decode($gateway['config'], true);
            $icons = ['sms' => '📱', 'whatsapp' => '💬', 'viber' => '📞', 'email' => '📧'];
            $icon = $icons[$gateway['type']] ?? '📡';

            $providerNames = [
                'twilio' => 'Twilio',
                'smpp' => 'SMPP',
                'gammu' => 'Gammu',
                'twilio_whatsapp' => 'Twilio WhatsApp',
                'whatsapp_business' => 'WhatsApp Business',
                '360dialog' => '360Dialog',
                'viber_bot' => 'Viber Bot'
            ];
            $providerName = $providerNames[$gateway['provider']] ?? $gateway['provider'];
            ?>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="text-4xl">
                        <?php echo $icon; ?>
                    </div>
                    <div class="flex gap-2">
                        <?php if ($gateway['is_default']): ?>
                            <span class="px-2 py-1 bg-primary-100 text-primary-700 text-xs font-medium rounded">Default</span>
                        <?php endif; ?>
                        <span
                            class="px-2 py-1 <?php echo $gateway['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?> text-xs font-medium rounded">
                            <?php echo $gateway['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>

                <h3 class="font-bold text-slate-800 mb-1">
                    <?php echo htmlspecialchars($gateway['name']); ?>
                </h3>
                <p class="text-sm text-slate-600 mb-4">
                    <?php echo $providerName; ?> • Priority:
                    <?php echo $gateway['priority']; ?>
                </p>

                <div class="space-y-2 mb-4 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-600">Daily Usage:</span>
                        <span class="font-medium">
                            <?php echo $gateway['daily_sent']; ?>/
                            <?php echo $gateway['daily_limit']; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Total Sent:</span>
                        <span class="font-medium">
                            <?php echo number_format($gateway['total_sent']); ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Cost/Message:</span>
                        <span class="font-medium">$
                            <?php echo number_format($gateway['cost_per_message'], 4); ?>
                        </span>
                    </div>
                    <?php if ($gateway['last_used_at']): ?>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Last Used:</span>
                            <span class="font-medium">
                                <?php echo date('M d, H:i', strtotime($gateway['last_used_at'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex gap-2">
                    <button onclick="testGateway(<?php echo $gateway['id']; ?>)"
                        class="flex-1 btn-secondary px-3 py-2 text-xs rounded-lg">
                        Test
                    </button>
                    <button
                        onclick="toggleGateway(<?php echo $gateway['id']; ?>, <?php echo $gateway['is_active'] ? 'false' : 'true'; ?>)"
                        class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs rounded-lg font-medium">
                        <?php echo $gateway['is_active'] ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
        <div class="text-6xl mb-4">📡</div>
        <h3 class="text-lg font-semibold text-slate-800 mb-2">No Gateways Configured</h3>
        <p class="text-slate-600 mb-4">Add your first messaging gateway to start sending messages</p>
        <button onclick="showAddModal()" class="btn inline-block">+ Add Gateway</button>
    </div>
<?php endif; ?>

<!-- Add Gateway Modal -->
<div id="addModal"
    class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 my-8">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Add Messaging Gateway</h2>
            <button onclick="closeAddModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="add_gateway">

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Gateway Name *</label>
                        <input type="text" name="name" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="My SMS Gateway">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Type *</label>
                        <select name="type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            <option value="sms">SMS</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="viber">Viber</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Provider *</label>
                    <select name="provider" id="provider" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg" onchange="showProviderConfig()">
                        <option value="">Select provider...</option>
                        <optgroup label="SMS">
                            <option value="twilio">Twilio (Cloud SMS)</option>
                            <option value="smpp">SMPP (Industry Standard)</option>
                            <option value="gammu">Gammu (Local GSM Modem)</option>
                        </optgroup>
                        <optgroup label="WhatsApp">
                            <option value="twilio_whatsapp">Twilio WhatsApp</option>
                            <option value="whatsapp_business">Meta WhatsApp Business API</option>
                            <option value="360dialog">360Dialog WhatsApp</option>
                        </optgroup>
                        <optgroup label="Viber">
                            <option value="viber_bot">Viber Bot API</option>
                        </optgroup>
                    </select>
                </div>

                <!-- Twilio Config -->
                <div id="twilio_config" class="hidden space-y-3 p-4 bg-slate-50 rounded-lg">
                    <h4 class="font-medium text-slate-800">Twilio Configuration</h4>
                    <input type="text" name="account_sid" placeholder="Account SID"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="auth_token" placeholder="Auth Token"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="from_number" placeholder="From Number (+1234567890)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>

                <!-- SMPP Config -->
                <div id="smpp_config" class="hidden space-y-3 p-4 bg-slate-50 rounded-lg">
                    <h4 class="font-medium text-slate-800">SMPP Configuration</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="host" placeholder="Host"
                            class="px-3 py-2 border border-slate-300 rounded-lg">
                        <input type="number" name="port" placeholder="Port (2775)"
                            class="px-3 py-2 border border-slate-300 rounded-lg">
                    </div>
                    <input type="text" name="system_id" placeholder="System ID"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="password" name="password" placeholder="Password"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="source_addr" placeholder="Source Address"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>

                <!-- Gammu Config -->
                <div id="gammu_config" class="hidden space-y-3 p-4 bg-slate-50 rounded-lg">
                    <h4 class="font-medium text-slate-800">Gammu Configuration</h4>
                    <input type="text" name="gammu_path" placeholder="Gammu Path (C:\Program Files\Gammu\bin\gammu.exe)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="device" placeholder="Device (COM3)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="connection" placeholder="Connection (at)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>

                <!-- WhatsApp Business API Config -->
                <div id="whatsapp_business_config" class="hidden space-y-3 p-4 bg-slate-50 rounded-lg">
                    <h4 class="font-medium text-slate-800">WhatsApp Business API Configuration</h4>
                    <input type="text" name="phone_number_id" placeholder="Phone Number ID"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="access_token" placeholder="Access Token"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="business_account_id" placeholder="Business Account ID"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>

                <!-- Twilio WhatsApp Config -->
                <div id="twilio_whatsapp_config" class="hidden space-y-3 p-4 bg-slate-50 rounded-lg">
                    <h4 class="font-medium text-slate-800">Twilio WhatsApp Configuration</h4>
                    <input type="text" name="account_sid" placeholder="Account SID"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="auth_token" placeholder="Auth Token"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="from_number" placeholder="WhatsApp Number (whatsapp:+14155238886)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>

                <!-- 360Dialog Config -->
                <div id="360dialog_config" class="hidden space-y-3 p-4 bg-slate-50 rounded-lg">
                    <h4 class="font-medium text-slate-800">360Dialog Configuration</h4>
                    <input type="text" name="api_key" placeholder="API Key"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="client_id" placeholder="Client ID"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>

                <!-- Viber Bot Config -->
                <div id="viber_bot_config" class="hidden space-y-3 p-4 bg-slate-50 rounded-lg">
                    <h4 class="font-medium text-slate-800">Viber Bot Configuration</h4>
                    <input type="text" name="auth_token" placeholder="Bot Auth Token"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="bot_name" placeholder="Bot Name"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="bot_avatar" placeholder="Bot Avatar URL (optional)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Priority</label>
                        <input type="number" name="priority" value="0"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Daily Limit</label>
                        <input type="number" name="daily_limit" value="1000"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Cost/Message</label>
                        <input type="number" name="cost_per_message" step="0.0001" value="0"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_default" class="rounded">
                        <span class="text-sm font-medium text-slate-700">Set as default gateway</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button type="submit" class="btn">Add Gateway</button>
                <button type="button" onclick="closeAddModal()"
                    class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }

    function showProviderConfig() {
        const provider = document.getElementById('provider').value;
        document.getElementById('twilio_config').classList.add('hidden');
        document.getElementById('smpp_config').classList.add('hidden');
        document.getElementById('gammu_config').classList.add('hidden');
        document.getElementById('whatsapp_business_config')?.classList.add('hidden');
        document.getElementById('twilio_whatsapp_config')?.classList.add('hidden');
        document.getElementById('360dialog_config')?.classList.add('hidden');
        document.getElementById('viber_bot_config')?.classList.add('hidden');

        if (provider) {
            document.getElementById(provider + '_config')?.classList.remove('hidden');
        }
    }

    function testGateway(gatewayId) {
        if (!confirm('Test this gateway by sending a test message?')) return;

        const phone = prompt('Enter phone number to test:');
        if (!phone) return;

        fetch('test_gateway.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `gateway_id=${gatewayId}&phone=${encodeURIComponent(phone)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Test message sent successfully!');
                } else {
                    alert('❌ Test failed: ' + data.error);
                }
            });
    }

    function toggleGateway(gatewayId, activate) {
        fetch('toggle_gateway.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${gatewayId}&active=${activate}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>