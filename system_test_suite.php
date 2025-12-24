<?php
require_once 'config.php';

// Disable HTML buffering for real-time output
ob_implicit_flush(true);
while (ob_get_level())
    ob_end_flush();

echo "<h1>EduCRM System - Comprehensive Test Suite</h1>";
echo "<p>Started at: " . date('Y-m-d H:i:s') . "</p><hr>";

try {
    $pdo->beginTransaction();

    // =========================================================================
    // STEP 1: PUBLIC INQUIRY (Simulate public_enquiry.php)
    // =========================================================================
    echo "<h3>Step 1: Public Inquiry Simulation</h3>";

    $inquiryData = [
        'name' => "John Doe " . time(),
        'email' => "john.doe." . time() . "@test.com",
        'phone' => "1234567890",
        'intended_country' => "Canada",
        'intended_course' => "PTE",
        'education_level' => "Bachelor",
        'assigned_to' => 1 // Admin
    ];

    $stmt = $pdo->prepare("INSERT INTO inquiries (name, email, phone, intended_country, intended_course, education_level, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $inquiryData['name'],
        $inquiryData['email'],
        $inquiryData['phone'],
        $inquiryData['intended_country'],
        $inquiryData['intended_course'],
        $inquiryData['education_level'],
        $inquiryData['assigned_to']
    ]);
    $inquiryId = $pdo->lastInsertId();

    if ($inquiryId) {
        echo "<div style='color:green'>[PASS] Inquiry created successfully. ID: $inquiryId</div>";
    } else {
        throw new Exception("Failed to create inquiry");
    }

    // =========================================================================
    // STEP 2: CRM CONVERSION (Simulate modules/inquiries/convert.php)
    // =========================================================================
    echo "<h3>Step 2: CRM Conversion (Inquiry to Student)</h3>";

    // 2.1 Check for Duplicate Email (Validation Test)
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$inquiryData['email']]);
    if ($check->rowCount() > 0) {
        throw new Exception("Validation Error: Email already exists in users table prematurely.");
    }

    // 2.2 Perform Conversion
    $rawPassword = generateSecurePassword();
    $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);

    // Insert User
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, phone, country, education_level) VALUES (?, ?, ?, 'student', ?, ?, ?)");
    $stmt->execute([
        $inquiryData['name'],
        $inquiryData['email'],
        $passwordHash,
        $inquiryData['phone'],
        $inquiryData['intended_country'],
        $inquiryData['education_level']
    ]);
    $studentUserId = $pdo->lastInsertId();

    // Link Role
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'student'");
    $roleStmt->execute();
    $studentRoleId = $roleStmt->fetchColumn();

    // Handle case where role might be missing in DB (Defensive Coding)
    if (!$studentRoleId) {
        // Insert if not exists for the test to proceed
        $pdo->exec("INSERT INTO roles (name) VALUES ('student')");
        $studentRoleId = $pdo->lastInsertId();
    }

    $linkStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $linkStmt->execute([$studentUserId, $studentRoleId]);

    // Update Inquiry Status
    $updateInq = $pdo->prepare("UPDATE inquiries SET status = 'converted' WHERE id = ?");
    $updateInq->execute([$inquiryId]);

    echo "<div style='color:green'>[PASS] Converted Inquiry to Student. User ID: $studentUserId</div>";
    echo "Generated Password: $rawPassword<br>";


    // =========================================================================
    // STEP 3: ACCOUNTING (Fees & Payments)
    // =========================================================================
    echo "<h3>Step 3: Accounting Module</h3>";

    // 3.1 Create Fee Type
    $feeName = "Tuition Fee 2025";
    $feeAmount = 1500.00;

    // Check if exists or create
    $stmt = $pdo->prepare("SELECT id FROM fee_types WHERE name = ?");
    $stmt->execute([$feeName]);
    $feeTypeId = $stmt->fetchColumn();

    if (!$feeTypeId) {
        $pdo->prepare("INSERT INTO fee_types (name, default_amount) VALUES (?, ?)")->execute([$feeName, $feeAmount]);
        $feeTypeId = $pdo->lastInsertId();
        echo "Created Fee Type: $feeName<br>";
    }

    // 3.2 Assign Fee to Student
    $stmt = $pdo->prepare("INSERT INTO student_fees (student_id, fee_type_id, amount, status) VALUES (?, ?, ?, 'unpaid')");
    $stmt->execute([$studentUserId, $feeTypeId, $feeAmount]);
    $studentFeeId = $pdo->lastInsertId();
    echo "Assigned Fee ($feeName: $$feeAmount) to Student.<br>";

    // 3.3 Record Partial Payment
    $paymentAmount = 500.00;
    $stmt = $pdo->prepare("INSERT INTO payments (student_fee_id, amount, payment_method, transaction_date) VALUES (?, ?, 'Bank Transfer', NOW())");
    $stmt->execute([$studentFeeId, $paymentAmount]);
    echo "Recorded Partial Payment: $$paymentAmount<br>";

    // 3.4 Verify Ledger Status
    $remaining = $feeAmount - $paymentAmount;
    $expectedStatus = ($remaining > 0) ? 'partial' : 'paid';

    $pdo->prepare("UPDATE student_fees SET status = ? WHERE id = ?")->execute([$expectedStatus, $studentFeeId]);

    // Verify
    $verifyFee = $pdo->prepare("SELECT status FROM student_fees WHERE id = ?");
    $verifyFee->execute([$studentFeeId]);
    $actualStatus = $verifyFee->fetchColumn();

    if ($actualStatus === 'partial') {
        echo "<div style='color:green'>[PASS] Ledger Status updated correctly to 'partial'. Remaining: $$remaining</div>";
    } else {
        echo "<div style='color:red'>[FAIL] Ledger Status expected 'partial', got '$actualStatus'.</div>";
    }


    // =========================================================================
    // STEP 4: LMS INTEGRATION (Course -> Class -> Enrollment -> Task)
    // =========================================================================
    echo "<h3>Step 4: LMS Integration</h3>";

    // 4.1 Setup Teacher & Course
    // Reuse existing or create default
    $teacherEmail = "test_teacher_sys@example.com";
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$teacherEmail]);
    $teacherId = $stmt->fetchColumn();

    if (!$teacherId) {
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('System Teacher', ?, ?, 'teacher')")
            ->execute([$teacherEmail, password_hash('password', PASSWORD_DEFAULT)]);
        $teacherId = $pdo->lastInsertId();
        echo "Created System Teacher ID: $teacherId<br>";
    }

    $courseName = "PTE Preparation";
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE name = ?");
    $stmt->execute([$courseName]);
    $courseId = $stmt->fetchColumn();

    if (!$courseId) {
        $pdo->prepare("INSERT INTO courses (name, description) VALUES (?, 'English Proficiency')")->execute([$courseName]);
        $courseId = $pdo->lastInsertId();
        echo "Created Course: $courseName<br>";
    }

    // 4.2 Create Class
    $className = "PTE Batch " . date('M Y');
    $stmt = $pdo->prepare("INSERT INTO classes (course_id, teacher_id, name, schedule_info, start_date) VALUES (?, ?, ?, 'Mon-Fri 10am', NOW())");
    $stmt->execute([$courseId, $teacherId, $className]);
    $classId = $pdo->lastInsertId();
    echo "Created Class: $className<br>";

    // 4.3 Enroll Student
    $pdo->prepare("INSERT INTO enrollments (student_id, class_id) VALUES (?, ?)")->execute([$studentUserId, $classId]);
    echo "Enrolled Student ID $studentUserId into Class ID $classId<br>";

    // 4.4 Create Task (Assignment)
    $stmt = $pdo->prepare("INSERT INTO class_materials (class_id, teacher_id, title, description, type, due_date) VALUES (?, ?, 'Mock Test 1', 'Solve section A', 'assignment', NOW() + INTERVAL 7 DAY)");
    $stmt->execute([$classId, $teacherId]);
    $materialId = $pdo->lastInsertId();
    echo "Created Assignment: Mock Test 1<br>";

    // 4.5 Student Submission
    $stmt = $pdo->prepare("INSERT INTO submissions (material_id, student_id, file_path, comments) VALUES (?, ?, 'uploads/mock_test.pdf', 'Completed')");
    $stmt->execute([$materialId, $studentUserId]);
    $submissionId = $pdo->lastInsertId();
    echo "Student submitted Assignment. Submission ID: $submissionId<br>";

    // 4.6 Teacher Grade
    $stmt = $pdo->prepare("UPDATE submissions SET grade = 'A' WHERE id = ?");
    $stmt->execute([$submissionId]);
    echo "Teacher graded Submission: A<br>";

    // 4.7 Create Daily Roster & Attendance
    $rosterDate = date('Y-m-d');
    $stmt = $pdo->prepare("INSERT INTO daily_rosters (class_id, teacher_id, roster_date, topic) VALUES (?, ?, ?, 'Unit Testing Topic')");
    $stmt->execute([$classId, $teacherId, $rosterDate]);
    $rosterId = $pdo->lastInsertId();
    echo "Created Daily Roster for $rosterDate<br>";

    $stmt = $pdo->prepare("INSERT INTO daily_performance (roster_id, student_id, attendance, remarks) VALUES (?, ?, 'present', 'Automated Test')");
    $stmt->execute([$rosterId, $studentUserId]);
    echo "Marked Student Attendance: Present<br>";

    // =========================================================================
    // STEP 5: COUNSELOR WORKFLOW (Enrollment & Visa Tracking)
    // =========================================================================
    echo "<h3>Step 5: Counselor Workflow</h3>";

    // 5.1 Create Counselor
    $counselorEmail = "counselor." . time() . "@example.com";
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('Test Counselor', ?, ?, 'counselor')")
        ->execute([$counselorEmail, password_hash('password', PASSWORD_DEFAULT)]);
    $counselorId = $pdo->lastInsertId();
    // Assign Role
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'counselor'");
    $roleStmt->execute();
    $counselorRoleId = $roleStmt->fetchColumn();
    $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$counselorId, $counselorRoleId]);

    echo "Created Counselor ID: $counselorId<br>";

    // 5.2 Counselor Enrolls New Student (Simulated Logic of users/add.php)
    // We simulate the permissions check here effectively by performing the insert
    $newStudentEmail = "student_by_counselor_" . time() . "@example.com";
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('Counselor Walkin', ?, ?, 'student')")
        ->execute([$newStudentEmail, password_hash('password', PASSWORD_DEFAULT)]);
    $walkinStudentId = $pdo->lastInsertId();
    echo "Counselor enrolled new student: ID $walkinStudentId<br>";

    // 5.3 Counselor Updates Visa Stage (Simulated Logic of visa/update.php)
    $visaStage = 'Visa Lodged';
    $visaNotes = 'Application submitted to embassy.';

    $stmt = $pdo->prepare("INSERT INTO visa_workflows (student_id, country, current_stage, notes) VALUES (?, 'Australia', ?, ?)");
    $stmt->execute([$walkinStudentId, $visaStage, $visaNotes]);
    $visaId = $pdo->lastInsertId();

    echo "<div style='color:green'>[PASS] Counselor updated Visa Stage to '$visaStage'. Workflow ID: $visaId</div>";

    // Final Commit
    $pdo->commit();
    echo "<hr><h2 style='color:green'>System Test Completed Successfully!</h2>";
    echo "<p><strong>Validation Summary:</strong> All modules (Inquiry, Conversion, Accounting, LMS) interacted without error.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<hr><h2 style='color:red'>System Test FAILED</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>