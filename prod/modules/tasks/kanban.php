<?php
/**
 * Tasks Kanban Board View
 * Drag-and-drop task management
 */
require_once '../../app/bootstrap.php';
requireLogin();

$pageDetails = ['title' => 'Tasks - Kanban Board'];
require_once '../../templates/header.php';

$kanbanConfig = [
    'entity' => 'tasks',
    'apiEndpoint' => '../../api/v1/kanban/index.php?path=tasks'
];
?>

<div class="page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="page-title">Task Board</h1>
        <p class="text-slate-500 text-sm">Drag and drop tasks to update status</p>
    </div>
    <div class="flex gap-3">
        <!-- View Toggle -->
        <div
            class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-1 bg-slate-100 dark:bg-slate-800">
            <a href="list.php"
                class="px-3 py-1.5 text-sm rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 transition-colors flex items-center gap-1">
                <?php echo \EduCRM\Services\NavigationService::getIcon('list', 16); ?> List
            </a>
            <span
                class="px-3 py-1.5 text-sm rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-200 shadow-sm flex items-center gap-1">
                <?php echo \EduCRM\Services\NavigationService::getIcon('columns', 16); ?> Board
            </span>
        </div>
        <a href="add.php" class="btn btn-primary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> Add Task
        </a>
    </div>
</div>

<?php include '../../templates/components/kanban-board.php'; ?>

<?php require_once '../../templates/footer.php'; ?>