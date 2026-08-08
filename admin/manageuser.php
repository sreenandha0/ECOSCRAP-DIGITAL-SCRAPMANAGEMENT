<?php
// ==============================================================================
// EcoScrap - Admin Manage Users (manage_users.php)
// Integrated directly with `ecoscrap_db.user` & `ecoscrap_db.activity`
// ==============================================================================

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "ecoscrap_db";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Handle Delete Request via POST
$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $delete_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            $pdo->beginTransaction();

            // Cascading deletion for related tables
            // Delete associated records in activity table first to prevent foreign key errors
            $pdo->prepare("DELETE FROM activity WHERE user_id = ?")->execute([$delete_id]);
            
            // Delete main user record
            $stmt = $pdo->prepare("DELETE FROM `user` WHERE user_id = ?");
            $stmt->execute([$delete_id]);

            $pdo->commit();
            $message = "User ID #{$delete_id} and all related activity logs deleted successfully.";
            $messageType = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Failed to delete user: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch Metrics
$total_users = $pdo->query("SELECT COUNT(*) FROM `user`")->fetchColumn();

// Count registered users this month
$new_this_month = $pdo->query("
    SELECT COUNT(*) FROM `user` 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetchColumn();

// Fetch All Users mapped with their actual activity counts from `activity` table
$query = "SELECT u.*, 
            COALESCE((SELECT COUNT(*) FROM activity a WHERE a.user_id = u.user_id), 0) AS total_pickups,
            COALESCE((SELECT COUNT(*) FROM activity a WHERE a.user_id = u.user_id AND a.status = 'Completed'), 0) AS completed_pickups,
            COALESCE((SELECT COUNT(*) FROM activity a WHERE a.user_id = u.user_id AND a.status = 'In Progress'), 0) AS in_progress_pickups
          FROM `user` u ORDER BY u.user_id DESC";
$users = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | EcoScrap Admin</title>
    
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --eco-primary: #10b981;
            --eco-primary-dark: #059669;
            --eco-dark: #0f172a;
            --eco-light-bg: #f8fafc;
            --eco-card-bg: #ffffff;
            --eco-border-radius: 18px;
            --eco-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
            --eco-hover-shadow: 0 20px 35px -5px rgba(16, 185, 129, 0.12);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--eco-light-bg);
            color: #334155;
            padding-bottom: 3rem;
        }

        .stat-card {
            background: var(--eco-card-bg);
            border: 1px solid #e2e8f0;
            border-radius: var(--eco-border-radius);
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--eco-shadow);
        }

        .stat-card .icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .user-card {
            background: var(--eco-card-bg);
            border: 1px solid #e2e8f0;
            border-radius: var(--eco-border-radius);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .user-card:hover {
            transform: translateY(-5px);
            border-color: #cbd5e1;
            box-shadow: var(--eco-hover-shadow);
        }

        .avatar-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #047857);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
            object-fit: cover;
            border: 2px solid #ecfdf5;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
        }

        .info-pill {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.825rem;
        }

        .modal-content {
            border-radius: 24px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .btn-eco-primary {
            background-color: var(--eco-primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-eco-primary:hover {
            background-color: var(--eco-primary-dark);
            color: white;
        }

        .btn-eco-soft {
            background-color: #f0fdf4;
            color: var(--eco-primary-dark);
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-eco-soft:hover {
            background-color: #dcfce7;
            color: #047857;
        }

        .search-input-group {
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 2px 8px;
            transition: all 0.2s;
        }

        .search-input-group:focus-within {
            border-color: var(--eco-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .empty-state-icon {
            width: 90px;
            height: 90px;
            background: #f0fdf4;
            color: var(--eco-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
        }
    </style>
</head>
<body>

<div class="container-fluid px-4 py-4">

    <!-- Flash Alert -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show rounded-4 mb-4 shadow-sm" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h2 class="fw-bold mb-0 text-dark">Manage Users</h2>
                <span class="badge rounded-pill px-3 py-2 fs-6" style="background:#d1fae5; color:#065f46;">
                    <?= count($users) ?> Total Accounts
                </span>
            </div>
            <p class="text-muted mb-0 mt-1">View, manage, and monitor all registered EcoScrap accounts.</p>
        </div>

        <!-- Controls Toolbar -->
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="search-input-group d-flex align-items-center">
                <i class="bi bi-search text-muted ms-2"></i>
                <input type="text" id="searchInput" class="form-control border-0 shadow-none" placeholder="Search name, email, district, place..." style="width: 260px;">
            </div>

            <select id="districtFilter" class="form-select border-slate-300 rounded-3" style="width: auto;">
                <option value="all">All Districts</option>
                <?php 
                $districts = array_unique(array_column($users, 'district'));
                foreach ($districts as $dist):
                    if ($dist):
                ?>
                    <option value="<?= strtolower(htmlspecialchars($dist)) ?>"><?= htmlspecialchars($dist) ?></option>
                <?php 
                    endif;
                endforeach; 
                ?>
            </select>

            <select id="sortFilter" class="form-select border-slate-300 rounded-3" style="width: auto;">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
            </select>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fw-medium fs-7">Total Registered</span>
                        <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($total_users) ?></h3>
                    </div>
                    <div class="icon-wrapper" style="background: #ecfdf5; color: #059669;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fw-medium fs-7">Joined This Month</span>
                        <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($new_this_month) ?></h3>
                    </div>
                    <div class="icon-wrapper" style="background: #e0f2fe; color: #0284c7;">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fw-medium fs-7">Active Activity Users</span>
                        <h3 class="fw-bold text-dark mt-1 mb-0">
                            <?= count(array_filter($users, fn($u) => $u['total_pickups'] > 0)) ?>
                        </h3>
                    </div>
                    <div class="icon-wrapper" style="background: #fef3c7; color: #d97706;">
                        <i class="bi bi-recycle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fw-medium fs-7">Districts Covered</span>
                        <h3 class="fw-bold text-dark mt-1 mb-0"><?= count($districts) ?></h3>
                    </div>
                    <div class="icon-wrapper" style="background: #f3e8ff; color: #7e22ce;">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Cards Container -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="userCardsGrid">
        <?php foreach ($users as $user): 
            $name_parts = explode(' ', trim($user['name']));
            $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
        ?>
        <div class="col user-card-item" 
             data-id="<?= $user['user_id'] ?>"
             data-name="<?= strtolower(htmlspecialchars($user['name'])) ?>"
             data-email="<?= strtolower(htmlspecialchars($user['email'])) ?>"
             data-phone="<?= htmlspecialchars($user['phone']) ?>"
             data-district="<?= strtolower(htmlspecialchars($user['district'])) ?>"
             data-place="<?= strtolower(htmlspecialchars($user['place'])) ?>"
             data-date="<?= strtotime($user['created_at']) ?>">
            
            <div class="user-card p-4">
                <!-- Card Header -->
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" class="avatar-circle" alt="Profile" onerror="this.outerHTML='<div class=\'avatar-circle\'><?= $initials ?></div>'">
                        <?php else: ?>
                            <div class="avatar-circle"><?= $initials ?></div>
                        <?php endif; ?>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($user['name']) ?></h6>
                            <small class="text-muted">User ID: #<?= sprintf('%04d', $user['user_id']) ?></small>
                        </div>
                    </div>
                </div>

                <!-- Contact & Location Info -->
                <div class="vstack gap-2 mb-3">
                    <div class="d-flex align-items-center text-muted fs-7">
                        <i class="bi bi-envelope me-2 text-success"></i>
                        <span class="text-truncate"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div class="d-flex align-items-center text-muted fs-7">
                        <i class="bi bi-telephone me-2 text-success"></i>
                        <span><?= htmlspecialchars($user['phone']) ?></span>
                    </div>
                    <div class="d-flex align-items-center text-muted fs-7">
                        <i class="bi bi-geo-alt me-2 text-success"></i>
                        <span class="text-truncate">
                            <?= htmlspecialchars($user['address']) ?>, <?= htmlspecialchars($user['place']) ?>, <?= htmlspecialchars($user['district']) ?> - <?= htmlspecialchars($user['pincode']) ?>
                        </span>
                    </div>
                </div>

                <!-- Stats Summary Pill -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="info-pill text-center">
                            <span class="d-block text-muted fs-8">Total Pickups</span>
                            <span class="fw-bold text-dark"><?= $user['total_pickups'] ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="info-pill text-center">
                            <span class="d-block text-muted fs-8">Completed</span>
                            <span class="fw-bold text-success"><?= $user['completed_pickups'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Footer Timestamp & Actions -->
                <div class="mt-auto pt-3 border-top d-flex align-items-center justify-content-between">
                    <small class="text-muted" style="font-size: 0.75rem;">
                        <i class="bi bi-calendar3 me-1"></i> Joined <?= date('M d, Y', strtotime($user['created_at'])) ?>
                    </small>
                    
                    <div class="btn-group gap-1">
                        <button type="button" 
                                class="btn btn-sm btn-eco-soft view-btn" 
                                data-bs-toggle="modal" 
                                data-bs-target="#viewDetailsModal"
                                data-user='<?= json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                            <i class="bi bi-eye"></i> View
                        </button>

                        <button type="button" 
                                class="btn btn-sm btn-outline-danger border-0 delete-btn" 
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteConfirmModal"
                                data-userid="<?= $user['user_id'] ?>"
                                data-username="<?= htmlspecialchars($user['name']) ?>"
                                data-useremail="<?= htmlspecialchars($user['email']) ?>">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="text-center py-5 d-none">
        <div class="empty-state-icon">
            <i class="bi bi-search"></i>
        </div>
        <h4 class="fw-bold text-dark mb-2">No users found</h4>
        <p class="text-muted mb-4">We couldn't find any accounts matching your search or location filter.</p>
        <button class="btn btn-eco-primary px-4 py-2" onclick="resetFilters()">
            <i class="bi bi-arrow-counterclockwise me-2"></i> Reset Filters
        </button>
    </div>

</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">User Details Overview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div id="modalAvatar" class="avatar-circle fs-4" style="width:64px; height:64px;"></div>
                    <div>
                        <h5 id="modalUserName" class="fw-bold mb-0"></h5>
                        <small id="modalUserID" class="text-muted d-block"></small>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted fs-8 d-block">Email Address</label>
                        <span id="modalUserEmail" class="fw-semibold text-dark"></span>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted fs-8 d-block">Phone Number</label>
                        <span id="modalUserPhone" class="fw-semibold text-dark"></span>
                    </div>
                    <div class="col-12">
                        <label class="text-muted fs-8 d-block">Full Address</label>
                        <span id="modalUserAddress" class="fw-semibold text-dark"></span>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted fs-8 d-block">Place</label>
                        <span id="modalUserPlace" class="fw-semibold text-dark"></span>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted fs-8 d-block">District</label>
                        <span id="modalUserDistrict" class="fw-semibold text-dark"></span>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted fs-8 d-block">Pincode</label>
                        <span id="modalUserPincode" class="fw-semibold text-dark"></span>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold mb-3">Scrap Activity Overview</h6>
                <div class="row g-3">
                    <div class="col-4 text-center p-3 border rounded-3 bg-light">
                        <span class="d-block text-muted fs-8">Total Pickups</span>
                        <h4 id="modalTotalPickups" class="fw-bold mb-0">0</h4>
                    </div>
                    <div class="col-4 text-center p-3 border rounded-3 bg-light">
                        <span class="d-block text-muted fs-8">Completed</span>
                        <h4 id="modalCompletedPickups" class="fw-bold text-success mb-0">0</h4>
                    </div>
                    <div class="col-4 text-center p-3 border rounded-3 bg-light">
                        <span class="d-block text-muted fs-8">In Progress</span>
                        <h4 id="modalInProgressPickups" class="fw-bold text-warning mb-0">0</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4">
            <div class="text-danger mb-3">
                <i class="bi bi-exclamation-triangle-fill display-3"></i>
            </div>
            <h4 class="fw-bold text-dark">Confirm Deletion</h4>
            <p class="text-muted mb-3">
                Are you sure you want to delete user <strong id="deleteTargetName"></strong>?
                This will remove their profile and associated pickup records from the database.
            </p>

            <div class="p-3 bg-light rounded-3 text-start mb-4 fs-7">
                <div><strong>User ID:</strong> #<span id="deleteTargetId"></span></div>
                <div><strong>Email:</strong> <span id="deleteTargetEmail"></span></div>
            </div>

            <form method="POST" action="manage_users.php">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="deleteTargetInput">

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 rounded-3">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const districtFilter = document.getElementById('districtFilter');
    const sortFilter = document.getElementById('sortFilter');
    const userGrid = document.getElementById('userCardsGrid');
    const emptyState = document.getElementById('emptyState');
    const cardItems = Array.from(document.querySelectorAll('.user-card-item'));

    function filterAndSortCards() {
        const query = searchInput.value.toLowerCase().trim();
        const distVal = districtFilter.value;
        const sortVal = sortFilter.value;

        let visibleCount = 0;

        cardItems.forEach(card => {
            const name = card.dataset.name;
            const email = card.dataset.email;
            const phone = card.dataset.phone;
            const district = card.dataset.district;
            const place = card.dataset.place;
            const id = card.dataset.id;

            const matchesSearch = name.includes(query) || 
                                  email.includes(query) || 
                                  phone.includes(query) || 
                                  district.includes(query) || 
                                  place.includes(query) || 
                                  id.includes(query);

            const matchesDistrict = (distVal === 'all') || (district === distVal);

            if (matchesSearch && matchesDistrict) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Sorting
        const visibleCards = cardItems.filter(c => c.style.display !== 'none');
        visibleCards.sort((a, b) => {
            const dateA = parseInt(a.dataset.date);
            const dateB = parseInt(b.dataset.date);
            return sortVal === 'newest' ? dateB - dateA : dateA - dateB;
        });

        visibleCards.forEach(card => userGrid.appendChild(card));
        emptyState.classList.toggle('d-none', visibleCount > 0);
    }

    searchInput.addEventListener('input', filterAndSortCards);
    districtFilter.addEventListener('change', filterAndSortCards);
    sortFilter.addEventListener('change', filterAndSortCards);

    window.resetFilters = function() {
        searchInput.value = '';
        districtFilter.value = 'all';
        sortFilter.value = 'newest';
        filterAndSortCards();
    };

    // View Details Modal
    const viewModal = document.getElementById('viewDetailsModal');
    viewModal.addEventListener('show.bs.modal', (e) => {
        const button = e.relatedTarget;
        const userData = JSON.parse(button.dataset.user);

        document.getElementById('modalUserName').textContent = userData.name;
        document.getElementById('modalUserID').textContent = `User ID: #${String(userData.user_id).padStart(4, '0')}`;
        document.getElementById('modalUserEmail').textContent = userData.email;
        document.getElementById('modalUserPhone').textContent = userData.phone;
        document.getElementById('modalUserAddress').textContent = userData.address;
        document.getElementById('modalUserPlace').textContent = userData.place;
        document.getElementById('modalUserDistrict').textContent = userData.district;
        document.getElementById('modalUserPincode').textContent = userData.pincode;
        document.getElementById('modalTotalPickups').textContent = userData.total_pickups;
        document.getElementById('modalCompletedPickups').textContent = userData.completed_pickups;
        document.getElementById('modalInProgressPickups').textContent = userData.in_progress_pickups;

        const initials = userData.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        document.getElementById('modalAvatar').textContent = initials;
    });

    // Delete Modal
    const deleteModal = document.getElementById('deleteConfirmModal');
    deleteModal.addEventListener('show.bs.modal', (e) => {
        const button = e.relatedTarget;
        document.getElementById('deleteTargetId').textContent = button.dataset.userid;
        document.getElementById('deleteTargetName').textContent = button.dataset.username;
        document.getElementById('deleteTargetEmail').textContent = button.dataset.useremail;
        document.getElementById('deleteTargetInput').value = button.dataset.userid;
    });
});
</script>

</body>
</html>