<?php

declare(strict_types=1);

/**
 * Standalone login form.
 *
 * Intentionally has no external asset dependencies (no app.css, no Quill).
 * All styles are inline so this page renders even when static assets are unavailable.
 *
 * Expected variables from the caller:
 *   string $loginError  - error message to display, or empty string.
 */

$loginError = isset($loginError) && is_string($loginError) ? $loginError : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daybook - Sign in</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 1rem;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
            padding: 2rem 2.5rem;
            width: 100%;
            max-width: 360px;
        }
        h1 {
            margin: 0 0 1.75rem;
            font-size: 1.5rem;
            font-weight: 600;
            color: #111;
        }
        .error {
            background: #fff0f0;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            color: #c00;
            font-size: .875rem;
            margin-bottom: 1.25rem;
            padding: .6rem .75rem;
        }
        label {
            display: block;
            font-size: .875rem;
            color: #444;
            margin-bottom: .3rem;
        }
        input[type="text"],
        input[type="password"] {
            display: block;
            width: 100%;
            padding: .5rem .65rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
            margin-bottom: 1rem;
            outline: none;
            transition: border-color .15s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #0071c5;
        }
        button[type="submit"] {
            display: block;
            width: 100%;
            padding: .6rem;
            background: #0071c5;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background .15s;
        }
        button[type="submit"]:hover { background: #005fa3; }
    </style>
</head>
<body>
<div class="card">
    <h1>Daybook</h1>

    <?php if ($loginError !== ''): ?>
        <div class="error"><?= htmlspecialchars($loginError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif ?>

    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="action" value="login">
        <label for="username">Username</label>
        <input type="text"
               id="username"
               name="username"
               autocomplete="username"
               required
               autofocus>
        <label for="password">Password</label>
        <input type="password"
               id="password"
               name="password"
               autocomplete="current-password"
               required>
        <button type="submit">Sign in</button>
    </form>
</div>
<script>
(function () {
    fetch('/sso.php', {credentials: 'include'})
        .then(function (r) { if (r.ok) window.location.reload(); })
        .catch(function () {});
}());
</script>
</body>
</html>
