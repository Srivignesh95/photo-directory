<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit  = 20;
$page   = (isset($_GET['page']) && is_numeric($_GET['page']) && intval($_GET['page']) > 0) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$sort = (isset($_GET['sort']) && $_GET['sort'] === 'DESC') ? 'DESC' : 'ASC'; // Default ASC
$is_admin = isAdmin();

// ---------- COUNT ----------
if ($search !== '') {
    if ($is_admin) {
        // Admins can search by name/email/phone
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM members m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?) AND u.status = 'approved'
        ");
        $count_stmt->execute(['%'.$search.'%', '%'.$search.'%', '%'.$search.'%']);
    } else {
        // Non-admins: restrict search to name only (optional but recommended)
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM members m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE u.name LIKE ? AND u.status = 'approved'
        ");
        $count_stmt->execute(['%'.$search.'%']);
    }
} else {
    $count_stmt = $pdo->query("
        SELECT COUNT(*) FROM members m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE u.status = 'approved'
    ");
}

$total_members = (int) $count_stmt->fetchColumn();
$total_pages   = ($total_members > 0) ? (int) ceil($total_members / $limit) : 1;

// ---------- DATA ----------
if ($search !== '') {
    if ($is_admin) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone, u.id AS user_id
            FROM members m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?) AND u.status = 'approved'
            ORDER BY u.name $sort
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute(['%'.$search.'%', '%'.$search.'%', '%'.$search.'%']);
    } else {
        $stmt = $pdo->prepare("
            SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone, u.id AS user_id
            FROM members m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE u.name LIKE ? AND u.status = 'approved'
            ORDER BY u.name $sort
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute(['%'.$search.'%']);
    }
} else {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone, u.id AS user_id
        FROM members m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE u.status = 'approved'
        ORDER BY u.name $sort
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute();
}

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h3 class="mb-4">Photo Directory</h3>

<div class="row">
    <form method="GET" class="mb-4">
        <div class="row g-2">
            <div class="col-md-4">
                <input
                    type="text"
                    name="search"
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                    class="form-control"
                    placeholder="Search by Name<?php echo $is_admin ? ', Email or Phone' : ''; ?>"
                >
            </div>
            <div class="col-md-3">
                <select name="sort" class="form-select">
                    <option value="ASC"  <?php echo ($sort === 'ASC')  ? 'selected' : ''; ?>>Name (A → Z)</option>
                    <option value="DESC" <?php echo ($sort === 'DESC') ? 'selected' : ''; ?>>Name (Z → A)</option>
                </select>
            </div>
            <div class="col-md-5 d-flex">
                <button class="btn btn-primary me-2" type="submit">Apply</button>
                <?php if ($search !== '' || isset($_GET['sort'])): ?>
                    <a href="directory.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="row">
    <?php foreach ($members as $member): ?>
        <?php
            // Build children list
            $child_stmt = $pdo->prepare("SELECT child_name FROM children WHERE member_id = ?");
            $child_stmt->execute([$member['id']]);
            $children = implode(', ', array_column($child_stmt->fetchAll(PDO::FETCH_ASSOC), 'child_name'));

            // Authorization to view private fields (admin or owner)
            $can_view_private = $is_admin || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $member['user_id']);

            // Precompute values we will pass to JS (private fields gated)
            $primary_phone  = $can_view_private ? (string) $member['primary_phone'] : '';
            $primary_email  = $can_view_private ? (string) $member['primary_email'] : '';
            $spouse_phone   = $can_view_private ? (string) $member['spouse_phone']  : '';
            $spouse_email   = $can_view_private ? (string) $member['spouse_email']  : '';

            $photo_src      = $member['family_photo']
                                ? "assets/images/uploads/" . $member['family_photo']
                                : "assets/images/default.png";

            $edit_link      = $can_view_private ? "admin/edit_member.php?id=" . urlencode((string)$member['id']) : "";
            $delete_link    = $is_admin ? "admin/delete_member.php?id=" . urlencode((string)$member['id']) : "";
        ?>
        <div class="col-md-3 mb-3">
            <div class="card shadow text-center p-2" style="cursor:pointer;"
                onclick='showMemberModal(
                    <?php echo json_encode($member["primary_name"]); ?>,
                    <?php echo json_encode($primary_phone); ?>,
                    <?php echo json_encode($primary_email); ?>,
                    <?php echo json_encode($member["spouse_name"]); ?>,
                    <?php echo json_encode($spouse_phone); ?>,
                    <?php echo json_encode($spouse_email); ?>,
                    <?php echo json_encode($children); ?>,
                    <?php echo json_encode($photo_src); ?>,
                    <?php echo json_encode($edit_link); ?>,
                    <?php echo json_encode($delete_link); ?>,
                    <?php echo $can_view_private ? 'true' : 'false'; ?>
                )'>

                <!-- Family Photo -->
                <img
                    src="<?php echo htmlspecialchars($photo_src, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Family Photo"
                    class="card-img-top"
                    style="object-fit:cover; border-radius:8px;"
                >

                <div class="card-body">
                    <h6 class="card-title"><?php echo htmlspecialchars($member['primary_name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                    <?php if (!empty($member['spouse_name'])): ?>
                        <p class="text-muted mb-0">
                            Spouse: <?php echo htmlspecialchars($member['spouse_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($total_members > 0 && $total_pages > 1): ?>
<nav aria-label="Member pagination">
    <ul class="pagination justify-content-center">
        <?php
            // Build base query string for links (preserve search & sort)
            $qs = function($params = []) use ($search, $sort) {
                $base = [
                    'search' => $search,
                    'sort'   => $sort,
                ];
                $merged = array_merge($base, $params);
                // Remove empty search for cleaner URLs
                if (isset($merged['search']) && $merged['search'] === '') {
                    unset($merged['search']);
                }
                return '?' . http_build_query($merged);
            };
        ?>
        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo ($page > 1) ? htmlspecialchars($qs(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') : '#'; ?>">
                Previous
            </a>
        </li>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo htmlspecialchars($qs(['page' => $i]), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo ($page < $total_pages) ? htmlspecialchars($qs(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') : '#'; ?>">
                Next
            </a>
        </li>
    </ul>
</nav>
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
                    <!-- Details on the left -->
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

                    <!-- Image on the right -->
                    <div class="col-md-4 col-12 text-center">
                        <img id="modalPhoto" src="" alt="Family Photo" class="img-fluid rounded shadow" style="max-height:250px; object-fit:cover;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <!-- Admin or Owner Controls -->
                <a href="#" id="editBtn" class="btn btn-primary" style="display:none;">Edit</a>
                <a href="#" id="deleteBtn" class="btn btn-danger" style="display:none;" onclick="return confirm('Are you sure you want to delete this member?');">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function showMemberModal(name, phone, email, spouse, spousePhone, spouseEmail, children, photo, editLink, deleteLink, isAdminOrOwner) {
    const fields = [
        { id: 'modalName',        value: name,        label: 'Name' },
        { id: 'modalPhone',       value: phone,       label: 'Phone' },
        { id: 'modalEmail',       value: email,       label: 'Email' },
        { id: 'modalSpouse',      value: spouse,      label: 'Spouse' },
        { id: 'modalSpousePhone', value: spousePhone, label: 'Spouse Phone' },
        { id: 'modalSpouseEmail', value: spouseEmail, label: 'Spouse Email' },
        { id: 'modalChildren',    value: children,    label: 'Children' }
    ];

    // Clear/hide all fields first depending on value presence
    fields.forEach(f => {
        const el = document.getElementById(f.id);
        const parentP = el ? el.closest('p') : null;
        if (!el || !parentP) return;

        if (f.value && f.value.toString().trim() !== '') {
            el.textContent = f.value;
            parentP.style.display = 'block';
        } else {
            el.textContent = '';
            parentP.style.display = 'none';
        }
    });

    // Photo
    document.getElementById('modalPhoto').src = photo || 'assets/images/default.png';

    // Edit/Delete (only if admin or profile owner)
    const editBtn = document.getElementById('editBtn');
    const deleteBtn = document.getElementById('deleteBtn');

    if (isAdminOrOwner) {
        if (editBtn) {
            editBtn.style.display = 'inline-block';
            editBtn.href = editLink || '#';
        }
        if (deleteBtn) {
            // delete shown only if link provided (admin)
            if (deleteLink && deleteLink.trim() !== '') {
                deleteBtn.style.display = 'inline-block';
                deleteBtn.href = deleteLink;
            } else {
                deleteBtn.style.display = 'none';
                deleteBtn.removeAttribute('href');
            }
        }
    } else {
        if (editBtn) {
            editBtn.style.display = 'none';
            editBtn.removeAttribute('href');
        }
        if (deleteBtn) {
            deleteBtn.style.display = 'none';
            deleteBtn.removeAttribute('href');
        }
    }

    new bootstrap.Modal(document.getElementById('memberModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
