<?php
session_start();

require_once "../includes/functions.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Register as an EcoScrap collector and join the smart recycling network."
    >

    <title>Collector Registration — EcoScrap</title>


    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- Remix Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >


    <!-- Shared Registration CSS -->
    <link
        rel="stylesheet"
        href="../assets/css/register.css"
    >


    <style>

        /*
         * Collector-specific branding.
         * The same register.css is shared with the User page.
         */

        .collector-accent {
            color: #38BDF8 !important;
        }

        .collector-badge {
            color: #0369A1 !important;
            background: rgba(14, 165, 233, 0.09) !important;
            border-color: rgba(14, 165, 233, 0.16) !important;
        }

        .collector-button {
            background:
                linear-gradient(
                    135deg,
                    #0EA5E9,
                    #047857
                ) !important;

            box-shadow:
                0 10px 25px rgba(14, 165, 233, 0.20) !important;
        }

        .collector-button:hover {
            box-shadow:
                0 14px 30px rgba(14, 165, 233, 0.28) !important;
        }

        .collector-icon {
            color: #38BDF8 !important;
        }

    </style>

</head>


<body>


<!-- =========================================================
     BACKGROUND
========================================================= -->

<div class="background-decoration">

    <div class="gradient-orb orb-one"></div>

    <div class="gradient-orb orb-two"></div>

    <div class="gradient-orb orb-three"></div>

</div>



<!-- =========================================================
     REGISTER PAGE
========================================================= -->

<main class="register-page">


    <div class="register-card">


        <!-- =================================================
             LEFT BRAND PANEL
        ================================================== -->

        <section class="brand-panel">


            <!-- Logo -->

            <a
                href="../index.php"
                class="brand-logo"
            >

                <img
                    src="../assets/logo/ecoscrap-logo.png.png"
                    alt="EcoScrap"
                >

                <span>EcoScrap</span>

            </a>



            <!-- Animated Logo -->

            <div class="logo-animation">

                <div class="animation-glow"></div>


                <video
                    class="ecoscrap-video"
                    autoplay
                    muted
                    loop
                    playsinline
                >

                    <source
                        src="../assets/logo/ecoscrap-logo.mp4.mp4"
                        type="video/mp4"
                    >

                </video>

            </div>



            <!-- Brand Content -->

            <div class="brand-content">


                <div class="live-badge">

                    <span class="live-dot"></span>

                    Become an EcoScrap Collector

                </div>


                <h1>

                    Turn collection
                    <span>into impact.</span>

                </h1>


                <p>

                    Join the EcoScrap collection network,
                    receive pickup requests and help recyclable
                    materials reach the right destination.

                </p>

            </div>



            <!-- Collector Features -->

            <div class="brand-features">


                <div class="feature">

                    <div class="feature-icon">

                        <i class="ri-truck-line"></i>

                    </div>


                    <div>

                        <strong>Smart Pickup Requests</strong>

                        <span>
                            Manage assigned collections
                        </span>

                    </div>

                </div>



                <div class="feature">

                    <div class="feature-icon">

                        <i class="ri-qr-scan-2-line"></i>

                    </div>


                    <div>

                        <strong>QR Verification</strong>

                        <span>
                            Verify collections securely
                        </span>

                    </div>

                </div>



                <div class="feature">

                    <div class="feature-icon">

                        <i class="ri-leaf-line"></i>

                    </div>


                    <div>

                        <strong>Make an Impact</strong>

                        <span>
                            Help build a circular economy
                        </span>

                    </div>

                </div>


            </div>


        </section>



        <!-- =================================================
             RIGHT FORM PANEL
        ================================================== -->

        <section class="form-panel">


            <!-- Back Home -->

            <a
                href="../index.php"
                class="back-home"
            >

                <i class="ri-arrow-left-line"></i>

                <span>Back to home</span>

            </a>



            <div class="form-container">


                <!-- Header -->

                <div class="register-header">


                    <div class="security-badge collector-badge">

                        <i class="ri-truck-line"></i>

                        Collector Registration

                    </div>


                    <h2>

                        Join the network

                    </h2>


                    <p>

                        Create your collector account and start
                        managing scrap pickups.

                    </p>

                </div>



                <!-- PHP Messages -->

                <?php

                if (function_exists('displayMessage')) {

                    displayMessage();

                }

                ?>



                <!-- =================================================
                     COLLECTOR REGISTRATION FORM
                ================================================== -->

                <form
                    action="register_process.php"
                    method="POST"
                    class="register-form"
                >


                    <!-- ==============================
                         NAME
                    =============================== -->

                    <div class="form-group">

                        <label
                            for="name"
                            class="form-label"
                        >

                            Full Name

                        </label>


                        <div class="input-wrapper">

                            <i
                                class="ri-user-3-line input-icon"
                            ></i>


                            <input
                                type="text"
                                id="name"
                                name="name"
                                class="form-input"
                                placeholder="Enter your full name"
                                autocomplete="name"
                                required
                            >

                        </div>

                    </div>



                    <!-- ==============================
                         EMAIL + PHONE
                    =============================== -->

                    <div class="form-row">


                        <div class="form-group">

                            <label
                                for="email"
                                class="form-label"
                            >

                                Email Address

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="ri-mail-line input-icon"
                                ></i>


                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-input"
                                    placeholder="name@example.com"
                                    autocomplete="email"
                                    required
                                >

                            </div>

                        </div>



                        <div class="form-group">

                            <label
                                for="phone"
                                class="form-label"
                            >

                                Phone Number

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="ri-phone-line input-icon"
                                ></i>


                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-input"
                                    placeholder="10-digit number"
                                    maxlength="10"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    required
                                >

                            </div>

                        </div>


                    </div>



                    <!-- ==============================
                         VEHICLE NUMBER
                    =============================== -->

                    <div class="form-group">

                        <label
                            for="vehicle_no"
                            class="form-label"
                        >

                            Vehicle Registration Number

                        </label>


                        <div class="input-wrapper">

                            <i
                                class="ri-truck-line input-icon collector-icon"
                            ></i>


                            <input
                                type="text"
                                id="vehicle_no"
                                name="vehicle_no"
                                class="form-input"
                                placeholder="e.g. KL07AB1234"
                                maxlength="20"
                                autocomplete="off"
                                required
                            >

                        </div>


                        <div
                            style="
                                margin-top:6px;
                                color:#94A3B8;
                                font-size:10px;
                            "
                        >

                            Enter your registered vehicle number.

                        </div>

                    </div>



                    <!-- ==============================
                         PINCODE
                    =============================== -->

                    <div class="form-group">

                        <label
                            for="pincode"
                            class="form-label"
                        >

                            Service Pincode

                        </label>


                        <div class="input-wrapper">

                            <i
                                class="ri-map-pin-line input-icon"
                            ></i>


                            <input
                                type="text"
                                id="pincode"
                                name="pincode"
                                class="form-input"
                                placeholder="6-digit pincode"
                                maxlength="6"
                                inputmode="numeric"
                                required
                            >

                        </div>


                        <div
                            style="
                                margin-top:6px;
                                color:#94A3B8;
                                font-size:10px;
                            "
                        >

                            Pickups will be assigned based on your service area.

                        </div>

                    </div>



                    <!-- ==============================
                         PASSWORDS
                    =============================== -->

                    <div class="form-row">


                        <!-- Password -->

                        <div class="form-group">

                            <label
                                for="password"
                                class="form-label"
                            >

                                Password

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="ri-lock-2-line input-icon"
                                ></i>


                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-input password-input"
                                    placeholder="Minimum 8 characters"
                                    autocomplete="new-password"
                                    required
                                >


                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="password"
                                    aria-label="Show password"
                                >

                                    <i class="ri-eye-line"></i>

                                </button>

                            </div>

                        </div>



                        <!-- Confirm Password -->

                        <div class="form-group">

                            <label
                                for="confirm_password"
                                class="form-label"
                            >

                                Confirm Password

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="ri-lock-password-line input-icon"
                                ></i>


                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    class="form-input password-input"
                                    placeholder="Repeat password"
                                    autocomplete="new-password"
                                    required
                                >


                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="confirm_password"
                                    aria-label="Show password"
                                >

                                    <i class="ri-eye-line"></i>

                                </button>

                            </div>

                        </div>


                    </div>



                    <!-- Password Strength -->

                    <div class="password-strength">

                        <div class="strength-bars">

                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>

                        </div>


                        <span id="passwordStrengthText">

                            Use at least 8 characters

                        </span>

                    </div>



                    <!-- Collector Agreement -->

                    <label class="terms-check">

                        <input
                            type="checkbox"
                            id="terms"
                            required
                        >

                        <span class="terms-box"></span>

                        <span>

                            I confirm that the information provided
                            is accurate and I agree to EcoScrap's
                            <a href="#">Terms</a>
                            and
                            <a href="#">Privacy Policy</a>.

                        </span>

                    </label>



                    <!-- Approval Notice -->

                    <div
                        style="
                            display:flex;
                            align-items:flex-start;
                            gap:9px;
                            padding:11px 12px;
                            margin-bottom:16px;
                            border-radius:9px;
                            background:#F0F9FF;
                            border:1px solid #BAE6FD;
                            color:#0369A1;
                            font-size:10px;
                            line-height:1.5;
                        "
                    >

                        <i
                            class="ri-information-line"
                            style="
                                font-size:16px;
                                flex-shrink:0;
                            "
                        ></i>


                        <span>

                            Your collector account will remain
                            <strong>Pending</strong> until an
                            administrator verifies and approves it.

                        </span>

                    </div>



                    <!-- Submit -->

                    <button
                        type="submit"
                        class="register-button collector-button"
                    >

                        <span>Apply as Collector</span>

                        <i class="ri-arrow-right-line"></i>

                    </button>


                </form>



                <!-- Login -->

                <div class="login-link">

                    Already have an account?

                    <a href="../login.php">

                        Sign in

                    </a>

                </div>


            </div>

        </section>


    </div>

</main>



<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>


/* =========================================================
   PASSWORD TOGGLE
========================================================= */

const passwordButtons =
    document.querySelectorAll(".password-toggle");


passwordButtons.forEach(function(button) {

    button.addEventListener("click", function() {

        const targetId =
            this.getAttribute("data-target");

        const input =
            document.getElementById(targetId);

        const icon =
            this.querySelector("i");


        if (input.type === "password") {

            input.type = "text";

            icon.className =
                "ri-eye-off-line";

            this.setAttribute(
                "aria-label",
                "Hide password"
            );

        } else {

            input.type = "password";

            icon.className =
                "ri-eye-line";

            this.setAttribute(
                "aria-label",
                "Show password"
            );

        }

    });

});



/* =========================================================
   PASSWORD STRENGTH
========================================================= */

const password =
    document.getElementById("password");

const strengthBars =
    document.querySelectorAll(
        ".strength-bars span"
    );

const strengthText =
    document.getElementById(
        "passwordStrengthText"
    );


password.addEventListener("input", function() {

    const value = this.value;

    let score = 0;


    if (value.length >= 8) {
        score++;
    }

    if (/[A-Z]/.test(value)) {
        score++;
    }

    if (/[0-9]/.test(value)) {
        score++;
    }

    if (/[^A-Za-z0-9]/.test(value)) {
        score++;
    }


    strengthBars.forEach(function(bar, index) {

        if (index < score) {

            bar.classList.add("active");

        } else {

            bar.classList.remove("active");

        }

    });


    if (value.length === 0) {

        strengthText.textContent =
            "Use at least 8 characters";

    } else if (score <= 1) {

        strengthText.textContent =
            "Weak password";

    } else if (score === 2) {

        strengthText.textContent =
            "Fair password";

    } else if (score === 3) {

        strengthText.textContent =
            "Good password";

    } else {

        strengthText.textContent =
            "Strong password";

    }

});



/* =========================================================
   PHONE — NUMBERS ONLY
========================================================= */

const phone =
    document.getElementById("phone");


phone.addEventListener("input", function() {

    this.value =
        this.value
            .replace(/\D/g, "")
            .slice(0, 10);

});



/* =========================================================
   PINCODE — NUMBERS ONLY
========================================================= */

const pincode =
    document.getElementById("pincode");


pincode.addEventListener("input", function() {

    this.value =
        this.value
            .replace(/\D/g, "")
            .slice(0, 6);

});



/* =========================================================
   VEHICLE NUMBER
========================================================= */

const vehicle =
    document.getElementById("vehicle_no");


vehicle.addEventListener("input", function() {

    this.value =
        this.value
            .toUpperCase()
            .replace(/\s/g, "");

});



/* =========================================================
   INPUT FOCUS
========================================================= */

const inputs =
    document.querySelectorAll(".form-input");


inputs.forEach(function(input) {

    input.addEventListener(
        "focus",
        function() {

            const wrapper =
                this.closest(".input-wrapper");

            if (wrapper) {
                wrapper.classList.add("focused");
            }

        }
    );


    input.addEventListener(
        "blur",
        function() {

            const wrapper =
                this.closest(".input-wrapper");

            if (wrapper) {
                wrapper.classList.remove("focused");
            }

        }
    );

});


</script>


</body>

</html>