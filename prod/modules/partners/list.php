<?php
require_once '../../app/bootstrap.php';
requireLogin();

// Admin/Counselor only
if (hasRole('student')) {
    header("Location: ../../index.php");
    exit;
}

$message = '';
// Add Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_partner'])) {
    $name = sanitize($_POST['name']);
    $country = sanitize($_POST['country']);
    $type = $_POST['type'];
    $website = sanitize($_POST['website']);

    $stmt = $pdo->prepare("INSERT INTO partners (name, country, type, website) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $country, $type, $website]);
    redirectWithAlert("list.php", "Partner added.", 'success');
}

$partners = $pdo->query("SELECT * FROM partners ORDER BY name")->fetchAll();

$pageDetails = ['title' => 'Partners Database'];
require_once '../../templates/header.php';
?>

<div class="card">
    <div class="page-header">
        <h2 class="page-title">Partner Universities & Agents</h2>
    </div>



    <?php renderFlashMessage(); ?>

    <!-- Quick Search with Alpine.js -->
    <div class="bg-slate-50 px-4 py-3 rounded-lg border border-slate-200 mb-4">
        <div x-data='searchFilter({
            data: <?php echo json_encode(array_map(function ($p) {
                return [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'country' => $p['country'] ?? '',
                    'type' => $p['type'] ?? '',
                    'website' => $p['website'] ?? ''
                ];
            }, $partners)); ?>,
            searchFields: ["name", "country", "type"],
            minLength: 1,
            maxResults: 8
        })' class="relative">
            <div class="flex items-center gap-3">
                <span class="text-slate-400">🔍</span>
                <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 1) showResults = true"
                    @keydown="handleKeydown($event)" @keydown.escape="showResults = false"
                    class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500"
                    placeholder="Quick search by name, country, or type..." autocomplete="off">

                <span x-show="loading" class="spinner text-slate-400"></span>
            </div>

            <!-- Search Results Dropdown -->
            <div x-show="showResults && results.length > 0" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" @click.outside="showResults = false"
                class="search-results-container absolute top-full left-0 right-0 mt-2 bg-white border border-slate-200 rounded-xl shadow-lg max-h-80 overflow-y-auto z-50">

                <template x-for="(item, index) in results" :key="item.id">
                    <a :href="'edit.php?id=' + item.id" :data-index="index" @mouseenter="setSelectedIndex(index)"
                        class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 transition-colors"
                        :class="{ 'bg-primary-50 border-l-4 border-l-teal-600': isSelected(index), 'hover:bg-slate-50': !isSelected(index) }">
                        <div class="w-9 h-9 bg-gradient-to-br from-teal-500 to-emerald-600 rounded-lg flex items-center justify-center text-white font-bold text-xs"
                            x-text="(item.type || 'U').charAt(0).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-800" x-text="item.name"></div>
                            <div class="text-xs text-slate-500">
                                <span x-text="item.country"></span> • <span x-text="item.type"></span>
                            </div>
                        </div>
                    </a>
                </template>

                <div x-show="results.length === 0 && query.length >= 1 && !loading"
                    class="px-4 py-3 text-center text-slate-500 text-sm">
                    No partners found
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id) {
            Modal.show({
                type: 'error',
                title: 'Delete Partner?',
                message: 'Are you sure you want to delete this partner? This action cannot be undone.',
                confirmText: 'Yes, Delete It',
                onConfirm: function () {
                    window.location.href = 'delete.php?id=' + id;
                }
            });
        }
    </script>

    <form method="POST" style="margin-bottom: 30px; background: #f8fafc; padding: 15px; border-radius: 8px;">
        <input type="hidden" name="add_partner" value="1">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 2fr; gap: 15px; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label>Institution Name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. University of Oxford">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Country</label>
                <input type="text" name="country" class="form-control" required placeholder="UK">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Type</label>
                <select name="type" class="form-control">
                    <option value="university">University</option>
                    <option value="college">College</option>
                    <option value="agent">Agent</option>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Website</label>
                <input type="text" name="website" class="form-control" placeholder="https://...">
            </div>
        </div>
        <button type="submit" class="btn" style="margin-top: 15px;">Add Partner</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Country</th>
                <th>Website</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($partners as $p): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                    <td><span class="status-badge" style="background: #e2e8f0;"><?php echo ucfirst($p['type']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($p['country']); ?></td>
                    <td><a href="<?php echo htmlspecialchars($p['website']); ?>"
                            target="_blank"><?php echo htmlspecialchars($p['website']); ?></a></td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 5px; justify-content: flex-end;">
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="action-btn default" title="Edit">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('edit', 16); ?>
                            </a>
                            <a href="#" onclick="confirmDelete(<?php echo $p['id']; ?>)" class="action-btn red"
                                title="Delete">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('trash', 16); ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>