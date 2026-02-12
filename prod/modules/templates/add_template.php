<?php
/**
 * Add/Edit Email Template - Visual Builder
 * Drag-and-drop email template editor using GrapesJS
 */

require_once '../../app/bootstrap.php';

requireLogin();
requireBranchManager();

$templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $templateId > 0;
$template = null;

// Load template for editing
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        redirectWithAlert('index.php', 'Template not found.', 'error');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_key = trim($_POST['template_key'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body_html = $_POST['body_html'] ?? '';
    $variables = $_POST['variables'] ?? '[]';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($template_key) || empty($name) || empty($subject)) {
        redirectWithAlert(($isEdit ? "add_template.php?id={$templateId}" : "add_template.php"), 
            'Template key, name, and subject are required.', 'error');
    }
    
    // Phase 6: Dynamic Channels Processing
    // We don't save to email_templates directly anymore.
    // We save to email_template_channels after the main template is saved.
    
    try {
        $pdo->beginTransaction();

        if ($isEdit) {
            $stmt = $pdo->prepare("
                UPDATE email_templates 
                SET name = ?, description = ?, subject = ?, body_html = ?, variables = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $subject, $body_html, $variables, $is_active, $templateId]);
            $message = 'Template updated successfully!';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO email_templates (template_key, name, description, subject, body_html, variables, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$template_key, $name, $description, $subject, $body_html, $variables, $is_active]);
            $templateId = $pdo->lastInsertId(); // Get ID for new template
            $message = 'Template created successfully!';
        }

        // Save Channel Configs
        // First delete existing configs for this template to ensure clean state (or use ON DUPLICATE KEY UPDATE)
        $pdo->prepare("DELETE FROM email_template_channels WHERE template_id = ?")->execute([$templateId]);

        // active_gateways is an array of IDs from the form now
        $active_gateways = $_POST['active_gateways'] ?? [];

        // Fetch channel type for each gateway to save correctly
        $gatewayTypes = [];
        if (!empty($active_gateways)) {
            $stmt = $pdo->query("SELECT id, type FROM messaging_gateways");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $gatewayTypes[$row['id']] = $row['type'];
            }
        }

        $insertChannel = $pdo->prepare("
            INSERT INTO email_template_channels (template_id, channel_type, gateway_id, is_active, custom_content)
            VALUES (?, ?, ?, 1, NULL)
        ");

        foreach ($active_gateways as $gatewayId) {
            if (isset($gatewayTypes[$gatewayId])) {
                $type = $gatewayTypes[$gatewayId];
                $insertChannel->execute([$templateId, $type, $gatewayId]);
            }
        }

        $pdo->commit();
        
        redirectWithAlert('index.php', $message, 'success');
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = $e->getCode() == 23000 ? 'Template key already exists.' : $e->getMessage();
        redirectWithAlert(($isEdit ? "add_template.php?id={$templateId}" : "add_template.php"), 
            'Error: ' . $error, 'error');
    }
}

$pageDetails = ['title' => ($isEdit ? 'Edit' : 'Create') . ' Email Template'];
require_once '../../templates/header.php';

// Default system variables
$defaultVariables = ['name', 'email', 'login_url', 'password'];

// Get ALL unique variables from ALL templates (making variables global/shared)
$allVarsStmt = $pdo->query("SELECT variables FROM email_templates WHERE variables IS NOT NULL");
$allVariables = $defaultVariables;
while ($row = $allVarsStmt->fetch(PDO::FETCH_ASSOC)) {
    $vars = json_decode($row['variables'] ?? '[]', true) ?: [];
    $allVariables = array_merge($allVariables, $vars);
}
$allVariables = array_unique($allVariables);
sort($allVariables);

// Current template's variables (for saving)
$currentVariables = $allVariables;

// Phase 6: Get Active Gateway Types for Dynamic UI
$activeGateways = [];
try {
    $stmt = $pdo->query("SELECT type, name FROM messaging_gateways WHERE is_active = 1 ORDER BY type ASC, name ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activeGateways[$row['type']][] = $row['name'];
    }
} catch (PDOException $e) {
    // Table might not exist yet if migration failed
    $activeGateways = [];
}

// Load existing channel configs if editing
$templateChannels = [];
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM email_template_channels WHERE template_id = ?");
    $stmt->execute([$templateId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // We now store multiple entries per type, so we can't key just by type if we want to check gateways
        // But for backward compatibility logic in other places, we might still key by type.
        // For the UI loop, we just need the list.
        $templateChannels[] = $row;
    }
}
?>

<!-- GrapesJS CSS -->
<link href="https://unpkg.com/grapesjs@0.21.7/dist/css/grapes.min.css" rel="stylesheet">
<link href="https://unpkg.com/grapesjs-preset-newsletter@1.0.1/dist/grapesjs-preset-newsletter.css" rel="stylesheet">

<style>
    /* GrapesJS Theme Customization */
    .gjs-one-bg { background-color: #1e293b; }
    .gjs-two-color { color: #f1f5f9; }
    .gjs-three-bg { background-color: #0f172a; }
    .gjs-four-color, .gjs-four-color-h:hover { color: #0d9488; }
    
    #gjs {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .gjs-frame-wrapper { background: #ffffff; }
    
    /* Make blocks panel wider and more readable */
    .gjs-pn-views-container { width: 280px !important; }
    .gjs-pn-views { width: 50px !important; }
    
    /* Better block styling */
    .gjs-block {
        width: 100% !important;
        min-height: 50px !important;
        padding: 10px !important;
        margin: 4px 0 !important;
        border-radius: 8px !important;
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        font-size: 13px !important;
        background: #334155 !important;
        border: 1px solid #475569 !important;
        transition: all 0.2s !important;
    }
    .gjs-block:hover {
        background: #475569 !important;
        transform: translateX(3px);
    }
    .gjs-block__media {
        width: 36px !important;
        height: 36px !important;
        font-size: 20px !important;
    }
    
    /* Category headers */
    .gjs-block-category {
        background: #0f172a !important;
        border-radius: 6px !important;
        margin: 8px !important;
        overflow: hidden;
    }
    .gjs-block-category .gjs-title {
        background: #1e293b !important;
        padding: 12px 15px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        border-bottom: 1px solid #334155 !important;
    }
    .gjs-block-category .gjs-blocks-c {
        padding: 8px !important;
    }
    
    /* Scrollbar styling */
    .gjs-blocks-cs::-webkit-scrollbar { width: 6px; }
    .gjs-blocks-cs::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
    
    .variable-chip {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        background: #0d9488;
        color: white;
        border-radius: 16px;
        font-size: 12px;
        cursor: pointer;
        margin: 2px;
        transition: all 0.2s;
    }
    .variable-chip:hover {
        background: #0f766e;
        transform: scale(1.05);
    }
    
    .editor-container {
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: 20px;
        min-height: 600px;
    }
    
    @media (max-width: 1024px) {
        .editor-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<form method="POST" id="templateForm">
    <div class="page-header">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="index.php" class="text-slate-400 hover:text-slate-600">
                    <?php echo \EduCRM\Services\NavigationService::getIcon('arrow-left', 18); ?>
                </a>
                <h1 class="page-title"><?php echo $isEdit ? 'Edit' : 'Create'; ?> Email Template</h1>
            </div>
            <p class="text-slate-500 text-sm">Use the visual builder to design your email template</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="previewTemplate()" class="btn btn-secondary">
                <?php echo \EduCRM\Services\NavigationService::getIcon('eye', 16); ?> Preview
            </button>
            <button type="submit" class="btn btn-primary">
                <?php echo \EduCRM\Services\NavigationService::getIcon('save', 16); ?> Save Template
            </button>
        </div>
    </div>

    <?php renderFlashMessage(); ?>

    <!-- Template Details -->
    <div class="card mb-6">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">Template Details</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Template Key <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="template_key" required
                           value="<?php echo htmlspecialchars($template['template_key'] ?? ''); ?>"
                           placeholder="e.g., welcome_custom"
                           <?php echo $isEdit ? 'readonly class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-100 cursor-not-allowed"' : 'class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"'; ?>>
                    <p class="mt-1 text-xs text-slate-500">Unique identifier (lowercase, underscores)</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Template Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" required
                           value="<?php echo htmlspecialchars($template['name'] ?? ''); ?>"
                           placeholder="e.g., Custom Welcome Email"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
                    <input type="text" name="description"
                           value="<?php echo htmlspecialchars($template['description'] ?? ''); ?>"
                           placeholder="Brief description..."
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>
            <div class="mt-6">
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Subject Line <span class="text-red-500">*</span>
                </label>
                <input type="text" name="subject" id="subject" required
                       value="<?php echo htmlspecialchars($template['subject'] ?? ''); ?>"
                       placeholder="e.g., Welcome to EduCRM, {name}!"
                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary font-mono text-sm">
                <p class="mt-1 text-xs text-slate-500">Use {variable} placeholders for dynamic content</p>
            </div>
            <div class="mt-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" 
                           <?php echo ($template['is_active'] ?? 1) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-primary focus:ring-primary border-slate-300 rounded">
                    <span class="ml-3 text-sm text-slate-700">Template is active</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Unified Messaging Distribution (Phase 6 Dynamic) -->
    <div class="card mb-6 border-l-4 border-l-indigo-500">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-slate-800">Multi-Channel Distribution</h2>
            <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full uppercase font-bold tracking-wider">Dynamic</span>
        </div>
        <div class="p-6">
            <p class="text-sm text-slate-500 mb-4">Select which channels to automatically send this notification to. Content will be generated from the email body.</p>
            <?php if (empty($activeGateways)): ?>
                <div class="text-center py-6 text-slate-500">
                    <p>No active gateways found. Configure gateways in Messaging > Gateways.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php 
                    // Flatten active gateways for linear iteration
                    $allGateways = [];
                    $stmt = $pdo->query("SELECT * FROM messaging_gateways WHERE is_active = 1 ORDER BY type ASC, name ASC");
                    $allGateways = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($allGateways as $gateway): 
                        // Check if this specific gateway is enabled for this template
                        // We check active_gateways ids if submitted, or templateChannels if existing
                        $isActive = false;
                        if ($isEdit) {
                            // Check if template has a channel entry with this gateway_id
                            foreach ($templateChannels as $ch) {
                                if ($ch['gateway_id'] == $gateway['id'] && $ch['is_active']) {
                                    $isActive = true;
                                    break;
                                }
                            }
                        }

                        $type = $gateway['type'];
                        $displayType = $type ?: 'Push';
                        $color = match($type) {
                            'whatsapp' => 'green',
                            'sms' => 'indigo',
                            'viber' => 'purple',
                            'push', '' => 'blue',
                            default => 'slate'
                        };
                    ?>
                        <label class="flex items-start space-x-3 p-3 border rounded-lg hover:bg-slate-50 transition-colors cursor-pointer bg-white shadow-sm">
                            <input type="checkbox" name="active_gateways[]" value="<?php echo $gateway['id']; ?>" 
                                    <?php echo $isActive ? 'checked' : ''; ?>
                                    class="h-5 w-5 mt-1 text-<?php echo $color; ?>-600 focus:ring-<?php echo $color; ?>-500 border-slate-300 rounded">
                            <div class="flex flex-col">
                                <span class="font-medium text-slate-800"><?php echo htmlspecialchars($gateway['name']); ?></span>
                                <span class="text-xs font-semibold text-<?php echo $color; ?>-600 uppercase tracking-wide mt-0.5">
                                    <?php echo htmlspecialchars($displayType); ?> &bull; <?php echo htmlspecialchars($gateway['provider']); ?>
                                </span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Visual Editor -->
    <div class="editor-container">
        <div class="card">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                <h2 class="text-lg font-semibold text-slate-800">Email Body</h2>
                <p class="text-xs text-slate-500 mt-1">Drag blocks from the right panel to build your email</p>
            </div>
            <div id="gjs" style="height: 500px;"></div>
            <input type="hidden" name="body_html" id="body_html">
            <input type="hidden" name="variables" id="variables_input" value="<?php echo htmlspecialchars(json_encode($currentVariables)); ?>">
        </div>
        
        <!-- Quick Tips (stays in sidebar) -->
        <div class="space-y-4">
            <div class="card">
                <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                    <h3 class="font-semibold text-slate-800">Quick Tips</h3>
                </div>
                <div class="p-4 text-sm text-slate-600 space-y-2">
                    <p>📦 Drag blocks from the right panel</p>
                    <p>🎨 Click elements to style them</p>
                    <p>📝 Double-click text to edit</p>
                    <p>🔗 Use {variable} in text for dynamic content</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Variables Section (Below Email Body) -->
    <div class="card mt-6">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-slate-800">📌 Variables</h3>
                <p class="text-xs text-slate-500 mt-0.5">Click to copy, then paste where needed</p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Insert Target Toggle -->
                <div class="flex rounded-lg overflow-hidden border border-slate-300">
                    <button type="button" id="targetBody" onclick="setInsertTarget('body')" 
                            class="px-3 py-1.5 text-xs font-medium bg-slate-800 text-white">
                        📧 Body
                    </button>
                    <button type="button" id="targetSubject" onclick="setInsertTarget('subject')" 
                            class="px-3 py-1.5 text-xs font-medium bg-white text-slate-700 hover:bg-slate-50">
                        📝 Subject
                    </button>
                </div>
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="variableSearch" placeholder="Search variables..." 
                           oninput="filterVariables(this.value)"
                           class="pl-8 pr-3 py-1.5 text-sm border border-slate-300 rounded-lg w-48 focus:ring-1 focus:ring-primary focus:border-primary">
                    <svg class="absolute left-2.5 top-2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="p-4">
            <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto" id="variableChips">
                <?php foreach ($currentVariables as $var): ?>
                    <span class="variable-chip" data-var="<?php echo $var; ?>" onclick="insertVariable('<?php echo $var; ?>')" title="Click to copy {<?php echo $var; ?>}">
                        {<?php echo $var; ?>}
                    </span>
                <?php endforeach; ?>
            </div>
            
            <!-- Add New Variable -->
            <div class="mt-4 pt-4 border-t border-slate-200 flex items-center gap-4">
                <span class="text-xs text-slate-500">Add Custom:</span>
                <div class="flex gap-2 items-center flex-1 max-w-xs">
                    <input type="text" id="newVariable" placeholder="variable_name" 
                           onkeypress="if(event.key==='Enter'){event.preventDefault();addVariable();}"
                           class="flex-1 px-3 py-1.5 text-sm border border-slate-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <button type="button" onclick="addVariable()" 
                            class="w-8 h-8 flex items-center justify-center bg-emerald-500 text-white rounded-full hover:bg-emerald-600 transition-colors" title="Add variable">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>
</form>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border border-slate-200 w-full max-w-3xl shadow-lg rounded-xl bg-white">
        <div class="flex justify-between items-center border-b border-slate-200 pb-3">
            <h3 class="text-lg font-semibold text-slate-800">Email Preview</h3>
            <button type="button" onclick="closePreviewModal()" class="text-slate-400 hover:text-slate-600">
                <?php echo \EduCRM\Services\NavigationService::getIcon('x', 24); ?>
            </button>
        </div>
        <div class="mt-4">
            <div class="mb-4 p-3 bg-slate-50 rounded-lg">
                <span class="text-xs text-slate-500 uppercase tracking-wider">Subject:</span>
                <p id="previewSubject" class="font-medium text-slate-800 mt-1"></p>
            </div>
            <div id="previewContent" class="max-h-[60vh] overflow-y-auto border border-slate-200 rounded-lg p-4 bg-white">
            </div>
        </div>
    </div>
</div>

<!-- GrapesJS Scripts -->
<script src="https://unpkg.com/grapesjs@0.21.7/dist/grapes.min.js"></script>
<script src="https://unpkg.com/grapesjs-preset-newsletter@1.0.1/dist/grapesjs-preset-newsletter.min.js"></script>

<script>
// Initialize GrapesJS
const editor = grapesjs.init({
    container: '#gjs',
    height: '500px',
    width: 'auto',
    fromElement: false,
    storageManager: false,
    plugins: ['gjs-preset-newsletter'],
    pluginsOpts: {
        'gjs-preset-newsletter': {
            modalTitleImport: 'Import HTML',
            modalBtnImport: 'Import',
        }
    },
    canvas: {
        styles: [
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'
        ]
    },
    // Default content
    components: `<?php echo $isEdit ? addslashes($template['body_html']) : '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;"><h2 style="color: #0f766e;">Hello {name}!</h2><p>This is your email content. Edit this text or drag new blocks here.</p><a href="#" style="display: inline-block; background: #0f766e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-top: 20px;">Call to Action</a></div>'; ?>`,
});

// Custom modal for entering link URL
let currentRte = null;
function showLinkModal() {
    // Create modal if doesn't exist
    let modal = document.getElementById('linkModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'linkModal';
        modal.innerHTML = `
            <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;">
                <div style="background: white; padding: 24px; border-radius: 12px; width: 400px; box-shadow: 0 20px 50px rgba(0,0,0,0.3);">
                    <h3 style="margin: 0 0 16px 0; color: #1e293b; font-size: 18px;">🔗 Add Link</h3>
                    <input type="url" id="linkUrlInput" placeholder="https://example.com" 
                           style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                    <div style="display: flex; gap: 10px; margin-top: 16px; justify-content: flex-end;">
                        <button onclick="closeLinkModal()" style="padding: 10px 20px; background: #f1f5f9; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Cancel</button>
                        <button onclick="applyLink()" style="padding: 10px 20px; background: #0f766e; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Apply Link</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Enter key to submit
        document.getElementById('linkUrlInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') applyLink();
        });
    }
    modal.style.display = 'block';
    setTimeout(() => document.getElementById('linkUrlInput').focus(), 100);
}

function closeLinkModal() {
    document.getElementById('linkModal').style.display = 'none';
    document.getElementById('linkUrlInput').value = '';
}

function applyLink() {
    const url = document.getElementById('linkUrlInput').value;
    if (url && currentRte) {
        currentRte.exec('createLink', url);
    }
    closeLinkModal();
}

// Add Link button to Rich Text Editor toolbar
editor.RichTextEditor.add('link', {
    icon: '🔗',
    attributes: { title: 'Add Link' },
    result: rte => {
        const selection = rte.selection();
        if (selection) {
            currentRte = rte;
            showLinkModal();
        } else {
            showToast('Please select some text first!');
        }
    }
});

// Add Unlink button
editor.RichTextEditor.add('unlink', {
    icon: '🔓',
    attributes: { title: 'Remove Link' },
    result: rte => rte.exec('unlink')
});

// Auto-open blocks panel when editor loads
editor.on('load', () => {
    const blocksBtn = editor.Panels.getButton('views', 'open-blocks');
    if (blocksBtn) blocksBtn.set('active', true);
    
    // Remove default blocks from newsletter preset that we don't want
    const bm = editor.BlockManager;
    const blocksToRemove = ['sect100', 'sect50', 'sect30', 'sect37', 'button', 'divider', 'text', 'text-sect', 'image', 'quote', 'link', 'link-block', 'grid-items', 'list-items'];
    blocksToRemove.forEach(id => {
        if (bm.get(id)) bm.remove(id);
    });
});

// ===== TEXT & FORMATTING (Resizable) =====
editor.BlockManager.add('heading-block', {
    label: '📝 Heading',
    content: {
        type: 'text',
        content: '<h2 style="color: #1e293b; font-size: 24px; margin: 10px 0;">Your Heading</h2>',
        resizable: true,
        style: { 'min-height': '30px' }
    },
    category: 'Text',
});

editor.BlockManager.add('paragraph-block', {
    label: '📄 Paragraph',
    content: {
        type: 'text',
        content: '<p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 10px 0;">Your paragraph text goes here. Double-click to edit.</p>',
        resizable: true,
        style: { 'min-height': '50px' }
    },
    category: 'Text',
});

// ===== BUTTON =====
editor.BlockManager.add('button-block', {
    label: '🔘 Button',
    content: '<a href="#" style="display: inline-block; background: #0f766e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 500;">Click Here</a>',
    category: 'Elements',
});

editor.BlockManager.add('divider-block', {
    label: '➖ Divider',
    content: '<hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">',
    category: 'Elements',
});

// ===== TABLE =====
editor.BlockManager.add('table-block', {
    label: '📊 Table',
    content: `<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
        <tr style="background: #f1f5f9;">
            <th style="border: 1px solid #e2e8f0; padding: 10px; text-align: left;">Header 1</th>
            <th style="border: 1px solid #e2e8f0; padding: 10px; text-align: left;">Header 2</th>
            <th style="border: 1px solid #e2e8f0; padding: 10px; text-align: left;">Header 3</th>
        </tr>
        <tr>
            <td style="border: 1px solid #e2e8f0; padding: 10px;">Data 1</td>
            <td style="border: 1px solid #e2e8f0; padding: 10px;">Data 2</td>
            <td style="border: 1px solid #e2e8f0; padding: 10px;">Data 3</td>
        </tr>
    </table>`,
    category: 'Elements',
});

// ===== MEDIA WITH UPLOAD =====
editor.BlockManager.add('image-block', {
    label: '🖼️ Image',
    content: { type: 'image' },
    category: 'Media',
    activate: true,
});

editor.BlockManager.add('logo-block', {
    label: '🏷️ Logo',
    content: '<img src="https://via.placeholder.com/150x50?text=Your+Logo" alt="Logo" style="max-width: 150px; height: auto;">',
    category: 'Media',
});

editor.BlockManager.add('video-thumbnail', {
    label: '🎬 Video Link',
    content: `<a href="#your-video-url" style="display: block; text-align: center; margin: 15px 0;">
        <img src="https://via.placeholder.com/480x270?text=▶+Click+to+Watch" alt="Video" style="max-width: 100%; border-radius: 8px; border: 2px solid #e2e8f0;">
    </a>`,
    category: 'Media',
});

// Configure Asset Manager for image uploads
editor.AssetManager.addType('uploadable', {
    view: {
        onRender() {
            const el = this.el;
            el.innerHTML = '<input type="file" accept="image/*" style="cursor: pointer;">';
            el.querySelector('input').addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        editor.AssetManager.add({ src: ev.target.result, name: file.name });
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    }
});

// Store variables
let variables = <?php echo json_encode($currentVariables); ?>;

// Filter variables based on search
function filterVariables(query) {
    const chips = document.querySelectorAll('#variableChips .variable-chip');
    const lowerQuery = query.toLowerCase();
    chips.forEach(chip => {
        const varName = chip.getAttribute('data-var') || chip.textContent;
        if (varName.toLowerCase().includes(lowerQuery)) {
            chip.style.display = '';
        } else {
            chip.style.display = 'none';
        }
    });
}

// Update hidden input on form submit
document.getElementById('templateForm').addEventListener('submit', function(e) {
    const html = editor.getHtml();
    const css = editor.getCss();
    const fullHtml = `<style>${css}</style>${html}`;
    document.getElementById('body_html').value = fullHtml;
    document.getElementById('variables_input').value = JSON.stringify(variables);
});

// Current insert target ('body' or 'subject')
let insertTarget = 'body';

// Set insert target and update UI
function setInsertTarget(target) {
    insertTarget = target;
    const bodyBtn = document.getElementById('targetBody');
    const subjectBtn = document.getElementById('targetSubject');
    
    if (target === 'body') {
        bodyBtn.className = 'flex-1 px-3 py-2 text-sm font-medium bg-slate-800 text-white';
        subjectBtn.className = 'flex-1 px-3 py-2 text-sm font-medium bg-white text-slate-700 hover:bg-slate-50';
    } else {
        subjectBtn.className = 'flex-1 px-3 py-2 text-sm font-medium bg-slate-800 text-white';
        bodyBtn.className = 'flex-1 px-3 py-2 text-sm font-medium bg-white text-slate-700 hover:bg-slate-50';
    }
}

// Insert variable based on current target
function insertVariable(varName) {
    if (insertTarget === 'body') {
        insertVariableToBody(varName);
    } else {
        insertVariableToSubject(varName);
    }
}

// Insert variable into EMAIL BODY - copies to clipboard for user to paste
function insertVariableToBody(varName) {
    const variable = '{' + varName + '}';
    
    // Copy to clipboard
    navigator.clipboard.writeText(variable).then(() => {
        showToast('📋 Copied ' + variable + ' - now paste it where you want (Ctrl+V)');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = variable;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('📋 Copied ' + variable + ' - now paste it where you want (Ctrl+V)');
    });
}

// Insert variable into SUBJECT LINE
function insertVariableToSubject(varName) {
    const input = document.getElementById('subject');
    const start = input.selectionStart || input.value.length;
    const variable = '{' + varName + '}';
    input.value = input.value.substring(0, start) + variable + input.value.substring(input.selectionEnd || start);
    input.focus();
    showToast('Inserted ' + variable + ' into subject');
}

// Toast notification
function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-4 right-4 bg-slate-800 text-white px-4 py-2 rounded-lg shadow-lg z-50';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2000);
}

// Add new variable
function addVariable() {
    const input = document.getElementById('newVariable');
    const varName = input.value.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
    
    if (varName && !variables.includes(varName)) {
        variables.push(varName);
        
        const chip = document.createElement('span');
        chip.className = 'variable-chip';
        chip.onclick = function() { insertVariable(varName); };
        chip.title = 'Click to insert {' + varName + '}';
        chip.textContent = '{' + varName + '}';
        document.getElementById('variableChips').appendChild(chip);
        
        input.value = '';
        showToast('Added variable {' + varName + '}');
    }
}

// Preview
function previewTemplate() {
    const subject = document.getElementById('subject').value;
    const html = editor.getHtml();
    const css = editor.getCss();
    
    const sampleData = {
        name: 'John Doe', email: 'john@example.com', password: 'temp123',
        login_url: '#', task_title: 'Sample Task', due_date: 'January 15, 2026'
    };
    
    let previewSubject = subject;
    let previewBody = html;
    
    for (const [key, value] of Object.entries(sampleData)) {
        const regex = new RegExp('\\{' + key + '\\}', 'g');
        previewSubject = previewSubject.replace(regex, value);
        previewBody = previewBody.replace(regex, value);
    }
    
    document.getElementById('previewSubject').textContent = previewSubject;
    document.getElementById('previewContent').innerHTML = `<style>${css}</style>${previewBody}`;
    document.getElementById('previewModal').classList.remove('hidden');
}

function closePreviewModal() {
    document.getElementById('previewModal').classList.add('hidden');
}

document.getElementById('previewModal').addEventListener('click', function(e) {
    if (e.target === this) closePreviewModal();
});
</script>

<?php require_once '../../templates/footer.php'; ?>
