<?php
require 'includes/auth.php';
require 'includes/conn.php';
require 'includes/header.php';

$userName = $_SESSION['user_name'] ?? 'Admin';
?>

<style>
.dashboard-card {
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    background-color: #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: 0.3s ease;
}
.dashboard-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.dashboard-card .card-body {
    padding: 30px;
}
.dashboard-card .card-title {
    font-size: 20px;
    color: #333;
}
.dashboard-card .card-text {
    color: #777;
    margin-bottom: 20px;
}
.dashboard-card .btn {
    background-color: #0069d9;
    color: white;
    border-radius: 6px;
}
.dashboard-card .btn:hover {
    background-color: #0056b3;
}
</style>

<div class="container py-5">
    <h2 class="mb-5 text-center">Welcome, <?= htmlspecialchars($userName) ?>!</h2>

    <div class="row g-4 justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Pending Approvals</h5>
                    <p class="card-text">Approve or reject newly registered members.</p>
                    <a href="admin_approve.php" class="btn w-100">Review Requests</a>
                </div>
            </div>
        </div>
        <div class="col-md-5 col-lg-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Add Member</h5>
                    <p class="card-text">Create a new member profile with photo and details.</p>
                    <a href="add_member.php" class="btn w-100">Add Member</a>
                </div>
            </div>
        </div>

        <div class="col-md-5 col-lg-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">View Members</h5>
                    <p class="card-text">See all registered members in the photo directory.</p>
                    <a href="index.php" class="btn w-100">View Directory</a>
                </div>
            </div>
        </div>

        <div class="col-md-5 col-lg-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Change Password</h5>
                    <p class="card-text">Update your login credentials securely.</p>
                    <a href="change-password.php" class="btn w-100">Change Password</a>
                </div>
            </div>
        </div>

        <div class="col-md-5 col-lg-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Logout</h5>
                    <p class="card-text">Securely end your session and exit.</p>
                    <a href="logout.php" class="btn btn-secondary w-100">Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
