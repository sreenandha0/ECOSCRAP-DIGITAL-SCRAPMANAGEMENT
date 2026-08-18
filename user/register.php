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
        content="Create your EcoScrap account and start making an impact."
    >

    <title>Create Account — EcoScrap</title>


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



            <!-- Brand Text -->

            <div class="brand-content">


                <div class="live-badge">

                    <span class="live-dot"></span>

                    Join the EcoScrap Network

                </div>


                <h1>

                    Make your waste
                    <span>matter.</span>

                </h1>


                <p>

                    Create your EcoScrap account and connect
                    with a smarter, cleaner way to manage
                    recyclable materials.

                </p>

            </div>



            <!-- Benefits -->

            <div class="brand-features">


                <div class="feature">

                    <div class="feature-icon">

                        <i class="ri-recycle-line"></i>

                    </div>


                    <div>

                        <strong>Recycle Smarter</strong>

                        <span>
                            Manage your scrap easily
                        </span>

                    </div>

                </div>



                <div class="feature">

                    <div class="feature-icon">

                        <i class="ri-truck-line"></i>

                    </div>


                    <div>

                        <strong>Easy Pickup</strong>

                        <span>
                            Connect with collectors
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
                            Build a greener future
                        </span>

                    </div>

                </div>


            </div>


        </section>



        <!-- =================================================
             RIGHT FORM PANEL
        ================================================== -->

        <section class="form-panel">


            <!-- Back -->

            <a
                href="../index.php"
                class="back-home"
            >

                <i class="ri-arrow-left-line"></i>

                <span>Back to home</span>

            </a>



            <div class="form-container">


                <!-- Mobile Logo -->

                <div class="mobile-logo">

                    <img
                        src="../assets/logo/ecoscrap-logo.png.png"
                        alt="EcoScrap"
                    >

                    <span>EcoScrap</span>

                </div>



                <!-- Header -->

                <div class="register-header">


                    <div class="security-badge">

                        <i class="ri-user-add-line"></i>

                        Create your account

                    </div>


                    <h2>

                        Get started

                    </h2>


                    <p>

                        Create your EcoScrap user account.

                    </p>

                </div>



                <!-- PHP Messages -->

                <?php

                if (function_exists('displayMessage')) {

                    displayMessage();

                }

                ?>



                <!-- =================================================
                     REGISTRATION FORM
                ================================================== -->

                <form
                    action="register_process.php"
                    method="POST"
                    enctype="multipart/form-data"
                    class="register-form"
                >



                    <!-- ==============================
                         PROFILE IMAGE
                    =============================== -->

                    <div class="profile-upload">


                        <div
                            class="profile-preview"
                            id="profilePreview"
                        >

                            <i class="ri-user-line"></i>

                        </div>


                        <div class="profile-upload-content">

                            <label
                                for="profile_image"
                                class="upload-button"
                            >

                                <i class="ri-camera-line"></i>

                                Upload photo

                            </label>


                            <input
                                type="file"
                                id="profile_image"
                                name="profile_image"
                                accept=".jpg,.jpeg,.png"
                                hidden
                            >


                            <span>

                                JPG, JPEG or PNG

                            </span>

                        </div>

                    </div>



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
                         ADDRESS
                    =============================== -->

                    <div class="form-group">

                        <label
                            for="address"
                            class="form-label"
                        >

                            Address

                        </label>


                        <div class="input-wrapper textarea-wrapper">

                            <i
                                class="ri-map-pin-line input-icon textarea-icon"
                            ></i>


                            <textarea
                                id="address"
                                name="address"
                                class="form-input form-textarea"
                                placeholder="Enter your address"
                                rows="2"
                                required
                            ></textarea>

                        </div>

                    </div>



                    <!-- ==============================
                         PLACE / DISTRICT
                    =============================== -->

                    <div class="form-row">


                        <div class="form-group">

                            <label
                                for="place"
                                class="form-label"
                            >

                                Place

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="ri-building-line input-icon"
                                ></i>


                                <input
                                    type="text"
                                    id="place"
                                    name="place"
                                    class="form-input"
                                    placeholder="Your place"
                                >

                            </div>

                        </div>



                        <div class="form-group">

                            <label
                                for="district"
                                class="form-label"
                            >

                                District

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="ri-map-2-line input-icon"
                                ></i>


                                <input
                                    type="text"
                                    id="district"
                                    name="district"
                                    class="form-input"
                                    placeholder="Your district"
                                >

                            </div>

                        </div>


                    </div>



                    <!-- ==============================
                         STATE / PINCODE
                    =============================== -->

                    <div class="form-row">


                        <div class="form-group">

                            <label
                                for="state"
                                class="form-label"
                            >

                                State

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="ri-government-line input-icon"
                                ></i>


                                <input
                                    type="text"
                                    id="state"
                                    name="state"
                                    class="form-input"
                                    placeholder="Your state"
                                >

                            </div>

                        </div>



                        <div class="form-group">

                            <label
                                for="pincode"
                                class="form-label"
                            >

                                Pincode

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="ri-navigation-line input-icon"
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

                        </div>


                    </div>



                    <!-- ==============================
                         PASSWORD
                    =============================== -->

                    <div class="form-row">


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



                    <!-- Password strength -->

                    <div class="password-strength">

                        <div class="strength-bars">

                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>

                        </div>


                        <span
                            id="passwordStrengthText"
                        >
                            Use at least 8 characters
                        </span>

                    </div>



                    <!-- Terms -->

                    <label class="terms-check">

                        <input
                            type="checkbox"
                            id="terms"
                            required
                        >

                        <span class="terms-box"></span>

                        <span>

                            I agree to EcoScrap's
                            <a href="#">Terms</a>
                            and
                            <a href="#">Privacy Policy</a>.

                        </span>

                    </label>



                    <!-- Submit -->

                    <button
                        type="submit"
                        class="register-button"
                    >

                        <span>Create Account</span>

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
   PROFILE IMAGE PREVIEW
========================================================= */

const profileInput =
    document.getElementById("profile_image");

const profilePreview =
    document.getElementById("profilePreview");


profileInput.addEventListener("change", function() {

    const file = this.files[0];

    if (!file) {
        return;
    }


    const reader =
        new FileReader();


    reader.onload = function(event) {

        profilePreview.innerHTML = "";

        const image =
            document.createElement("img");

        image.src =
            event.target.result;

        image.alt =
            "Profile preview";

        profilePreview.appendChild(image);

    };


    reader.readAsDataURL(file);

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

    const value =
        this.value;

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
        this.value.replace(/\D/g, "")
        .slice(0, 10);

});



/* =========================================================
   PINCODE — NUMBERS ONLY
========================================================= */

const pincode =
    document.getElementById("pincode");


pincode.addEventListener("input", function() {

    this.value =
        this.value.replace(/\D/g, "")
        .slice(0, 6);

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

            this.closest(".input-wrapper")
                ?.classList.add("focused");

        }
    );


    input.addEventListener(
        "blur",
        function() {

            this.closest(".input-wrapper")
                ?.classList.remove("focused");

        }
    );

});


</script>


</body>

</html>