<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != "Admin") {
    header("Location: ../login.php");
    exit();
}

/*
====================================
TOGGLE USER STATUS (Disable/Enable)
====================================
*/
if (isset($_GET['toggle_status']) && isset($_GET['current_status'])) {
    $user_id = (int)$_GET['toggle_status'];
    $new_status = ($_GET['current_status'] === 'Disabled') ? 'Active' : 'Disabled';

    // Update status if your user table supports it, or handle session message
    $stmt = $conn->prepare("UPDATE user SET status=? WHERE user_id=?");
    if ($stmt) {
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "User status updated to " . $new_status . ".";
        } else {
            $_SESSION['error'] = "Unable to update user status.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Status column missing or query failed.";
    }

    header("Location: manageuser.php");
    exit();
}

/*
====================================
FETCH USERS WITH ACTIVITY COUNT
====================================
*/
$query = "
    SELECT 
        u.*, 
        COUNT(a.activity_id) AS total_pickups 
    FROM user u 
    LEFT JOIN activity a ON u.user_id = a.user_id 
    GROUP BY u.user_id 
    ORDER BY u.created_at DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | EcoScrap Admin</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- Export Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <style>
        :root {
            --primary: #10B981;
            --secondary: #047857;
            --accent: #0EA5E9;
            --bg-color: #F8FAFC;
            --surface: rgba(255, 255, 255, 0.9);
            --surface-border: rgba(15, 23, 42, 0.08);
            --text-main: #0F172A;
            --text-muted: #64748B;
            --font-main: 'Inter', sans-serif;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            padding: 40px 24px;
            -webkit-font-smoothing: antialiased;
        }

        .ambient-glow {
            position: fixed;
            top: -100px;
            right: -100px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .workspace-container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .glass-card {
            background: var(--surface);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.06);
            transition: var(--transition);
            padding: 24px;
        }

        /* Page Top Controls */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .header-title p {
            font-size: 14px;
            color: var(--text-muted);
        }

        .controls-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            width: 100%;
            margin-bottom: 20px;
            justify-content: space-between;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 260px;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 18px;
        }

        .search-input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--surface-border);
            border-radius: 12px;
            background: #FFFFFF;
            font-family: var(--font-main);
            font-size: 14px;
            outline: none;
            transition: var(--transition);
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .export-group {
            display: flex;
            gap: 8px;
        }

        .btn-export {
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--surface-border);
            background: #FFFFFF;
            color: var(--text-main);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .btn-export:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* System Flash Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .alert-success { background: #DCFCE7; color: #166534; }
        .alert-danger { background: #FEE2E2; color: #991B1B; }

        /* Data Table */
        .table-responsive {
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table th {
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--surface-border);
            text-align: left;
        }

        .custom-table td {
            padding: 16px;
            font-size: 14px;
            border-bottom: 1px solid var(--surface-border);
            vertical-align: middle;
        }

        .user-profile-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active { background: #DCFCE7; color: #166534; }
        .badge-disabled { background: #FEE2E2; color: #991B1B; }
        .badge-count { background: #E0F2FE; color: #0369A1; font-weight: 700; }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid var(--surface-border);
            background: #FFFFFF;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-main);
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-icon:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-toggle-disable {
            color: #DC2626;
        }
        .btn-toggle-disable:hover {
            background: #FEE2E2;
            border-color: #EF4444;
            color: #DC2626;
        }

        .btn-toggle-enable {
            color: #10B981;
        }
        .btn-toggle-enable:hover {
            background: #DCFCE7;
            border-color: #10B981;
            color: #10B981;
        }

        /* Pagination Bar */
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-info {
            font-size: 13px;
            color: var(--text-muted);
        }

        .pagination-btns {
            display: flex;
            gap: 6px;
        }

        .btn-page {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--surface-border);
            background: #FFFFFF;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-page.active {
            background: var(--primary);
            color: #FFFFFF;
            border-color: var(--primary);
        }

        .btn-page:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal Viewer */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-box {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            border: 1px solid var(--surface-border);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            transform: scale(0.95);
            transition: var(--transition);
        }

        .modal-overlay.active .modal-box {
            transform: scale(1);
        }

        .modal-profile-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-profile-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 12px;
        }

        .modal-details-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 20px;
            margin-bottom: 24px;
        }

        .modal-detail-item {
            display: flex;
            flex-direction: column;
        }

        .modal-detail-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .modal-detail-val {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            word-break: break-word;
        }

        /* Responsive Mobile Layout */
        @media (max-width: 768px) {
            .custom-table, .custom-table thead, .custom-table tbody, .custom-table th, .custom-table td, .custom-table tr {
                display: block;
            }

            .custom-table thead {
                display: none;
            }

            .custom-table tr {
                margin-bottom: 16px;
                border: 1px solid var(--surface-border);
                border-radius: 16px;
                padding: 16px;
                background: #FFFFFF;
            }

            .custom-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px dashed var(--surface-border);
            }

            .custom-table td:last-child {
                border-bottom: none;
                padding-top: 12px;
            }

            .custom-table td::before {
                content: attr(data-label);
                font-size: 12px;
                font-weight: 700;
                color: var(--text-muted);
                text-transform: uppercase;
            }
        }
    </style>
</head>

<body>

    <div class="ambient-glow"></div>

    <main class="workspace-container">

        <!-- Top Header -->
        <header class="page-header">
            <div class="header-title">
                <h1>Manage Users</h1>
                <p>View accounts, search registered details, and track scrap pickup requests</p>
            </div>
            <span class="badge badge-active" style="padding: 8px 16px; font-size: 14px;">
                Total Users: <strong id="totalCountDisplay" style="margin-left: 6px;"><?= $result ? $result->num_rows : 0; ?></strong>
            </span>
        </header>

        <!-- Flash Alerts -->
        <?php if (isset($_SESSION['msg'])) { ?>
            <div class="alert alert-success"><?= $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
        <?php } ?>
        <?php if (isset($_SESSION['error'])) { ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php } ?>

        <div class="glass-card">

            <!-- Controls (Search & Export) -->
            <div class="controls-bar">
                <div class="search-box">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by name, email, place, phone...">
                </div>

                <div class="export-group">
                    <button class="btn-export" onclick="exportToExcel()">
                        <i class="ph ph-file-xls" style="color: #10B981; font-size: 18px;"></i> Excel
                    </button>
                    <button class="btn-export" onclick="exportToPDF()">
                        <i class="ph ph-file-pdf" style="color: #EF4444; font-size: 18px;"></i> PDF
                    </button>
                </div>
            </div>

            <!-- Table View -->
            <div class="table-responsive">
                <table class="custom-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Pickups</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php 
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) { 
                                $user_status = isset($row['status']) ? $row['status'] : 'Active';
                                $profile_img = !empty($row['profile_image']) ? "../uploads/profile/" . $row['profile_image'] : "https://via.placeholder.com/80";
                        ?>
                                <tr class="user-row" 
                                    data-id="<?= $row['user_id']; ?>"
                                    data-name="<?= htmlspecialchars($row['name']); ?>"
                                    data-email="<?= htmlspecialchars($row['email']); ?>"
                                    data-phone="<?= htmlspecialchars($row['phone']); ?>"
                                    data-address="<?= htmlspecialchars($row['address'] ?? ''); ?>"
                                    data-place="<?= htmlspecialchars($row['place'] ?? ''); ?>"
                                    data-district="<?= htmlspecialchars($row['district'] ?? ''); ?>"
                                    data-state="<?= htmlspecialchars($row['state'] ?? ''); ?>"
                                    data-pincode="<?= htmlspecialchars($row['pincode']); ?>"
                                    data-registered="<?= date("d M Y", strtotime($row['created_at'])); ?>"
                                    data-pickups="<?= $row['total_pickups']; ?>"
                                    data-status="<?= $user_status; ?>"
                                    data-img="<?= $profile_img; ?>">
                                    
                                    <td data-label="User">
                                        <div class="user-profile-cell">
                                            <img src="<?= $profile_img; ?>" class="user-avatar" alt="Profile">
                                            <div>
                                                <strong style="display: block; font-weight: 600;"><?= htmlspecialchars($row['name']); ?></strong>
                                                <span style="font-size: 12px; color: var(--text-muted);">ID: #<?= $row['user_id']; ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <td data-label="Email"><?= htmlspecialchars($row['email']); ?></td>

                                    <td data-label="Phone"><?= htmlspecialchars($row['phone']); ?></td>

                                    <td data-label="Location">
                                        <?= htmlspecialchars($row['place'] ?? 'N/A'); ?>, <br>
                                        <span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($row['district'] ?? ''); ?></span>
                                    </td>

                                    <td data-label="Pickups">
                                        <span class="badge badge-count">
                                            <i class="ph ph-arrows-counter-clockwise" style="margin-right: 4px;"></i> <?= $row['total_pickups']; ?>
                                        </span>
                                    </td>

                                    <td data-label="Status">
                                        <?php if ($user_status === 'Disabled'): ?>
                                            <span class="badge badge-disabled">Disabled</span>
                                        <?php else: ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php endif; ?>
                                    </td>

                                    <td data-label="Registered"><?= date("d M Y", strtotime($row['created_at'])); ?></td>

                                    <td data-label="Actions">
                                        <div class="action-btns">
                                            <button class="btn-icon" title="View Profile Modal" onclick="openProfileModal(this)">
                                                <i class="ph ph-eye"></i>
                                            </button>

                                            <?php if ($user_status === 'Disabled'): ?>
                                                <a href="?toggle_status=<?= $row['user_id']; ?>&current_status=Disabled" 
                                                   class="btn-icon btn-toggle-enable" 
                                                   title="Enable Account"
                                                   onclick="return confirm('Re-enable access for this user?');">
                                                    <i class="ph ph-check-circle"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?toggle_status=<?= $row['user_id']; ?>&current_status=Active" 
                                                   class="btn-icon btn-toggle-disable" 
                                                   title="Disable Account"
                                                   onclick="return confirm('Disable this user\'s access?');">
                                                    <i class="ph ph-prohibit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                </tr>
                        <?php 
                            } 
                        } else {
                        ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                    No registered users found.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-bar">
                <div class="page-info" id="pageInfo">Showing 0 of 0 users</div>
                <div class="pagination-btns" id="paginationBtns"></div>
            </div>

        </div>

    </main>

    <!-- Modal View -->
    <div class="modal-overlay" id="profileModal">
        <div class="modal-box">
            <div class="modal-profile-header">
                <img id="modalImg" src="" alt="Profile Picture">
                <h3 id="modalName" style="font-size: 20px; font-weight: 700;"></h3>
                <span id="modalStatusBadge" class="badge"></span>
            </div>

            <div class="modal-details-list">
                <div class="modal-detail-item">
                    <span class="modal-detail-label">User ID</span>
                    <span class="modal-detail-val" id="modalId"></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Phone</span>
                    <span class="modal-detail-val" id="modalPhone"></span>
                </div>
                <div class="modal-detail-item" style="grid-column: span 2;">
                    <span class="modal-detail-label">Email</span>
                    <span class="modal-detail-val" id="modalEmail"></span>
                </div>
                <div class="modal-detail-item" style="grid-column: span 2;">
                    <span class="modal-detail-label">Address</span>
                    <span class="modal-detail-val" id="modalAddress"></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Place</span>
                    <span class="modal-detail-val" id="modalPlace"></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">District</span>
                    <span class="modal-detail-val" id="modalDistrict"></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Pincode</span>
                    <span class="modal-detail-val" id="modalPincode"></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Total Requests</span>
                    <span class="modal-detail-val" id="modalPickups"></span>
                </div>
                <div class="modal-detail-item" style="grid-column: span 2;">
                    <span class="modal-detail-label">Registered Date</span>
                    <span class="modal-detail-val" id="modalRegistered"></span>
                </div>
            </div>

            <div style="text-align: right;">
                <button class="btn-export" onclick="closeProfileModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Global Table Controls
        const rowsPerPage = 10;
        let currentPage = 1;
        let allRows = Array.from(document.querySelectorAll('.user-row'));
        let filteredRows = [...allRows];

        const searchInput = document.getElementById('searchInput');
        const pageInfo = document.getElementById('pageInfo');
        const paginationBtns = document.getElementById('paginationBtns');

        // Render Table Pagination View
        function renderTable() {
            const total = filteredRows.length;
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            allRows.forEach(row => row.style.display = 'none');

            const currentBatch = filteredRows.slice(start, end);
            currentBatch.forEach(row => row.style.display = '');

            pageInfo.innerText = total > 0 
                ? `Showing ${start + 1} to ${Math.min(end, total)} of ${total} users` 
                : 'No matching users found';

            renderPaginationControls(total);
        }

        // Render Page Numbers
        function renderPaginationControls(total) {
            paginationBtns.innerHTML = '';
            const pageCount = Math.ceil(total / rowsPerPage);

            if (pageCount <= 1) return;

            for (let i = 1; i <= pageCount; i++) {
                const btn = document.createElement('button');
                btn.className = `btn-page ${i === currentPage ? 'active' : ''}`;
                btn.innerText = i;
                btn.onclick = () => {
                    currentPage = i;
                    renderTable();
                };
                paginationBtns.appendChild(btn);
            }
        }

        // Live Search Filter
        searchInput.addEventListener('keyup', function () {
            const query = this.value.toLowerCase().trim();

            filteredRows = allRows.filter(row => {
                const name = row.dataset.name.toLowerCase();
                const email = row.dataset.email.toLowerCase();
                const phone = row.dataset.phone.toLowerCase();
                const place = row.dataset.place.toLowerCase();

                return name.includes(query) || email.includes(query) || phone.includes(query) || place.includes(query);
            });

            currentPage = 1;
            renderTable();
        });

        // Open Profile Details Modal
        function openProfileModal(btn) {
            const tr = btn.closest('tr');
            
            document.getElementById('modalImg').src = tr.dataset.img;
            document.getElementById('modalName').innerText = tr.dataset.name;
            document.getElementById('modalId').innerText = '#' + tr.dataset.id;
            document.getElementById('modalEmail').innerText = tr.dataset.email;
            document.getElementById('modalPhone').innerText = tr.dataset.phone;
            document.getElementById('modalAddress').innerText = tr.dataset.address || 'N/A';
            document.getElementById('modalPlace').innerText = tr.dataset.place || 'N/A';
            document.getElementById('modalDistrict').innerText = tr.dataset.district || 'N/A';
            document.getElementById('modalPincode').innerText = tr.dataset.pincode;
            document.getElementById('modalPickups').innerText = tr.dataset.pickups + ' Requests';
            document.getElementById('modalRegistered').innerText = tr.dataset.registered;

            const statusBadge = document.getElementById('modalStatusBadge');
            if(tr.dataset.status === 'Disabled') {
                statusBadge.className = 'badge badge-disabled';
                statusBadge.innerText = 'Disabled';
            } else {
                statusBadge.className = 'badge badge-active';
                statusBadge.innerText = 'Active';
            }

            document.getElementById('profileModal').classList.add('active');
        }

        function closeProfileModal() {
            document.getElementById('profileModal').classList.remove('active');
        }

        // Close Modal Overlay Click
        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeProfileModal();
            }
        };

        // Export to Excel
        function exportToExcel() {
            const data = filteredRows.map(row => ({
                "User ID": row.dataset.id,
                "Name": row.dataset.name,
                "Email": row.dataset.email,
                "Phone": row.dataset.phone,
                "Place": row.dataset.place,
                "District": row.dataset.district,
                "Pincode": row.dataset.pincode,
                "Total Pickups": row.dataset.pickups,
                "Status": row.dataset.status,
                "Registered": row.dataset.registered
            }));

            const worksheet = XLSX.utils.json_to_sheet(data);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Users");
            XLSX.writeFile(workbook, "Registered_Users_Report.xlsx");
        }

        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            doc.text("EcoScrap - Registered Users Report", 14, 15);

            const body = filteredRows.map(row => [
                row.dataset.id,
                row.dataset.name,
                row.dataset.email,
                row.dataset.phone,
                `${row.dataset.place}, ${row.dataset.district}`,
                row.dataset.pickups,
                row.dataset.status
            ]);

            doc.autoTable({
                head: [["ID", "Name", "Email", "Phone", "Location", "Requests", "Status"]],
                body: body,
                startY: 22,
                styles: { fontSize: 8 }
            });

            doc.save("Registered_Users_Report.pdf");
        }

        // Initialize Table
        renderTable();
    </script>
</body>

</html>