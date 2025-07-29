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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $spouse_name = trim($_POST['spouse_name']);
    $spouse_phone = trim($_POST['spouse_phone']);
    $spouse_email = trim($_POST['spouse_email']);
    $children = isset($_POST['children']) ? $_POST['children'] : [];

    // ✅ Validation
    if (empty($name)) {
        $error = "Primary member name is required.";
    } elseif (empty($email) && empty($phone)) {
        $error = "Please provide either an Email or a Phone number.";
    }

    // ✅ Prepare values (NULL if empty)
    $emailValue = !empty($email) ? $email : null;
    $phoneValue = !empty($phone) ? $phone : null;

    // ✅ Check for duplicates before starting transaction
    if (!$error && (!empty($emailValue) || !empty($phoneValue))) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $check->execute([$emailValue, $phoneValue]);
        if ($check->fetch()) {
            $error = "Email or Phone already exists. Cannot create a new user.";
        }
    }

    if (!$error) {
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
            $user_id = null;

            // ✅ Create user if email OR phone exists
            if (!empty($emailValue) || !empty($phoneValue)) {
                $temp_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'user', 'approved')");
                $stmt->execute([$name, $emailValue, $phoneValue, $hashed_password]);
                $user_id = $pdo->lastInsertId();

                // ✅ Send email if email exists
                if (!empty($emailValue)) {
                    $subject = "Welcome to Photo Directory!";
                    $message = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f7f7f7; color: #333;'>
                            <h2 style='color: #2a7ae2;'>Welcome to Photo Directory!</h2>
                            <p>Your account has been created by an administrator.</p>
                            <p><strong>Here are your login details:</strong></p>
                            <ul style='line-height: 1.6;'>
                                <li><strong>Email:</strong> $emailValue</li>
                                <li><strong>Temporary Password:</strong> $temp_password</li>
                            </ul>
                            <p style='margin: 20px 0;'>
                                <a href='" . BASE_URL . "auth/login.php' style='background-color: #2a7ae2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Photo Directory</a>
                            </p>
                            <p><em>We recommend changing your password after logging in.</em></p>
                            <hr style='border: none; border-top: 1px solid #ccc;'>
                            <p style='font-size: 12px; color: #888;'>Thank you,<br>Photo Directory Team</p>
                        </div>
                    ";

                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                    $headers .= "From: no-reply@photodirectory.com\r\n";

                    @mail($emailValue, $subject, $message, $headers);
                }
            }

            // ✅ Insert member
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
            $success = "Member added successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
            error_log("Transaction failed: " . $e->getMessage());
        }
    }
}
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
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="addMemberForm" novalidate>
            <div class="mb-3">
                <label>Family Photo</label>
                <input type="file" name="family_photo" class="form-control" accept="image/*" onchange="previewImage(event)">
                <img id="photoPreview" src="" alt="" class="mt-2" style="display:none;width:150px;border:1px solid #ccc;padding:5px;">
            </div>

            <div class="mb-3">
                <label>Primary Member Name</label>
                <input type="text" name="name" id="name" class="form-control">
                <div class="invalid-feedback">Primary member name is required.</div>
            </div>

            <div class="mb-3">
                <label>Phone</label>
                <input type="text" name="phone" id="phone" class="form-control">
                <div class="invalid-feedback">Please provide a phone number or an email.</div>
            </div>

            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" id="email" class="form-control">
                <div class="invalid-feedback">Please provide a phone number or an email.</div>
            </div>

            <h5>Spouse Details</h5>
            <div class="mb-3">
                <label>Name</label>
                <input type="text" name="spouse_name" class="form-control">
            </div>
            <div class="mb-3">
                <label>Phone</label>
                <input type="text" name="spouse_phone" class="form-control">
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="spouse_email" class="form-control">
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
    reader.readAsDataURL(event.target.files[0]);
}

// ✅ Client-side validation
document.getElementById("addMemberForm").addEventListener("submit", function(e) {
    let valid = true;
    const name = document.getElementById("name");
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
    document.getElementById(id).addEventListener("input", function() {
        this.classList.remove("is-invalid");
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
