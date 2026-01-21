<?php
require_once '../../app/bootstrap.php';
requireLogin();

requireAdminCounselorOrBranchManager();

// Fetch all students with visa workflows including SLA fields
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, 
           c.name as country_name, 
           vs.name as stage_name,
           vw.country as legacy_country,
           vw.current_stage as legacy_stage,
           vw.priority,
           vw.expected_completion_date,
           vw.updated_at,
           CASE 
               WHEN vw.expected_completion_date IS NOT NULL 
                    AND vw.expected_completion_date < CURDATE() 
                    AND vs.name NOT IN ('Approved', 'Rejected') THEN 1 
               ELSE 0 
           END as is_overdue
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN visa_workflows vw ON u.id = vw.student_id
    LEFT JOIN countries c ON vw.country_id = c.id
    LEFT JOIN visa_stages vs ON vw.stage_id = vs.id
    WHERE r.name = 'student'
    ORDER BY is_overdue DESC, vw.priority DESC, u.name ASC
");
$stmt->execute();
$students = $stmt->fetchAll();

// Get pipeline stats for summary cards
$pipelineStats = $pdo->query("
    SELECT vs.name as stage, COUNT(vw.id) as count
    FROM visa_stages vs
    LEFT JOIN visa_workflows vw ON vw.stage_id = vs.id
    GROUP BY vs.id, vs.name
    ORDER BY vs.stage_order
")->fetchAll(PDO::FETCH_KEY_PAIR);

$overdueCount = $pdo->query("
    SELECT COUNT(*) FROM visa_workflows vw
    JOIN visa_stages vs ON vw.stage_id = vs.id
    WHERE vw.expected_completion_date < CURDATE()
    AND vs.name NOT IN ('Approved', 'Rejected')
")->fetchColumn();

$pageDetails = ['title' => 'Visa Tracking'];
require_once '../../templates/header.php';
?>

<style>
    .stat-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        text-align: center;
    }

    .stat-card.overdue {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        border-color: #fca5a5;
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
    }

    .stat-label {
        font-size: 12px;
        color: #64748b;
        margin-top: 4px;
    }

    .priority-badge {
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .priority-normal {
        background: #e2e8f0;
        color: #475569;
    }

    .priority-urgent {
        background: #fef3c7;
        color: #92400e;
    }

    .priority-critical {
        background: #fee2e2;
        color: #dc2626;
    }

    .overdue-badge {
        background: #dc2626;
        color: white;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
    }

    .stage-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 500;
    }

    .stage-doc {
        background: #e0f2fe;
        color: #0369a1;
    }

    .stage-submission {
        background: #fef3c7;
        color: #92400e;
    }

    .stage-interview {
        background: #f3e8ff;
        color: #7c3aed;
    }

    .stage-approved {
        background: #dcfce7;
        color: #166534;
    }

    .stage-rejected {
        background: #fee2e2;
        color: #dc2626;
    }
</style>

<div class="card">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="page-title" style="margin: 0;">Visa Tracking Workflow</h2>
        <a href="document_types.php" class="btn btn-primary" style="font-size: 13px;">⚙️ Manage Document Types</a>
    </div>

    <?php renderFlashMessage(); ?>

    <!-- Pipeline Summary Cards -->
    <div class="stat-cards">
        <?php foreach ($pipelineStats as $stage => $count):
            $stageClass = strtolower(str_replace(' ', '-', $stage));
            ?>
            <div class="stat-card">
                <div class="stat-value"><?php echo $count; ?></div>
                <div class="stat-label"><?php echo htmlspecialchars($stage); ?></div>
            </div>
        <?php endforeach; ?>

        <div class="stat-card overdue">
            <div class="stat-value" style="color: #dc2626;"><?php echo $overdueCount; ?></div>
            <div class="stat-label">⚠ Overdue</div>
        </div>
    </div>

    <!-- Quick Search with Alpine.js -->
    <div class="bg-slate-50 px-4 py-3 rounded-lg border border-slate-200 mb-4">
        <div x-data='searchFilter({
            data: <?php echo json_encode(array_map(function ($s) {
                return [
                    'id' => $s['id'],
                    'name' => $s['name'],
                    'email' => $s['email'],
                    'country' => $s['country_name'] ?? $s['legacy_country'] ?? '',
                    'stage' => $s['stage_name'] ?? $s['legacy_stage'] ?? 'Not Started',
                    'priority' => $s['priority'] ?? 'normal',
                    'isOverdue' => (bool) $s['is_overdue']
                ];
            }, $students)); ?>,
            searchFields: ["name", "country", "stage"],
            minLength: 1,
            maxResults: 8
        })' class="relative">
            <div class="flex items-center gap-3">
                <span class="text-slate-400">🔍</span>
                <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 1) showResults = true"
                    @keydown="handleKeydown($event)" @keydown.escape="showResults = false"
                    class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500"
                    placeholder="Quick search by student name, country, or stage..." autocomplete="off">

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
                    <a :href="'update.php?student_id=' + item.id" :data-index="index"
                        @mouseenter="setSelectedIndex(index)"
                        class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 transition-colors"
                        :class="{ 'bg-primary-50 border-l-4 border-l-teal-600': isSelected(index), 'hover:bg-slate-50': !isSelected(index) }">
                        <div class="w-9 h-9 bg-gradient-to-br from-sky-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-xs"
                            x-text="item.name.substring(0, 2).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-800">
                                <span x-text="item.name"></span>
                                <span x-show="item.isOverdue"
                                    class="ml-1.5 px-1.5 py-0.5 bg-red-600 text-white text-[9px] font-bold rounded">OVERDUE</span>
                            </div>
                            <div class="text-xs text-slate-500" x-text="item.email"></div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-medium text-sky-700" x-text="item.stage"></div>
                            <div class="text-[10px] text-slate-400" x-text="item.country || 'No country'"></div>
                        </div>
                    </a>
                </template>

                <div x-show="results.length === 0 && query.length >= 1 && !loading"
                    class="px-4 py-3 text-center text-slate-500 text-sm">
                    No students found
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Country</th>
                <th>Stage</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Last Update</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $s):
                $stageName = $s['stage_name'] ?? $s['legacy_stage'] ?? 'N/A';
                $stageClass = 'stage-' . strtolower(str_replace(' ', '-', $stageName));
                ?>
                <tr <?php echo $s['is_overdue'] ? 'style="background: #fef2f2;"' : ''; ?>>
                    <td>
                        <strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($s['email']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($s['country_name'] ?? $s['legacy_country'] ?? '-'); ?></td>
                    <td>
                        <span class="stage-badge <?php echo $stageClass; ?>">
                            <?php echo htmlspecialchars($stageName); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($s['priority'] && $s['priority'] !== 'normal'): ?>
                            <span class="priority-badge priority-<?php echo $s['priority']; ?>">
                                <?php echo ucfirst($s['priority']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #94a3b8;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['is_overdue']): ?>
                            <span class="overdue-badge">⚠ OVERDUE</span>
                        <?php elseif ($s['expected_completion_date']): ?>
                            <span style="font-size: 11px; color: #64748b;">
                                Due: <?php echo date('M j', strtotime($s['expected_completion_date'])); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #94a3b8;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $s['updated_at'] ? date('M j, Y', strtotime($s['updated_at'])) : '-'; ?></td>
                    <td>
                        <a href="update.php?student_id=<?php echo $s['id']; ?>" class="action-btn default"
                            title="Update Stage">
                            <?php echo \EduCRM\Services\NavigationService::getIcon('edit', 16); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>