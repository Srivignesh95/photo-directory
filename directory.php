<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$isAdmin = isAdmin();
$userId = $_SESSION['user_id'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$selectFields = $isAdmin ?
    "u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone, u.id AS user_id" :
    "u.name AS primary_name, u.id AS user_id";

$whereSearch = $search ?
    "(u.name LIKE ?" . ($isAdmin ? " OR u.email LIKE ? OR u.phone LIKE ?" : "") . ") AND u.status = 'approved'" :
    "u.status = 'approved'";

$countSql = "SELECT COUNT(*) FROM members m LEFT JOIN users u ON m.user_id = u.id WHERE $whereSearch";
$countStmt = $pdo->prepare($countSql);
$searchParams = $search ? ($isAdmin ? ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'] : ['%' . $search . '%']) : [];
$countStmt->execute($searchParams);
$total_members = $countStmt->fetchColumn();
$total_pages = ceil($total_members / $limit);

$sort = isset($_GET['sort']) && $_GET['sort'] === 'DESC' ? 'DESC' : 'ASC';

$dataSql = "
    SELECT m.*, $selectFields
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE $whereSearch
    ORDER BY u.name $sort
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($searchParams);
$members = $stmt->fetchAll();
?>

<h3 class="mb-4">Photo Directory</h3>
<form method="GET" class="mb-4 row g-2">
    <div class="col-md-4">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search by Name">
    </div>
    <div class="col-md-3">
        <select name="sort" class="form-select">
            <option value="ASC" <?php if ($sort === 'ASC') echo 'selected'; ?>>Name (A → Z)</option>
            <option value="DESC" <?php if ($sort === 'DESC') echo 'selected'; ?>>Name (Z → A)</option>
        </select>
    </div>
    <div class="col-md-5 d-flex">
        <button class="btn btn-primary me-2" type="submit">Apply</button>
        <?php if ($search || isset($_GET['sort'])): ?>
            <a href="directory.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>

<div class="row">
<?php foreach ($members as $member):
    $child_stmt = $pdo->prepare("SELECT child_name FROM children WHERE member_id = ?");
    $child_stmt->execute([$member['id']]);
    $children = implode(', ', array_column($child_stmt->fetchAll(), 'child_name'));
    $isOwnerOrAdmin = $isAdmin || $userId == $member['user_id'];
?>
    <div class="col-md-3 mb-3">
        <div class="card shadow text-center p-2" style="cursor:pointer;"
            onclick='showMemberModal(
                <?php echo json_encode($member["primary_name"]); ?>,
                <?php echo json_encode($isOwnerOrAdmin ? ($member["primary_phone"] ?? '') : ''); ?>,
                <?php echo json_encode($isOwnerOrAdmin ? ($member["primary_email"] ?? '') : ''); ?>,
                <?php echo json_encode($member["spouse_name"] ?? ''); ?>,
                <?php echo json_encode($isOwnerOrAdmin ? ($member["spouse_phone"] ?? '') : ''); ?>,
                <?php echo json_encode($isOwnerOrAdmin ? ($member["spouse_email"] ?? '') : ''); ?>,
                <?php echo json_encode($children); ?>,
                <?php echo json_encode($member["family_photo"] ? "assets/images/uploads/".$member["family_photo"] : "assets/images/default.png"); ?>,
                <?php echo json_encode($isOwnerOrAdmin ? "admin/edit_member.php?id=".$member["id"] : ""); ?>,
                <?php echo json_encode($isOwnerOrAdmin ? "admin/delete_member.php?id=".$member["id"] : ""); ?>,
                <?php echo json_encode($isOwnerOrAdmin); ?>
            )'>
            <img src="<?php echo $member['family_photo'] ? 'assets/images/uploads/' . htmlspecialchars($member['family_photo']) : 'assets/images/default.png'; ?>"
                alt="Family Photo" class="card-img-top" style="object-fit:cover; border-radius:8px;">
            <div class="card-body">
                <h6 class="card-title"><?php echo htmlspecialchars($member['primary_name']); ?></h6>
                <?php if (!empty($member['spouse_name'])): ?>
                    <p class="text-muted mb-0">Spouse: <?php echo htmlspecialchars($member['spouse_name']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($total_pages > 1): ?>
<nav><ul class="pagination justify-content-center">
    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
        <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">Previous</a>
    </li>
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        </li>
    <?php endfor; ?>
    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
        <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next</a>
    </li>
</ul></nav>
<?php endif; ?>

<!-- Modal -->
<div class="modal fade" id="memberModal" tabindex="-1" aria-labelledby="memberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="memberModalLabel" class="modal-title">Member Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8 col-12">
                        <p><strong>Name:</strong> <span id="modalName"></span></p>
                        <p><strong>Phone:</strong> <span id="modalPhone"></span></p>
                        <p><strong>Email:</strong> <span id="modalEmail"></span></p>
                        <hr>
                        <p><strong>Spouse:</strong> <span id="modalSpouse"></span></p>
                        <p><strong>Spouse Phone:</strong> <span id="modalSpousePhone"></span></p>
                        <p><strong>Spouse Email:</strong> <span id="modalSpouseEmail"></span></p>
                        <hr>
                        <p><strong>Children:</strong> <span id="modalChildren"></span></p>
                    </div>
                    <div class="col-md-4 col-12 text-center">
                        <img id="modalPhoto" src="" alt="Family Photo" class="img-fluid rounded shadow" style="max-height:250px; object-fit:cover;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="editBtn" class="btn btn-primary" style="display:none;">Edit</a>
                <a href="#" id="deleteBtn" class="btn btn-danger" style="display:none;" onclick="return confirm('Are you sure you want to delete this member?');">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function showMemberModal(name, phone, email, spouse, spousePhone, spouseEmail, children, photo, editLink, deleteLink, isAdminOrOwner) {
    const showOrHide = (id, value) => {
        const el = document.getElementById(id);
        const wrapper = el.closest('p');
        if (value && value.trim() !== '' && isAdminOrOwner) {
            el.textContent = value;
            wrapper.style.display = 'block';
        } else if (id === 'modalName' || id === 'modalSpouse' || id === 'modalChildren') {
            el.textContent = value;
            wrapper.style.display = 'block';
        } else {
            wrapper.style.display = 'none';
        }
    };

    showOrHide('modalName', name);
    showOrHide('modalPhone', phone);
    showOrHide('modalEmail', email);
    showOrHide('modalSpouse', spouse);
    showOrHide('modalSpousePhone', spousePhone);
    showOrHide('modalSpouseEmail', spouseEmail);
    showOrHide('modalChildren', children);

    document.getElementById('modalPhoto').src = photo;
    document.getElementById('editBtn').style.display = isAdminOrOwner ? 'inline-block' : 'none';
    document.getElementById('deleteBtn').style.display = isAdminOrOwner ? 'inline-block' : 'none';
    document.getElementById('editBtn').href = editLink;
    document.getElementById('deleteBtn').href = deleteLink;

    new bootstrap.Modal(document.getElementById('memberModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
