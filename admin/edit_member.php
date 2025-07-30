<?php
require_once __DIR__ . '/../includes/header.php';
checkAuth();

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$member_id = intval($_GET['id']);

// Fetch member details
$stmt = $pdo->prepare("
    SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone, u.id AS user_id, u.role AS user_role
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.id = ?
");
$stmt->execute([$member_id]);
$member = $stmt->fetch();

if (!$member) {
    die("Member not found");
}

// Permission check: Admin OR Owner
if (!isAdmin() && $_SESSION['user_id'] != $member['user_id']) {
    die("Access Denied");
}

// Fetch children
$child_stmt = $pdo->prepare("SELECT child_name FROM children WHERE member_id = ?");
$child_stmt->execute([$member_id]);
$children = $child_stmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';
$success = '';

// Initialize sticky value for address if form not submitted yet
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Ensure index exists for first render
    if (!isset($member['mailing_address'])) {
        $member['mailing_address'] = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name']);
    $phone         = trim($_POST['phone']);
    $email         = trim($_POST['email']);
    $spouse_name   = trim($_POST['spouse_name']);
    $spouse_phone  = trim($_POST['spouse_phone']);
    $spouse_email  = trim($_POST['spouse_email']);
    $mailing_addr  = trim($_POST['mailing_address'] ?? ''); // ✅ NEW
    $children_input = isset($_POST['children']) ? $_POST['children'] : [];
    $make_admin    = isset($_POST['make_admin']) ? true : false;

    // ✅ Validation
    if (empty($name)) {
        $error = "Primary member name is required.";
    } elseif (empty($email) && empty($phone)) {
        $error = "Please provide either an Email or a Phone number.";
    }

    $emailValue   = $email !== '' ? $email : null;
    $phoneValue   = $phone !== '' ? $phone : null;
    $addressValue = $mailing_addr !== '' ? $mailing_addr : null;

    // ✅ Check for duplicates in users table (excluding current user)
    if (!$error && $member['user_id']) {
        $check = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR phone = ?) AND id != ?");
        $check->execute([$emailValue, $phoneValue, $member['user_id']]);
        if ($check->fetch()) {
            $error = "Email or Phone already exists for another user.";
        }
    }

    // ✅ Handle photo upload
    $photo_name = $member['family_photo'];
    if (!$error && !empty($_FILES['family_photo']['name'])) {
        $photo_ext = pathinfo($_FILES['family_photo']['name'], PATHINFO_EXTENSION);
        $photo_ext = strtolower($photo_ext);
        // (Optional) simple allowlist
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($photo_ext, $allowed)) {
            $error = "Unsupported image type. Allowed: JPG, PNG, GIF, WEBP.";
        } else {
            $photo_name = time() . '_' . uniqid('', true) . '.' . $photo_ext;
            $target_path = __DIR__ . '/../assets/images/uploads/' . $photo_name;

            if (move_uploaded_file($_FILES['family_photo']['tmp_name'], $target_path)) {
                if ($member['family_photo'] && file_exists(__DIR__ . '/../assets/images/uploads/' . $member['family_photo'])) {
                    @unlink(__DIR__ . '/../assets/images/uploads/' . $member['family_photo']);
                }
            } else {
                $error = "Failed to upload the photo.";
            }
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // ✅ Update user info if linked
            if ($member['user_id']) {
                $role = $make_admin ? 'admin' : 'user';
                $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, email=?, role=? WHERE id=?");
                $stmt->execute([$name, $phoneValue, $emailValue, $role, $member['user_id']]);
            }

            // ✅ Update member info (now includes mailing_address)
            $stmt = $pdo->prepare("
                UPDATE members
                SET family_photo = ?,
                    spouse_name = ?,
                    spouse_phone = ?,
                    spouse_email = ?,
                    mailing_address = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $photo_name,
                $spouse_name !== '' ? $spouse_name : null,
                $spouse_phone !== '' ? $spouse_phone : null,
                $spouse_email !== '' ? $spouse_email : null,
                $addressValue,
                $member_id
            ]);

            // ✅ Update children
            $pdo->prepare("DELETE FROM children WHERE member_id=?")->execute([$member_id]);
            if (!empty($children_input)) {
                $child_stmt = $pdo->prepare("INSERT INTO children (member_id, child_name) VALUES (?, ?)");
                foreach ($children_input as $child_name) {
                    if (!empty(trim($child_name))) {
                        $child_stmt->execute([$member_id, trim($child_name)]);
                    }
                }
            }

            $pdo->commit();
            $success = "Member details updated successfully.";

            // Refresh $member values for sticky display after POST
            $member['family_photo']   = $photo_name;
            $member['primary_name']   = $name;
            $member['primary_phone']  = $phone;
            $member['primary_email']  = $email;
            $member['spouse_name']    = $spouse_name;
            $member['spouse_phone']   = $spouse_phone;
            $member['spouse_email']   = $spouse_email;
            $member['mailing_address']= $mailing_addr;

            // Refresh children list for sticky display
            $children = array_values(array_filter(array_map('trim', $children_input)));
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } else {
        // Keep posted address visible if there was an error
        $member['mailing_address'] = $mailing_addr;
    }
}
?>

<div class="container">
    <h3>Edit Member</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
            <label>Family Photo</label>
            <input type="file" name="family_photo" class="form-control" onchange="previewImage(event)" accept="image/*">
            <?php if (!empty($member['family_photo'])): ?>
                <img id="photoPreview" src="../assets/images/uploads/<?php echo htmlspecialchars($member['family_photo'], ENT_QUOTES, 'UTF-8'); ?>" style="width:150px;margin-top:10px;">
            <?php else: ?>
                <img id="photoPreview" style="display:none;width:150px;margin-top:10px;">
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label>Primary Member Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($member['primary_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($member['primary_phone'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input
                type="email"
                name="email"
                class="form-control"
                value="<?php echo htmlspecialchars($member['primary_email'], ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo !empty($member['primary_email']) ? 'readonly' : ''; ?>
            >
        </div>

        <!-- ✅ NEW: Mailing Address (single-line). Make it a textarea if you prefer multiline. -->
        <div class="mb-3">
            <label>Mailing Address</label>
            <input
                type="text"
                name="mailing_address"
                class="form-control"
                placeholder="Street, City, Province/State, Postal/ZIP, Country"
                value="<?php echo htmlspecialchars($member['mailing_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            >
            <!-- If you prefer multiline:
            <textarea name="mailing_address" class="form-control" rows="2"><?php echo htmlspecialchars($member['mailing_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            -->
        </div>

        <h5>Spouse Details</h5>
        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="spouse_name" class="form-control" value="<?php echo htmlspecialchars($member['spouse_name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="spouse_phone" class="form-control" value="<?php echo htmlspecialchars($member['spouse_phone'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="spouse_email" class="form-control" value="<?php echo htmlspecialchars($member['spouse_email'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <h5>Children</h5>
        <div id="children-container">
            <?php foreach ($children as $child): ?>
                <input type="text" name="children[]" class="form-control mb-2" value="<?php echo htmlspecialchars($child, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-secondary mb-3" onclick="addChild()">Add Child</button>

        <?php if (isAdmin()): ?>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" name="make_admin" id="makeAdmin" <?php echo ($member['user_role'] === 'admin') ? 'checked' : ''; ?>>
            <label for="makeAdmin" class="form-check-label">Make this user an Admin</label>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary w-100">Update Member</button>
    </form>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
