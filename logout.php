<?php
session_start();

/* =====================
   NO-CACHE HEADERS
   (applied to every response from this endpoint)
===================== */
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

/**
 * The actual logout (clearing session data, deleting the cookie,
 * destroying the session) only ever runs on a POST request.
 *
 * A plain GET (e.g. someone just navigating to this URL, or a
 * stray <img>/<a> pointing at it from another page) will NOT by
 * itself end the session -- it only shows the transition screen
 * below, which submits the real POST itself. This closes off the
 * simplest form of forced/CSRF logout via a bare link or image tag.
 */
if (!$isPost) {
    render_logout_transition();
    exit();
}

/* =====================
   CLEAR SESSION DATA
===================== */
$_SESSION = [];

/* =====================
   DELETE SESSION COOKIE
===================== */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => 'Lax',
    ]);
}

/* =====================
   DESTROY SESSION
===================== */
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

/* =====================
   REDIRECT TO LOGIN
===================== */
header("Location: login?loggedout=1");
exit();

/**
 * Renders the branded "Signing you out…" transition.
 * Auto-submits a hidden POST form to this same page after a short
 * delay so the real logout happens via POST. Degrades gracefully
 * to a visible button if JavaScript is unavailable.
 */
function render_logout_transition(): void
{
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing out…</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        html, body {
            margin: 0;
            height: 100%;
            font-family: 'Public Sans', system-ui, sans-serif;
            background: #3d6fd4;
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.1rem;
        }

        .loading-overlay .spinner {
            width: 42px;
            height: 42px;
            border: 3.5px solid rgba(255,255,255,0.3);
            border-top-color: #FFFFFF;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        .loading-overlay p {
            color: #FFFFFF;
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            margin: 0;
        }

        .fallback-btn {
            margin-top: 0.5rem;
            padding: 0.7rem 1.4rem;
            border-radius: 6px;
            border: none;
            background: #FFFFFF;
            color: #3d6fd4;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .fallback-btn:hover { background: #eef3ff; }

        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div class="loading-overlay">
        <div class="spinner"></div>
        <p>Signing you out…</p>

        <form id="logoutForm" method="POST">
            <noscript>
                <button type="submit" class="fallback-btn">Click to finish signing out</button>
            </noscript>
        </form>
    </div>

    <script>
        var form = document.getElementById('logoutForm');
        setTimeout(function () {
            form.submit();
        }, 1100);
    </script>

</body>
</html>
HTML;
}