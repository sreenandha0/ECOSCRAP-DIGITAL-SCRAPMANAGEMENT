<?php

session_start();

require_once "../includes/db.php";


// =====================================================
// AUTHENTICATION
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}


$user_id = (int) $_SESSION['user_id'];


// =====================================================
// FETCH USER
// =====================================================

$stmt = $conn->prepare("
    SELECT *
    FROM `user`
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();


if (!$user) {
    session_destroy();

    header("Location: ../login.php");
    exit();
}


// =====================================================
// HELPER FUNCTIONS
// =====================================================

function escape_html($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function get_initials(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name);

    $first =
        substr($parts[0] ?? 'U', 0, 1);

    $second =
        substr($parts[1] ?? '', 0, 1);

    return strtoupper($first . $second);
}


$userName =
    trim((string) ($user['name'] ?? 'User'));

$userEmail =
    trim((string) ($user['email'] ?? ''));

$userAddress =
    trim((string) ($user['address'] ?? ''));

$userPincode =
    trim((string) ($user['pincode'] ?? ''));

$userInitials =
    get_initials($userName);

$currentDate =
    date('Y-m-d');

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Create a recyclable scrap pickup request with EcoScrap."
    >

    <title>
        Create Pickup Request | EcoScrap
    </title>


    <!-- Google Font -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- Remix Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >


    <!-- Existing CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <style>

        :root {
            --bg: #f4f8f4;
            --card: #ffffff;
            --card-soft: #fbfdfb;

            --border: #e0e9e1;
            --border-dark: #cedbd0;

            --green: #16a34a;
            --green-dark: #14532d;
            --green-soft: #e9f8ed;

            --navy: #12231a;
            --text: #17221b;
            --muted: #77847b;
            --soft: #9ba89f;

            --danger: #dc2626;

            --radius-xl: 22px;
            --radius-lg: 17px;
            --radius-md: 11px;

            --shadow:
                0 18px 45px rgba(20, 83, 45, .08);

            --shadow-hover:
                0 24px 55px rgba(20, 83, 45, .15);

            --transition:
                all .25s cubic-bezier(.4, 0, .2, 1);
        }


        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(
                    circle at 10% 0%,
                    rgba(187, 247, 208, .5),
                    transparent 27%
                ),
                radial-gradient(
                    circle at 95% 92%,
                    rgba(186, 230, 253, .32),
                    transparent 25%
                ),
                var(--bg);
            font-family:
                'Plus Jakarta Sans',
                system-ui,
                -apple-system,
                sans-serif;
        }


        body::before {
            position: fixed;
            z-index: -1;
            inset: 0;
            content: '';
            pointer-events: none;
            background:
                linear-gradient(
                    135deg,
                    rgba(255, 255, 255, .3),
                    transparent 55%
                );
        }


        a {
            text-decoration: none;
        }


        button,
        input,
        select,
        textarea {
            font: inherit;
        }


        /* =====================================================
           NAVBAR
        ===================================================== */

        .navbar {
            position: sticky;
            top: 15px;
            z-index: 100;
            width: min(1240px, calc(100% - 40px));
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin: 15px auto 0;
            padding: 11px 14px;
            background: rgba(255, 255, 255, .92);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }


        .brand {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-shrink: 0;
            color: var(--green-dark);
        }


        .brand:hover {
            color: var(--green-dark);
        }


        .brand-logo {
            width: 41px;
            height: 41px;
            padding: 3px;
            object-fit: contain;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 11px;
        }


        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }


        .brand-title {
            color: var(--green-dark);
            font-size: 16px;
            font-weight: 800;
        }


        .brand-subtitle {
            margin-top: 4px;
            color: var(--green);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .7px;
            text-transform: uppercase;
        }


        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: auto;
        }


        .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 12px;
            color: var(--muted);
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            transition: var(--transition);
        }


        .nav-link i {
            font-size: 17px;
        }


        .nav-link:hover {
            color: var(--green-dark);
            background: var(--green-soft);
        }


        .nav-link.active {
            color: var(--green-dark);
            background: var(--green-soft);
            box-shadow: inset 0 -2px 0 var(--green);
        }


        .nav-right {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-shrink: 0;
        }


        .user-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 9px 5px 5px;
            background: #f7fbf7;
            border: 1px solid var(--border);
            border-radius: 10px;
        }


        .user-avatar {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            color: #ffffff;
            background: linear-gradient(
                135deg,
                #22c55e,
                #166534
            );
            border-radius: 9px;
            font-size: 10px;
            font-weight: 800;
        }


        .user-name {
            max-width: 100px;
            overflow: hidden;
            color: var(--green-dark);
            font-size: 10px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }


        .logout-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 12px;
            color: #b91c1c;
            background: #fff5f5;
            border: 1px solid #fee2e2;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 800;
            transition: var(--transition);
        }


        .logout-button:hover {
            color: #ffffff;
            background: #dc2626;
        }


        .menu-button {
            display: none;
            width: 39px;
            height: 39px;
            place-items: center;
            color: var(--green-dark);
            background: var(--green-soft);
            border: 1px solid #c9ebd1;
            border-radius: 10px;
            font-size: 20px;
            cursor: pointer;
        }


        /* =====================================================
           PAGE WRAPPER
        ===================================================== */

        .page-wrapper {
            width: min(1240px, calc(100% - 40px));
            margin: 0 auto;
            padding: 38px 0 70px;
        }


        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 27px;
        }


        .page-header-content {
            text-align: center;
            flex: 1;
        }


        .page-header h1 {
            margin: 0;
            color: var(--green-dark);
            font-size: clamp(26px, 4vw, 37px);
            font-weight: 800;
            letter-spacing: -.9px;
        }


        .page-header p {
            max-width: 520px;
            margin: 10px auto 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.65;
        }


        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex-shrink: 0;
            padding: 10px 13px;
            color: var(--green-dark);
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(20,83,45,.05);
            font-size: 11px;
            font-weight: 800;
            transition: var(--transition);
        }


        .back-link:hover {
            color: var(--green);
            border-color: #b8dec1;
            transform: translateX(-3px);
        }


        /* =====================================================
           TWO COLUMN LAYOUT
        ===================================================== */

        .request-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(310px, .85fr);
            align-items: start;
            gap: 22px;
        }


        .card {
            position: relative;
            overflow: hidden;
            padding: 26px;
            background: rgba(255, 255, 255, .9);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
        }


        .card::before {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 4px;
            content: '';
            background: linear-gradient(
                90deg,
                var(--green),
                #86efac,
                #bae6fd
            );
        }


        .card-header {
            display: flex;
            align-items: center;
            gap: 11px;
            padding-bottom: 19px;
            margin-bottom: 22px;
            border-bottom: 1px solid #edf2ee;
        }


        .card-header-icon {
            width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            color: var(--green-dark);
            background: var(--green-soft);
            border-radius: 11px;
            font-size: 20px;
        }


        .card-header h2 {
            margin: 0;
            color: var(--green-dark);
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .2px;
            text-transform: uppercase;
        }


        .card-header p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 10px;
        }


        /* =====================================================
           FORM
        ===================================================== */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }


        .span-two {
            grid-column: span 2;
        }


        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }


        .form-label {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 7px;
            color: var(--green-dark);
            font-size: 11px;
            font-weight: 800;
        }


        .required {
            color: var(--danger);
        }


        .optional {
            color: var(--soft);
            font-size: 9px;
            font-weight: 600;
        }


        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }


        .input-icon {
            position: absolute;
            z-index: 2;
            left: 13px;
            color: #99a79d;
            font-size: 17px;
            pointer-events: none;
            transition: var(--transition);
        }


        .textarea-icon {
            top: 13px;
            align-self: flex-start;
        }


        .form-input,
        .form-select {
            width: 100%;
            min-height: 44px;
            padding: 11px 13px 11px 40px;
            color: var(--text);
            background: #ffffff;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            outline: none;
            font-size: 11px;
            transition: var(--transition);
        }


        .form-input::placeholder {
            color: #a1aea5;
        }


        .form-input:focus,
        .form-select:focus {
            border-color: var(--green);
            box-shadow:
                0 0 0 4px rgba(22,163,74,.09);
        }


        .form-input:focus ~ .input-icon,
        .form-select:focus ~ .input-icon {
            color: var(--green);
        }


        .form-select {
            cursor: pointer;
            appearance: none;
            background-image: url(
                "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23778078'%3E%3Cpath d='M12 16L6 10H18L12 16Z'/%3E%3C/svg%3E"
            );
            background-repeat: no-repeat;
            background-position: right 13px center;
            background-size: 16px;
            padding-right: 38px;
        }


        textarea.form-input {
            min-height: 91px;
            padding-top: 12px;
            resize: vertical;
        }


        .field-help {
            margin-top: 5px;
            color: var(--soft);
            font-size: 9px;
        }


        /* =====================================================
           IMAGE UPLOAD
        ===================================================== */

        .image-upload {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 140px;
            padding: 22px;
            text-align: center;
            background: #fbfdfb;
            border: 2px dashed #cbdccc;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
        }


        .image-upload:hover,
        .image-upload.dragover {
            background: var(--green-soft);
            border-color: var(--green);
        }


        .image-upload input {
            position: absolute;
            z-index: 4;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }


        .upload-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            margin-bottom: 8px;
            color: var(--green-dark);
            background: var(--green-soft);
            border-radius: 50%;
            font-size: 20px;
        }


        .upload-title {
            color: var(--green-dark);
            font-size: 11px;
            font-weight: 800;
        }


        .upload-subtitle {
            margin-top: 4px;
            color: var(--muted);
            font-size: 9px;
        }


        .file-name {
            z-index: 5;
            display: none;
            max-width: 90%;
            margin-top: 8px;
            padding: 6px 9px;
            overflow: hidden;
            color: var(--green-dark);
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 7px;
            font-size: 9px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }


        .image-preview {
            z-index: 5;
            display: none;
            max-width: 140px;
            max-height: 82px;
            margin-top: 8px;
            object-fit: cover;
            border: 3px solid #ffffff;
            border-radius: 9px;
            box-shadow: 0 6px 15px rgba(20,83,45,.14);
        }


        .file-error {
            display: none;
            margin-top: 6px;
            color: var(--danger);
            font-size: 9px;
            font-weight: 700;
        }


        /* =====================================================
           SUBMIT BUTTON
        ===================================================== */

        .submit-area {
            display: flex;
            justify-content: flex-end;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid #edf2ee;
        }


        .submit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-width: 190px;
            padding: 13px 18px;
            color: #ffffff;
            background: linear-gradient(
                135deg,
                #22c55e,
                #15803d
            );
            border: 0;
            border-radius: var(--radius-md);
            box-shadow:
                0 10px 22px rgba(22,163,74,.24);
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            transition: var(--transition);
        }


        .submit-button:hover {
            box-shadow:
                0 15px 28px rgba(22,163,74,.34);
            transform: translateY(-2px);
        }


        .submit-button:disabled {
            cursor: wait;
            opacity: .7;
            transform: none;
        }


        .submit-button i {
            font-size: 17px;
        }


        /* =====================================================
           SUMMARY CARD
        ===================================================== */

        .summary-card {
            position: sticky;
            top: 93px;
        }


        .summary-header {
            display: flex;
            align-items: center;
            gap: 10px;
        }


        .summary-leaf {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            color: var(--green);
            background: var(--green-soft);
            border-radius: 11px;
            font-size: 20px;
        }


        .summary-title {
            color: var(--green-dark);
            font-size: 14px;
            font-weight: 800;
        }


        .summary-caption {
            margin-top: 3px;
            color: var(--muted);
            font-size: 10px;
        }


        .summary-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 24px;
        }


        .summary-row {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }


        .summary-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
        }


        .summary-value {
            color: var(--green-dark);
            font-size: 11px;
            font-weight: 800;
            word-break: break-word;
        }


        .summary-value.empty {
            color: var(--soft);
            font-weight: 600;
        }


        .summary-divider {
            height: 1px;
            margin: 22px 0;
            background: var(--border);
        }


        .location-title {
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--green-dark);
            font-size: 11px;
            font-weight: 800;
        }


        .location-title i {
            color: var(--green);
            font-size: 17px;
        }


        .location-address {
            margin-top: 11px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
            word-break: break-word;
        }


        .location-pincode {
            margin-top: 7px;
            color: var(--green-dark);
            font-size: 10px;
            font-weight: 800;
        }


        .collector-note {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 12px;
            margin-top: 22px;
            color: var(--green-dark);
            background: #f0fdf4;
            border: 1px solid #d7f3dd;
            border-radius: 11px;
            font-size: 9px;
            line-height: 1.5;
        }


        .collector-note i {
            flex-shrink: 0;
            color: var(--green);
            font-size: 15px;
        }


        /* =====================================================
           TOAST
        ===================================================== */

        .toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 500;
            display: none;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            color: #ffffff;
            background: var(--green-dark);
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(20,83,45,.2);
            font-size: 10px;
            font-weight: 700;
        }


        .toast.show {
            display: flex;
            animation: toastIn .25s ease;
        }


        .toast.error {
            background: #b91c1c;
        }


        @keyframes toastIn {

            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .request-layout {
                grid-template-columns: minmax(0, 1fr);
            }


            .summary-card {
                position: static;
            }


            .summary-list {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                column-gap: 22px;
            }


            .location-block,
            .collector-note,
            .summary-divider {
                grid-column: 1 / -1;
            }

        }


        @media (max-width: 720px) {

            .navbar {
                align-items: center;
                flex-wrap: wrap;
                width: calc(100% - 24px);
                margin-top: 10px;
                padding: 9px 11px;
            }


            .menu-button {
                display: grid;
                margin-left: auto;
            }


            .nav-links {
                display: none;
                order: 4;
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 4px;
                padding-top: 10px;
                margin: 0;
                border-top: 1px solid var(--border);
            }


            .nav-links.open {
                display: flex;
            }


            .nav-link {
                justify-content: flex-start;
                padding: 11px;
                font-size: 11px;
            }


            .nav-right {
                order: 3;
            }


            .logout-button span {
                display: none;
            }


            .user-name {
                display: none;
            }


            .page-wrapper {
                width: calc(100% - 24px);
                padding-top: 28px;
            }


            .page-header {
                align-items: center;
                flex-direction: column;
                gap: 17px;
            }


            .page-header-content {
                order: 1;
            }


            .back-link {
                order: 2;
                align-self: flex-start;
            }

        }


        @media (max-width: 560px) {

            .card {
                padding: 21px 17px;
                border-radius: 18px;
            }


            .form-grid {
                grid-template-columns: 1fr;
                gap: 17px;
            }


            .span-two {
                grid-column: span 1;
            }


            .summary-list {
                display: flex;
            }


            .submit-area {
                justify-content: stretch;
            }


            .submit-button {
                width: 100%;
            }


            .page-header h1 {
                font-size: 28px;
            }


            .page-header p {
                font-size: 11px;
            }

        }

    </style>

</head>

<body>

    <!-- =====================================================
         NAVBAR
    ===================================================== -->

    


    <!-- =====================================================
         PAGE
    ===================================================== -->

    <main class="page-wrapper">


        <!-- Page Header -->

        <header class="page-header">

            <a
                href="dashboard.php"
                class="back-link"
            >
                <i class="ri-arrow-left-line"></i>
                Back to Dashboard
            </a>


            <div class="page-header-content">

                <h1>
                    Create Pickup Request
                </h1>


                <p>
                    Tell us what you want to recycle and where we should collect it.
                </p>

            </div>

        </header>


        <!-- Two-column layout -->

        <div class="request-layout">


            <!-- =================================================
                 LEFT: FORM CARD
            ================================================= -->

            <section class="card">

                <div class="card-header">

                    <div class="card-header-icon">
                        <i class="ri-recycle-line"></i>
                    </div>


                    <div>

                        <h2>
                            Scrap Information
                        </h2>


                        <p>
                            Provide details about the items you want to recycle.
                        </p>

                    </div>

                </div>


                <form
                    action="create_request_process.php"
                    method="POST"
                    enctype="multipart/form-data"
                    id="pickupForm"
                >

                    <div class="form-grid">


                        <!-- Scrap type -->

                        <div class="form-group span-two">

                            <label
                                for="scrap_type"
                                class="form-label"
                            >
                                Scrap Type
                                <span class="required">*</span>
                            </label>


                            <div class="input-wrapper">

                                <select
                                    id="scrap_type"
                                    name="scrap_type"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Select scrap type
                                    </option>

                                    <option value="Paper">
                                        Paper
                                    </option>

                                    <option value="Plastic">
                                        Plastic
                                    </option>

                                    <option value="Metal">
                                        Metal
                                    </option>

                                    <option value="Glass">
                                        Glass
                                    </option>

                                    <option value="E-Waste">
                                        E-Waste
                                    </option>

                                    <option value="Mixed Waste">
                                        Mixed Waste
                                    </option>

                                </select>


                                <i class="ri-recycle-line input-icon"></i>

                            </div>

                        </div>


                        <!-- Weight -->

                        <div class="form-group span-two">

                            <label
                                for="scrap_weight"
                                class="form-label"
                            >
                                Estimated Weight
                                <span class="required">*</span>
                            </label>


                            <div class="input-wrapper">

                                <input
                                    type="number"
                                    id="scrap_weight"
                                    name="scrap_weight"
                                    class="form-input"
                                    step="0.01"
                                    min="0.1"
                                    max="100000"
                                    placeholder="Enter weight"
                                    required
                                >


                                <i class="ri-scales-3-line input-icon"></i>

                            </div>


                            <span class="field-help">
                                Enter the estimated weight in kilograms.
                            </span>

                        </div>


                        <!-- Image -->

                        <div class="form-group span-two">

                            <label
                                for="scrap_image"
                                class="form-label"
                            >
                                Scrap Image
                                <span class="optional">
                                    Optional
                                </span>
                            </label>


                            <div
                                class="image-upload"
                                id="imageUpload"
                            >

                                <input
                                    type="file"
                                    id="scrap_image"
                                    name="scrap_image"
                                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                >


                                <div class="upload-icon">
                                    <i class="ri-camera-3-line"></i>
                                </div>


                                <div class="upload-title">
                                    Upload Image
                                </div>


                                <div class="upload-subtitle">
                                    JPG, JPEG, or PNG · Maximum 5 MB
                                </div>


                                <div
                                    class="file-name"
                                    id="fileName"
                                ></div>


                                <img
                                    src=""
                                    alt="Selected scrap image preview"
                                    class="image-preview"
                                    id="imagePreview"
                                >

                            </div>


                            <span
                                class="file-error"
                                id="fileError"
                            ></span>

                        </div>


                        <!-- Pickup information heading -->

                        <div class="form-group span-two">

                            <div class="card-header" style="margin: 8px 0 0; padding: 0 0 14px;">

                                <div class="card-header-icon">
                                    <i class="ri-map-pin-line"></i>
                                </div>


                                <div>

                                    <h2>
                                        Pickup Information
                                    </h2>


                                    <p>
                                        Tell us when and where to collect your scrap.
                                    </p>

                                </div>

                            </div>

                        </div>


                        <!-- Address -->

                        <div class="form-group span-two">

                            <label
                                for="pickup_address"
                                class="form-label"
                            >
                                Pickup Address
                                <span class="required">*</span>
                            </label>


                            <div class="input-wrapper">

                                <textarea
                                    id="pickup_address"
                                    name="pickup_address"
                                    class="form-input"
                                    rows="3"
                                    maxlength="500"
                                    placeholder="Enter complete address"
                                    required
                                ><?= escape_html($userAddress) ?></textarea>


                                <i class="ri-map-pin-line input-icon textarea-icon"></i>

                            </div>

                        </div>


                        <!-- Pincode -->

                        <div class="form-group span-two">

                            <label
                                for="pickup_pincode"
                                class="form-label"
                            >
                                Pincode
                                <span class="required">*</span>
                            </label>


                            <div class="input-wrapper">

                                <input
                                    type="text"
                                    id="pickup_pincode"
                                    name="pickup_pincode"
                                    class="form-input"
                                    value="<?= escape_html($userPincode) ?>"
                                    inputmode="numeric"
                                    pattern="[0-9]{6}"
                                    minlength="6"
                                    maxlength="6"
                                    placeholder="Enter pincode"
                                    required
                                >


                                <i class="ri-map-pin-user-line input-icon"></i>

                            </div>

                        </div>


                        <!-- Date -->

                        <div class="form-group">

                            <label
                                for="preferred_pickup_date"
                                class="form-label"
                            >
                                Preferred Date
                                <span class="required">*</span>
                            </label>


                            <div class="input-wrapper">

                                <input
                                    type="date"
                                    id="preferred_pickup_date"
                                    name="preferred_pickup_date"
                                    class="form-input"
                                    min="<?= escape_html($currentDate) ?>"
                                    required
                                >


                                <i class="ri-calendar-line input-icon"></i>

                            </div>

                        </div>


                        <!-- Time -->

                        <div class="form-group">

                            <label
                                for="pickup_time"
                                class="form-label"
                            >
                                Pickup Time
                                <span class="required">*</span>
                            </label>


                            <div class="input-wrapper">

                                <input
                                    type="time"
                                    id="pickup_time"
                                    name="pickup_time"
                                    class="form-input"
                                    required
                                >


                                <i class="ri-time-line input-icon"></i>

                            </div>

                        </div>


                        <!-- Remarks -->

                        <div class="form-group span-two">

                            <label
                                for="remarks"
                                class="form-label"
                            >
                                Additional Remarks
                                <span class="optional">
                                    Optional
                                </span>
                            </label>


                            <div class="input-wrapper">

                                <textarea
                                    id="remarks"
                                    name="remarks"
                                    class="form-input"
                                    rows="2"
                                    maxlength="255"
                                    placeholder="Optional message..."
                                ></textarea>


                                <i class="ri-chat-1-line input-icon textarea-icon"></i>

                            </div>

                        </div>

                    </div>


                    <!-- Submit -->

                    <div class="submit-area">

                        <button
                            type="submit"
                            class="submit-button"
                            id="submitButton"
                        >
                            <span>
                                REQUEST PICKUP
                            </span>

                            <i class="ri-arrow-right-line"></i>
                        </button>

                    </div>

                </form>

            </section>


            <!-- =================================================
                 RIGHT: SUMMARY CARD
            ================================================= -->

            <aside class="card summary-card">

                <div class="summary-header">

                    <div class="summary-leaf">
                        <i class="ri-leaf-line"></i>
                    </div>


                    <div>

                        <div class="summary-title">
                            Pickup Request
                        </div>


                        <div class="summary-caption">
                            Live request summary
                        </div>

                    </div>

                </div>


                <div class="summary-list">


                    <!-- Summary scrap type -->

                    <div class="summary-row">

                        <span class="summary-label">
                            Scrap Type
                        </span>


                        <span
                            class="summary-value empty"
                            id="summaryScrapType"
                        >
                            Not selected
                        </span>

                    </div>


                    <!-- Summary weight -->

                    <div class="summary-row">

                        <span class="summary-label">
                            Weight
                        </span>


                        <span
                            class="summary-value empty"
                            id="summaryWeight"
                        >
                            -- kg
                        </span>

                    </div>


                    <!-- Summary date -->

                    <div class="summary-row">

                        <span class="summary-label">
                            Pickup Date
                        </span>


                        <span
                            class="summary-value empty"
                            id="summaryDate"
                        >
                            Not selected
                        </span>

                    </div>


                    <!-- Summary time -->

                    <div class="summary-row">

                        <span class="summary-label">
                            Pickup Time
                        </span>


                        <span
                            class="summary-value empty"
                            id="summaryTime"
                        >
                            Not selected
                        </span>

                    </div>

                </div>


                <div class="summary-divider"></div>


                <!-- Location -->

                <div class="location-block">

                    <div class="location-title">

                        <i class="ri-map-pin-line"></i>

                        <span>
                            Pickup Location
                        </span>

                    </div>


                    <div
                        class="location-address"
                        id="summaryAddress"
                    >
                        Address not selected
                    </div>


                    <div
                        class="location-pincode"
                        id="summaryPincode"
                    >
                        Pincode: --
                    </div>

                </div>


                <div class="summary-divider"></div>


                <!-- Collector note -->

                <div class="collector-note">

                    <i class="ri-checkbox-circle-line"></i>


                    <span>
                        Collector will be assigned by the admin based on availability and location.
                    </span>

                </div>

            </aside>

        </div>

    </main>


    <!-- Toast -->

    <div
        class="toast"
        id="toast"
        role="status"
        aria-live="polite"
    >

        <i
            class="ri-error-warning-line"
            id="toastIcon"
        ></i>


        <span id="toastText"></span>

    </div>


    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {


                // =================================================
                // MOBILE NAVBAR
                // =================================================

                const menuButton =
                    document.getElementById(
                        'menuButton'
                    );

                const navLinks =
                    document.getElementById(
                        'navLinks'
                    );


                if (
                    menuButton &&
                    navLinks
                ) {

                    menuButton.addEventListener(
                        'click',
                        function () {

                            const isOpen =
                                navLinks.classList.toggle(
                                    'open'
                                );

                            menuButton.setAttribute(
                                'aria-expanded',
                                isOpen
                                    ? 'true'
                                    : 'false'
                            );

                            menuButton.innerHTML =
                                isOpen
                                    ? '<i class="ri-close-line"></i>'
                                    : '<i class="ri-menu-3-line"></i>';
                        }
                    );


                    navLinks
                        .querySelectorAll('a')
                        .forEach(
                            function (link) {

                                link.addEventListener(
                                    'click',
                                    function () {

                                        if (
                                            window.innerWidth <= 720
                                        ) {
                                            navLinks.classList.remove(
                                                'open'
                                            );

                                            menuButton.setAttribute(
                                                'aria-expanded',
                                                'false'
                                            );

                                            menuButton.innerHTML =
                                                '<i class="ri-menu-3-line"></i>';
                                        }
                                    }
                                );
                            }
                        );
                }


                // =================================================
                // FORM ELEMENTS
                // =================================================

                const scrapType =
                    document.getElementById(
                        'scrap_type'
                    );

                const scrapWeight =
                    document.getElementById(
                        'scrap_weight'
                    );

                const pickupAddress =
                    document.getElementById(
                        'pickup_address'
                    );

                const pickupPincode =
                    document.getElementById(
                        'pickup_pincode'
                    );

                const pickupDate =
                    document.getElementById(
                        'preferred_pickup_date'
                    );

                const pickupTime =
                    document.getElementById(
                        'pickup_time'
                    );

                const fileInput =
                    document.getElementById(
                        'scrap_image'
                    );

                const imageUpload =
                    document.getElementById(
                        'imageUpload'
                    );

                const imagePreview =
                    document.getElementById(
                        'imagePreview'
                    );

                const fileName =
                    document.getElementById(
                        'fileName'
                    );

                const fileError =
                    document.getElementById(
                        'fileError'
                    );

                const form =
                    document.getElementById(
                        'pickupForm'
                    );

                const submitButton =
                    document.getElementById(
                        'submitButton'
                    );


                // Summary elements

                const summaryScrapType =
                    document.getElementById(
                        'summaryScrapType'
                    );

                const summaryWeight =
                    document.getElementById(
                        'summaryWeight'
                    );

                const summaryDate =
                    document.getElementById(
                        'summaryDate'
                    );

                const summaryTime =
                    document.getElementById(
                        'summaryTime'
                    );

                const summaryAddress =
                    document.getElementById(
                        'summaryAddress'
                    );

                const summaryPincode =
                    document.getElementById(
                        'summaryPincode'
                    );


                // =================================================
                // SUMMARY HELPERS
                // =================================================

                function setSummaryValue(
                    element,
                    value,
                    emptyText
                ) {

                    const cleanValue =
                        value.trim();

                    if (
                        cleanValue === ''
                    ) {
                        element.textContent =
                            emptyText;

                        element.classList.add(
                            'empty'
                        );

                        return;
                    }

                    element.textContent =
                        cleanValue;

                    element.classList.remove(
                        'empty'
                    );
                }


                function formatDate(dateValue) {

                    if (
                        !dateValue
                    ) {
                        return '';
                    }

                    const parts =
                        dateValue.split('-');

                    if (
                        parts.length !== 3
                    ) {
                        return dateValue;
                    }

                    return `${parts[2]}-${parts[1]}-${parts[0]}`;
                }


                function formatTime(timeValue) {

                    if (
                        !timeValue
                    ) {
                        return '';
                    }

                    const parts =
                        timeValue.split(':');

                    let hours =
                        parseInt(parts[0], 10);

                    const minutes =
                        parts[1] || '00';

                    const suffix =
                        hours >= 12
                            ? 'PM'
                            : 'AM';

                    hours =
                        hours % 12 || 12;

                    return `${hours}:${minutes} ${suffix}`;
                }


                function updateSummary() {

                    setSummaryValue(
                        summaryScrapType,
                        scrapType.value,
                        'Not selected'
                    );


                    const weight =
                        scrapWeight.value.trim();

                    setSummaryValue(
                        summaryWeight,
                        weight
                            ? `${weight} kg`
                            : '',
                        '-- kg'
                    );


                    setSummaryValue(
                        summaryDate,
                        formatDate(
                            pickupDate.value
                        ),
                        'Not selected'
                    );


                    setSummaryValue(
                        summaryTime,
                        formatTime(
                            pickupTime.value
                        ),
                        'Not selected'
                    );


                    const address =
                        pickupAddress.value.trim();

                    if (
                        address === ''
                    ) {
                        summaryAddress.textContent =
                            'Address not selected';

                        summaryAddress.classList.add(
                            'empty'
                        );
                    } else {
                        summaryAddress.textContent =
                            address;

                        summaryAddress.classList.remove(
                            'empty'
                        );
                    }


                    const pincode =
                        pickupPincode.value.trim();

                    summaryPincode.textContent =
                        pincode
                            ? `Pincode: ${pincode}`
                            : 'Pincode: --';
                }


                [
                    scrapType,
                    scrapWeight,
                    pickupAddress,
                    pickupPincode,
                    pickupDate,
                    pickupTime
                ].forEach(
                    function (element) {

                        element.addEventListener(
                            'input',
                            updateSummary
                        );

                        element.addEventListener(
                            'change',
                            updateSummary
                        );
                    }
                );


                // =================================================
                // PINCODE INPUT
                // =================================================

                pickupPincode.addEventListener(
                    'input',
                    function () {

                        this.value =
                            this.value
                                .replace(/\D/g, '')
                                .slice(0, 6);

                        updateSummary();
                    }
                );


                // =================================================
                // IMAGE UPLOAD
                // =================================================

                const allowedTypes = [
                    'image/jpeg',
                    'image/png'
                ];

                const maxSize =
                    5 * 1024 * 1024;


                function showFileError(message) {

                    fileError.textContent =
                        message;

                    fileError.style.display =
                        'block';

                    fileInput.setCustomValidity(
                        message
                    );
                }


                function clearFileError() {

                    fileError.textContent =
                        '';

                    fileError.style.display =
                        'none';

                    fileInput.setCustomValidity(
                        ''
                    );
                }


                function previewFile(file) {

                    clearFileError();

                    imagePreview.style.display =
                        'none';

                    fileName.style.display =
                        'none';

                    imagePreview.removeAttribute(
                        'src'
                    );


                    if (
                        !file
                    ) {
                        return;
                    }


                    if (
                        !allowedTypes.includes(
                            file.type
                        )
                    ) {
                        showFileError(
                            'Please select a JPG, JPEG, or PNG image.'
                        );

                        fileInput.value =
                            '';

                        return;
                    }


                    if (
                        file.size > maxSize
                    ) {
                        showFileError(
                            'Image size must be less than 5 MB.'
                        );

                        fileInput.value =
                            '';

                        return;
                    }


                    fileName.textContent =
                        `Selected: ${file.name}`;

                    fileName.style.display =
                        'block';


                    const reader =
                        new FileReader();


                    reader.onload =
                        function (event) {

                            imagePreview.src =
                                event.target.result;

                            imagePreview.style.display =
                                'block';
                        };


                    reader.readAsDataURL(file);
                }


                fileInput.addEventListener(
                    'change',
                    function () {

                        previewFile(
                            this.files[0] || null
                        );
                    }
                );


                [
                    'dragenter',
                    'dragover'
                ].forEach(
                    function (eventName) {

                        imageUpload.addEventListener(
                            eventName,
                            function (event) {

                                event.preventDefault();

                                imageUpload.classList.add(
                                    'dragover'
                                );
                            }
                        );
                    }
                );


                [
                    'dragleave',
                    'drop'
                ].forEach(
                    function (eventName) {

                        imageUpload.addEventListener(
                            eventName,
                            function (event) {

                                event.preventDefault();

                                imageUpload.classList.remove(
                                    'dragover'
                                );
                            }
                        );
                    }
                );


                imageUpload.addEventListener(
                    'drop',
                    function (event) {

                        const droppedFiles =
                            event.dataTransfer.files;

                        if (
                            !droppedFiles ||
                            !droppedFiles.length
                        ) {
                            return;
                        }


                        try {

                            const dataTransfer =
                                new DataTransfer();

                            dataTransfer.items.add(
                                droppedFiles[0]
                            );

                            fileInput.files =
                                dataTransfer.files;

                        } catch (error) {
                            // Standard file selection remains available.
                        }


                        previewFile(
                            droppedFiles[0]
                        );
                    }
                );


                // =================================================
                // FORM SUBMIT VALIDATION
                // =================================================

                form.addEventListener(
                    'submit',
                    function (event) {

                        clearFileError();


                        const pincode =
                            pickupPincode.value.trim();


                        if (
                            pincode.length !== 6
                        ) {
                            event.preventDefault();

                            pickupPincode.setCustomValidity(
                                'Please enter a valid 6-digit pincode.'
                            );

                            pickupPincode.reportValidity();

                            return;
                        }


                        const selectedDate =
                            pickupDate.value;


                        if (
                            selectedDate
                        ) {

                            const today =
                                new Date();

                            today.setHours(
                                0,
                                0,
                                0,
                                0
                            );


                            const dateParts =
                                selectedDate
                                    .split('-')
                                    .map(Number);


                            const selected =
                                new Date(
                                    dateParts[0],
                                    dateParts[1] - 1,
                                    dateParts[2]
                                );


                            if (
                                selected < today
                            ) {
                                event.preventDefault();

                                showToast(
                                    'Please select today or a future date.'
                                );

                                return;
                            }
                        }


                        submitButton.disabled =
                            true;

                        submitButton.innerHTML =
                            '<i class="ri-loader-4-line ri-spin"></i><span>Submitting...</span>';
                    }
                );


                // =================================================
                // TOAST
                // =================================================

                function showToast(message) {

                    const toast =
                        document.getElementById(
                            'toast'
                        );

                    const toastText =
                        document.getElementById(
                            'toastText'
                        );


                    toastText.textContent =
                        message;

                    toast.classList.add(
                        'show'
                    );


                    setTimeout(
                        function () {

                            toast.classList.remove(
                                'show'
                            );

                        },
                        3500
                    );
                }


                // Initial summary

                updateSummary();

            }
        );

    </script>

</body>

</html>