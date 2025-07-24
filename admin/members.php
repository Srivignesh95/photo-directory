<?php
require_once __DIR__ . '/../includes/header.php';
checkAuth();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 6; // Members per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total members for pagination
if ($search) {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM members m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?
    ");
    $count_stmt->execute(['%' . $search . '%', '%' . $search . '%', '%' . $search . '%']);
} else {
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM members");
}
$total_members = $count_stmt->fetchColumn();
$total_pages = ceil($total_members / $limit);

// Fetch members
if ($search) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone, u.id AS user_id
        FROM members m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?
        ORDER BY u.name ASC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute(['%' . $search . '%', '%' . $search . '%', '%' . $search . '%']);
} else {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone, u.id AS user_id
        FROM members m
        LEFT JOIN users u ON m.user_id = u.id
        ORDER BY u.name ASC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute();
}
$members = $stmt->fetchAll();
?>

<h3 class="mb-4">Photo Directory</h3>

<!-- Search Form -->
<form method="GET" class="mb-4">
    <div class="input-group">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search by Name, Email, or Phone">
        <button class="btn btn-primary" type="submit">Search</button>
        <?php if ($search): ?>
            <a href="members.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>


<div class="row">
    <?php foreach ($members as $member): ?>
        <div class="col-md-3 mb-3">
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
    <nav>
        <ul class="pagination justify-content-center">
            <!-- Previous -->
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">Previous</a>
            </li>

            <!-- Page Numbers -->
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <!-- Next -->
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next</a>
            </li>
        </ul>
    </nav>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
