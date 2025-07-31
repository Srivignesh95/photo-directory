<?php
require_once __DIR__ . '/../includes/header.php';
checkAuth();
if (!isAdmin()) {
    die("Access Denied");
}

// ✅ Enable PDO exception mode
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$error = '';
$success = '';

// Keep submitted values to repopulate the form after POST
$posted = [
    'name'            => '',
    'phone'           => '',
    'email'           => '',
    'spouse_name'     => '',
    'spouse_phone'    => '',
    'spouse_email'    => '',
    'mailing_address' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read inputs
    $name            = trim($_POST['name'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $spouse_name     = trim($_POST['spouse_name'] ?? '');
    $spouse_phone    = trim($_POST['spouse_phone'] ?? '');
    $spouse_email    = trim($_POST['spouse_email'] ?? '');
    $mailing_address = trim($_POST['mailing_address'] ?? '');
    $children        = isset($_POST['children']) ? $_POST['children'] : [];

    // Store for sticky form values
    $posted = [
        'name'            => $name,
        'phone'           => $phone,
        'email'           => $email,
        'spouse_name'     => $spouse_name,
        'spouse_phone'    => $spouse_phone,
        'spouse_email'    => $spouse_email,
        'mailing_address' => $mailing_address
    ];

    // ✅ Validation (primary)
    if (empty($name)) {
        $error = "Primary member name is required.";
    } elseif (empty($email) && empty($phone)) {
        $error = "Please provide either an Email or a Phone number for the primary member.";
    }

    // ✅ Additional validation (spouse)
    if (!$error && !empty($spouse_email) && !filter_var($spouse_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Spouse email is not a valid email address.";
    }
    if (!$error && !empty($email) && !empty($spouse_email) && strcasecmp($spouse_email, $email) === 0) {
        $error = "Spouse email cannot be the same as the primary member email.";
    }

    // ✅ Prepare values (NULL if empty)
    $emailValue = !empty($email) ? $email : null;
    $phoneValue = !empty($phone) ? $phone : null;

    // ✅ Check for duplicates (PRIMARY) before starting transaction
    if (!$error && (!empty($emailValue) || !empty($phoneValue))) {
        $checkSql = "SELECT id FROM users WHERE ";
        $params   = [];
        $clauses  = [];
        if (!empty($emailValue)) {
            $clauses[] = "email = ?";
            $params[]  = $emailValue;
        }
        if (!empty($phoneValue)) {
            $clauses[] = "phone = ?";
            $params[]  = $phoneValue;
        }
        $checkSql .= implode(" OR ", $clauses);
        $check = $pdo->prepare($checkSql);
        $check->execute($params);
        if ($check->fetch()) {
            $error = "Email or Phone already exists for another user. Cannot create a new primary user.";
        }
    }

    // ✅ Handle photo upload (before transaction, but only set $error if something goes wrong)
    $photo_name = 'default.png';
    if (!$error) {
        if (!empty($_FILES['family_photo']['name'])) {
            $photo_ext = pathinfo($_FILES['family_photo']['name'], PATHINFO_EXTENSION);
            $photo_ext = strtolower($photo_ext);
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($photo_ext, $allowed)) {
                $error = "Unsupported image type. Allowed: JPG, PNG, GIF, WEBP.";
            } else {
                // Hardened file name
                $photo_name  = time() . '_' . bin2hex(random_bytes(6)) . '.' . $photo_ext;
                $target_path = __DIR__ . '/../assets/images/uploads/' . $photo_name;

                // Create dir if missing
                $dir = dirname($target_path);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }

                if (!@move_uploaded_file($_FILES['family_photo']['tmp_name'], $target_path)) {
                    $error = "Failed to upload the photo.";
                }
            }
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            $user_id = null;         // primary user's id
            $spouse_user_id = null;  // spouse user's id (optional link)

            // ---------- PRIMARY USER ----------
            if (!empty($emailValue) || !empty($phoneValue)) {
                $temp_password   = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, phone, password, role, status)
                    VALUES (?, ?, ?, ?, 'user', 'approved')
                ");
                $stmt->execute([$name, $emailValue, $phoneValue, $hashed_password]);
                $user_id = (int)$pdo->lastInsertId();

                // Send welcome email if email exists
                if (!empty($emailValue)) {
                    $subject = "Welcome to Photo Directory!";
                    $message = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f7f7f7; color: #333;'>
                            <h2 style='color: #2a7ae2;'>Welcome to Photo Directory!</h2>
                            <p>Your account has been created by an administrator.</p>
                            <p><strong>Here are your login details:</strong></p>
                            <ul style='line-height: 1.6;'>
                                <li><strong>Email:</strong> {$emailValue}</li>
                                <li><strong>Temporary Password:</strong> {$temp_password}</li>
                            </ul>
                            <p style='margin: 20px 0;'>
                                <a href='" . BASE_URL . "auth/login.php' style='background-color: #2a7ae2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Photo Directory</a>
                            </p>
                            <p><em>We recommend changing your password after logging in.</em></p>
                            <hr style='border: none; border-top: 1px solid #ccc;'>
                            <p style='font-size: 12px; color: #888;'>Thank you,<br>Photo Directory Team</p>
                        </div>
                    ";

                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                    $headers .= "From: no-reply@photodirectory.com\r\n";
                    // Consider logging errors rather than suppressing with @ in production
                    @mail($emailValue, $subject, $message, $headers);
                }
            }

            // ---------- SPOUSE USER (optional) ----------
            if (!empty($spouse_email)) {
                // Try to find existing user by spouse email
                $findSpouse = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $findSpouse->execute([$spouse_email]);
                $existingSpouse = $findSpouse->fetch(PDO::FETCH_ASSOC);

                if ($existingSpouse) {
                    // Link to existing spouse account; no email sent
                    $spouse_user_id = (int)$existingSpouse['id'];
                } else {
                    // Create new spouse user with temp password and email it
                    $spouse_temp_password   = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                    $spouse_hashed_password = password_hash($spouse_temp_password, PASSWORD_DEFAULT);

                    $spouse_account_name = !empty($spouse_name) ? $spouse_name : ($name . " - Spouse");

                    $createSpouse = $pdo->prepare("
                        INSERT INTO users (name, email, phone, password, role, status)
                        VALUES (?, ?, NULL, ?, 'user', 'approved')
                    ");
                    $createSpouse->execute([$spouse_account_name, $spouse_email, $spouse_hashed_password]);
                    $spouse_user_id = (int)$pdo->lastInsertId();

                    // Send welcome email to spouse
                    $subjectS = "Welcome to Photo Directory!";
                    $messageS = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f7f7f7; color: #333;'>
                            <h2 style='color: #2a7ae2;'>Welcome to Photo Directory!</h2>
                            <p>An administrator has created an account for you.</p>
                            <p><strong>Your login details:</strong></p>
                            <ul style='line-height: 1.6;'>
                                <li><strong>Email:</strong> {$spouse_email}</li>
                                <li><strong>Temporary Password:</strong> {$spouse_temp_password}</li>
                            </ul>
                            <p style='margin: 20px 0;'>
                                <a href='" . BASE_URL . "auth/login.php' style='background-color: #2a7ae2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Photo Directory</a>
                            </p>
                            <p><em>Please change your password after logging in.</em></p>
                            <hr style='border: none; border-top: 1px solid #ccc;'>
                            <p style='font-size: 12px; color: #888;'>Thank you,<br>Photo Directory Team</p>
                        </div>
                    ";
                    $headersS  = "MIME-Version: 1.0\r\n";
                    $headersS .= "Content-type:text/html;charset=UTF-8\r\n";
                    $headersS .= "From: no-reply@photodirectory.com\r\n";
                    @mail($spouse_email, $subjectS, $messageS, $headersS);
                }
            }

            // ---------- INSERT MEMBER ----------
            // Includes spouse_user_id FK link (nullable)
            $stmt = $pdo->prepare("
                INSERT INTO members (
                    user_id, spouse_user_id, family_photo,
                    spouse_name, spouse_phone, spouse_email,
                    mailing_address
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id ?: null,
                $spouse_user_id ?: null,
                $photo_name,
                !empty($spouse_name) ? $spouse_name : null,
                !empty($spouse_phone) ? $spouse_phone : null,
                !empty($spouse_email) ? $spouse_email : null,
                !empty($mailing_address) ? $mailing_address : null
            ]);
            $member_id = (int)$pdo->lastInsertId();

            // ---------- INSERT CHILDREN ----------
            if (!empty($children)) {
                $child_stmt = $pdo->prepare("INSERT INTO children (member_id, child_name) VALUES (?, ?)");
                foreach ($children as $child_name) {
                    $child_name = trim($child_name);
                    if (!empty($child_name)) {
                        $child_stmt->execute([$member_id, $child_name]);
                    }
                }
            }

            $pdo->commit();
            $success = "Member added successfully.";
            // Clear sticky values on success
            $posted = [
                'name'            => '',
                'phone'           => '',
                'email'           => '',
                'spouse_name'     => '',
                'spouse_phone'    => '',
                'spouse_email'    => '',
                'mailing_address' => ''
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
            error_log("Transaction failed: " . $e->getMessage());
        }
    }
}

// Helper to safely echo values
function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>

<div class="row">
    <div class="col-md-3">
        <div class="list-group">
            <a href="dashboard.php" class="list-group-item list-group-item-action">Dashboard</a>
            <a href="pending.php" class="list-group-item list-group-item-action">Pending Approvals</a>
            <a href="members.php" class="list-group-item list-group-item-action">Manage Members</a>
            <a href="add_member.php" class="list-group-item list-group-item-action active">Add Member</a>
        </div>
    </div>

    <div class="col-md-9">
        <h3>Add New Member</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="addMemberForm" novalidate>
            <div class="mb-3">
                <label>Family Photo</label>
                <input type="file" name="family_photo" class="form-control" accept="image/*" onchange="previewImage(event)">
                <img id="photoPreview" src="" alt="" class="mt-2" style="display:none;width:150px;border:1px solid #ccc;padding:5px;">
            </div>

            <div class="mb-3">
                <label>Primary Member Name</label>
                <input type="text" name="name" id="name" class="form-control" value="<?php echo h($posted['name']); ?>">
                <div class="invalid-feedback">Primary member name is required.</div>
            </div>

            <div class="mb-3">
                <label>Phone</label>
                <input type="text" name="phone" id="phone" class="form-control" value="<?php echo h($posted['phone']); ?>">
                <div class="invalid-feedback">Please provide a phone number or an email.</div>
            </div>

            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" id="email" class="form-control" value="<?php echo h($posted['email']); ?>">
                <div class="invalid-feedback">Please provide a phone number or an email.</div>
            </div>

            <!-- ✅ Mailing Address -->
            <div class="mb-3">
                <label>Mailing Address</label>
                <textarea name="mailing_address" id="mailing_address" class="form-control" rows="3" placeholder="Street, City, Province/State, Postal/ZIP, Country"><?php echo h($posted['mailing_address']); ?></textarea>
            </div>

            <h5>Spouse Details</h5>
            <div class="mb-3">
                <label>Name</label>
                <input type="text" name="spouse_name" class="form-control" value="<?php echo h($posted['spouse_name']); ?>">
            </div>
            <div class="mb-3">
                <label>Phone</label>
                <input type="text" name="spouse_phone" class="form-control" value="<?php echo h($posted['spouse_phone']); ?>">
            </div>
            <div class="mb-3">
                <label>Email (optional)</label>
                <input type="email" name="spouse_email" class="form-control" value="<?php echo h($posted['spouse_email']); ?>">
                <div class="form-text">If provided, a spouse login will be created (or linked) and a temporary password will be emailed.</div>
            </div>

            <h5>Children</h5>
            <div id="children-container"></div>
            <button type="button" class="btn btn-secondary mb-3" onclick="addChild()">Add Child</button>

            <button type="submit" class="btn btn-primary w-100">Add Member</button>
        </form>
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

function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function() {
        const img = document.getElementById('photoPreview');
        img.src = reader.result;
        img.style.display = 'block';
    }
    if (event.target.files && event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}

// ✅ Client-side validation
document.getElementById("addMemberForm").addEventListener("submit", function(e) {
    let valid = true;
    const name  = document.getElementById("name");
    const phone = document.getElementById("phone");
    const email = document.getElementById("email");

    [name, phone, email].forEach(field => field.classList.remove("is-invalid"));

    if (name.value.trim() === "") {
        name.classList.add("is-invalid");
        valid = false;
    }

    if (phone.value.trim() === "" && email.value.trim() === "") {
        phone.classList.add("is-invalid");
        email.classList.add("is-invalid");
        valid = false;
    }

    if (!valid) e.preventDefault();
});

["name", "phone", "email"].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener("input", function() {
            this.classList.remove("is-invalid");
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
