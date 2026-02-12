<?php
/**
 * Automation Workflows Management
 * Configure trigger events and their associated templates
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Admin only access
requireAdmin();

$automationService = new \EduCRM\Services\AutomationService($pdo);

// Handle flash messages
$message = '';
$messageType = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $messageType = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash_message'] = [
            'text' => 'Invalid security token. Please try again.',
            'type' => 'error'
        ];
    } else {
        $action = $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'create':
                    $automationService->createWorkflow([
                        'name' => $_POST['name'],
                        'trigger_event' => $_POST['trigger_event'],
                        'channel' => $_POST['channel'],
                        'template_id' => (int) $_POST['template_id'],
                        'gateway_id' => $_POST['gateway_id'] ?: null,
                        'delay_minutes' => (int) ($_POST['delay_minutes'] ?? 0),
                        'schedule_type' => $_POST['schedule_type'] ?? 'immediate',
                        'schedule_offset' => (int) ($_POST['schedule_offset'] ?? 0) * ($_POST['schedule_direction'] === 'before' ? -1 : 1),
                        'schedule_unit' => $_POST['schedule_unit'] ?? 'minutes',
                        'schedule_reference' => $_POST['schedule_reference'] ?: null,
                        'conditions' => !empty($_POST['conditions_json']) ? json_decode($_POST['conditions_json'], true) : json_decode($_POST['conditions'] ?? '[]', true),
                        'is_active' => isset($_POST['is_active'])
                    ]);
                    $_SESSION['flash_message'] = ['text' => 'Workflow created successfully!', 'type' => 'success'];
                    break;

                case 'update':
                    $id = (int) $_POST['id'];
                    $automationService->updateWorkflow($id, [
                        'name' => $_POST['name'],
                        'template_id' => (int) $_POST['template_id'],
                        'gateway_id' => $_POST['gateway_id'] ?: null,
                        'delay_minutes' => (int) ($_POST['delay_minutes'] ?? 0),
                        'schedule_type' => $_POST['schedule_type'] ?? 'immediate',
                        'schedule_offset' => (int) ($_POST['schedule_offset'] ?? 0) * ($_POST['schedule_direction'] === 'before' ? -1 : 1),
                        'schedule_unit' => $_POST['schedule_unit'] ?? 'minutes',
                        'schedule_reference' => $_POST['schedule_reference'] ?: null,
                        'conditions' => !empty($_POST['conditions_json']) ? json_decode($_POST['conditions_json'], true) : json_decode($_POST['conditions'] ?? '[]', true),
                        'is_active' => isset($_POST['is_active'])
                    ]);
                    $_SESSION['flash_message'] = ['text' => 'Workflow updated successfully!', 'type' => 'success'];
                    break;

                case 'delete':
                    $id = (int) $_POST['id'];
                    $automationService->deleteWorkflow($id);
                    $_SESSION['flash_message'] = ['text' => 'Workflow deleted successfully!', 'type' => 'danger'];
                    break;

                case 'toggle':
                    $id = (int) $_POST['id'];
                    $automationService->toggleWorkflow($id);
                    $_SESSION['flash_message'] = ['text' => 'Workflow status updated!', 'type' => 'success'];
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = ['text' => 'Error: ' . $e->getMessage(), 'type' => 'error'];
        }
    }

    // Redirect to avoid form resubmission
    header('Location: workflows.php' . (!empty($_POST['filter_trigger']) ? '?trigger=' . urlencode($_POST['filter_trigger']) : ''));
    exit;
}

// Get filters
$triggerEvent = $_GET['trigger'] ?? '';

// Get workflows
$workflows = $automationService->getWorkflows([
    'trigger_event' => $triggerEvent ?: null
]);

// Get templates for dropdown
$templates = $automationService->getTemplates(['is_active' => true]);

// Get messaging gateways for SMS/WhatsApp
$gateways = $automationService->getMessagingGateways();

// Get available channels dynamically
$availableChannels = $automationService->getAvailableChannels();

// Group workflows by trigger event
$workflowsByTrigger = [];
foreach ($workflows as $workflow) {
    $workflowsByTrigger[$workflow['trigger_event']][] = $workflow;
}

$triggerEvents = \EduCRM\Services\AutomationService::TRIGGER_EVENTS;

$pageTitle = 'Automation Workflows';
include __DIR__ . '/../../templates/header.php';
?>

<div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Automation Workflows</h1>
            <p class="text-slate-600">Configure automated notifications for system events</p>
        </div>
        <button onclick="openCreateModal()"
            class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Workflow
        </button>
    </div>

    <?php if ($message): ?>
        <?php
        $msgClasses = [
            'success' => 'bg-green-50 border-green-200 text-green-700',
            'error' => 'bg-red-50 border-red-200 text-red-700',
            'danger' => 'bg-red-50 border-red-200 text-red-700',
            'warning' => 'bg-orange-50 border-orange-200 text-orange-700',
            'info' => 'bg-blue-50 border-blue-200 text-blue-700'
        ];
        $msgClass = $msgClasses[$messageType] ?? $msgClasses['success'];
        ?>
        <div class="mb-6 p-4 rounded-lg border <?= $msgClass ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Filter by trigger -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6">
        <form method="GET" class="flex gap-4 items-end">
            <div class="flex-1 max-w-xs">
                <label class="block text-sm font-medium text-slate-700 mb-1">Filter by Trigger Event</label>
                <select name="trigger" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">All Events</option>
                    <?php foreach ($triggerEvents as $key => $event): ?>
                        <option value="<?= $key ?>" <?= $triggerEvent === $key ? 'selected' : '' ?>><?= $event['name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit"
                class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors">
                Filter
            </button>
            <?php if ($triggerEvent): ?>
                <a href="workflows.php" class="px-4 py-2 text-slate-600 hover:text-slate-800">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Workflows grouped by trigger -->
    <?php if (empty($workflowsByTrigger)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
            <svg class="w-12 h-12 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <p class="text-slate-500">No workflows configured. Create your first automation workflow.</p>
        </div>
    <?php else: ?>

        <?php foreach ($triggerEvents as $eventKey => $eventInfo): ?>
            <?php if (isset($workflowsByTrigger[$eventKey])): ?>
                <div class="mb-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div
                            class="w-10 h-10 rounded-lg bg-gradient-to-br from-teal-500 to-teal-600 flex items-center justify-center text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-slate-800"><?= $eventInfo['name'] ?></h2>
                            <p class="text-sm text-slate-500"><?= $eventInfo['description'] ?></p>
                        </div>
                    </div>

                    <div class="space-y-3 ml-13">
                        <?php foreach ($workflowsByTrigger[$eventKey] as $workflow): ?>
                            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-4 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <span class="px-2.5 py-1 text-xs font-medium rounded-full <?=
                                        $workflow['channel'] === 'email' ? 'bg-blue-100 text-blue-700' :
                                        ($workflow['channel'] === 'sms' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700')
                                        ?>">
                                        <?= strtoupper($workflow['channel']) ?>
                                    </span>
                                    <div>
                                        <h3 class="font-medium text-slate-800"><?= htmlspecialchars($workflow['name']) ?></h3>
                                        <p class="text-sm text-slate-500">
                                            Template: <span
                                                class="text-slate-700"><?= htmlspecialchars($workflow['template_name'] ?? 'Unknown') ?></span>
                                            <?php if ($workflow['delay_minutes'] > 0): ?>
                                                <span class="text-slate-400 mx-2">|</span>
                                                Delay: <?= $workflow['delay_minutes'] ?> min
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $workflow['id'] ?>">
                                        <button type="submit"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none <?= $workflow['is_active'] ? 'bg-teal-600' : 'bg-slate-200' ?>">
                                            <span class="sr-only">Toggle</span>
                                            <span
                                                class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?= $workflow['is_active'] ? 'translate-x-5' : 'translate-x-0' ?>"></span>
                                        </button>
                                    </form>
                                    <button onclick="editWorkflow(<?= $workflow['id'] ?>)"
                                        class="p-2 text-slate-600 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        onclick="deleteWorkflow(<?= $workflow['id'] ?>, '<?= htmlspecialchars(addslashes($workflow['name'])) ?>')"
                                        class="p-2 text-slate-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

    <?php endif; ?>

    <!-- Available triggers without workflows -->
    <div class="mt-8">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Available Trigger Events</h3>
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($triggerEvents as $eventKey => $eventInfo): ?>
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                    <h4 class="font-medium text-slate-700"><?= $eventInfo['name'] ?></h4>
                    <p class="text-sm text-slate-500 mb-2"><?= $eventInfo['description'] ?></p>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach (array_slice($eventInfo['variables'], 0, 5) as $var): ?>
                            <span
                                class="text-xs bg-white text-slate-600 px-2 py-0.5 rounded border border-slate-200">{<?= $var ?>}</span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="workflowModal" class="fixed inset-0 bg-black/50 z-50 hidden" onclick="closeModal(event)">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full" onclick="event.stopPropagation()">
            <form id="workflowForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="workflowId" value="">

                <div class="p-6 border-b border-slate-200">
                    <h2 id="modalTitle" class="text-xl font-bold text-slate-800">New Workflow</h2>
                </div>

                <!-- Tabs -->
                <div class="px-6 pt-4 border-b border-slate-200">
                    <nav class="flex space-x-4">
                        <button type="button" onclick="switchTab('settings')" id="tab-btn-settings"
                            class="px-3 py-2 text-sm font-medium border-b-2 border-teal-600 text-teal-600">Settings</button>
                        <button type="button" onclick="switchTab('timing')" id="tab-btn-timing"
                            class="px-3 py-2 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700">Timing</button>
                        <button type="button" onclick="switchTab('conditions')" id="tab-btn-conditions"
                            class="px-3 py-2 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700">Conditions</button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Tab: Settings -->
                    <div id="tab-settings" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Workflow Name *</label>
                            <input type="text" name="name" id="inputName" class="w-full rounded-lg border-slate-300"
                                placeholder="Welcome Email for New Users">
                        </div>

                        <div id="triggerField">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Trigger Event *</label>
                            <select name="trigger_event" id="inputTrigger" onchange="updateVariables()"
                                class="w-full rounded-lg border-slate-300">
                                <option value="">Select trigger...</option>
                                <?php foreach ($triggerEvents as $key => $event): ?>
                                    <option value="<?= $key ?>"><?= $event['name'] ?> - <?= $event['description'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="triggerVariables" class="mt-2 text-xs text-slate-500 hidden">
                                Available: <span id="variablesList"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div id="channelField">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Channel *</label>
                                <select name="channel" id="inputChannel" onchange="updateTemplates()"
                                    class="w-full rounded-lg border-slate-300">
                                    <?php foreach ($availableChannels as $channel): ?>
                                        <option value="<?= htmlspecialchars($channel) ?>">
                                            <?= htmlspecialchars(ucfirst($channel)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Template *</label>
                                <select name="template_id" id="inputTemplate"
                                    class="w-full rounded-lg border-slate-300">
                                    <option value="">Select template...</option>
                                </select>
                            </div>
                        </div>

                        <div id="gatewayField" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Messaging Gateway</label>
                            <select name="gateway_id" id="inputGateway" class="w-full rounded-lg border-slate-300">
                                <option value="">Default Gateway</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <input type="checkbox" name="is_active" id="inputActive" checked
                                class="rounded border-slate-300 text-teal-600">
                            <label for="inputActive" class="text-sm text-slate-700">Active (workflow will execute when
                                triggered)</label>
                        </div>
                    </div>

                    <!-- Tab: Timing -->
                    <div id="tab-timing" class="space-y-4 hidden">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Schedule Type</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="schedule_type" value="immediate" checked
                                        onclick="toggleScheduleFields()" class="text-teal-600">
                                    <span class="text-sm text-slate-700">Immediately</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="schedule_type" value="delay"
                                        onclick="toggleScheduleFields()" class="text-teal-600">
                                    <span class="text-sm text-slate-700">Delay</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="schedule_type" value="distinct_time"
                                        onclick="toggleScheduleFields()" class="text-teal-600">
                                    <span class="text-sm text-slate-700">Scheduled Time</span>
                                </label>
                            </div>
                        </div>

                        <!-- Delay Fields -->
                        <div id="field-delay" class="hidden bg-slate-50 p-4 rounded-lg border border-slate-200">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Wait For</label>
                            <div class="flex gap-2">
                                <input type="number" name="delay_minutes" id="inputDelay" min="0" value="0"
                                    class="w-24 rounded-lg border-slate-300">
                                <span class="self-center text-slate-500">minutes after trigger event</span>
                            </div>
                        </div>

                        <!-- Scheduled Fields -->
                        <div id="field-scheduled" class="hidden bg-slate-50 p-4 rounded-lg border border-slate-200">
                            <div class="grid grid-cols-3 gap-2 items-center">
                                <input type="number" name="schedule_offset" id="inputOffset" value="1"
                                    class="rounded-lg border-slate-300">
                                <select name="schedule_unit" id="inputUnit" class="rounded-lg border-slate-300">
                                    <option value="minutes">Minutes</option>
                                    <option value="hours">Hours</option>
                                    <option value="days">Days</option>
                                </select>
                                <select name="schedule_direction" id="inputDirection"
                                    class="rounded-lg border-slate-300">
                                    <option value="after">After</option>
                                    <option value="before">Before</option>
                                </select>
                            </div>
                            <div class="mt-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Reference Date</label>
                                <select name="schedule_reference" id="inputReference"
                                    class="w-full rounded-lg border-slate-300">
                                    <option value="created_at">Creation Date</option>
                                    <option value="due_date">Due Date (Tasks)</option>
                                    <option value="start_date">Start Date (Classes)</option>
                                    <option value="appointment_date">Appointment Date</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Conditions -->
                    <div id="tab-conditions" class="space-y-4 hidden">
                        <div class="flex justify-between items-center">
                            <h3 class="text-sm font-medium text-slate-700">Trigger Conditions (AND Logic)</h3>
                            <button type="button" onclick="addConditionRow()"
                                class="text-xs text-teal-600 hover:text-teal-700 font-medium">+ Add Condition</button>
                        </div>

                        <div id="conditions-container" class="space-y-2">
                            <!-- Rows injected by JS -->
                        </div>

                        <input type="hidden" name="conditions_json" id="inputConditionsJson">

                        <!-- Legacy fallbacks -->
                        <textarea name="conditions" id="inputConditions" rows="2" class="hidden"></textarea>
                    </div>
                </div>

                <div class="p-6 border-t border-slate-200 flex justify-end gap-3">
                    <button type="button" onclick="closeModal()"
                        class="px-4 py-2 text-slate-700 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-colors">Save
                        Workflow</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId" value="">
</form>

<script>
    // Data for JS
    const triggerEvents = <?= json_encode($triggerEvents) ?>;
    const templates = <?= json_encode($templates) ?>;

    const gateways = <?= json_encode($gateways) ?>;
    const workflows = <?= json_encode(array_column($workflows, null, 'id')) ?>;

    // Tab switching
    function switchTab(tab) {
        ['settings', 'timing', 'conditions'].forEach(t => {
            document.getElementById(`tab-${t}`).classList.toggle('hidden', t !== tab);
            const btn = document.getElementById(`tab-btn-${t}`);
            if (t === tab) {
                btn.classList.add('border-teal-600', 'text-teal-600');
                btn.classList.remove('border-transparent', 'text-slate-500');
            } else {
                btn.classList.remove('border-teal-600', 'text-teal-600');
                btn.classList.add('border-transparent', 'text-slate-500');
            }
        });
    }

    // Toggle schedule fields
    function toggleScheduleFields() {
        const type = document.querySelector('input[name="schedule_type"]:checked').value;
        document.getElementById('field-delay').classList.toggle('hidden', type !== 'delay');
        document.getElementById('field-scheduled').classList.toggle('hidden', type !== 'distinct_time');
    }

    // Conditions Builder
    function addConditionRow(data = {}) {
        const container = document.getElementById('conditions-container');
        const row = document.createElement('div');
        row.className = 'grid grid-cols-3 gap-2 items-center';
        row.innerHTML = `
        <input type="text" class="cond-field w-full h-10 px-3 rounded-lg border-slate-300 text-sm focus:ring-teal-500 focus:border-teal-500" placeholder="Field (e.g. country)" value="${data.field || ''}">
        <select class="cond-operator w-full h-10 px-3 rounded-lg border-slate-300 text-sm focus:ring-teal-500 focus:border-teal-500">
            <option value="=" ${data.operator === '=' ? 'selected' : ''}>Equals</option>
            <option value="!=" ${data.operator === '!=' ? 'selected' : ''}>Not Equals</option>
            <option value="IN" ${data.operator === 'IN' ? 'selected' : ''}>In List (comma sep)</option>
        </select>
        <div class="flex gap-2 items-center">
            <input type="text" class="cond-value w-full h-10 px-3 rounded-lg border-slate-300 text-sm focus:ring-teal-500 focus:border-teal-500" placeholder="Value" value="${data.value || ''}">
            <button type="button" onclick="this.closest('div.grid').remove()" class="text-red-400 hover:text-red-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    `;
        container.appendChild(row);
    }

    // Serialize conditions before submit
    document.getElementById('workflowForm').addEventListener('submit', function (e) {
        // Validation
        const name = document.getElementById('inputName').value;
        const trigger = document.getElementById('inputTrigger').value;
        const channel = document.getElementById('inputChannel').value;
        const template = document.getElementById('inputTemplate').value;

        if (!name || !trigger || !channel || !template) {
            e.preventDefault();
            switchTab('settings');
            // Small timeout to allow tab switch to happen
            setTimeout(() => alert('Please fill in all required fields in Settings tab.'), 10);
            return false;
        }
        const rows = document.querySelectorAll('#conditions-container > div');
        const conditions = [];
        rows.forEach(row => {
            const field = row.querySelector('.cond-field').value;
            const operator = row.querySelector('.cond-operator').value;
            const value = row.querySelector('.cond-value').value;
            if (field) {
                conditions.push({ field, operator, value });
            }
        });
        document.getElementById('inputConditionsJson').value = JSON.stringify(conditions);
    });

    // Open create modal
    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'New Workflow';
        document.getElementById('formAction').value = 'create';
        document.getElementById('workflowId').value = '';
        document.getElementById('workflowForm').reset();
        document.getElementById('conditions-container').innerHTML = ''; // Clear condition rows
        document.getElementById('triggerField').classList.remove('hidden');
        document.getElementById('channelField').classList.remove('hidden');
        document.getElementById('inputActive').checked = true;

        switchTab('settings');
        updateTemplates();
        toggleScheduleFields();
        document.getElementById('workflowModal').classList.remove('hidden');
    }

    // Edit workflow
    function editWorkflow(id) {
        const workflow = workflows[id];
        if (!workflow) return;

        document.getElementById('modalTitle').textContent = 'Edit Workflow';
        document.getElementById('formAction').value = 'update';
        document.getElementById('workflowId').value = id;
        document.getElementById('inputName').value = workflow.name;
        document.getElementById('inputTrigger').value = workflow.trigger_event;
        document.getElementById('inputChannel').value = workflow.channel;
        document.getElementById('inputActive').checked = workflow.is_active == 1;
        document.getElementById('inputGateway').value = workflow.gateway_id || '';

        // Timing
        const schedType = workflow.schedule_type || 'immediate';
        const radios = document.getElementsByName('schedule_type');
        for (let r of radios) { r.checked = r.value === schedType; }

        document.getElementById('inputDelay').value = workflow.delay_minutes || 0;
        document.getElementById('inputOffset').value = workflow.schedule_offset || 0;
        document.getElementById('inputUnit').value = workflow.schedule_unit || 'minutes';
        document.getElementById('inputReference').value = workflow.schedule_reference || 'created_at';

        // Conditions
        document.getElementById('conditions-container').innerHTML = '';
        let dbConditions = [];
        try {
            dbConditions = JSON.parse(workflow.conditions || '[]');
        } catch (e) { dbConditions = []; }

        if (dbConditions.length > 0) {
            dbConditions.forEach(c => addConditionRow(c));
        } else {
            // addConditionRow(); // Don't add empty row by default
        }

        // Hide trigger/channel
        document.getElementById('triggerField').classList.add('hidden');
        document.getElementById('channelField').classList.add('hidden');

        switchTab('settings');
        updateTemplates();
        toggleScheduleFields();

        setTimeout(() => {
            document.getElementById('inputTemplate').value = workflow.template_id;
        }, 50);

        updateVariables();
        document.getElementById('workflowModal').classList.remove('hidden');
    }

    // Update template dropdown based on channel
    function updateTemplates() {
        const channel = document.getElementById('inputChannel').value;
        const select = document.getElementById('inputTemplate');
        const gatewayField = document.getElementById('gatewayField');

        select.innerHTML = '<option value="">Select template...</option>';

        templates.filter(t => t.channel.toLowerCase() === channel.toLowerCase()).forEach(t => {
            const option = document.createElement('option');
            option.value = t.id;
            option.textContent = t.name;
            select.appendChild(option);
        });

        // Show gateway field for SMS/WhatsApp/Viber
        gatewayField.classList.toggle('hidden', channel === 'email');

        if (channel !== 'email') {
            updateGateways(channel);
        }
    }

    // Update gateway dropdown based on channel
    function updateGateways(channel) {
        const select = document.getElementById('inputGateway');
        const currentVal = select.value;

        select.innerHTML = '<option value="">Default Gateway</option>';

        gateways.filter(g => g.type.toLowerCase() === channel.toLowerCase()).forEach(g => {
            const option = document.createElement('option');
            option.value = g.id;
            option.textContent = `${g.name} (${g.provider})`;
            select.appendChild(option);
        });

        // Restore selection if valid
        if (currentVal) {
            const exists = Array.from(select.options).some(o => o.value == currentVal);
            if (exists) select.value = currentVal;
        }
    }

    // Update variables display
    function updateVariables() {
        const trigger = document.getElementById('inputTrigger').value;
        const container = document.getElementById('triggerVariables');
        const list = document.getElementById('variablesList');

        if (trigger && triggerEvents[trigger]) {
            list.textContent = triggerEvents[trigger].variables.map(v => `{${v}}`).join(', ');
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }

    // Close modal
    function closeModal(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('workflowModal').classList.add('hidden');
    }

    // Delete workflow
    function deleteWorkflow(id, name) {
        if (confirm(`Delete workflow "${name}"? This cannot be undone.`)) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>