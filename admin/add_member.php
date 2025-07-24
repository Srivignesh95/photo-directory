<?php
require_once __DIR__ . '/../includes/header.php';
checkAuth();
if (!isAdmin()) {
    die("Access Denied");
}

$error = '';
$success = '';

// ✅ Add image resize function (supports JPEG and PNG)
function resizeImage($source, $destination, $targetWidth, $targetHeight) {
    list($width, $height) = getimagesize($source);
    $type = mime_content_type($source);

    if ($type == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($type == 'image/png') {
        $image = imagecreatefrompng($source);
    } else {
        return false; // Unsupported type
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
    $children = isset($_POST['children']) ? $_POST['children'] : [];

    // ✅ Handle photo upload
    $photo_name = '';
    if (!empty($_FILES['family_photo']['name'])) {
        $photo_ext = pathinfo($_FILES['family_photo']['name'], PATHINFO_EXTENSION);
        $photo_name = time() . '_' . uniqid() . '.' . $photo_ext;
        $target_path = __DIR__ . '/../assets/images/uploads/' . $photo_name;

        if (move_uploaded_file($_FILES['family_photo']['tmp_name'], $target_path)) {
            resizeImage($target_path, $target_path, 400, 300); // ✅ Resize for consistency
        }
    }

    try {
        $pdo->beginTransaction();

        $user_id = null;
        if ($email) {
            // ✅ Create user with temp password
            $temp_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'user', 'approved')");
            $stmt->execute([$name, $email, $phone, $hashed_password]);
            $user_id = $pdo->lastInsertId();

            // ✅ Send email with temp password
            $subject = "Your Photo Directory Access";
            $message = "
                <h2>Welcome to Photo Directory</h2>
                <p>Your account has been created by Admin.</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Temporary Password:</strong> $temp_password</p>
                <p><a href='".BASE_URL."auth/login.php'>Login Here</a></p>
            ";
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@photodirectory.com\r\n";

            mail($email, $subject, $message, $headers);
        }

        // ✅ Insert into members table
        $stmt = $pdo->prepare("INSERT INTO members (user_id, family_photo, spouse_name, spouse_phone, spouse_email) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $photo_name, $spouse_name, $spouse_phone, $spouse_email]);
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
        
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label>Family Photo</label>
                <input type="file" name="family_photo" class="form-control" accept="image/*" onchange="previewImage(event)">
                <img id="photoPreview" src="" alt="" class="mt-2" style="display:none;width:150px;border:1px solid #ccc;padding:5px;">
            </div>
            
            <div class="mb-3">
                <label>Primary Member Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            
            <div class="mb-3">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" required>
            </div>
            
            <div class="mb-3">
                <label>Email (optional)</label>
                <input type="email" name="email" class="form-control">
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
