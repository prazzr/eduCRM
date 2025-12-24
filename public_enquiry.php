<?php
// Standalone config for public access (or just include config and ensure no requireLogin)
// We'll reuse config.php but NOT call requireLogin()
require_once 'config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $country = sanitize($_POST['country']);
    $course = sanitize($_POST['course']);
    $edu_level = sanitize($_POST['edu_level']);

    // Assign to Admin (ID 1) by default or leave NULL
    $assigned_to = 1;

    if ($name && $email) {
        try {
            $stmt = $pdo->prepare("INSERT INTO inquiries (name, email, phone, intended_country, intended_course, education_level, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $country, $course, $edu_level, $assigned_to]);
            $message = "Thank you! Your inquiry has been received. We will contact you shortly.";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Name and Email are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Your Journey - EduCRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .inquiry-card {
            width: 100%;
            max-width: 600px;
            padding: 40px;
            border-top: 5px solid var(--primary-color);
        }

        .hero-text {
            text-align: center;
            margin-bottom: 30px;
        }

        .hero-text h1 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .hero-text p {
            color: var(--text-secondary);
        }
    </style>
</head>

<body>

    <div class="card inquiry-card">
        <div class="hero-text">
            <h1>Start Your Study Abroad Journey</h1>
            <p>Fill out the form below and our expert counselors will guide you.</p>
        </div>

        <?php if ($message): ?>
            <div
                style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 6px; text-align: center; margin-bottom: 20px;">
                <strong><?php echo $message; ?></strong>
            </div>
            <div style="text-align: center;">
                <a href="login.php" class="btn btn-secondary">Admin Login</a>
            </div>
        <?php else: ?>

            <?php if ($error): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Interested Country</label>
                        <select name="country" class="form-control">
                            <option value="">Select Country</option>
                            <option value="USA">USA</option>
                            <option value="UK">UK</option>
                            <option value="Australia">Australia</option>
                            <option value="Canada">Canada</option>
                            <option value="Europe">Europe</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Course Interest</label>
                        <select name="course" class="form-control">
                            <option value="">Select Course</option>
                            <option value="IELTS">IELTS Prep</option>
                            <option value="PTE">PTE Prep</option>
                            <option value="Study Abroad">Study Abroad Counseling</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Current Education Level</label>
                    <input type="text" name="edu_level" class="form-control" placeholder="e.g. High School Graduate">
                </div>

                <button type="submit" class="btn" style="width: 100%; padding: 12px; font-size: 16px;">Submit
                    Inquiry</button>
            </form>

            <p style="text-align: center; margin-top: 20px; font-size: 13px;">
                <a href="login.php" style="color: #94a3b8;">Staff Login</a>
            </p>

        <?php endif; ?>
    </div>

</body>

</html>