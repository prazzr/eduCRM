<?php
/**
 * Visa Workflow Update
 * Manages visa application workflow stages and document checklists
 */
require_once '../../app/bootstrap.php';



requireLogin();
requireAdminCounselorOrBranchManager();

// Validate student ID parameter
$student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
if (!$student_id) {
    die("Invalid Student ID");
}

// Load lookup data using cached service
$lookup = \EduCRM\Services\LookupCacheService::getInstance($pdo);
$countries = $lookup->getActiveRecords('countries');
$stages = $lookup->getAll('visa_stages');

// Fetch Student & Current Workflow with FK columns + new SLA fields
$stmt = $pdo->prepare("SELECT u.*, 
                      vw.id as workflow_id, 
                      vw.country_id,
                      vw.stage_id,
                      vw.checklist_json,
                      vw.stage_started_at,
                      vw.expected_completion_date,
                      vw.priority,
                      c.name as visa_country, 
                      vs.name as current_stage,
                      vs.default_sla_days,
                      vs.allowed_next_stages,
                      vw.notes 
                      FROM users u 
                      LEFT JOIN visa_workflows vw ON u.id = vw.student_id 
                      LEFT JOIN countries c ON vw.country_id = c.id
                      LEFT JOIN visa_stages vs ON vw.stage_id = vs.id
                      WHERE u.id = ?");
$stmt->execute([$student_id]);
$data = $stmt->fetch();

if (!$data) {
    die("Student not found.");
}

// Load history for this workflow
$history = [];
if ($data['workflow_id']) {
    $histStmt = $pdo->prepare("
        SELECT h.*, 
               fs.name as from_stage_name, 
               ts.name as to_stage_name,
               u.name as changed_by_name
        FROM visa_workflow_history h
        LEFT JOIN visa_stages fs ON h.from_stage_id = fs.id
        LEFT JOIN visa_stages ts ON h.to_stage_id = ts.id
        LEFT JOIN users u ON h.changed_by = u.id
        WHERE h.workflow_id = ?
        ORDER BY h.changed_at DESC
        LIMIT 20
    ");
    $histStmt->execute([$data['workflow_id']]);
    $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Load document types from database (instead of hardcoded)
$docTypesStmt = $pdo->query("SELECT * FROM document_types WHERE is_active = 1 ORDER BY display_order");
$documentTypes = $docTypesStmt->fetchAll(PDO::FETCH_ASSOC);

// Load student's current document statuses
// Load student's current document statuses
$studentDocsStmt = $pdo->prepare("
    SELECT sd.*, dt.code
    FROM student_documents sd
    JOIN document_types dt ON sd.document_type_id = dt.id
    WHERE sd.student_id = ?
");
$studentDocsStmt->execute([$student_id]);

// Map documents by type ID
$studentDocsMap = [];
while ($sd = $studentDocsStmt->fetch(PDO::FETCH_ASSOC)) {
    $studentDocsMap[$sd['document_type_id']] = $sd;
}

// Get student documents in a proper map
$sdMapStmt = $pdo->prepare("
    SELECT sd.document_type_id, sd.status, sd.file_path, sd.original_filename
    FROM student_documents sd
    WHERE sd.student_id = ?
");
$sdMapStmt->execute([$student_id]);
$studentDocsMap = [];
foreach ($sdMapStmt->fetchAll(PDO::FETCH_ASSOC) as $sd) {
    $studentDocsMap[$sd['document_type_id']] = $sd;
}

// Build checklist from database
$checklist = [];
foreach ($documentTypes as $dt) {
    $existingDoc = $studentDocsMap[$dt['id']] ?? null;
    $status = $existingDoc ? $existingDoc['status'] : 'pending';
    $hasFile = $existingDoc && !empty($existingDoc['file_path']);

    $checklist[] = [
        'id' => $dt['id'],
        'code' => $dt['code'],
        'label' => $dt['name'],
        'required' => $dt['is_required_default'],
        'status' => $status,
        'has_file' => $hasFile,
        'filename' => $existingDoc['original_filename'] ?? null
    ];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $country_id = !empty($_POST['country_id']) ? (int) $_POST['country_id'] : null;
    $stage_id = (int) $_POST['stage_id'];
    $priority = in_array($_POST['priority'] ?? '', ['normal', 'urgent', 'critical']) ? $_POST['priority'] : 'normal';
    $notes = sanitize($_POST['notes']);

    // Update checklist from form - save to student_documents table
    foreach ($checklist as $item) {
        $docTypeId = $item['id'];
        $status = $_POST['checklist_' . $docTypeId] ?? 'pending';
        $validStatuses = ['pending', 'uploaded', 'verified', 'rejected', 'not_required'];
        $status = in_array($status, $validStatuses) ? $status : 'pending';

        // Upsert into student_documents
        $upsertStmt = $pdo->prepare("
            INSERT INTO student_documents (student_id, document_type_id, status, workflow_id)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                verified_by = IF(VALUES(status) = 'verified', ?, verified_by),
                verified_at = IF(VALUES(status) = 'verified', NOW(), verified_at)
        ");
        $upsertStmt->execute([
            $student_id,
            $docTypeId,
            $status,
            $data['workflow_id'],
            $_SESSION['user_id']
        ]);
    }

    // Keep checklist_json for backward compatibility
    $checklist_json = json_encode($checklist);

    // Validate stage transition (Gap #5)
    $transitionValid = true;
    $old_stage_id = $data['stage_id'];
    if ($old_stage_id && $old_stage_id != $stage_id && !empty($data['allowed_next_stages'])) {
        $allowed = json_decode($data['allowed_next_stages'], true) ?: [];
        $newStageName = '';
        foreach ($stages as $s) {
            if ($s['id'] == $stage_id) {
                $newStageName = $s['name'];
                break;
            }
        }
        if (!empty($allowed) && !in_array($newStageName, $allowed)) {
            $transitionValid = false;
            redirectWithAlert("update.php?student_id=$student_id", "Invalid transition: Cannot move from '{$data['current_stage']}' to '$newStageName'. Allowed: " . implode(', ', $allowed), 'error');
        }
    }

    if ($transitionValid) {
        // Get SLA for new stage
        $new_sla_days = 7;
        foreach ($stages as $s) {
            if ($s['id'] == $stage_id) {
                $new_sla_days = $s['default_sla_days'] ?? 7;
                break;
            }
        }
        $expected_date = date('Y-m-d', strtotime("+$new_sla_days days"));

        if ($data['workflow_id']) {
            // Record history if stage changed (Gap #1)
            if ($old_stage_id != $stage_id) {
                $hist = $pdo->prepare("INSERT INTO visa_workflow_history 
                    (workflow_id, from_stage_id, to_stage_id, changed_by, notes) 
                    VALUES (?, ?, ?, ?, ?)");
                $hist->execute([$data['workflow_id'], $old_stage_id, $stage_id, $_SESSION['user_id'], $notes]);
            }

            // Update existing with SLA fields (Gap #3)
            $upd = $pdo->prepare("UPDATE visa_workflows SET 
                country_id = ?, 
                stage_id = ?, 
                checklist_json = ?,
                priority = ?,
                stage_started_at = IF(? != stage_id, NOW(), stage_started_at),
                expected_completion_date = IF(? != stage_id, ?, expected_completion_date),
                notes = ? 
                WHERE student_id = ?");
            $upd->execute([
                $country_id,
                $stage_id,
                $checklist_json,
                $priority,
                $stage_id,
                $stage_id,
                $expected_date,
                $notes,
                $student_id
            ]);
        } else {
            // Create new using FK columns with SLA
            $ins = $pdo->prepare("INSERT INTO visa_workflows 
                (student_id, country_id, stage_id, checklist_json, priority, stage_started_at, expected_completion_date, notes) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
            $ins->execute([$student_id, $country_id, $stage_id, $checklist_json, $priority, $expected_date, $notes]);

            $workflow_id = $pdo->lastInsertId();

            // Record initial history entry
            $hist = $pdo->prepare("INSERT INTO visa_workflow_history 
                (workflow_id, from_stage_id, to_stage_id, changed_by, notes) 
                VALUES (?, NULL, ?, ?, ?)");
            $hist->execute([$workflow_id, $stage_id, $_SESSION['user_id'], "Initial visa workflow created"]);
        }

        // Get names for log
        $countryName = '';
        $stageName = '';
        foreach ($countries as $c) {
            if ($c['id'] == $country_id) {
                $countryName = $c['name'];
                break;
            }
        }
        foreach ($stages as $s) {
            if ($s['id'] == $stage_id) {
                $stageName = $s['name'];
                break;
            }
        }

        // Add a log entry for the student
        $log = $pdo->prepare("INSERT INTO student_logs (student_id, author_id, type, message) VALUES (?, ?, 'note', ?)");
        $msg = "Visa status updated for $countryName: $stageName (Priority: $priority). Notes: $notes";
        $log->execute([$student_id, $_SESSION['user_id'], $msg]);

        // Send email notification if stage changed
        if ($old_stage_id && $old_stage_id != $stage_id && $data['workflow_id']) {
            try {
                $emailService = new \EduCRM\Services\EmailNotificationService($pdo);
                $emailService->sendWorkflowUpdateNotification($data['workflow_id'], $old_stage_id, $stage_id);
            } catch (Exception $e) {
                error_log("Failed to send workflow update email: " . $e->getMessage());
            }
        }

        redirectWithAlert("list.php", "Visa status updated successfully.", 'warning');
    }
}

// Calculate checklist completion (verified or not_required count as complete)
$checklistComplete = 0;
$checklistTotal = count($checklist);
foreach ($checklist as $item) {
    if (in_array($item['status'], ['verified', 'not_required']))
        $checklistComplete++;
}
$checklistPercent = $checklistTotal > 0 ? round(($checklistComplete / $checklistTotal) * 100) : 0;

// Check if overdue
$isOverdue = false;
if (
    $data['expected_completion_date'] && $data['current_stage'] &&
    !in_array($data['current_stage'], ['Approved', 'Rejected'])
) {
    $isOverdue = strtotime($data['expected_completion_date']) < time();
}

$pageDetails = ['title' => 'Update Visa Status'];
require_once '../../templates/header.php';
?>

<style>
    .visa-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 24px;
    }

    @media (max-width: 900px) {
        .visa-grid {
            grid-template-columns: 1fr;
        }
    }

    .timeline {
        border-left: 3px solid #e2e8f0;
        padding-left: 20px;
        margin-left: 10px;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -27px;
        top: 4px;
        width: 12px;
        height: 12px;
        background: #0ea5e9;
        border-radius: 50%;
        border: 2px solid #fff;
    }

    .timeline-item.initial::before {
        background: #22c55e;
    }

    .checklist-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 8px;
        background: #f8fafc;
    }

    .checklist-item.verified {
        background: #dcfce7;
    }

    .checklist-item.uploaded {
        background: #e0f2fe;
    }

    .checklist-item.rejected {
        background: #fee2e2;
    }

    .checklist-item.not_required {
        background: #f3f4f6;
        opacity: 0.7;
    }

    .priority-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
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
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.7;
        }
    }

    .progress-bar {
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #0ea5e9, #22c55e);
        transition: width 0.3s;
    }
</style>

<div class="visa-grid">
    <!-- Main Form -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Update Visa Status</h2>
            <div style="display: flex; gap: 8px; align-items: center;">
                <?php if ($isOverdue): ?>
                    <span class="overdue-badge">⚠ OVERDUE</span>
                <?php endif; ?>
                <?php if ($data['priority']): ?>
                    <span
                        class="priority-badge priority-<?php echo $data['priority']; ?>"><?php echo ucfirst($data['priority']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <p style="color: #64748b; margin-bottom: 20px;">
            Student: <strong><?php echo htmlspecialchars($data['name']); ?></strong>
            <?php if ($data['expected_completion_date']): ?>
                | Expected: <strong><?php echo date('M j, Y', strtotime($data['expected_completion_date'])); ?></strong>
            <?php endif; ?>
        </p>

        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Destination Country</label>
                    <select name="country_id" class="form-control" required>
                        <option value="">Select Country</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $data['country_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control">
                        <option value="normal" <?php echo ($data['priority'] ?? 'normal') == 'normal' ? 'selected' : ''; ?>>Normal</option>
                        <option value="urgent" <?php echo $data['priority'] == 'urgent' ? 'selected' : ''; ?>>Urgent
                        </option>
                        <option value="critical" <?php echo $data['priority'] == 'critical' ? 'selected' : ''; ?>>Critical
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Current Stage</label>
                <select name="stage_id" class="form-control" required>
                    <?php foreach ($stages as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $data['stage_id'] == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($data['allowed_next_stages']):
                    $allowed = json_decode($data['allowed_next_stages'], true) ?: [];
                    if (!empty($allowed)):
                        ?>
                        <small style="color: #64748b;">Allowed transitions: <?php echo implode(', ', $allowed); ?></small>
                    <?php endif; endif; ?>
            </div>

            <!-- Checklist Section (Gap #4) -->
            <div class="form-group">
                <label style="display: flex; justify-content: space-between; align-items: center;">
                    Document Checklist
                    <span
                        style="font-size: 12px; color: #64748b;"><?php echo $checklistComplete; ?>/<?php echo $checklistTotal; ?>
                        verified (<?php echo $checklistPercent; ?>%)</span>
                </label>
                <div class="progress-bar" style="margin-bottom: 12px;">
                    <div class="progress-fill" style="width: <?php echo $checklistPercent; ?>%;"></div>
                </div>

                <?php foreach ($checklist as $item): ?>
                    <div class="checklist-item <?php echo $item['status']; ?>">
                        <span style="flex: 1;">
                            <?php echo htmlspecialchars($item['label']); ?>
                            <?php if ($item['required']): ?><span style="color: #dc2626;">*</span><?php endif; ?>
                            <?php if ($item['has_file']): ?>
                                <span style="font-size: 11px; color: #0ea5e9;">📎
                                    <?php echo htmlspecialchars($item['filename']); ?></span>
                            <?php endif; ?>
                        </span>
                        <select name="checklist_<?php echo $item['id']; ?>"
                            style="padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <option value="pending" <?php echo $item['status'] == 'pending' ? 'selected' : ''; ?>>⏳ Pending
                            </option>
                            <option value="uploaded" <?php echo $item['status'] == 'uploaded' ? 'selected' : ''; ?>>📤
                                Uploaded</option>
                            <option value="verified" <?php echo $item['status'] == 'verified' ? 'selected' : ''; ?>>✅ Verified
                            </option>
                            <option value="rejected" <?php echo $item['status'] == 'rejected' ? 'selected' : ''; ?>>❌ Rejected
                            </option>
                            <option value="not_required" <?php echo $item['status'] == 'not_required' ? 'selected' : ''; ?>>➖
                                Not Required
                            </option>
                        </select>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top: 12px; text-align: right;">
                    <a href="document_types.php" style="font-size: 12px; color: #6b7280;">⚙️ Manage Document Types</a>
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control"
                    rows="3"><?php echo htmlspecialchars($data['notes'] ?? ''); ?></textarea>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn">Update Workflow</button>
                <a href="list.php" class="btn btn-secondary">Back to List</a>
            </div>
        </form>
    </div>

    <!-- History Timeline (Gap #1) -->
    <div class="card">
        <h3 style="margin-top: 0;">Stage History</h3>

        <?php if (empty($history)): ?>
            <p style="color: #64748b; text-align: center; padding: 20px;">No history yet. Updates will appear here.</p>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($history as $h): ?>
                    <div class="timeline-item <?php echo $h['from_stage_id'] === null ? 'initial' : ''; ?>">
                        <div style="font-weight: 600; color: #1e293b;">
                            <?php if ($h['from_stage_id']): ?>
                                <?php echo htmlspecialchars($h['from_stage_name']); ?> →
                                <?php echo htmlspecialchars($h['to_stage_name']); ?>
                            <?php else: ?>
                                Workflow Created: <?php echo htmlspecialchars($h['to_stage_name']); ?>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                            by <?php echo htmlspecialchars($h['changed_by_name']); ?> •
                            <?php echo date('M j, Y g:i A', strtotime($h['changed_at'])); ?>
                        </div>
                        <?php if ($h['notes']): ?>
                            <div
                                style="font-size: 13px; color: #475569; margin-top: 6px; padding: 8px; background: #f8fafc; border-radius: 6px;">
                                <?php echo htmlspecialchars($h['notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>