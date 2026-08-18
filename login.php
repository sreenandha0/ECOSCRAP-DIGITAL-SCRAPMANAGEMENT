<?php
// Keep your existing PHP includes/session logic above this line if you have any.
// Example:
// session_start();
// require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description"
        content="EcoScrap — Smart scrap collection and recycling management platform.">

    <title>Login — EcoScrap</title>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Remix Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css"
        rel="stylesheet">

    <!-- Login CSS -->
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

    <!-- Background decoration -->
    <div class="background-decoration">
        <div class="gradient-orb orb-one"></div>
        <div class="gradient-orb orb-two"></div>
        <div class="gradient-orb orb-three"></div>
    </div>


    <!-- Main Login -->
    <main class="login-page">

        <div class="login-card">


            <!-- =====================================================
                 LEFT — BRAND / VIDEO
            ====================================================== -->

            <section class="brand-panel">

                <!-- Top logo -->
                <a href="index.php" class="brand-logo">

                    <img
                        src="assets/logo/ecoscrap-logo.png.png"
                        alt="EcoScrap Logo">

                    <span>EcoScrap</span>

                </a>


                <!-- Animated logo -->
                <div class="logo-animation">

                    <div class="animation-glow"></div>

                    <video
                        class="ecoscrap-video"
                        autoplay
                        muted
                        loop
                        playsinline>

                        <source
                            src="assets/logo/ecoscrap-logo.mp4.mp4"
                            type="video/mp4">

                    </video>

                </div>


                <!-- Brand content -->
                <div class="brand-content">

                    <div class="live-badge">

                        <span class="live-dot"></span>

                        Smart Recycling Network

                    </div>

                    <h1>
                        Turn waste into
                        <span>value.</span>
                    </h1>

                    <p>
                        Connect with verified collectors,
                        schedule scrap pickups and help
                        build a cleaner, more sustainable future.
                    </p>

                </div>


                <!-- Brand features -->
                <div class="brand-features">

                    <div class="feature">

                        <div class="feature-icon">
                            <i class="ri-recycle-line"></i>
                        </div>

                        <div>
                            <strong>Smart Recycling</strong>
                            <span>Manage scrap efficiently</span>
                        </div>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">
                            <i class="ri-map-pin-line"></i>
                        </div>

                        <div>
                            <strong>Easy Pickup</strong>
                            <span>Connect with collectors</span>
                        </div>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">
                            <i class="ri-leaf-line"></i>
                        </div>

                        <div>
                            <strong>Real Impact</strong>
                            <span>Track your environmental impact</span>
                        </div>

                    </div>

                </div>

            </section>



            <!-- =====================================================
                 RIGHT — LOGIN FORM
            ====================================================== -->

            <section class="form-panel">

                <!-- Back -->
                <a href="index.php" class="back-home">

                    <i class="ri-arrow-left-line"></i>

                    <span>Back to home</span>

                </a>


                <div class="form-container">


                    <!-- Small mobile logo -->
                    <div class="mobile-logo">

                        <img
                            src="assets/logo/ecoscrap-logo.png.png"
                            alt="EcoScrap">

                        <span>EcoScrap</span>

                    </div>


                    <!-- Header -->
                    <div class="login-header">

                        <div class="security-badge">

                            <i class="ri-shield-check-line"></i>

                            Secure Portal

                        </div>

                        <h2>Welcome back</h2>

                        <p>
                            Sign in to continue to your EcoScrap account.
                        </p>

                    </div>


                    <!-- PHP message -->
                    <?php
                    if (function_exists('displayMessage')) {
                        displayMessage();
                    }
                    ?>


                    <!-- =================================================
                         LOGIN FORM
                    ================================================== -->

                    <form
                        action="includes/login_process.php"
                        method="POST"
                        class="login-form"
                        autocomplete="on">


                        <!-- Email -->
                        <div class="form-group">

                            <label
                                for="email"
                                class="form-label">

                                Email Address

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="ri-mail-line input-icon">
                                </i>


                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-input"
                                    placeholder="name@example.com"
                                    required
                                    autocomplete="email">

                            </div>

                        </div>



                        <!-- Password -->
                        <div class="form-group">

                            <div class="password-label-row">

                                <label
                                    for="password"
                                    class="form-label">

                                    Password

                                </label>

                            </div>


                            <div class="input-wrapper">

                                <i
                                    class="ri-lock-2-line input-icon">
                                </i>


                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-input password-input"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password">


                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="passwordToggle"
                                    aria-label="Show password">

                                    <i
                                        id="eyeIcon"
                                        class="ri-eye-line">
                                    </i>

                                </button>

                            </div>

                        </div>



                        <!-- Options -->
                        <div class="form-options">

                            <label class="remember-me">

                                <input
                                    type="checkbox"
                                    name="remember">

                                <span class="checkmark"></span>

                                <span class="remember-text">
                                    Remember me
                                </span>

                            </label>


                            <a
                                href="#"
                                class="forgot-password">

                                Forgot password?

                            </a>

                        </div>



                        <!-- Login button -->
                        <button
                            type="submit"
                            class="login-button">

                            <span>Sign in</span>

                            <i class="ri-arrow-right-line"></i>

                        </button>

                    </form>



                    <!-- Divider -->
                    <div class="divider">

                        <span>Need an account?</span>

                    </div>



                    <!-- Registration -->
                    <div class="registration-options">


                        <!-- User -->
                        <a
                            href="user/register.php"
                            class="register-card">

                            <div class="register-icon user-icon">

                                <i class="ri-user-3-line"></i>

                            </div>

                            <div class="register-text">

                                <strong>I'm a User</strong>

                                <span>Create a user account</span>

                            </div>

                            <i class="ri-arrow-right-line register-arrow"></i>

                        </a>



                        <!-- Collector -->
                        <a
                            href="scrapcollector/register.php"
                            class="register-card">

                            <div class="register-icon collector-icon">

                                <i class="ri-truck-line"></i>

                            </div>

                            <div class="register-text">

                                <strong>I'm a Collector</strong>

                                <span>Join as a scrap collector</span>

                            </div>

                            <i class="ri-arrow-right-line register-arrow"></i>

                        </a>

                    </div>



                    <!-- Footer -->
                    <p class="login-footer">

                        By continuing, you agree to EcoScrap's
                        <a href="#">Terms</a>
                        and
                        <a href="#">Privacy Policy</a>.

                    </p>

                </div>

            </section>

        </div>

    </main>



    <!-- =========================================================
         PASSWORD TOGGLE
    ========================================================== -->

    <script>

        const passwordInput =
            document.getElementById("password");

        const passwordToggle =
            document.getElementById("passwordToggle");

        const eyeIcon =
            document.getElementById("eyeIcon");


        passwordToggle.addEventListener("click", function () {

            if (passwordInput.type === "password") {

                passwordInput.type = "text";

                eyeIcon.className =
                    "ri-eye-off-line";

                passwordToggle.setAttribute(
                    "aria-label",
                    "Hide password"
                );

            } else {

                passwordInput.type = "password";

                eyeIcon.className =
                    "ri-eye-line";

                passwordToggle.setAttribute(
                    "aria-label",
                    "Show password"
                );

            }

        });


        /* =====================================================
           INPUT FOCUS ANIMATION
        ====================================================== */

        const inputs =
            document.querySelectorAll(".form-input");


        inputs.forEach(function (input) {

            input.addEventListener("focus", function () {

                this.closest(".input-wrapper")
                    .classList.add("focused");

            });


            input.addEventListener("blur", function () {

                this.closest(".input-wrapper")
                    .classList.remove("focused");

            });

        });

    </script>

</body>

</html>