<?php
require_once '../../app/bootstrap.php';

requireLogin();
requireAdminCounselorOrBranchManager();

$templateId = $_GET['id'] ?? null;

if (!$templateId) {
    die('Template ID required');
}

$stmt = $pdo->prepare("SELECT * FROM messaging_templates WHERE id = ?");
$stmt->execute([$templateId]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die('Template not found');
}

$variables = json_decode($template['variables'], true) ?? [];
?>

<div class="space-y-4">
    <div>
        <h3 class="font-bold text-slate-800 mb-2">
            <?php echo htmlspecialchars($template['name']); ?>
        </h3>
        <p class="text-sm text-slate-600">Type:
            <?php echo ucfirst($template['message_type']); ?>
        </p>
        <?php if ($template['category']): ?>
            <p class="text-sm text-slate-600">Category:
                <?php echo ucfirst($template['category']); ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($template['subject']): ?>
        <div>
            <p class="text-xs font-medium text-slate-700 mb-1">Subject:</p>
            <p class="text-sm text-slate-800">
                <?php echo htmlspecialchars($template['subject']); ?>
            </p>
        </div>
    <?php endif; ?>

    <div>
        <p class="text-xs font-medium text-slate-700 mb-1">Message:</p>
        <div class="p-3 bg-slate-50 rounded-lg text-sm text-slate-800 whitespace-pre-wrap">
            <?php echo htmlspecialchars($template['content']); ?>
        </div>
    </div>

    <?php if (count($variables) > 0): ?>
        <div>
            <p class="text-xs font-medium text-slate-700 mb-2">Available Variables:</p>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($variables as $var): ?>
                    <span class="px-2 py-1 bg-primary-100 text-primary-700 text-xs rounded font-mono">{
                        <?php echo $var; ?>}
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="pt-4 border-t border-slate-200">
        <p class="text-xs text-slate-500">
            Created by
            <?php echo htmlspecialchars($template['created_by_name'] ?? 'Unknown'); ?> on
            <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
        </p>
    </div>
</div>