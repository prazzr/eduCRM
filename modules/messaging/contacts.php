<?php
require_once '../../app/bootstrap.php';


requireLogin();
requireAdminCounselorOrBranchManager();

\EduCRM\Services\MessagingFactory::init($pdo);

// Handle contact actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_contact') {
        $tags = [];
        if (!empty($_POST['tags'])) {
            $tags = array_map('trim', explode(',', $_POST['tags']));
        }

        $stmt = $pdo->prepare("
            INSERT INTO messaging_contacts (name, phone_number, email, whatsapp_number, entity_type, entity_id, tags)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['name'],
            $_POST['phone_number'],
            $_POST['email'] ?? null,
            $_POST['whatsapp_number'] ?? $_POST['phone_number'],
            $_POST['entity_type'] ?? null,
            $_POST['entity_id'] ?? null,
            json_encode($tags)
        ]);

        redirectWithAlert('contacts.php', 'Contact added successfully', 'success');
    }

    if ($_POST['action'] === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $header = fgetcsv($file); // Skip header
            $imported = 0;

            while (($row = fgetcsv($file)) !== false) {
                if (count($row) >= 2) { // At least name and phone
                    $stmt = $pdo->prepare("
                        INSERT INTO messaging_contacts (name, phone_number, email, tags)
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $row[0], // name
                        $row[1], // phone
                        $row[2] ?? null, // email
                        json_encode([])
                    ]);

                    $imported++;
                }
            }

            fclose($file);

            redirectWithAlert('contacts.php', "Imported $imported contacts successfully", 'success');
        }
    }

    if ($_POST['action'] === 'sync_students') {
        $stmt = $pdo->query("
            SELECT id, name, email, phone FROM users WHERE role = 'student'
        ");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $synced = 0;

        foreach ($students as $student) {
            if ($student['phone']) {
                // Check if already exists
                $check = $pdo->prepare("SELECT id FROM messaging_contacts WHERE entity_type = 'student' AND entity_id = ?");
                $check->execute([$student['id']]);

                if (!$check->fetch()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO messaging_contacts (name, phone_number, email, entity_type, entity_id, tags)
                        VALUES (?, ?, ?, 'student', ?, ?)
                    ");

                    $stmt->execute([
                        $student['name'],
                        $student['phone'],
                        $student['email'],
                        $student['id'],
                        json_encode(['student'])
                    ]);

                    $synced++;
                }
            }
        }

        redirectWithAlert('contacts.php', "Synced $synced student contacts", 'success');
    }
}

// Get all contacts
$search = $_GET['search'] ?? '';
$tag = $_GET['tag'] ?? '';

$sql = "SELECT * FROM messaging_contacts WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (name LIKE ? OR phone_number LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all unique tags
$stmt = $pdo->query("SELECT DISTINCT tags FROM messaging_contacts WHERE tags IS NOT NULL");
$allTags = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $tags = json_decode($row['tags'], true) ?? [];
    $allTags = array_merge($allTags, $tags);
}
$allTags = array_unique($allTags);

$pageDetails = ['title' => 'Contacts'];
require_once '../../templates/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">👥 Contacts</h1>
        <p class="text-slate-600 mt-1">Manage messaging contacts</p>
    </div>
    <div class="flex gap-2">
        <button onclick="showSyncModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Sync Students</button>
        <button onclick="showImportModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Import CSV</button>
        <button onclick="showAddModal()" class="btn">+ Add Contact</button>
    </div>
</div>

<?php renderFlashMessage(); ?>

<!-- Search & Filters -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
    <form method="GET" class="flex gap-3">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
            placeholder="Search contacts..." class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm">Search</button>
        <a href="contacts.php" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Clear</a>
    </form>
</div>

<!-- Contacts Table -->
<?php if (count($contacts) > 0): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Phone</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Tags</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Type</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-700 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($contacts as $contact):
                    $tags = json_decode($contact['tags'], true) ?? [];
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-medium text-slate-800">
                            <?php echo htmlspecialchars($contact['name']); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <?php echo htmlspecialchars($contact['phone_number']); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <?php echo htmlspecialchars($contact['email'] ?? '-'); ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if (count($tags) > 0): ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($tags as $tag): ?>
                                        <span class="px-2 py-0.5 bg-primary-100 text-primary-700 text-xs rounded">
                                            <?php echo $tag; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-sm text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <?php echo $contact['entity_type'] ? ucfirst($contact['entity_type']) : 'Custom'; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="sendMessage(<?php echo $contact['id']; ?>)"
                                class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                                Send SMS
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
        <div class="text-6xl mb-4">👥</div>
        <h3 class="text-lg font-semibold text-slate-800 mb-2">No Contacts Yet</h3>
        <p class="text-slate-600 mb-4">Add contacts manually, import from CSV, or sync from students</p>
        <div class="flex gap-3 justify-center">
            <button onclick="showAddModal()" class="btn inline-block">+ Add Contact</button>
            <button onclick="showImportModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-block">Import
                CSV</button>
        </div>
    </div>
<?php endif; ?>

<!-- Add Contact Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Add Contact</h2>
            <button onclick="closeAddModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="add_contact">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Name *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Phone Number *</label>
                    <input type="tel" name="phone_number" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="+1234567890">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Tags (comma-separated)</label>
                    <input type="text" name="tags" class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="student, vip, active">
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button type="submit" class="btn">Add Contact</button>
                <button type="button" onclick="closeAddModal()"
                    class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Import CSV Modal -->
<div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Import Contacts from CSV</h2>
            <button onclick="closeImportModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="p-6">
            <input type="hidden" name="action" value="import_csv">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">CSV File *</label>
                    <input type="file" name="csv_file" accept=".csv" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <p class="text-xs text-slate-500 mt-1">Format: name, phone, email (header row required)</p>
                </div>

                <div class="p-3 bg-slate-50 rounded-lg">
                    <p class="text-xs font-medium text-slate-700 mb-2">Example CSV format:</p>
                    <pre class="text-xs text-slate-600">name,phone,email
John Doe,+1234567890,john@example.com
Jane Smith,+0987654321,jane@example.com</pre>
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button type="submit" class="btn">Import Contacts</button>
                <button type="button" onclick="closeImportModal()"
                    class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Sync Students Modal -->
<div id="syncModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Sync Students</h2>
            <button onclick="closeSyncModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="sync_students">

            <p class="text-sm text-slate-600 mb-4">
                This will import all students from the system into your contacts list.
                Existing contacts will not be duplicated.
            </p>

            <div class="flex gap-3 pt-4 border-t border-slate-200">
                <button type="submit" class="btn">Sync Now</button>
                <button type="button" onclick="closeSyncModal()"
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

    function showImportModal() {
        document.getElementById('importModal').classList.remove('hidden');
    }

    function closeImportModal() {
        document.getElementById('importModal').classList.add('hidden');
    }

    function showSyncModal() {
        document.getElementById('syncModal').classList.remove('hidden');
    }

    function closeSyncModal() {
        document.getElementById('syncModal').classList.add('hidden');
    }

    function sendMessage(contactId) {
        window.location.href = `campaigns.php?contact_id=${contactId}`;
    }
</script>

<?php require_once '../../templates/footer.php'; ?>