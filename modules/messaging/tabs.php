<?php
// Messaging Module Common Navigation
$current_page = basename($_SERVER['PHP_SELF']);

$nav_items = [];

// Gateways tab - admin only (system infrastructure)
if (hasRole('admin')) {
    $nav_items['gateways.php'] = ['label' => 'Gateways', 'icon' => 'server'];
}

// Common tabs for admin, counselor, and branch_manager
$nav_items['templates.php'] = ['label' => 'Templates', 'icon' => 'file-text'];
$nav_items['campaigns.php'] = ['label' => 'Campaigns', 'icon' => 'send'];
$nav_items['queue.php'] = ['label' => 'Queue', 'icon' => 'list'];
$nav_items['contacts.php'] = ['label' => 'Contacts', 'icon' => 'users'];

// Logs tab - admin only
if (hasRole('admin')) {
    $nav_items['gateway_logs.php'] = ['label' => 'Logs', 'icon' => 'activity'];
}
?>

<div class="mb-6 border-b border-slate-200">
    <div class="flex gap-1 overflow-x-auto">
        <?php foreach ($nav_items as $file => $item):
            $isActive = ($current_page === $file) ||
                ($current_page === 'gateway_logs.php' && $file === 'gateways.php'); // Logs is child of gateways
        
            // Special case overrides if needed
            if ($current_page === 'gateway_logs.php' && $file === 'gateway_logs.php')
                $isActive = true;
            if ($current_page === 'gateway_logs.php' && $file === 'gateways.php')
                $isActive = false;

            $activeClass = $isActive
                ? 'border-primary-600 text-primary-600 bg-primary-50/50'
                : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50';
            ?>
            <a href="<?php echo htmlspecialchars($file); ?>"
                class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap <?php echo $activeClass; ?>">
                <?php echo \EduCRM\Services\NavigationService::getIcon($item['icon'], 16); ?>
                <?php echo htmlspecialchars($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>