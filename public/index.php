<?php

declare(strict_types=1);

// TODO(mvc-lite): this file mixes routing, validation, data fetching, and HTML
// rendering. Planned refactor: extract POST handling to
// src/Controller/InstructionController.php, move the HTML page to
// templates/layout.php with a shared templates/partials/rows.php (fixes the
// DRY violation with api/rows.php), and extract inline JS to assets/app.js.

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Exception/AuthenticationException.php';
require_once __DIR__ . '/../src/Exception/AuthorizationException.php';
require_once __DIR__ . '/../src/Auth/Authenticator.php';
require_once __DIR__ . '/../src/functions.php';

apply_system_timezone();
App\Env::load(__DIR__ . '/../.env');

$authEnabled = strtolower(trim(App\Env::get('AUTH_ENABLED') ?? 'true')) !== 'false';
$currentUser = null;

if ($authEnabled) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
    session_start();

    $auth = new App\Auth\Authenticator([
        'LDAP_HOST'             => App\Env::require('LDAP_HOST'),
        'LDAP_PORT'             => App\Env::require('LDAP_PORT'),
        'LDAP_DOMAIN'           => App\Env::require('LDAP_DOMAIN'),
        'LDAP_BASE_DN'          => App\Env::require('LDAP_BASE_DN'),
        'LDAP_SERVICE_DN'       => App\Env::require('LDAP_SERVICE_DN'),
        'LDAP_SERVICE_PASSWORD' => App\Env::require('LDAP_SERVICE_PASSWORD'),
        'LDAP_REQUIRED_GROUP'   => App\Env::require('LDAP_REQUIRED_GROUP'),
    ]);

    // Kerberos SSO path: Apache validated identity, PHP must still check group membership.
    if (isset($_SERVER['REMOTE_USER']) && $auth->getAuthenticatedUser() === null) {
        try {
            $auth->verifyGroupMembership((string) $_SERVER['REMOTE_USER']);
            $auth->startSession((string) $_SERVER['REMOTE_USER']);
        } catch (App\Exception\AuthorizationException $e) {
            error_log('[Daybook] SSO authorization failed: ' . $e->getMessage());
            $loginError = 'Invalid credentials.';
            require __DIR__ . '/../templates/login.php';
            exit;
        } catch (App\Exception\AuthenticationException $e) {
            error_log('[Daybook] SSO authentication error: ' . $e->getMessage());
            $loginError = 'Authentication service unavailable. Try again later.';
            require __DIR__ . '/../templates/login.php';
            exit;
        }
    }

    // Login form POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
        $username   = trim((string) ($_POST['username'] ?? ''));
        $password   = (string) ($_POST['password'] ?? '');
        $loginError = '';
        try {
            $samAccountName = $auth->authenticateWithLdap($username, $password);
            if ($samAccountName !== false) {
                $auth->startSession($samAccountName);
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
            error_log('[Daybook] LDAP bind rejected for user: ' . $username);
            $loginError = 'Invalid credentials.';
        } catch (App\Exception\AuthorizationException $e) {
            error_log('[Daybook] Authorization failed: ' . $e->getMessage());
            $loginError = 'Invalid credentials.';
        } catch (App\Exception\AuthenticationException $e) {
            error_log('[Daybook] Authentication error: ' . $e->getMessage());
            $loginError = 'Authentication service unavailable. Try again later.';
        }
        require __DIR__ . '/../templates/login.php';
        exit;
    }

    // Logout POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
        $auth->logout();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Auth guard: show login form if no valid session exists.
    $currentUser = $auth->getAuthenticatedUser();
    if ($currentUser === null) {
        $loginError = '';
        require __DIR__ . '/../templates/login.php';
        exit;
    }
} else {
    $currentUser = 'local';
}

// --- Handle POST (PRG pattern) ---

$db    = get_db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $view   = $_POST['view']   ?? '';

    if ($action === 'add') {
        $date        = trim($_POST['date']        ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_rich     = ($_POST['is_rich'] ?? '0') === '1';

        if ($is_rich) {
            $description = sanitise_rich_html($description);
        }

        $empty = $description === '' || $description === '<p><br></p>';

        if ($date === '' || $empty) {
            $error = 'Date and description are required.';
        } else {
            add_instruction($db, $date, $description, $is_rich);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } elseif ($action === 'update') {
        $id          = (int) ($_POST['id']          ?? 0);
        $date        = trim($_POST['date']           ?? '');
        $description = trim($_POST['description']    ?? '');
        $is_rich     = ($_POST['is_rich'] ?? '0') === '1';

        if ($is_rich) {
            $description = sanitise_rich_html($description);
        }

        $empty = $description === '' || $description === '<p><br></p>';

        if ($id > 0 && $date !== '' && !$empty) {
            update_instruction($db, $id, $date, $description, $is_rich);
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            match ($action) {
                'delete'    => delete_instruction($db, $id),
                'archive'   => set_archived($db, $id, true),
                'unarchive' => set_archived($db, $id, false),
                default     => null,
            };
        }

        $redirect = $view === 'archived' ? '?view=archived' : '';
        header('Location: ' . $_SERVER['PHP_SELF'] . $redirect);
        exit;
    }
}

$sort          = ($_GET['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$show_archived = ($_GET['view'] ?? '') === 'archived';
$instructions  = fetch_instructions($db, $show_archived, $sort);
$has_logo      = file_exists(__DIR__ . '/assets/logo.png');

$emoji_langs = [];
foreach (glob(__DIR__ . '/assets/emoji-picker/*/emojibase/data.json') ?: [] as $p) {
    $emoji_langs[] = basename(dirname(dirname($p)));
}
sort($emoji_langs);
if (!in_array('en', $emoji_langs, true)) {
    $emoji_langs[] = 'en';
}

// Detect browser language from the Accept-Language header (e.g. "fr-FR,fr;q=0.9,en;q=0.8").
$accept_lang  = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$browser_lang = strtolower(substr($accept_lang, 0, 2));
$emoji_lang   = in_array($browser_lang, $emoji_langs, true) ? $browser_lang : 'en';

// Embed the i18n translations inline so JS never needs a dynamic import().
$emoji_i18n_js = 'null';
if ($emoji_lang !== 'en') {
    $i18n_file = __DIR__ . '/assets/emoji-picker/i18n/' . $emoji_lang . '.js';
    if (is_file($i18n_file)) {
        $raw           = file_get_contents($i18n_file);
        $stripped      = (string) preg_replace('/^\s*export\s+default\s+/u', '', trim((string) $raw));
        $emoji_i18n_js = rtrim($stripped, ';');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daybook</title>
    <link rel="stylesheet" href="assets/quill.snow.css">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>

<header class="page-header">
    <h1>Daybook</h1>
    <?php if ($has_logo): ?>
        <img src="assets/logo.png" alt="Logo" class="logo">
    <?php else: ?>
        <div class="logo-placeholder">Place logo here</div>
    <?php endif ?>
    <?php if ($authEnabled): ?>
        <div class="user-info">
            <span class="username"><?= h((string) $currentUser) ?></span>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-ghost">Logout</button>
            </form>
        </div>
    <?php endif ?>
</header>

<?php if ($error !== ''): ?>
    <div class="error"><?= h($error) ?></div>
<?php endif ?>

<form id="add-form" class="add-form" method="post" action="<?= h($_SERVER['PHP_SELF']) ?>">
    <input type="hidden" name="action"      value="add">
    <input type="hidden" name="is_rich"     value="1">
    <input type="hidden" name="description" id="description-input">
    <input type="datetime-local" name="date" value="<?= h(date('Y-m-d\TH:i')) ?>" required>
    <div class="editor-wrap">
        <div id="editor"></div>
    </div>
    <button type="submit" class="btn btn-primary">Add</button>
</form>

<div id="edit-backdrop" class="modal-backdrop">
    <div class="modal">
        <p class="modal-title">Edit instruction</p>
        <form id="edit-form" class="add-form" method="post" action="<?= h($_SERVER['PHP_SELF']) ?>">
            <input type="hidden" name="action"      value="update">
            <input type="hidden" name="id"          id="edit-id">
            <input type="hidden" name="is_rich"     value="1">
            <input type="hidden" name="description" id="edit-description-input">
            <input type="datetime-local" name="date" id="edit-date" required>
            <div class="editor-wrap">
                <div id="edit-editor"></div>
            </div>
        </form>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" id="edit-cancel">Cancel</button>
            <button form="edit-form" type="submit" class="btn btn-primary">Save</button>
        </div>
    </div>
</div>

<div class="tabs">
    <a href="<?= h($_SERVER['PHP_SELF']) ?>?sort=<?= h($sort) ?>"
       class="tab <?= !$show_archived ? 'active' : '' ?>">Active</a>
    <a href="<?= h($_SERVER['PHP_SELF']) ?>?sort=<?= h($sort) ?>&view=archived"
       class="tab <?= $show_archived ? 'active' : '' ?>">Archived</a>
</div>

<table>
    <thead>
        <tr>
            <th><a href="<?= h($_SERVER['PHP_SELF']) ?>?sort=<?= $sort === 'asc' ? 'desc' : 'asc' ?><?= $show_archived ? '&view=archived' : '' ?>" class="sort-link">Date<span class="sort-arrow sort-<?= h($sort) ?>"></span></a></th>
            <th>Description</th>
            <?php if ($show_archived): ?>
                <th>Archived on</th>
            <?php endif ?>
            <th></th>
        </tr>
    </thead>
    <tbody id="instructions-body">
        <?php if ($instructions === []): ?>
            <tr>
                <td colspan="<?= $show_archived ? 4 : 3 ?>" class="empty">
                    <?= $show_archived
                        ? 'No archived instructions.'
                        : 'No instructions yet. Add one above.' ?>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($instructions as $row): ?>
                <tr>
                    <td class="date"><?= h(str_replace('T', ' ', (string) $row['date'])) ?></td>
                    <?php if ((int) $row['is_rich'] === 1): ?>
                        <td class="description rich"><?= $row['description'] ?></td>
                    <?php else: ?>
                        <td class="description plain"><?= h((string) $row['description']) ?></td>
                    <?php endif ?>
                    <?php if ($show_archived): ?>
                        <td class="archived-at"><?= h((string) ($row['archived_at'] ?? '')) ?></td>
                    <?php endif ?>
                    <td class="actions">
                        <?php if ($show_archived): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="action" value="unarchive">
                                <input type="hidden" name="id"     value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="view"   value="archived">
                                <button type="submit" class="btn btn-ghost">Restore</button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn btn-ghost edit-btn"
                                    data-id="<?= (int) $row['id'] ?>"
                                    data-date="<?= h(strlen((string) $row['date']) === 10 ? $row['date'] . 'T00:00' : (string) $row['date']) ?>"
                                    data-content="<?= h((string) $row['description']) ?>">Edit</button>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="id"     value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="btn btn-ghost">Archive</button>
                            </form>
                        <?php endif ?>
                        <form method="post" style="display:inline"
                              onsubmit="return confirm('Delete this instruction?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id"     value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="view"   value="<?= $show_archived ? 'archived' : '' ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        <?php endif ?>
    </tbody>
</table>

<script type="module" src="assets/emoji-picker/picker.js"></script>
<script src="assets/quill.js"></script>
<script>
(function () {
    var SizeStyle = Quill.import('attributors/style/size');
    SizeStyle.whitelist = ['11px', '13px', '16px', '20px', '28px', '40px'];
    Quill.register(SizeStyle, true);
    Quill.register(Quill.import('attributors/style/color'),      true);
    Quill.register(Quill.import('attributors/style/background'), true);

    var toolbar = [
        ['bold', 'italic', 'strike', 'link'],
        [{ color: [] }, { background: [] }],
        [{ size: ['11px', false, '16px', '20px', '28px', '40px'] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['clean']
    ];

    var quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Instruction description...',
        modules: { toolbar: toolbar }
    });

    var editQuill = new Quill('#edit-editor', {
        theme: 'snow',
        modules: { toolbar: toolbar }
    });

    document.getElementById('add-form').addEventListener('submit', function (e) {
        var html = quill.root.innerHTML.trim();
        if (html === '' || html === '<p><br></p>') {
            e.preventDefault();
            alert('Description is required.');
            return;
        }
        document.getElementById('description-input').value = html;
    });

    // Event delegation keeps edit buttons functional after tbody innerHTML swaps.
    document.getElementById('instructions-body').addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-btn');
        if (!btn) { return; }
        document.getElementById('edit-id').value   = btn.dataset.id;
        document.getElementById('edit-date').value = btn.dataset.date;
        editQuill.root.innerHTML                   = btn.dataset.content;
        document.getElementById('edit-backdrop').classList.add('open');
    });

    document.getElementById('edit-cancel').addEventListener('click', function () {
        document.getElementById('edit-backdrop').classList.remove('open');
    });

    document.getElementById('edit-backdrop').addEventListener('click', function (e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });

    document.getElementById('edit-form').addEventListener('submit', function (e) {
        var html = editQuill.root.innerHTML.trim();
        if (html === '' || html === '<p><br></p>') {
            e.preventDefault();
            alert('Description is required.');
            return;
        }
        document.getElementById('edit-description-input').value = html;
    });

    // --- Emoji picker ---

    customElements.whenDefined('emoji-picker').then(function () {
        var lang = <?= json_encode($emoji_lang) ?>;
        var i18n = <?= $emoji_i18n_js ?>;

        var activeQuill     = null;
        var activeSelection = null;

        var picker    = document.createElement('emoji-picker');
        picker.locale = lang;
        picker.dataSource = new URL('assets/emoji-picker/' + lang + '/emojibase/data.json', document.baseURI).href;
        if (i18n) { picker.i18n = i18n; }
        picker.style.display = 'none';
        document.body.appendChild(picker);

        document.addEventListener('click', function () { picker.style.display = 'none'; });
        picker.addEventListener('click',   function (e) { e.stopPropagation(); });

        picker.addEventListener('emoji-click', function (e) {
            var detail   = e.detail;
            var skinTone = detail.skinTone;
            var emojiChar = '';

            // Prefer the raw DB record's skins array: indexed 0..N-1, each {tone, unicode}.
            // detail.unicode is pre-computed but may fall back to base if the summary layer
            // filtered out skin variants (emojiSupportLevel check).
            if (skinTone > 0 && detail.emoji && Array.isArray(detail.emoji.skins)) {
                var skin = detail.emoji.skins.find(function (s) { return s.tone === skinTone; });
                if (skin) { emojiChar = skin.unicode || ''; }
            }
            if (!emojiChar) {
                emojiChar = detail.unicode ||
                    (detail.emoji && (detail.emoji.emoji || detail.emoji.unicode)) || '';
            }

            if (emojiChar && activeQuill && activeSelection !== null) {
                activeQuill.insertText(activeSelection.index, emojiChar, 'user');
                activeQuill.setSelection(activeSelection.index + emojiChar.length, 0);
            }
            picker.style.display = 'none';
        });

        function addEmojiToggle(q) {
            var container = q.getModule('toolbar').container;
            var span      = document.createElement('span');
            span.className = 'ql-formats';

            var btn = document.createElement('button');
            btn.type        = 'button';
            btn.className   = 'ql-emoji';
            btn.textContent = 'Emoji';

            btn.addEventListener('mousedown', function () {
                activeQuill     = q;
                activeSelection = q.getSelection() || { index: Math.max(0, q.getLength() - 1), length: 0 };
            });

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (picker.style.display !== 'none') {
                    picker.style.display = 'none';
                    return;
                }
                picker.style.display = 'block';
                var rect = btn.getBoundingClientRect();
                picker.style.top  = (rect.bottom + 4) + 'px';
                picker.style.left = Math.min(rect.left, window.innerWidth - picker.offsetWidth - 8) + 'px';
            });

            span.appendChild(btn);
            container.appendChild(span);
        }

        addEmojiToggle(quill);
        addEmojiToggle(editQuill);
    }); // customElements.whenDefined

    // --- Auto-refresh ---

    var refreshTbody    = document.getElementById('instructions-body');
    var refreshDate     = document.querySelector('#add-form input[type="datetime-local"]');
    var dateUserModified = false;

    refreshDate.addEventListener('input', function () { dateUserModified = true; });

    function nowDatetimeLocal() {
        var d   = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + '-'
            + pad(d.getMonth() + 1) + '-'
            + pad(d.getDate()) + 'T'
            + pad(d.getHours()) + ':'
            + pad(d.getMinutes());
    }

    function refreshList() {
        // Do not disrupt an open edit modal.
        if (document.getElementById('edit-backdrop').classList.contains('open')) { return; }

        var params = new URLSearchParams(window.location.search);
        fetch('api/rows.php?' + params.toString())
            .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
            .then(function (html) { refreshTbody.innerHTML = html; })
            .catch(function () { /* silently ignore network errors */ });

        if (!dateUserModified && document.activeElement !== refreshDate) {
            refreshDate.value = nowDatetimeLocal();
        }
    }

    setInterval(refreshList, 30000);
}());
</script>
</body>
</html>
