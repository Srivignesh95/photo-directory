<?php
require 'includes/auth.php'; // Only admins
require 'includes/conn.php';
require 'includes/header.php';

$error = '';
$success = '';
$member = null;

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
if (!$id) {
    die("Invalid member ID.");
}

// Fetch current member data
$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    die("Member not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = trim($_POST['first_name']);
    $last = trim($_POST['last_name']);
    $contact = trim($_POST['contact_info']);
    $designation = trim($_POST['designation']);
    $email = trim($_POST['email']);
    $access = isset($_POST['give_access']) ? 1 : 0;

    $facebook = trim($_POST['facebook']);
    $twitter = trim($_POST['twitter']);
    $instagram = trim($_POST['instagram']);
    $linkedin = trim($_POST['linkedin']);

    // Validate email/phone rule
    if (empty($email) && empty($contact)) {
        $error = "Please provide at least an email or phone number.";
    }
    if ($access === 1 && empty($email)) {
        $error = "Cannot grant access without an email address.";
    }

    // Validate duplicate email if email is provided and changed
    if (empty($error) && !empty($email) && $email !== $member['email']) {
        $check = $pdo->prepare("SELECT id FROM members WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        if ($check->rowCount() > 0) {
            $error = "Email is already in use by another member.";
        }
    }

    // Handle photo upload if no errors so far
    $photoName = $member['photo'];
    if (empty($error) && isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (in_array($_FILES['photo']['type'], $allowedTypes)) {
            $uploadDir = 'assets/images/uploads/';
            $photoName = time() . '_' . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $photoName);
        } else {
            $error = "Invalid photo format. Only JPG and PNG allowed.";
        }
    }

    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("UPDATE members SET 
                photo = ?, first_name = ?, last_name = ?, contact_info = ?, 
                designation = ?, access = ?, email = ?, 
                facebook = ?, twitter = ?, instagram = ?, linkedin = ? 
                WHERE id = ?");
            $stmt->execute([
                $photoName, $first, $last, $contact, $designation, $access, $email,
                $facebook, $twitter, $instagram, $linkedin, $id
            ]);

            $success = "✅ Member updated successfully!";

            // If admin grants access for the first time and email exists
            if ($access === 1 && $member['access'] == 0 && !empty($email)) {
                $resetToken = bin2hex(random_bytes(16));
                $updateToken = $pdo->prepare("UPDATE members SET reset_token = ? WHERE id = ?");
                $updateToken->execute([$resetToken, $id]);

                // Send email notification
                $subject = "Your Account Access Granted";
                $link = "https://yourdomain.com/reset-password.php?token=$resetToken";
                $body = "Hi $first,\n\nYour account now has login access. Set your password here:\n$link\n\nThanks!";
                $headers = "From: no-reply@yourdomain.com\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                mail($email, $subject, $body, $headers);
            }

            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
            $stmt->execute([$id]);
            $member = $stmt->fetch();

        } catch (PDOException $e) {
            $error = "❌ Update failed: " . $e->getMessage();
        }
    }
}
?>

<div class="container py-5">
    <h2 class="mb-4">Edit Member</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label>First Name</label>
            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($member['first_name']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Last Name</label>
            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($member['last_name']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Contact Info (Phone)</label>
            <input type="text" name="contact_info" class="form-control" value="<?= htmlspecialchars($member['contact_info']) ?>">
        </div>

        <div class="mb-3">
            <label>Designation</label>
            <input type="text" name="designation" class="form-control" value="<?= htmlspecialchars($member['designation']) ?>">
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($member['email']) ?>">
            <?php if (empty($member['email'])): ?>
                <small class="text-danger">⚠ No email provided. Cannot grant access without an email.</small>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label>Photo</label><br>
            <img src="assets/images/uploads/<?= htmlspecialchars($member['photo']) ?>" alt="Current Photo" width="100" style="border-radius:8px"><br><br>
            <input type="file" name="photo" class="form-control" accept="image/*">
        </div>

        <!-- Social Links -->
        <div class="mb-3"><label>Facebook</label><input type="url" name="facebook" class="form-control" value="<?= htmlspecialchars($member['facebook'] ?? '') ?>"></div>
        <div class="mb-3"><label>Twitter</label><input type="url" name="twitter" class="form-control" value="<?= htmlspecialchars($member['twitter'] ?? '') ?>"></div>
        <div class="mb-3"><label>Instagram</label><input type="url" name="instagram" class="form-control" value="<?= htmlspecialchars($member['instagram'] ?? '') ?>"></div>
        <div class="mb-3"><label>LinkedIn</label><input type="url" name="linkedin" class="form-control" value="<?= htmlspecialchars($member['linkedin'] ?? '') ?>"></div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="give_access" class="form-check-input" id="accessCheckbox" <?= $member['access'] == 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="accessCheckbox">This person can access the database</label>
        </div>

        <button type="submit" class="btn btn-success">Update Member</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php require 'includes/footer.php'; ?>
