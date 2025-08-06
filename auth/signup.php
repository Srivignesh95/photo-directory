<?php
require_once __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

$photo_name = null;
$name = $email = $phone = $password = $confirm_password = '';
$spouse_name = $spouse_phone = $spouse_email = '';
$mailing_address = '';
$children = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $spouse_name = trim($_POST['spouse_name'] ?? '');
    $spouse_phone = trim($_POST['spouse_phone'] ?? '');
    $spouse_email = trim($_POST['spouse_email'] ?? '');
    $spouse_login = isset($_POST['spouse_login']) && $_POST['spouse_login'] == '1';
    $mailing_address = trim($_POST['mailing_address'] ?? '');
    $children = isset($_POST['children']) ? $_POST['children'] : [];

    $phoneValue = ($phone !== '') ? $phone : null;
    $addressValue = ($mailing_address !== '') ? $mailing_address : null;

    if ($name && $email && $password && $confirm_password) {
        if ($password === $confirm_password) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email already exists.";
            } else {
                $photo_name = 'default.png';
                if (!empty($_FILES['family_photo']['name'])) {
                    $photo_ext = pathinfo($_FILES['family_photo']['name'], PATHINFO_EXTENSION);
                    $photo_ext = strtolower($photo_ext);
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (!in_array($photo_ext, $allowed)) {
                        $error = "Unsupported image type. Allowed: JPG, PNG, GIF, WEBP.";
                    } else {
                        $photo_name = time() . '_' . uniqid('', true) . '.' . $photo_ext;
                        $target_path = __DIR__ . '/../assets/images/uploads/' . $photo_name;
                        if (!@move_uploaded_file($_FILES['family_photo']['tmp_name'], $target_path)) {
                            $error = "Failed to upload the photo.";
                            $photo_name = 'default.png';
                        }
                    }
                }

                if (!$error) {
                    try {
                        $pdo->beginTransaction();

                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'user', 'pending')");
                        $stmt->execute([$name, $email, $phoneValue, $hashed_password]);
                        $user_id = $pdo->lastInsertId();

                        $spouse_user_id = null;
                        if ($spouse_login) {
                            if (empty($spouse_email)) {
                                throw new Exception("Spouse email is required if login is requested.");
                            }

                            $checkSpouse = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                            $checkSpouse->execute([$spouse_email]);
                            $existingSpouse = $checkSpouse->fetch(PDO::FETCH_ASSOC);

                            if ($existingSpouse) {
                                $spouse_user_id = (int)$existingSpouse['id'];
                            } else {
                                $spouse_account_name = !empty($spouse_name) ? $spouse_name : ($name . " - Spouse");
                                $dummy_password = password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
                                $stmtSpouse = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, NULL, ?, 'user', 'pending')");
                                $stmtSpouse->execute([$spouse_account_name, $spouse_email, $dummy_password]);
                                $spouse_user_id = (int)$pdo->lastInsertId();

                                // Send welcome email to spouse (no temp password)
                                $subjectS = "Welcome to St. Timothy’s Photo Directory";
                                $messageS = "<div style='font-family: Arial; background-color: #f9f9f9; padding: 20px; color: #333;'><h2>Welcome, " . htmlspecialchars($spouse_account_name) . "!</h2><p>Your account for the <strong>St. Timothy’s Photo Directory</strong> has been created and is currently under review.</p><p>You will receive another email once your account has been approved.</p><p>Thank you for your patience!</p><br><p style='font-size: 12px; color: #777;'>— The Photo Directory Team</p></div>";
                                $headersS  = "MIME-Version: 1.0\r\n";
                                $headersS .= "Content-type:text/html;charset=UTF-8\r\n";
                                $headersS .= "From: no-reply@photodirectory.com\r\n";
                                @mail($spouse_email, $subjectS, $messageS, $headersS);
                            }
                        }

                        $stmt = $pdo->prepare("INSERT INTO members (user_id, spouse_user_id, family_photo, spouse_name, spouse_phone, spouse_email, mailing_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $spouse_user_id, $photo_name, $spouse_name ?: null, $spouse_phone ?: null, $spouse_email ?: null, $addressValue]);

                        if (!empty($children)) {
                            $child_stmt = $pdo->prepare("INSERT INTO children (member_id, child_name) VALUES (?, ?)");
                            foreach ($children as $child_name) {
                                if (!empty(trim($child_name))) {
                                    $child_stmt->execute([$pdo->lastInsertId(), trim($child_name)]);
                                }
                            }
                        }

                        $pdo->commit();
                        $success = "Signup successful! Your account is pending admin approval.";

                        // Send welcome email to primary user
                        $subjectU = "Welcome to St. Timothy’s Photo Directory";
                        $messageU = "<div style='font-family: Arial; background-color: #f9f9f9; padding: 20px; color: #333;'><h2>Welcome, " . htmlspecialchars($name) . "!</h2><p>Thank you for signing up for the <strong>St. Timothy’s Photo Directory</strong>.</p><p>Your account is currently under review by our admin team. You will receive another email once your account has been approved.</p><p>We appreciate your patience!</p><br><p style='font-size: 12px; color: #777;'>— The Photo Directory Team</p></div>";
                        $headersU  = "MIME-Version: 1.0\r\n";
                        $headersU .= "Content-type:text/html;charset=UTF-8\r\n";
                        $headersU .= "From: no-reply@photodirectory.com\r\n";
                        @mail($email, $subjectU, $messageU, $headersU);

                        // Reset form values
                        $name = $email = $phone = $password = $confirm_password = '';
                        $spouse_name = $spouse_phone = $spouse_email = '';
                        $mailing_address = '';
                        $children = [];
                        $photo_name = null;

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "Error: " . $e->getMessage();
                    }
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
<!-- HTML FORM -->
<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card shadow">
      <div class="card-header bg-dark text-white text-center">
        <h4>Signup</h4>
      </div>
      <div class="card-body">
        <?php if ($error): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
          <div class="mb-3">
            <label>Family Photo</label>
            <input type="file" name="family_photo" class="form-control" accept="image/*">
          </div>

          <div class="mb-3">
            <label>Name (Primary Member)*</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
          </div>

          <div class="mb-3">
            <label>Email*</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
          </div>

          <div class="mb-3">
            <label>Phone (Optional)</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>">
          </div>

          <div class="mb-3">
            <label>Mailing Address</label>
            <input type="text" name="mailing_address" class="form-control" value="<?php echo htmlspecialchars($mailing_address); ?>">
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
            <input type="text" name="spouse_name" class="form-control" value="<?php echo htmlspecialchars($spouse_name); ?>">
          </div>

          <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="spouse_phone" class="form-control" value="<?php echo htmlspecialchars($spouse_phone); ?>">
          </div>

          <div class="mb-3">
            <label>Email</label>
            <input type="email" name="spouse_email" class="form-control" value="<?php echo htmlspecialchars($spouse_email); ?>">
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="spouse_login" value="1" id="spouse_login">
            <label class="form-check-label" for="spouse_login">Does your spouse need a login?</label>
          </div>

          <h5>Children</h5>
          <div id="children-container">
            <?php foreach ($children as $child): ?>
              <input type="text" name="children[]" class="form-control mb-2" value="<?php echo htmlspecialchars($child); ?>">
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-secondary mb-3" onclick="addChild()">Add Child</button>

          <button type="submit" class="btn btn-primary w-100">Signup</button>
        </form>

        <div class="mt-3 text-center">
          <a href="login.php">Already have an account? Login here</a>
          <a href="forgot_password.php" class="d-block">Forgot Password?</a>
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
