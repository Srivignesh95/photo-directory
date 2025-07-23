<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'includes/header.php';
require 'includes/conn.php';

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search and sort parameters
$search = trim($_GET['search'] ?? '');
$sortOption = $_GET['sort'] ?? 'first_asc';

switch ($sortOption) {
    case 'first_desc': $orderBy = 'first_name DESC'; break;
    case 'last_asc':  $orderBy = 'last_name ASC'; break;
    case 'last_desc': $orderBy = 'last_name DESC'; break;
    case 'first_asc':
    default: $orderBy = 'first_name ASC'; break;
}

// Count total filtered results
$countQuery = "SELECT COUNT(*) FROM members WHERE first_name LIKE :search OR last_name LIKE :search";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute(['search' => "%$search%"]);
$totalMembers = $countStmt->fetchColumn();
$totalPages = ceil($totalMembers / $limit);

// Fetch filtered members
$dataQuery = "SELECT * FROM members WHERE first_name LIKE :search OR last_name LIKE :search ORDER BY $orderBy LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($dataQuery);
$stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$members = $stmt->fetchAll();
?>


<div class="team-section-1">
    <div class="container">
        <div class="team-check">
            <h2>Members</h2>
        </div>
        <form method="GET" class="mb-4 d-flex justify-content-end">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control w-auto me-3" placeholder="Search name...">
            <select name="sort" class="form-select w-auto" onchange="this.form.submit()">
                <option value="first_asc" <?= ($_GET['sort'] ?? '') === 'first_asc' ? 'selected' : '' ?>>First Name (A–Z)</option>
                <option value="first_desc" <?= ($_GET['sort'] ?? '') === 'first_desc' ? 'selected' : '' ?>>First Name (Z–A)</option>
                <option value="last_asc" <?= ($_GET['sort'] ?? '') === 'last_asc' ? 'selected' : '' ?>>Last Name (A–Z)</option>
                <option value="last_desc" <?= ($_GET['sort'] ?? '') === 'last_desc' ? 'selected' : '' ?>>Last Name (Z–A)</option>
            </select>
        </form>

        <div class="row gy-4 justify-content-center">
        <?php if (empty($members)): ?>
            <div class="col-12 text-center">
                <div class="alert alert-warning">No members found matching your criteria.</div>
            </div>
        <?php endif; ?>
            <?php foreach ($members as $member): ?>
            <div class="col-lg-3 col-md-6">
                <div class="team-member">
                    <div class="team-images">
                        <img src="assets/images/uploads/<?= htmlspecialchars($member['photo']) ?>" alt="<?= htmlspecialchars($member['first_name']) ?>">
                    </div>
                    <div class="team-desc">
                        <a href="javascript:void(0)" class="name">
                            <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                        </a>
                        <span><?= htmlspecialchars($member['designation']) ?></span><br>
                        <span><?= htmlspecialchars($member['contact_info']) ?></span>
                        <div class="social-icon d-flex flex-row">
                            <?php if (!empty($member['facebook_url'])): ?>
                                <a href="<?= htmlspecialchars($member['facebook_url']) ?>" target="_blank"><i class="ri-facebook-fill"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($member['twitter_url'])): ?>
                                <a href="<?= htmlspecialchars($member['twitter_url']) ?>" target="_blank"><i class="ri-twitter-x-fill"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($member['instagram_url'])): ?>
                                <a href="<?= htmlspecialchars($member['instagram_url']) ?>" target="_blank"><i class="ri-instagram-line"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($member['linkedin_url'])): ?>
                                <a href="<?= htmlspecialchars($member['linkedin_url']) ?>" target="_blank"><i class="ri-linkedin-fill"></i></a>
                            <?php endif; ?>
                        </div>

                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_access'] == 1): ?>
                            <div class="mt-3 text-center">
                                <a href="edit_member.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                                <a href="delete_member.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this member?');">Delete</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Controls -->
        <!-- Pagination Controls -->
        <div class="mt-5 d-flex justify-content-center">
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&sort=<?= urlencode($sortOption) ?>&search=<?= urlencode($search) ?>">Prev</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&sort=<?= urlencode($sortOption) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&sort=<?= urlencode($sortOption) ?>&search=<?= urlencode($search) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>

    </div>
</div>

<?php require 'includes/footer.php'; ?>
