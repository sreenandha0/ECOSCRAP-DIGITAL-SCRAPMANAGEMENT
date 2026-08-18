(() => {
    "use strict";

    const sidebar = document.getElementById("dashboardSidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const menuButton = document.getElementById("mobileMenuButton");

    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    ).matches;

    function setSidebar(open) {
        if (!sidebar || !overlay || !menuButton) {
            return;
        }

        sidebar.classList.toggle("open", open);
        overlay.classList.toggle("visible", open);

        menuButton.setAttribute(
            "aria-expanded",
            String(open)
        );

        menuButton.setAttribute(
            "aria-label",
            open ? "Close navigation" : "Open navigation"
        );

        menuButton.innerHTML = open
            ? '<i class="ri-close-line"></i>'
            : '<i class="ri-menu-line"></i>';

        document.body.classList.toggle(
            "sidebar-is-open",
            open
        );
    }

    if (menuButton) {
        menuButton.addEventListener("click", () => {
            const isOpen = sidebar.classList.contains("open");
            setSidebar(!isOpen);
        });
    }

    if (overlay) {
        overlay.addEventListener("click", () => {
            setSidebar(false);
        });
    }

    document.querySelectorAll(".sidebar-link").forEach((link) => {
        link.addEventListener("click", () => {
            if (window.innerWidth <= 900) {
                setSidebar(false);
            }
        });
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            setSidebar(false);
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Animated statistic counters
    |--------------------------------------------------------------------------
    */

    function animateCounter(element) {
        const target = Number(element.dataset.value || 0);

        if (reducedMotion || target === 0) {
            element.textContent = target.toLocaleString();
            return;
        }

        const duration = 850;
        const startTime = performance.now();

        function update(currentTime) {
            const progress = Math.min(
                (currentTime - startTime) / duration,
                1
            );

            const eased = 1 - Math.pow(1 - progress, 3);

            element.textContent = Math.round(
                target * eased
            ).toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }

    document
        .querySelectorAll(".count-up")
        .forEach(animateCounter);

    /*
    |--------------------------------------------------------------------------
    | Environmental impact ring
    |--------------------------------------------------------------------------
    */

    const impactRing = document.querySelector(
        ".impact-ring"
    );

    if (impactRing && !reducedMotion) {
        const finalProgress = impactRing.style.getPropertyValue(
            "--progress"
        );

        impactRing.style.setProperty(
            "--progress",
            "0%"
        );

        window.requestAnimationFrame(() => {
            window.setTimeout(() => {
                impactRing.style.setProperty(
                    "--progress",
                    finalProgress
                );
            }, 250);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Profile button
    |--------------------------------------------------------------------------
    */

    const profileButton = document.querySelector(
        ".profile-button"
    );

    if (profileButton) {
        profileButton.addEventListener("click", () => {
            const open = profileButton.classList.toggle(
                "open"
            );

            profileButton.setAttribute(
                "aria-expanded",
                String(open)
            );
        });
    }

    document.addEventListener("click", (event) => {
        if (
            profileButton &&
            !profileButton.contains(event.target)
        ) {
            profileButton.classList.remove("open");

            profileButton.setAttribute(
                "aria-expanded",
                "false"
            );
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Reset mobile sidebar after resizing
    |--------------------------------------------------------------------------
    */

    window.addEventListener("resize", () => {
        if (window.innerWidth > 900) {
            setSidebar(false);
        }
    });
})();