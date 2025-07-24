<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

// Fetch user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$member = $stmt->fetch();

if (!$member) {
    die("Profile not found.");
}

// Fetch children
$child_stmt = $pdo->prepare("SELECT child_name FROM children WHERE member_id = ?");
$child_stmt->execute([$member['id']]);
$children = $child_stmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';
$success = '';

// ✅ Add image resize function
function resizeImage($source, $destination, $targetWidth, $targetHeight) {
    list($width, $height) = getimagesize($source);
    $type = mime_content_type($source);

    if ($type == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($type == 'image/png') {
        $image = imagecreatefrompng($source);
    } else {
        return false; // Unsupported format
    }

    $newImage = imagecreatetruecolor($targetWidth, $targetHeight);
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    if ($type == 'image/jpeg') {
        imagejpeg($newImage, $destination, 90);
    } elseif ($type == 'image/png') {
        imagepng($newImage, $destination);
    }

    imagedestroy($image);
    imagedestroy($newImage);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $spouse_name = trim($_POST['spouse_name']);
    $spouse_phone = trim($_POST['spouse_phone']);
    $spouse_email = trim($_POST['spouse_email']);
    $children_input = isset($_POST['children']) ? $_POST['children'] : [];

    // ✅ Handle photo upload
    $photo_name = $member['family_photo'];
    if (!empty($_FILES['family_photo']['name'])) {
        $photo_ext = pathinfo($_FILES['family_photo']['name'], PATHINFO_EXTENSION);
        $photo_name = time() . '_' . uniqid() . '.' . $photo_ext;
        $target_path = __DIR__ . '/assets/images/uploads/' . $photo_name;

        if (move_uploaded_file($_FILES['family_photo']['tmp_name'], $target_path)) {
            resizeImage($target_path, $target_path, 400, 300);
        }

        // Delete old photo if exists
        if ($member['family_photo'] && file_exists(__DIR__ . '/assets/images/uploads/' . $member['family_photo'])) {
            unlink(__DIR__ . '/assets/images/uploads/' . $member['family_photo']);
        }
    }

    try {
        $pdo->beginTransaction();

        // ✅ Update users table
        $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, email=? WHERE id=?");
        $stmt->execute([$name, $phone, $email, $user_id]);

        // ✅ Update members table
        $stmt = $pdo->prepare("UPDATE members SET family_photo=?, spouse_name=?, spouse_phone=?, spouse_email=? WHERE user_id=?");
        $stmt->execute([$photo_name, $spouse_name, $spouse_phone, $spouse_email, $user_id]);

        // ✅ Update children
        $pdo->prepare("DELETE FROM children WHERE member_id=?")->execute([$member['id']]);
        $child_stmt = $pdo->prepare("INSERT INTO children (member_id, child_name) VALUES (?, ?)");
        foreach ($children_input as $child_name) {
            if (!empty(trim($child_name))) {
                $child_stmt->execute([$member['id'], trim($child_name)]);
            }
        }

        $pdo->commit();
        $success = "Profile updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating profile: " . $e->getMessage();
    }
}
?>

<div class="container">
    <h3>Your Profile</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Family Photo</label>
            <input type="file" name="family_photo" class="form-control" onchange="previewImage(event)">
            <?php if ($member['family_photo']): ?>
                <img id="photoPreview" src="assets/images/uploads/<?php echo $member['family_photo']; ?>" style="width:150px;margin-top:10px;">
            <?php else: ?>
                <img id="photoPreview" style="display:none;width:150px;margin-top:10px;">
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($member['primary_name']); ?>" required>
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($member['primary_phone']); ?>" required>
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($member['primary_email']); ?>">
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

        <button type="submit" class="btn btn-primary w-100">Update Profile</button>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
