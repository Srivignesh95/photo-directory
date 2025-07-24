<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

// Fetch all members (joined with users table)
$stmt = $pdo->query("
    SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone, u.id AS user_id
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
    ORDER BY u.name ASC
");
$members = $stmt->fetchAll();
?>

<h3 class="mb-4">Photo Directory</h3>

<div class="row">
    <?php foreach ($members as $member): ?>
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <?php if ($member['family_photo']): ?>
                    <img src="assets/images/uploads/<?php echo htmlspecialchars($member['family_photo']); ?>" class="card-img-top" alt="Family Photo">
                <?php else: ?>
                    <img src="assets/images/default.jpg" class="card-img-top" alt="No Image">
                <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($member['primary_name']); ?></h5>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($member['primary_phone']); ?></p>
                    <?php if ($member['primary_email']): ?>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($member['primary_email']); ?></p>
                    <?php endif; ?>

                    <?php if ($member['spouse_name']): ?>
                        <p><strong>Spouse:</strong> <?php echo htmlspecialchars($member['spouse_name']); ?></p>
                        <?php if ($member['spouse_phone']): ?><p><strong>Phone:</strong> <?php echo htmlspecialchars($member['spouse_phone']); ?></p><?php endif; ?>
                        <?php if ($member['spouse_email']): ?><p><strong>Email:</strong> <?php echo htmlspecialchars($member['spouse_email']); ?></p><?php endif; ?>
                    <?php endif; ?>

                    <?php
                        $child_stmt = $pdo->prepare("SELECT child_name FROM children WHERE member_id = ?");
                        $child_stmt->execute([$member['id']]);
                        $children = $child_stmt->fetchAll();
                        if ($children):
                    ?>
                        <p><strong>Children:</strong>
                            <?php echo implode(', ', array_column($children, 'child_name')); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Actions -->
                    <?php if (isAdmin()): ?>
                        <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                        <a href="delete_member.php?id=<?php echo $member['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
                    <?php elseif ($_SESSION['user_id'] == $member['user_id']): ?>
                        <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
