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
?>

<div class="container mt-4">
    <h3>Your Profile</h3>

    <div class="card shadow p-3">
        <div class="row">
            <!-- Profile Photo -->
            <div class="col-md-4 text-center">
                <img src="assets/images/uploads/<?php echo $member['family_photo'] ? htmlspecialchars($member['family_photo']) : 'default.jpg'; ?>"
                     alt="Family Photo" class="img-fluid rounded shadow" style="max-width: 200px; height: auto;">
            </div>

            <!-- Profile Details -->
            <div class="col-md-8">
                <h4 class="mb-3"><?php echo htmlspecialchars($member['primary_name']); ?></h4>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($member['primary_email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($member['primary_phone'] ?? 'N/A'); ?></p>
                <hr>
                <h5>Spouse Details</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($member['spouse_name'] ?? 'N/A'); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($member['spouse_phone'] ?? 'N/A'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($member['spouse_email'] ?? 'N/A'); ?></p>
                <hr>
                <h5>Children</h5>
                <?php if (!empty($children)): ?>
                    <ul>
                        <?php foreach ($children as $child): ?>
                            <li><?php echo htmlspecialchars($child); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No children added.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-3 text-end">
            <a href="admin/edit_member.php?id=<?php echo $member['id']; ?>" class="btn btn-primary">Edit Profile</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
