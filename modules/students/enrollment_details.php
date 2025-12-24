<?php
require_once '../../config.php';
requireLogin();

$student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;

if (!$student_id || !$class_id) {
    header("Location: list.php"); // Fallback
    exit;
}

// Year and Month for Calendar
$year = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : date('n');

// Fetch basic info
$info_stmt = $pdo->prepare("
    SELECT u.name as student_name, c.name as class_name, co.name as course_name, c.teacher_id, ut.name as teacher_name
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN classes c ON e.class_id = c.id
    JOIN courses co ON c.course_id = co.id
    LEFT JOIN users ut ON c.teacher_id = ut.id
    WHERE e.student_id = ? AND e.class_id = ?
");
$info_stmt->execute([$student_id, $class_id]);
$details = $info_stmt->fetch();

if (!$details)
    die("Enrollment record not found.");

// Fetch all monthly performance data for calendar
$start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$end_date = date("Y-m-t", strtotime($start_date));

$perf_stmt = $pdo->prepare("
    SELECT dp.*, dr.roster_date 
    FROM daily_performance dp
    JOIN daily_rosters dr ON dp.roster_id = dr.id
    WHERE dp.student_id = ? AND dr.class_id = ? 
    AND dr.roster_date BETWEEN ? AND ?
");
$perf_stmt->execute([$student_id, $class_id, $start_date, $end_date]);
$monthly_perf = $perf_stmt->fetchAll();

// Map performance to days
$day_map = [];
$monthly_absents = 0;
$total_class_marks = 0;
$total_home_marks = 0;
$marks_count = 0;

foreach ($monthly_perf as $p) {
    $d = (int) date('j', strtotime($p['roster_date']));
    $day_map[$d] = $p;

    if ($p['attendance'] == 'absent')
        $monthly_absents++;

    $total_class_marks += $p['class_task_mark'];
    $total_home_marks += $p['home_task_mark'];
    $marks_count++;
}

$avg_class = $marks_count > 0 ? $total_class_marks / $marks_count : 0;
$avg_home = $marks_count > 0 ? $total_home_marks / $marks_count : 0;

// Fetch Lifetime Performance for THIS class only
$life_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN attendance = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN attendance = 'late' THEN 1 ELSE 0 END) as late_days,
        AVG(class_task_mark) as avg_class_mark,
        AVG(home_task_mark) as avg_home_mark
    FROM daily_performance dp
    JOIN daily_rosters dr ON dp.roster_id = dr.id
    WHERE dp.student_id = ? AND dr.class_id = ?
");
$life_stmt->execute([$student_id, $class_id]);
$life_perf = $life_stmt->fetch();

$life_attn = 0;
if ($life_perf['total_days'] > 0) {
    $life_attn = (($life_perf['present_days'] + ($life_perf['late_days'] * 0.5)) / $life_perf['total_days']) * 100;
}

// Calendar Setup
$days_in_month = (int) date('t', strtotime($start_date));
$start_day_of_week = (int) date('w', strtotime($start_date)); // 0 (Sun) to 6 (Sat)
$month_name = date('F', strtotime($start_date));

// Monthly Navigation
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}


$pageDetails = ['title' => 'Enrollment Detail - ' . htmlspecialchars($details['student_name'])];
require_once '../../includes/header.php';
?>

<style>
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
    }

    .calendar-day {
        aspect-ratio: 1 / 1;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        position: relative;
        padding: 5px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        font-size: 14px;
        transition: transform 0.2s;
    }

    .calendar-day:hover {
        transform: scale(1.02);
        z-index: 10;
        cursor: default;
    }

    .day-num {
        font-weight: bold;
        color: #64748b;
    }

    /* Statuses */
    .status-present {
        background: #dcfce7;
        border-color: #86efac;
    }

    .status-absent {
        background: #fee2e2;
        border-color: #fca5a5;
    }

    .status-holiday {
        background: #f1f5f9;
        border-color: #cbd5e1;
        opacity: 0.6;
    }

    .status-none {
        background: #f8fafc;
        border-color: #e2e8f0;
    }

    .tooltip {
        visibility: hidden;
        width: 120px;
        background-color: #1e293b;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px 0;
        position: absolute;
        bottom: 110%;
        left: 50%;
        margin-left: -60px;
        opacity: 0;
        transition: opacity 0.3s;
        font-size: 10px;
        pointer-events: none;
    }

    .calendar-day:hover .tooltip {
        visibility: visible;
        opacity: 1;
    }
</style>

<div class="card">
    <!-- Header/Dashboard -->
    <div
        style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
        <div>
            <h2 style="margin: 0;"><?php echo htmlspecialchars($details['student_name']); ?></h2>
            <p style="color: #64748b; margin: 5px 0 0 0;">
                <strong>Enrollment:</strong> <?php echo htmlspecialchars($details['course_name']); ?> -
                <?php echo htmlspecialchars($details['class_name']); ?>
                <br><strong>Teacher:</strong> <?php echo htmlspecialchars($details['teacher_name'] ?: 'Unassigned'); ?>
            </p>
        </div>
        <div style="text-align: right;">
            <a href="profile.php?id=<?php echo $student_id; ?>&tab=classes" class="btn btn-secondary">← Back to
                Profile</a>
        </div>
    </div>

    <!-- Monthly Summary Dashboard -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
        <div class="card" style="background: #fff1f2; text-align: center; border: none;">
            <div style="font-size: 12px; color: #be123c; font-weight: bold; text-transform: uppercase;">Absents
                (<?php echo $month_name; ?>)</div>
            <div style="font-size: 32px; font-weight: 800; color: #9f1239;"><?php echo $monthly_absents; ?></div>
        </div>
        <div class="card" style="background: #f0fdf4; text-align: center; border: none;">
            <div style="font-size: 12px; color: #15803d; font-weight: bold; text-transform: uppercase;">Avg Class Task
                (<?php echo $month_name; ?>)</div>
            <div style="font-size: 32px; font-weight: 800; color: #166534;"><?php echo round($avg_class, 1); ?></div>
        </div>
        <div class="card" style="background: #eff6ff; text-align: center; border: none;">
            <div style="font-size: 12px; color: #1d4ed8; font-weight: bold; text-transform: uppercase;">Avg Home Task
                (<?php echo $month_name; ?>)</div>
            <div style="font-size: 32px; font-weight: 800; color: #1e40af;"><?php echo round($avg_home, 1); ?></div>
        </div>
    </div>

    <!-- Lifetime Summary Dashboard -->
    <h4 style="margin: 30px 0 15px 0; color: #64748b; font-size: 14px; text-transform: uppercase;">Lifetime Class
        Performance</h4>
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; opacity: 0.85;">
        <div
            style="padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center;">
            <div style="font-size: 10px; color: #94a3b8; font-weight: bold;">OVERALL ATTENDANCE</div>
            <div
                style="font-size: 20px; font-weight: bold; color: <?php echo $life_attn >= 75 ? '#16a34a' : '#ef4444'; ?>;">
                <?php echo round($life_attn, 1); ?>%</div>
        </div>
        <div
            style="padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center;">
            <div style="font-size: 10px; color: #94a3b8; font-weight: bold;">CLASS TASK AVG</div>
            <div style="font-size: 20px; font-weight: bold; color: #1e293b;">
                <?php echo round($life_perf['avg_class_mark'] ?: 0, 1); ?></div>
        </div>
        <div
            style="padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center;">
            <div style="font-size: 10px; color: #94a3b8; font-weight: bold;">HOME TASK AVG</div>
            <div style="font-size: 20px; font-weight: bold; color: #1e293b;">
                <?php echo round($life_perf['avg_home_mark'] ?: 0, 1); ?></div>
        </div>
    </div>

    <!-- Calendar Controls -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0;"><?php echo $month_name . ' ' . $year; ?></h3>
        <div style="display: flex; gap: 5px;">
            <a href="?student_id=<?php echo $student_id; ?>&class_id=<?php echo $class_id; ?>&month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>"
                class="btn btn-secondary" style="padding: 5px 10px;">← Prev</a>
            <a href="?student_id=<?php echo $student_id; ?>&class_id=<?php echo $class_id; ?>&month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>"
                class="btn btn-secondary" style="padding: 5px 10px;">Next →</a>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="calendar-grid">
        <!-- Weekdays Header -->
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $wd): ?>
            <div style="text-align: center; font-weight: bold; font-size: 12px; color: #94a3b8; padding: 5px;">
                <?php echo $wd; ?>
            </div>
        <?php endforeach; ?>

        <!-- Empty cells for padding -->
        <?php for ($i = 0; $i < $start_day_of_week; $i++): ?>
            <div style="aspect-ratio: 1/1;"></div>
        <?php endfor; ?>

        <!-- Days of Month -->
        <?php
        for ($day = 1; $day <= $days_in_month; $day++):
            $d_ts = strtotime("$year-$month-$day");
            $is_weekend = (date('w', $d_ts) == 0 || date('w', $d_ts) == 6);

            $status_class = 'status-none';
            if ($is_weekend)
                $status_class = 'status-holiday';

            $marks_info = '';
            if (isset($day_map[$day])) {
                $status_class = ($day_map[$day]['attendance'] == 'present') ? 'status-present' : 'status-absent';
                $marks_info = "Class: " . $day_map[$day]['class_task_mark'] . " | Home: " . $day_map[$day]['home_task_mark'];
            }
            ?>
            <div class="calendar-day <?php echo $status_class; ?>">
                <span class="day-num"><?php echo $day; ?></span>

                <?php if ($marks_info): ?>
                    <span class="tooltip"><?php echo $marks_info; ?></span>
                <?php endif; ?>

                <?php if (isset($day_map[$day])): ?>
                    <div style="text-align: right; font-size: 9px; font-weight: 800; color: #1e293b;">
                        <?php echo ($day_map[$day]['attendance'] == 'present') ? '✓' : '✗'; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <div style="margin-top: 20px; display: flex; gap: 15px; font-size: 12px; color: #64748b; justify-content: center;">
        <span style="display: flex; align-items: center; gap: 4px;"><span
                style="width: 12px; height: 12px; background: #dcfce7; border: 1px solid #86efac; border-radius: 2px;"></span>
            Present</span>
        <span style="display: flex; align-items: center; gap: 4px;"><span
                style="width: 12px; height: 12px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 2px;"></span>
            Absent</span>
        <span style="display: flex; align-items: center; gap: 4px;"><span
                style="width: 12px; height: 12px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 2px;"></span>
            Weekend / Holiday</span>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>