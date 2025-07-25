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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $spouse_name = trim($_POST['spouse_name']);
    $spouse_phone = trim($_POST['spouse_phone']);
    $spouse_email = trim($_POST['spouse_email']);
    $children_input = isset($_POST['children']) ? $_POST['children'] : [];
    $make_admin = isset($_POST['make_admin']) ? true : false;

    // ✅ Validation
    if (empty($name)) {
        $error = "Primary member name is required.";
    } elseif (empty($email) && empty($phone)) {
        $error = "Please provide either an Email or a Phone number.";
    }

    $emailValue = !empty($email) ? $email : null;
    $phoneValue = !empty($phone) ? $phone : null;

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
        $photo_name = time() . '_' . uniqid() . '.' . $photo_ext;
        $target_path = __DIR__ . '/../assets/images/uploads/' . $photo_name;

        if (move_uploaded_file($_FILES['family_photo']['tmp_name'], $target_path)) {
            if ($member['family_photo'] && file_exists(__DIR__ . '/../assets/images/uploads/' . $member['family_photo'])) {
                unlink(__DIR__ . '/../assets/images/uploads/' . $member['family_photo']);
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

            // ✅ Update member info
            $stmt = $pdo->prepare("UPDATE members SET family_photo=?, spouse_name=?, spouse_phone=?, spouse_email=? WHERE id=?");
            $stmt->execute([
                $photo_name,
                !empty($spouse_name) ? $spouse_name : null,
                !empty($spouse_phone) ? $spouse_phone : null,
                !empty($spouse_email) ? $spouse_email : null,
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
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container">
    <h3>Edit Member</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Family Photo</label>
            <input type="file" name="family_photo" class="form-control" onchange="previewImage(event)">
            <?php if ($member['family_photo']): ?>
                <img id="photoPreview" src="../assets/images/uploads/<?php echo htmlspecialchars($member['family_photo']); ?>" style="width:150px;margin-top:10px;">
            <?php else: ?>
                <img id="photoPreview" style="display:none;width:150px;margin-top:10px;">
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label>Primary Member Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($member['primary_name']); ?>" required>
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($member['primary_phone']); ?>">
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" 
                value="<?php echo htmlspecialchars($member['primary_email']); ?>" 
                <?php echo !empty($member['primary_email']) ? 'readonly' : ''; ?>>
        </div>

        <h5>Spouse Details</h5>
        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="spouse_name" class="form-control" value="<?php echo htmlspecialchars($member['spouse_name']); ?>">
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="spouse_phone" class="form-control" value="<?php echo htmlspecialchars($member['spouse_phone']); ?>">
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="spouse_email" class="form-control" value="<?php echo htmlspecialchars($member['spouse_email']); ?>">
        </div>

        <h5>Children</h5>
        <div id="children-container">
            <?php foreach ($children as $child): ?>
                <input type="text" name="children[]" class="form-control mb-2" value="<?php echo htmlspecialchars($child); ?>">
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
    reader.readAsDataURL(event.target.files[0]);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
