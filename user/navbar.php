<?php
/**
 * Shared navigation foundation for authenticated User pages.
 *
 * Include this component after session initialization. It deliberately renders
 * a top navigation only; the dashboard remains the sole User page with a
 * sidebar.
 */

if (!function_exists('ecoscrap_user_back_url')) {
    function ecoscrap_user_back_url(): string
    {
        $fallback = 'dashboard.php';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $requestHost = $_SERVER['HTTP_HOST'] ?? '';

        if ($referer === '' || $requestHost === '') {
            return $fallback;
        }

        $refererParts = parse_url($referer);

        if (
            $refererParts === false ||
            !isset($refererParts['scheme'], $refererParts['host']) ||
            !in_array(strtolower($refererParts['scheme']), ['http', 'https'], true)
        ) {
            return $fallback;
        }

        $refererHost = strtolower(
            $refererParts['host'] .
            (isset($refererParts['port']) ? ':' . $refererParts['port'] : '')
        );

        if (!hash_equals(strtolower($requestHost), $refererHost)) {
            return $fallback;
        }

        $allowedPages = [
            'dashboard.php',
            'create_request.php',
            'history.php',
            'track_status.php',
            'profile.php',
            'update_profile.php',
            'feedback.php',
        ];

        $page = basename((string) ($refererParts['path'] ?? ''));

        return in_array($page, $allowedPages, true) ? $page : $fallback;
    }
}

if (!function_exists('ecoscrap_render_user_back_button')) {
    function ecoscrap_render_user_back_button(string $label = 'Back'): void
    {
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars(ecoscrap_user_back_url(), ENT_QUOTES, 'UTF-8');

        echo '<a class="user-back-button" href="' . $safeUrl . '">'
            . '<i class="ri-arrow-left-line" aria-hidden="true"></i>'
            . '<span>' . $safeLabel . '</span>'
            . '</a>';
    }
}

if (!function_exists('ecoscrap_render_user_navbar')) {
    function ecoscrap_render_user_navbar(string $currentPage = ''): void
    {
        $name = trim((string) ($_SESSION['name'] ?? 'EcoScrap User'));
        $initial = strtoupper(substr($name, 0, 1));
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        $links = [
            'create_request.php' => ['Request Pickup', 'ri-add-circle-line'],
            'track_status.php' => ['Track Status', 'ri-route-line'],
            'history.php' => ['History', 'ri-history-line'],
        ];
        ?>
        <header class="user-navbar">
            <a href="dashboard.php" class="user-navbar-brand" aria-label="EcoScrap User dashboard">
                <span class="user-navbar-brand-mark"><i class="ri-leaf-line" aria-hidden="true"></i></span>
                Eco<span>Scrap</span>
            </a>

            <button
                type="button"
                class="user-navbar-menu-button"
                aria-label="Open navigation menu"
                aria-controls="userNavbarLinks"
                aria-expanded="false"
            >
                <i class="ri-menu-line" aria-hidden="true"></i>
            </button>

            <nav class="user-navbar-links" id="userNavbarLinks" aria-label="User navigation">
                <?php foreach ($links as $url => [$label, $icon]): ?>
                    <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"<?= $currentPage === $url ? ' aria-current="page"' : '' ?>>
                        <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="user-navbar-actions">
                <a class="user-navbar-notifications" href="dashboard.php" aria-label="View notifications on dashboard">
                    <i class="ri-notification-3-line" aria-hidden="true"></i>
                </a>
                <a class="user-navbar-profile" href="profile.php">
                    <span class="user-navbar-avatar" aria-hidden="true"><?= htmlspecialchars($initial ?: 'U', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="user-navbar-identity">
                        <strong><?= $safeName ?></strong>
                        <small>EcoScrap User</small>
                    </span>
                </a>
            </div>
        </header>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const menuButton = document.querySelector('.user-navbar-menu-button');
                const links = document.getElementById('userNavbarLinks');

                if (!menuButton || !links) return;

                menuButton.addEventListener('click', function () {
                    const isOpen = links.classList.toggle('is-open');
                    menuButton.setAttribute('aria-expanded', String(isOpen));
                });
            });
        </script>
        <?php
    }
}
?>
