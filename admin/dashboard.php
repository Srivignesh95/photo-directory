<?php
require_once __DIR__ . '/../includes/header.php';
checkAuth();
if (!isAdmin()) {
    die("Access Denied");
}

// Fetch stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_members = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$pending_count = $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
?>

<div class="row">
    <div class="col-md-3">
        <div class="list-group position-sticky" style="top:20px;">
            <a href="dashboard.php" class="list-group-item list-group-item-action active">Dashboard</a>
            <a href="pending.php" class="list-group-item list-group-item-action">Pending Approvals (<?php echo $pending_count; ?>)</a>
            <a href="members.php" class="list-group-item list-group-item-action">Manage Members</a>
            <a href="add_member.php" class="list-group-item list-group-item-action">Add Member</a>
        </div>
    </div>

    <div class="col-md-9">
        <h3>Admin Dashboard</h3>
        <div class="row">
            <div class="col-md-4">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <p class="card-text fs-3"><?php echo $total_users; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Members</h5>
                        <p class="card-text fs-3"><?php echo $total_members; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Pending Approvals</h5>
                        <p class="card-text fs-3"><?php echo $pending_count; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <p>Use the sidebar to manage users and members.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
