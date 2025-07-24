<?php
require_once __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $spouse_name = trim($_POST['spouse_name']);
    $spouse_phone = trim($_POST['spouse_phone']);
    $spouse_email = trim($_POST['spouse_email']);
    $children = isset($_POST['children']) ? $_POST['children'] : [];

    // ✅ Use NULL if phone is empty
    $phoneValue = !empty($phone) ? $phone : null;

    if ($name && $email && $password && $confirm_password) {
        if ($password === $confirm_password) {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email already exists.";
            } else {
                // ✅ Handle photo upload
                $photo_name = 'default.jpg';
                if (!empty($_FILES['family_photo']['name'])) {
                    $photo_ext = pathinfo($_FILES['family_photo']['name'], PATHINFO_EXTENSION);
                    $photo_name = time() . '_' . uniqid() . '.' . $photo_ext;
                    $target_path = __DIR__ . '/../assets/images/uploads/' . $photo_name;
                    move_uploaded_file($_FILES['family_photo']['tmp_name'], $target_path);
                }

                try {
                    $pdo->beginTransaction();

                    // ✅ Create user (phone can be NULL)
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'user', 'pending')");
                    $stmt->execute([$name, $email, $phoneValue, $hashed_password]);
                    $user_id = $pdo->lastInsertId();

                    // ✅ Create member
                    $stmt = $pdo->prepare("INSERT INTO members (user_id, family_photo, spouse_name, spouse_phone, spouse_email) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $user_id,
                        $photo_name,
                        !empty($spouse_name) ? $spouse_name : null,
                        !empty($spouse_phone) ? $spouse_phone : null,
                        !empty($spouse_email) ? $spouse_email : null
                    ]);
                    $member_id = $pdo->lastInsertId();

                    // ✅ Insert children
                    if (!empty($children)) {
                        $child_stmt = $pdo->prepare("INSERT INTO children (member_id, child_name) VALUES (?, ?)");
                        foreach ($children as $child_name) {
                            if (!empty(trim($child_name))) {
                                $child_stmt->execute([$member_id, trim($child_name)]);
                            }
                        }
                    }

                    $pdo->commit();
                    $success = "Signup successful! Your account is pending admin approval.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error: " . $e->getMessage();
                }
            }
        } else {
            $error = "Passwords do not match.";
        }
    } else {
        $error = "Please fill all required fields.";
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-dark text-white text-center">
                <h4>Signup</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label>Family Photo</label>
                        <input type="file" name="family_photo" class="form-control" accept="image/*">
                        <?php if (!empty($photo_name) && $photo_name != 'default.jpg'): ?>
                            <p class="text-muted">Photo already uploaded: <?php echo htmlspecialchars($photo_name); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label>Name (Primary Member)*</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label>Email*</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label>Phone (Optional)</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label>Password*</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Confirm Password*</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <h5>Spouse Details</h5>
                    <div class="mb-3">
                        <label>Name</label>
                        <input type="text" name="spouse_name" class="form-control" value="<?php echo htmlspecialchars($spouse_name ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label>Phone</label>
                        <input type="text" name="spouse_phone" class="form-control" value="<?php echo htmlspecialchars($spouse_phone ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="spouse_email" class="form-control" value="<?php echo htmlspecialchars($spouse_email ?? ''); ?>">
                    </div>

                    <h5>Children</h5>
                    <div id="children-container">
                        <?php if (!empty($children)): ?>
                            <?php foreach ($children as $child): ?>
                                <input type="text" name="children[]" class="form-control mb-2" value="<?php echo htmlspecialchars($child); ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-secondary mb-3" onclick="addChild()">Add Child</button>

                    <button type="submit" class="btn btn-primary w-100">Signup</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="login.php">Already have an account? Login here</a>
                    <a href="forgot_password.php" class="d-block mb-2">Forgot Password?</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addChild() {
    const container = document.getElementById('children-container');
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'children[]';
    input.placeholder = 'Child Name';
    input.className = 'form-control mb-2';
    container.appendChild(input);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
