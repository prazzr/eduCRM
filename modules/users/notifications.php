<?php
require_once '../../app/bootstrap.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $preferences = $_POST['prefs'] ?? [];
        
        // Begin transaction
        $pdo->beginTransaction();

        // We receive prefs in format: prefs[event_key][channel] = 'on'
        
        // First, get all available events to know what we need to update
        $stmt = $pdo->query("SELECT event_key FROM notification_events");
        $events = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($events as $eventKey) {
            foreach (['email', 'sms', 'whatsapp'] as $channel) {
                // Check if enabled in POST
                $isEnabled = isset($preferences[$eventKey][$channel]) ? 1 : 0;

                // Upsert preference
                $stmt = $pdo->prepare("
                    INSERT INTO user_notification_preferences (user_id, event_key, channel, is_enabled)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)
                ");
                $stmt->execute([$user_id, $eventKey, $channel, $isEnabled]);
            }
        }
        
        $pdo->commit();
        $message = 'Notification preferences updated successfully.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to update preferences: ' . $e->getMessage();
    }
}

// Fetch Events and Current Preferences
$stmt = $pdo->query("SELECT * FROM notification_events ORDER BY category, name");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT event_key, channel, is_enabled FROM user_notification_preferences WHERE user_id = ?");
$stmt->execute([$user_id]);
$currentPrefs = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['is_enabled']) {
        $currentPrefs[$row['event_key']][$row['channel']] = true;
    }
}

// Group events by category
$groupedEvents = [];
foreach ($events as $event) {
    $groupedEvents[$event['category']][] = $event;
}

$pageDetails = ['title' => 'Notification Preferences'];
require_once '../../templates/header.php';
?>

<div class="max-w-4xl mx-auto mt-10 px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Notification Settings</h1>
        <a href="<?php echo BASE_URL; ?>" class="text-slate-500 hover:text-slate-700">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded mb-6">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <form method="POST" class="p-6">
            
            <?php foreach ($groupedEvents as $category => $categoryEvents): ?>
                <div class="mb-8 last:mb-0">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">
                        <?php echo ucfirst($category); ?> Notifications
                    </h3>
                    
                    <div class="space-y-4">
                        <?php foreach ($categoryEvents as $event): 
                            $defaultChannels = json_decode($event['default_channels'], true) ?? [];
                        ?>
                            <div class="flex items-center justify-between py-3 hover:bg-slate-50 rounded-lg px-2 -mx-2 transition-colors">
                                <div>
                                    <h4 class="font-medium text-slate-800"><?php echo htmlspecialchars($event['name']); ?></h4>
                                    <?php if ($event['description']): ?>
                                        <p class="text-sm text-slate-500"><?php echo htmlspecialchars($event['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex gap-4">
                                    <!-- Email Toggle -->
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <?php 
                                            // Determine if checked: explicit DB preference OR (not in DB AND default is true)
                                            // Actually simpler: we used ON DUPLICATE, so if not in DB, use default logic
                                            // The logic below checks $currentPrefs which is only from DB. 
                                            // If key missing in DB, we should fallback to default.
                                            
                                            $emailChecked = false;
                                            if (isset($currentPrefs[$event['event_key']]['email'])) {
                                                $emailChecked = true;
                                            } elseif (!isset($currentPrefs[$event['event_key']]) && in_array('email', $defaultChannels)) {
                                                // Not set in DB at all for this event, so use default
                                                $emailChecked = true; 
                                            }
                                        ?>
                                        <input type="checkbox" name="prefs[<?php echo $event['event_key']; ?>][email]" 
                                            class="form-checkbox h-4 w-4 text-primary-600 rounded border-slate-300"
                                            <?php echo $emailChecked ? 'checked' : ''; ?>>
                                        <span class="text-sm text-slate-600">Email</span>
                                    </label>

                                    <!-- SMS Toggle -->
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <?php 
                                            $smsChecked = false;
                                            if (isset($currentPrefs[$event['event_key']]['sms'])) {
                                                $smsChecked = true;
                                            } elseif (!isset($currentPrefs[$event['event_key']]) && in_array('sms', $defaultChannels)) {
                                                $smsChecked = true;
                                            }
                                        ?>
                                        <input type="checkbox" name="prefs[<?php echo $event['event_key']; ?>][sms]" 
                                            class="form-checkbox h-4 w-4 text-primary-600 rounded border-slate-300"
                                            <?php echo $smsChecked ? 'checked' : ''; ?>>
                                        <span class="text-sm text-slate-600">SMS</span>
                                    </label>
                                    
                                     <!-- WhatsApp Toggle -->
                                     <label class="flex items-center space-x-2 cursor-pointer">
                                        <?php 
                                            $waChecked = false;
                                            if (isset($currentPrefs[$event['event_key']]['whatsapp'])) {
                                                $waChecked = true;
                                            } elseif (!isset($currentPrefs[$event['event_key']]) && in_array('whatsapp', $defaultChannels)) {
                                                $waChecked = true;
                                            }
                                        ?>
                                        <input type="checkbox" name="prefs[<?php echo $event['event_key']; ?>][whatsapp]" 
                                            class="form-checkbox h-4 w-4 text-primary-600 rounded border-slate-300"
                                            <?php echo $waChecked ? 'checked' : ''; ?>>
                                        <span class="text-sm text-slate-600">WhatsApp</span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="mt-8 pt-6 border-t border-slate-200 flex justify-end">
                <button type="submit" class="btn btn-primary px-6">Save Preferences</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
