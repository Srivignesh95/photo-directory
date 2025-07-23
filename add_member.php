<?php
require 'includes/auth.php'; // Ensure only logged-in admins
require 'includes/header.php';
require 'includes/conn.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = trim($_POST['first_name']);
    $last = trim($_POST['last_name']);
    $contact = trim($_POST['contact_info'] ?? '');
    $designation = trim($_POST['designation']) ?: 'Member';
    $email = trim($_POST['email']);
    $facebook = trim($_POST['facebook'] ?? '');
    $twitter = trim($_POST['twitter'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');

    // ✅ Validate at least one contact (email or phone)
    if (empty($email) && empty($contact)) {
        $error = "Please provide at least an email or a phone number.";
    }

    // ✅ Validate access rule
    $access = isset($_POST['give_access']) ? 1 : 0;
    if ($access === 1 && empty($email)) {
        $error = "Cannot grant access without an email address.";
    }

    // ✅ Proceed only if no validation errors so far
    if (empty($error)) {
        // Handle photo upload
        $photoName = 'default.jpg';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $uploadDir = 'assets/images/uploads/';
            $photoName = time() . '_' . basename($_FILES['photo']['name']);
            $targetPath = $uploadDir . $photoName;
            move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath);
        }

        $approved = 1;
        $verified = 1;
        $passwordHash = '';
        $tempPlainPassword = '';
        $resetToken = null;

        // ✅ Generate token and password only if email and access granted
        if ($access === 1 && !empty($email)) {
            $tempPlainPassword = bin2hex(random_bytes(4)); // Temporary password
            $passwordHash = password_hash($tempPlainPassword, PASSWORD_DEFAULT);
            $resetToken = bin2hex(random_bytes(16)); // For password reset link
        }

        try {
            // ✅ Check for duplicate email only if email is provided
            if (!empty($email)) {
                $checkStmt = $pdo->prepare("SELECT id FROM members WHERE email = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->rowCount() > 0) {
                    $error = "A member with this email already exists.";
                }
            }

            if (empty($error)) {
                // ✅ Insert into DB
                $stmt = $pdo->prepare("INSERT INTO members 
                    (photo, first_name, last_name, contact_info, designation, access, email, password, approved, facebook, twitter, instagram, linkedin, reset_token, is_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $photoName,
                    $first,
                    $last,
                    !empty($contact) ? $contact : null,
                    $designation,
                    $access,
                    !empty($email) ? $email : null,
                    $passwordHash,
                    $approved,
                    $facebook,
                    $twitter,
                    $instagram,
                    $linkedin,
                    $resetToken,
                    $verified
                ]);

                // ✅ Send email only if email exists
                if (!empty($email)) {
                    $subject = "Your Login Access – St. Timothy Church Directory";
                    $changeLink = "https://svkzone.com/photo_directory/reset-password.php?token=$resetToken";

                    $body = "Hi $first $last,\r\n\r\n";
                    $body .= "You’ve been added to the St. Timothy Church Directory.\r\n\r\n";
                    if ($access === 1) {
                        $body .= "Here are your login details:\r\n";
                        $body .= "Email: $email\r\n";
                        $body .= "Temporary Password: $tempPlainPassword\r\n";
                        $body .= "Set your password here: $changeLink\r\n\r\n";
                    } else {
                        $body .= "You are added as a member (view-only).\r\n";
                    }
                    $body .= "Blessings,\r\nChurch Admin";

                    $headers = "From: no-reply@svkzone.com\r\n";
                    $headers .= "Reply-To: admin@svkzone.com\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                    $userMail = mail($email, $subject, $body, $headers);
                    $adminMail = mail("admin@svkzone.com", "[Copy] Member Added: $first $last", $body, $headers);

                    // ✅ Log for debugging
                    file_put_contents("log.txt", "Added: $email | Token: $resetToken | UserMail: $userMail | AdminMail: $adminMail\n", FILE_APPEND);
                }

                $success = "✅ Member added successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container py-5">
    <h2 class="mb-4">Add New Member</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3"><label>First Name</label><input type="text" name="first_name" class="form-control" required></div>
        <div class="mb-3"><label>Last Name</label><input type="text" name="last_name" class="form-control" required></div>
        <div class="mb-3"><label>Contact Info (Phone)</label><input type="text" name="contact_info" class="form-control"></div>
        <div class="mb-3"><label>Designation</label><input type="text" name="designation" class="form-control" value="Member"></div>
        <div class="mb-3"><label>Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
        <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control"></div>
        <div class="mb-3"><label>Facebook</label><input type="url" name="facebook" class="form-control"></div>
        <div class="mb-3"><label>Twitter</label><input type="url" name="twitter" class="form-control"></div>
        <div class="mb-3"><label>Instagram</label><input type="url" name="instagram" class="form-control"></div>
        <div class="mb-3"><label>LinkedIn</label><input type="url" name="linkedin" class="form-control"></div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="give_access" class="form-check-input" id="accessCheckbox">
            <label class="form-check-label" for="accessCheckbox">This person can access the database?</label>
        </div>
        <button type="submit" class="btn btn-primary">Add Member</button>
    </form>
</div>

<?php require 'includes/footer.php'; ?>
