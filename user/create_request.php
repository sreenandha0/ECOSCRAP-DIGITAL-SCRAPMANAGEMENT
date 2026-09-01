<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'User') {
    header("Location: ../login.php");
    exit();
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$today = date("Y-m-d");
$user_id = (int)($_SESSION['user_id'] ?? 0);

$user = [
    'name' => '',
    'address' => '',
    'pincode' => ''
];

if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT name, address, pincode FROM `user` WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $user['name'] = $row['name'] ?? '';
            $user['address'] = $row['address'] ?? '';
            $user['pincode'] = $row['pincode'] ?? '';
        }
        $stmt->close();
    }
}

$flash = null;
if (function_exists('getMessage')) {
    $flash = getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoScrap | Create Pickup Request</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --eco-light: #82c843;
            --eco-primary: #2e7d32;
            --eco-primary-dark: #236128;
            --eco-dark: #004d40;
            --eco-accent: #00b4d8;
            --body-bg: #f1f5f4;
            --text-main: #16342f;
            --text-muted: #64748b;
            --text-soft: #94a3b8;
            --border: #e6eeeb;
            --white: #ffffff;
            --shadow-sm: 0 8px 25px rgba(22, 52, 47, 0.06);
            --shadow-md: 0 18px 45px rgba(22, 52, 47, 0.10);
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;
            --spring: cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 90% 0%, rgba(130, 200, 67, 0.14), transparent 30%),
                var(--body-bg);
            color: var(--text-main);
            font-family: "DM Sans", sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        .user-page-shell {
            min-height: 100vh;
            padding: 34px 5% 52px;
        }

        .page-top {
            max-width: 1520px;
            margin: 0 auto 22px;
        }

        .breadcrumb {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 12px;
        }

        .breadcrumb a {
            color: var(--eco-primary);
            font-weight: 700;
        }

        .page-title {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: clamp(28px, 3vw, 38px);
            color: var(--eco-dark);
            letter-spacing: -0.7px;
            margin-bottom: 8px;
        }

        .page-desc {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.65;
            max-width: 780px;
        }

        .user-page-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 1520px;
            margin: 18px auto 0;
            padding: 13px 15px;
            border: 1px solid #bde5c0;
            border-radius: var(--radius-sm);
            background: #effaf0;
            color: #256029;
            font-size: 13px;
            font-weight: 600;
        }

        .impact-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            min-height: 150px;
            margin: 24px auto 25px;
            padding: 27px 31px;
            border-radius: var(--radius-lg);
            background: linear-gradient(120deg, rgba(0, 77, 64, 0.97), rgba(46, 125, 50, 0.95));
            color: white;
            box-shadow: var(--shadow-md);
            max-width: 1520px;
        }

        .impact-card::before {
            position: absolute;
            top: -75px;
            right: 13%;
            width: 210px;
            height: 210px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            content: "";
        }

        .impact-card::after {
            position: absolute;
            top: -35px;
            right: 5%;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            content: "";
        }

        .impact-info {
            position: relative;
            z-index: 2;
            max-width: 700px;
        }

        .impact-info .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 10px;
            color: var(--eco-light);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .impact-info h2 {
            margin-bottom: 7px;
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 20px;
        }

        .impact-info p {
            max-width: 560px;
            color: rgba(255,255,255,0.72);
            font-size: 12px;
            line-height: 1.6;
        }

        .impact-progress-wrap {
            position: relative;
            z-index: 2;
            width: 260px;
            flex: 0 0 260px;
        }

        .impact-progress-head {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: rgba(255,255,255,0.75);
            font-size: 11px;
            font-weight: 600;
        }

        .impact-progress-head strong {
            color: white;
        }

        .progress-bar {
            height: 9px;
            overflow: hidden;
            border-radius: 9px;
            background: rgba(255,255,255,0.18);
        }

        .progress-bar span {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: inherit;
            background: var(--eco-light);
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(290px, 0.7fr);
            gap: 20px;
            max-width: 1520px;
            margin: 0 auto;
        }

        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .form-card {
            padding: 26px;
        }

        .card-title {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 19px;
            color: var(--eco-dark);
            margin-bottom: 8px;
        }

        .card-desc {
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 22px;
        }

        .section {
            padding-top: 18px;
            margin-top: 18px;
            border-top: 1px solid var(--border);
        }

        .section:first-of-type {
            padding-top: 0;
            margin-top: 0;
            border-top: 0;
        }

        .section h3 {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 16px;
            color: var(--eco-dark);
            margin-bottom: 6px;
        }

        .section-note {
            color: var(--text-muted);
            font-size: 12px;
            margin-bottom: 16px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            color: var(--text-main);
            font-size: 13px;
            font-weight: 700;
        }

        .field-wrap {
            position: relative;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 13px 14px;
            background: #fff;
            color: var(--text-main);
            transition: 0.25s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--eco-light);
            box-shadow: 0 0 0 4px rgba(130, 200, 67, 0.12);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .with-unit {
            padding-right: 54px;
        }

        .unit {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-soft);
            font-size: 12px;
            pointer-events: none;
        }

        .helper-text {
            color: var(--text-soft);
            font-size: 12px;
            line-height: 1.5;
        }

        .input-error {
            color: #c62828;
            font-size: 12px;
        }

        .upload-box {
            padding: 16px;
            border-radius: 14px;
            border: 1px dashed var(--border);
            background: #fafdfb;
            transition: 0.25s ease;
        }

        .upload-box.dragover {
            border-color: var(--eco-light);
            background: #f4fbef;
        }

        .upload-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .upload-preview {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            object-fit: cover;
            background: #fff;
            border: 1px solid var(--border);
        }

        .upload-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .mini-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 12px;
            padding: 9px 12px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text-main);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .mini-btn:hover {
            border-color: var(--eco-light);
            color: var(--eco-primary);
        }

        .side-card {
            padding: 24px;
        }

        .tip-list {
            display: grid;
            gap: 12px;
        }

        .tip-item {
            padding: 15px;
            border-radius: 14px;
            background: #f8fbfa;
            border: 1px solid #edf2ef;
        }

        .tip-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
        }

        .tip-item span {
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 22px;
        }

        .btn-primary-green,
        .btn-outline-green {
            border-radius: 14px;
            padding: 13px 18px;
            font-weight: 800;
            font-size: 13px;
            transition: 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary-green {
            background: var(--eco-primary);
            color: white;
            box-shadow: 0 10px 20px rgba(46, 125, 50, 0.18);
        }

        .btn-primary-green:hover {
            background: var(--eco-primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline-green {
            border: 1px solid var(--border);
            background: white;
            color: var(--text-main);
        }

        .btn-outline-green:hover {
            border-color: var(--eco-light);
            color: var(--eco-primary);
        }

        .toast {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 1000;
            min-width: 280px;
            max-width: 90vw;
            padding: 14px 16px;
            border-radius: 14px;
            background: #e8f7e9;
            color: #256029;
            box-shadow: var(--shadow-md);
            display: none;
        }

        .toast.show {
            display: block;
        }

        @media (max-width: 980px) {
            .user-page-shell {
                padding: 24px 18px 36px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .impact-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .impact-progress-wrap {
                width: 100%;
                flex: 1 1 auto;
            }
        }

        @media (max-width: 560px) {
            .impact-card {
                padding: 22px 18px;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn-primary-green,
            .actions .btn-outline-green {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="user-page-shell">
    <div class="page-top">
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="ri-arrow-left-line"></i> Back</a>
            <span>/</span>
            <span>Create Pickup Request</span>
        </div>

        <h1 class="page-title">Create Pickup Request</h1>
        <p class="page-desc">Schedule a scrap pickup by entering your scrap details, location, and preferred pickup time. The EcoScrap team will process your request after submission.</p>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="user-page-alert">
            <i class="ri-information-line"></i>
            <span><?= e($flash) ?></span>
        </div>
    <?php endif; ?>

    <section class="impact-card">
        <div class="impact-info">
            <p class="eyebrow"><i class="ri-recycle-line"></i> Ready to recycle?</p>
            <h2>Schedule a convenient scrap pickup</h2>
            <p>Help keep recyclable materials out of landfills by submitting a pickup request in just a few steps.</p>
        </div>

        <div class="impact-progress-wrap">
            <div class="impact-progress-head">
                <strong>Request</strong>
                <span>Ready</span>
            </div>
            <div class="progress-bar"><span></span></div>
        </div>
    </section>

    <div class="content-grid">
        <div class="card form-card">
            <h2 class="card-title">Pickup Request Form</h2>
            <p class="card-desc">Please fill in all required fields carefully. You can optionally upload a scrap image for reference.</p>

            <form action="create_request_process.php" method="POST" enctype="multipart/form-data" id="requestForm" novalidate>
                <div class="section">
                    <h3>Scrap Details</h3>
                    <p class="section-note">Tell us what you want us to collect.</p>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="scrap_type">Scrap Type</label>
                            <select id="scrap_type" name="scrap_type" required>
                                <option value="">Select scrap type</option>
                                <option value="Plastic">Plastic</option>
                                <option value="Paper">Paper</option>
                                <option value="Metal">Metal</option>
                                <option value="Glass">Glass</option>
                                <option value="E-Waste">E-Waste</option>
                                <option value="Other">Other</option>
                            </select>
                            <span class="input-error" data-error-for="scrap_type"></span>
                        </div>

                        <div class="form-group">
                            <label for="scrap_weight">Scrap Weight</label>
                            <div class="field-wrap">
                                <input type="number" id="scrap_weight" name="scrap_weight" class="with-unit" step="0.1" min="0.1" placeholder="e.g. 5.5" required>
                                <span class="unit">kg</span>
                            </div>
                            <span class="input-error" data-error-for="scrap_weight"></span>
                        </div>

                        <div class="form-group full">
                            <label>Scrap Image</label>
                            <div class="upload-box" id="uploadBox">
                                <div class="helper-text">Upload a JPG, JPEG, or PNG image up to 5MB.</div>

                                <div class="upload-row">
                                    <img id="imagePreview" class="upload-preview" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3E%3Crect width='120' height='120' rx='16' fill='%23ecfdf5'/%3E%3Cpath d='M35 80l16-18 12 13 10-12 12 17H35z' fill='%232e7d32'/%3E%3Ccircle cx='45' cy='45' r='8' fill='%2382c843'/%3E%3C/svg%3E" alt="Upload preview">
                                    <div style="flex:1; min-width:220px;">
                                        <input type="file" id="scrap_image" name="scrap_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                        <div class="helper-text" style="margin-top:8px;">Choose an image if you want to show the scrap condition before pickup.</div>
                                        <div class="upload-actions">
                                            <label class="mini-btn" for="scrap_image"><i class="ri-upload-2-line"></i> Choose file</label>
                                            <button type="button" class="mini-btn" id="removeImageBtn"><i class="ri-close-line"></i> Remove</button>
                                        </div>
                                    </div>
                                </div>

                                <span class="input-error" data-error-for="scrap_image"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h3>Pickup Location</h3>
                    <p class="section-note">Where should we collect the scrap from?</p>

                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="pickup_address">Pickup Address</label>
                            <textarea id="pickup_address" name="pickup_address" placeholder="Enter the full pickup address" required><?= e($_POST['pickup_address'] ?? $user['address']) ?></textarea>
                            <span class="input-error" data-error-for="pickup_address"></span>
                        </div>

                        <div class="form-group">
                            <label for="pickup_pincode">Pincode</label>
                            <input type="text" id="pickup_pincode" name="pickup_pincode" maxlength="6" inputmode="numeric" placeholder="6-digit pincode" value="<?= e($_POST['pickup_pincode'] ?? $user['pincode']) ?>" required>
                            <span class="input-error" data-error-for="pickup_pincode"></span>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h3>Pickup Schedule</h3>
                    <p class="section-note">Choose a preferred date and time for pickup.</p>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="preferred_pickup_date">Preferred Pickup Date</label>
                            <input type="date" id="preferred_pickup_date" name="preferred_pickup_date" min="<?= e($today) ?>" required>
                            <span class="input-error" data-error-for="preferred_pickup_date"></span>
                        </div>

                        <div class="form-group">
                            <label for="pickup_time">Preferred Pickup Time</label>
                            <input type="time" id="pickup_time" name="pickup_time" required>
                            <span class="input-error" data-error-for="pickup_time"></span>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h3>Additional Information</h3>
                    <p class="section-note">Add any instructions that may help the pickup team.</p>

                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" placeholder="Optional instructions, landmark details, or notes"><?= e($_POST['remarks'] ?? '') ?></textarea>
                            <span class="input-error" data-error-for="remarks"></span>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <a class="btn-outline-green" href="dashboard.php">Cancel</a>
                    <button class="btn-primary-green" type="submit">
                        <i class="ri-send-plane-2-line"></i>
                        Submit Pickup Request
                    </button>
                </div>
            </form>
        </div>

        <div class="card side-card">
            <h2 class="card-title">Helpful Notes</h2>
            <p class="card-desc">A few quick reminders before you submit.</p>

            <div class="tip-list">
                <div class="tip-item">
                    <strong><i class="ri-checkbox-circle-line" style="color:var(--eco-primary); margin-right:6px;"></i> Fill all required fields</strong>
                    <span>Scrap type, weight, address, pincode, date, and time are required.</span>
                </div>
                <div class="tip-item">
                    <strong><i class="ri-time-line" style="color:var(--eco-primary); margin-right:6px;"></i> Choose a future date</strong>
                    <span>Your pickup date cannot be earlier than today.</span>
                </div>
                <div class="tip-item">
                    <strong><i class="ri-image-line" style="color:var(--eco-primary); margin-right:6px;"></i> Optional scrap image</strong>
                    <span>JPG, JPEG, or PNG up to 5MB for easier identification.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const form = document.getElementById('requestForm');
const imageInput = document.getElementById('scrap_image');
const imagePreview = document.getElementById('imagePreview');
const uploadBox = document.getElementById('uploadBox');
const removeBtn = document.getElementById('removeImageBtn');
const toast = document.getElementById('toast');

const today = new Date().toISOString().split('T')[0];
document.getElementById('preferred_pickup_date').min = today;

function showToast(message, type = 'success') {
    toast.textContent = message;
    toast.style.background = type === 'success' ? '#e8f7e9' : '#fff4f4';
    toast.style.color = type === 'success' ? '#256029' : '#a94442';
    toast.style.border = type === 'success' ? '1px solid #bde5c0' : '1px solid #f3c5c5';
    toast.classList.add('show');
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}

function setError(field, message) {
    const el = document.querySelector(`[data-error-for="${field}"]`);
    if (el) el.textContent = message || '';
}

function clearErrors() {
    document.querySelectorAll('.input-error').forEach(el => el.textContent = '');
}

imageInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png'];
    if (!validTypes.includes(file.type)) {
        setError('scrap_image', 'Only JPG, JPEG, and PNG images are allowed.');
        this.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        setError('scrap_image', 'Image size must be below 5MB.');
        this.value = '';
        return;
    }

    setError('scrap_image', '');

    const reader = new FileReader();
    reader.onload = function (event) {
        imagePreview.src = event.target.result;
    };
    reader.readAsDataURL(file);
});

removeBtn.addEventListener('click', function () {
    imageInput.value = '';
    imagePreview.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3E%3Crect width='120' height='120' rx='16' fill='%23ecfdf5'/%3E%3Cpath d='M35 80l16-18 12 13 10-12 12 17H35z' fill='%232e7d32'/%3E%3Ccircle cx='45' cy='45' r='8' fill='%2382c843'/%3E%3C/svg%3E";
    setError('scrap_image', '');
});

document.getElementById('pickup_pincode').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
});

uploadBox.addEventListener('dragover', function (e) {
    e.preventDefault();
    uploadBox.classList.add('dragover');
});
uploadBox.addEventListener('dragleave', function () {
    uploadBox.classList.remove('dragover');
});
uploadBox.addEventListener('drop', function (e) {
    e.preventDefault();
    uploadBox.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        imageInput.files = e.dataTransfer.files;
        imageInput.dispatchEvent(new Event('change'));
    }
});

form.addEventListener('submit', function (e) {
    clearErrors();

    const scrapType = document.getElementById('scrap_type').value.trim();
    const scrapWeight = parseFloat(document.getElementById('scrap_weight').value);
    const address = document.getElementById('pickup_address').value.trim();
    const pincode = document.getElementById('pickup_pincode').value.trim();
    const date = document.getElementById('preferred_pickup_date').value;
    const time = document.getElementById('pickup_time').value;
    const file = imageInput.files[0];

    let hasError = false;
    const todayStr = new Date().toISOString().split('T')[0];

    if (!scrapType) { setError('scrap_type', 'Please select a scrap type.'); hasError = true; }
    if (!scrapWeight || scrapWeight <= 0) { setError('scrap_weight', 'Enter a positive scrap weight.'); hasError = true; }
    if (!address) { setError('pickup_address', 'Pickup address is required.'); hasError = true; }
    if (!/^[0-9]{6}$/.test(pincode)) { setError('pickup_pincode', 'Pincode must be 6 digits.'); hasError = true; }
    if (!date) { setError('preferred_pickup_date', 'Pickup date is required.'); hasError = true; }
    else if (date < todayStr) { setError('preferred_pickup_date', 'Pickup date cannot be in the past.'); hasError = true; }
    if (!time) { setError('pickup_time', 'Pickup time is required.'); hasError = true; }

    if (file) {
        const validTypes = ['image/jpeg', 'image/png'];
        if (!validTypes.includes(file.type)) {
            setError('scrap_image', 'Only JPG, JPEG, and PNG images are allowed.');
            hasError = true;
        } else if (file.size > 5 * 1024 * 1024) {
            setError('scrap_image', 'Image size must be below 5MB.');
            hasError = true;
        }
    }

    if (hasError) {
        e.preventDefault();
        showToast('Please correct the highlighted fields.', 'error');
    }
});
</script>
</body>
</html>