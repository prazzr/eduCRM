<?php
require_once '../../app/bootstrap.php';


requireLogin();
requireAdmin();

\EduCRM\Services\MessagingFactory::init($pdo);

// Handle gateway actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_gateway') {
        try {
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
                'bot_avatar' => $_POST['bot_avatar'] ?? '',
                'url' => $_POST['url'] ?? '',
                'topic_prefix' => $_POST['topic_prefix'] ?? '',
                'default_country_code' => $_POST['default_country_code'] ?? '+1'
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

            redirectWithAlert('gateways.php', 'Gateway added successfully', 'success');
        } catch (Exception $e) {
            redirectWithAlert('gateways.php', 'Error adding gateway: ' . $e->getMessage(), 'error');
        }
    }
}

// Get all gateways
$stmt = $pdo->query("SELECT * FROM messaging_gateways ORDER BY priority DESC, is_default DESC");
$gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageDetails = ['title' => 'Messaging Gateways'];
require_once '../../templates/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Messaging Gateways</h1>
        <p class="text-slate-500 mt-1 text-sm">Configure SMS, WhatsApp, and Viber gateways</p>
    </div>
    <button onclick="showAddModal()" class="btn btn-primary">
        <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> Add Gateway
    </button>
</div>

<?php require_once 'tabs.php'; ?>

<?php renderFlashMessage(); ?>

<!-- Gateways List -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                    <th class="p-3 font-semibold">Gateway Name</th>
                    <th class="p-3 font-semibold">Provider</th>
                    <th class="p-3 font-semibold">Type</th>
                    <th class="p-3 font-semibold">Usage / Limit</th>
                    <th class="p-3 font-semibold">Status</th>
                    <th class="p-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($gateways) > 0): ?>
                    <?php foreach ($gateways as $gateway):
                        $config = json_decode($gateway['config'], true);
                        $providerNames = [
                            'twilio' => 'Twilio',
                            'smpp' => 'SMPP',
                            'gammu' => 'Gammu',
                            'twilio_whatsapp' => 'Twilio WhatsApp',
                            'whatsapp_business' => 'WhatsApp Business',
                            '360dialog' => '360Dialog',
                            'viber_bot' => 'Viber Bot',
                            'ntfy' => 'ntfy (Self-Hosted)'
                        ];
                        $providerName = $providerNames[$gateway['provider']] ?? $gateway['provider'];
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-3">
                                <div class="font-medium text-slate-800"><?php echo htmlspecialchars($gateway['name']); ?></div>
                                <?php if ($gateway['is_default']): ?>
                                    <span
                                        class="inline-block mt-1 px-1.5 py-0.5 bg-primary-50 text-primary-700 text-[10px] uppercase font-bold tracking-wider rounded">Default</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <span class="text-sm text-slate-600"><?php echo htmlspecialchars($providerName); ?></span>
                            </td>
                            <td class="p-3">
                                <span class="text-xs font-semibold px-2 py-1 rounded bg-slate-100 text-slate-600 uppercase">
                                    <?php echo htmlspecialchars($gateway['type']); ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <div class="text-sm text-slate-700">
                                    <?php echo number_format($gateway['daily_sent']); ?> /
                                    <?php echo number_format($gateway['daily_limit']); ?>
                                </div>
                                <div class="text-xs text-slate-500">
                                    Total: <?php echo number_format($gateway['total_sent']); ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <span
                                    class="px-2 py-1 <?php echo $gateway['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?> text-xs font-medium rounded">
                                    <?php echo $gateway['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex gap-2 justify-end">
                                    <a href="gateway_logs.php?id=<?php echo $gateway['id']; ?>" class="action-btn slate"
                                        title="View Logs">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('file-text', 16); ?>
                                    </a>
                                    <button onclick="testGateway(<?php echo $gateway['id']; ?>)" class="action-btn blue"
                                        title="Test Gateway">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('send', 16); ?>
                                    </button>
                                    <button
                                        onclick="toggleGateway(<?php echo $gateway['id']; ?>, <?php echo $gateway['is_active'] ? 'false' : 'true'; ?>)"
                                        class="action-btn <?php echo $gateway['is_active'] ? 'red' : 'green'; ?>"
                                        title="<?php echo $gateway['is_active'] ? 'Disable' : 'Enable'; ?>">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon($gateway['is_active'] ? 'pause' : 'play', 16); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center text-slate-400">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('radio', 48); ?>
                                <h3 class="mt-2 text-sm font-medium text-slate-900">No Gateways Configured</h3>
                                <p class="mt-1 text-sm text-slate-500">Add your first messaging gateway to start sending
                                    messages.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>



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
                            <option value="push">Push Notification</option>
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
                        <optgroup label="Push Notification">
                            <option value="ntfy">ntfy (Self-Hosted)</option>
                        </optgroup>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Default Country Code</label>
                    <input type="text" name="default_country_code" value="+1"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="+1 (USA), +44 (UK), +977 (Nepal), etc.">
                    <p class="text-xs text-slate-500 mt-1">Used when phone numbers don't include country code</p>
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

                <!-- ntfy Config -->
                <div id="ntfy_config" class="hidden space-y-3 p-4 bg-slate-50 rounded-lg">
                    <h4 class="font-medium text-slate-800">ntfy Configuration</h4>
                    <input type="text" name="url" placeholder="Server URL (http://localhost:8090)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="topic_prefix" placeholder="Topic Prefix (educrm)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="text" name="access_token" placeholder="Access Token (Optional)"
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
        document.getElementById('ntfy_config')?.classList.add('hidden');

        if (provider) {
            document.getElementById(provider + '_config')?.classList.remove('hidden');
        }
    }

    function testGateway(gatewayId) {
        Modal.show({
            type: 'confirm',
            title: 'Test Gateway',
            message: 'Enter the phone number to send a test message:',
            confirmText: 'Send Test',
            onConfirm: () => showPhoneInputModal(gatewayId)
        });
    }

    function showPhoneInputModal(gatewayId) {
        // Create phone input modal
        const modal = document.getElementById('customModal');
        const message = document.getElementById('modalMessage');
        message.innerHTML = '<input type="tel" id="testPhoneInput" class="w-full px-3 py-2 border border-slate-300 rounded-lg mt-2" placeholder="+9779812345678">';

        Modal.show({
            type: 'info',
            title: 'Enter Phone Number',
            message: '',
            confirmText: 'Send Test',
            onConfirm: () => {
                const phone = document.getElementById('testPhoneInput')?.value;
                if (!phone) {
                    Toast.warning('Please enter a phone number');
                    return;
                }
                sendTestMessage(gatewayId, phone);
            }
        });
        // Re-add input after show
        document.getElementById('modalMessage').innerHTML = '<input type="tel" id="testPhoneInput" class="w-full px-3 py-2 border border-slate-300 rounded-lg mt-2" placeholder="+9779812345678">';
        document.getElementById('testPhoneInput')?.focus();
    }

    function sendTestMessage(gatewayId, phone) {
        fetch('test_gateway.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `gateway_id=${gatewayId}&phone=${encodeURIComponent(phone)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Toast.success('Test message sent successfully!');
                } else {
                    Modal.error('Test failed: ' + data.error, 'Gateway Test Failed');
                }
            })
            .catch(err => Modal.error('Network error: ' + err.message, 'Connection Error'));
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
                    Toast.success(activate ? 'Gateway activated' : 'Gateway deactivated');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    Modal.error(data.message, 'Error');
                }
            })
            .catch(err => Modal.error('Network error: ' + err.message, 'Connection Error'));
    }
</script>

<?php require_once '../../templates/footer.php'; ?>