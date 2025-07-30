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

// Helpers
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<h3 class="mb-4">Photo Directory</h3>

<div class="row">
    <form method="GET" class="mb-4">
        <div class="row g-2">
            <div class="col-md-4">
                <input
                    type="text"
                    name="search"
                    value="<?php echo h($search); ?>"
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

<div class="row" id="memberCards">
    <?php foreach ($members as $member): ?>
        <?php
            // Build children list
            $child_stmt = $pdo->prepare("SELECT child_name FROM children WHERE member_id = ?");
            $child_stmt->execute([$member['id']]);
            $children = implode(', ', array_column($child_stmt->fetchAll(PDO::FETCH_ASSOC), 'child_name'));

            // Authorization to view private fields (admin or owner)
            $can_view_private = $is_admin || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $member['user_id']);

            // Precompute values for dataset (private fields gated)
            $primary_phone  = $can_view_private ? (string) $member['primary_phone'] : '';
            $primary_email  = $can_view_private ? (string) $member['primary_email'] : '';
            $spouse_phone   = $can_view_private ? (string) $member['spouse_phone']  : '';
            $spouse_email   = $can_view_private ? (string) $member['spouse_email']  : '';

            // Admin-only mailing address (empty for non-admins)
            $mailing_address = ($is_admin && isset($member['mailing_address'])) ? (string) $member['mailing_address'] : '';

            $photo_src = !empty($member['family_photo'])
                ? "assets/images/uploads/" . $member['family_photo']
                : "assets/images/default.png";

            $edit_link   = $can_view_private ? "admin/edit_member.php?id=" . urlencode((string)$member['id']) : "";
            $delete_link = $is_admin ? "admin/delete_member.php?id=" . urlencode((string)$member['id']) : "";
        ?>
        <div class="col-md-3 mb-3">
            <div class="card shadow text-center p-2 member-card"
                 tabindex="0"
                 role="button"
                 data-name="<?php echo h($member['primary_name']); ?>"
                 data-phone="<?php echo h($primary_phone); ?>"
                 data-email="<?php echo h($primary_email); ?>"
                 data-spouse="<?php echo h($member['spouse_name']); ?>"
                 data-spouse-phone="<?php echo h($spouse_phone); ?>"
                 data-spouse-email="<?php echo h($spouse_email); ?>"
                 data-address="<?php echo h($mailing_address); ?>"
                 data-children="<?php echo h($children); ?>"
                 data-photo="<?php echo h($photo_src); ?>"
                 data-edit-link="<?php echo h($edit_link); ?>"
                 data-delete-link="<?php echo h($delete_link); ?>"
                 data-admin-or-owner="<?php echo $can_view_private ? 'true' : 'false'; ?>"
            >
                <img
                    src="<?php echo h($photo_src); ?>"
                    alt="Family Photo"
                    class="card-img-top"
                    style="object-fit:cover; border-radius:8px;"
                >
                <div class="card-body">
                    <h6 class="card-title"><?php echo h($member['primary_name']); ?></h6>
                    <?php if (!empty($member['spouse_name'])): ?>
                        <p class="text-muted mb-0">
                            Spouse: <?php echo h($member['spouse_name']); ?>
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
                if (isset($merged['search']) && $merged['search'] === '') unset($merged['search']);
                return '?' . http_build_query($merged);
            };
        ?>
        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo ($page > 1) ? h($qs(['page' => $page - 1])) : '#'; ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo h($qs(['page' => $i])); ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo ($page < $total_pages) ? h($qs(['page' => $page + 1])) : '#'; ?>">Next</a>
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

                        <!-- HR for Address (shown only if address is visible) -->
                        <hr id="hrAddress" style="display:none;">

                        <!-- Admin-only via server-side empty value for non-admins -->
                        <p id="pAddress"><strong>Mailing Address:</strong> <span id="modalAddress"></span></p>

                        <!-- HR shown only if spouse section has visible fields -->
                        <hr id="hrSpouse">

                        <p><strong>Spouse:</strong> <span id="modalSpouse"></span></p>
                        <p><strong>Spouse Phone:</strong> <span id="modalSpousePhone"></span></p>
                        <p><strong>Spouse Email:</strong> <span id="modalSpouseEmail"></span></p>

                        <!-- HR shown only if children section has visible fields -->
                        <hr id="hrChildren">
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
// Delegate click to all .member-card items (no inline JS)
document.getElementById('memberCards').addEventListener('click', function (e) {
    const card = e.target.closest('.member-card');
    if (!card) return;

    showMemberModal(
        card.dataset.name || '',
        card.dataset.phone || '',
        card.dataset.email || '',
        card.dataset.spouse || '',
        card.dataset.spousePhone || '',
        card.dataset.spouseEmail || '',
        card.dataset.address || '',
        card.dataset.children || '',
        card.dataset.photo || '',
        card.dataset.editLink || '',
        card.dataset.deleteLink || '',
        (card.dataset.adminOrOwner === 'true')
    );
});

function showMemberModal(name, phone, email, spouse, spousePhone, spouseEmail, address, children, photo, editLink, deleteLink, isAdminOrOwner) {
    const fields = [
        { id: 'modalName',        value: name },
        { id: 'modalPhone',       value: phone },
        { id: 'modalEmail',       value: email },
        { id: 'modalSpouse',      value: spouse },
        { id: 'modalSpousePhone', value: spousePhone },
        { id: 'modalSpouseEmail', value: spouseEmail },
        { id: 'modalAddress',     value: address },
        { id: 'modalChildren',    value: children }
    ];

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

    // HR before Address
    const pAddress  = document.getElementById('pAddress');
    const hrAddress = document.getElementById('hrAddress');
    if (pAddress && hrAddress) {
        const hasAddress = (document.getElementById('modalAddress').textContent.trim() !== '');
        pAddress.style.display  = hasAddress ? 'block' : 'none';
        hrAddress.style.display = hasAddress ? 'block' : 'none';
    }

    // HRs for spouse/children
    const spouseVisible =
        (document.getElementById('modalSpouse').textContent.trim() !== '') ||
        (document.getElementById('modalSpousePhone').textContent.trim() !== '') ||
        (document.getElementById('modalSpouseEmail').textContent.trim() !== '');
    const childrenVisible =
        (document.getElementById('modalChildren').textContent.trim() !== '');

    const hrSpouse   = document.getElementById('hrSpouse');
    const hrChildren = document.getElementById('hrChildren');

    if (hrSpouse)   hrSpouse.style.display   = spouseVisible ? 'block' : 'none';
    if (hrChildren) hrChildren.style.display = childrenVisible ? 'block' : 'none';

    // Photo
    document.getElementById('modalPhoto').src = photo || 'assets/images/default.png';

    // Edit/Delete buttons
    const editBtn = document.getElementById('editBtn');
    const deleteBtn = document.getElementById('deleteBtn');

    if (isAdminOrOwner) {
        if (editBtn) {
            editBtn.style.display = 'inline-block';
            editBtn.href = editLink || '#';
        }
        if (deleteBtn) {
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
