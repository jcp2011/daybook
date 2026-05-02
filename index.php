<?php
declare(strict_types=1);

require_once __DIR__ . '/src/functions.php';

apply_system_timezone();

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daybook</title>
    <link rel="stylesheet" href="assets/quill.snow.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 14px;
            background: #f5f5f5;
            color: #1a1a1a;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .logo { max-height: 60px; max-width: 200px; object-fit: contain; display: block; }

        .logo-placeholder {
            width: 120px;
            height: 60px;
            border: 2px dashed #d1d5db;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: .75rem;
            font-style: italic;
        }

        h1 { font-size: 1.4rem; font-weight: 600; }

        .add-form {
            display: flex;
            gap: .5rem;
            margin-bottom: 1.5rem;
            align-items: flex-start;
        }

        .add-form input[type="datetime-local"] {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: .4rem .6rem;
            font: inherit;
            background: #fff;
            width: 190px;
            align-self: flex-start;
        }

        .editor-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .editor-wrap .ql-toolbar {
            border-color: #d1d5db;
            border-radius: 4px 4px 0 0;
            background: #f9fafb;
        }

        .editor-wrap .ql-container {
            border-color: #d1d5db;
            border-radius: 0 0 4px 4px;
            font-family: inherit;
            font-size: 14px;
            min-height: 60px;
            max-height: 160px;
            overflow-y: auto;
        }

        .tabs {
            display: flex;
            gap: .25rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .tab {
            padding: .4rem .9rem;
            text-decoration: none;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .tab.active { color: #1a1a1a; border-bottom-color: #1a1a1a; font-weight: 500; }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }

        th {
            background: #f9fafb;
            text-align: left;
            padding: .6rem .9rem;
            font-weight: 600;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: .65rem .9rem;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }

        td.date { white-space: nowrap; color: #6b7280; width: 150px; }
        td.archived-at { white-space: nowrap; color: #9ca3af; width: 140px; font-size: .8rem; }
        td.description { word-break: break-word; }
        td.description.plain { white-space: pre-wrap; }
        td.description.rich p { margin: 0 0 .25em; }
        td.description.rich ol,
        td.description.rich ul { padding-left: 1.5em; margin: 0 0 .25em; }
        td.actions { white-space: nowrap; width: 1%; text-align: right; }

        .btn {
            display: inline-block;
            padding: .3rem .65rem;
            border: 1px solid transparent;
            border-radius: 4px;
            font: inherit;
            font-size: .8rem;
            cursor: pointer;
            line-height: 1.4;
        }

        .btn-primary { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }
        .btn-primary:hover { background: #333; }

        .btn-ghost { background: transparent; color: #6b7280; border-color: #d1d5db; }
        .btn-ghost:hover { color: #1a1a1a; border-color: #9ca3af; }

        .btn-danger { background: transparent; color: #dc2626; border-color: #fca5a5; }
        .btn-danger:hover { background: #fef2f2; }

        .btn + .btn { margin-left: .3rem; }

        .error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #dc2626;
            padding: .5rem .9rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .empty { color: #9ca3af; text-align: center; padding: 2rem; }

        .sort-link { text-decoration: none; color: inherit; display: inline-flex; align-items: center; gap: .35rem; }
        .sort-link:hover { color: #1a1a1a; }
        .sort-arrow { display: inline-block; width: 0; height: 0; }
        .sort-asc  { border-left: 4px solid transparent; border-right: 4px solid transparent; border-bottom: 5px solid #6b7280; }
        .sort-desc { border-left: 4px solid transparent; border-right: 4px solid transparent; border-top: 5px solid #6b7280; }

        .emoji-panel {
            position: fixed;
            z-index: 200;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            padding: .75rem;
            width: 284px;
            max-height: 300px;
            overflow-y: auto;
        }

        .emoji-cat {
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #9ca3af;
            margin: .5rem 0 .25rem;
        }

        .emoji-cat:first-child { margin-top: 0; }

        .emoji-grid { display: flex; flex-wrap: wrap; gap: 2px; }

        .emoji-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.25rem;
            padding: .2rem;
            border-radius: 4px;
            line-height: 1;
        }

        .emoji-btn:hover { background: #f3f4f6; }

        .ql-toolbar .ql-emoji { width: auto; padding: 3px 7px; font-size: .75rem; font-family: inherit; }

        .ql-snow .ql-picker.ql-size .ql-picker-label::before,
        .ql-snow .ql-picker.ql-size .ql-picker-item::before { content: 'Normal'; }

        .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="11px"]::before,
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="11px"]::before { content: '11px'; }
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="11px"]::before { font-size: 11px; }

        .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="16px"]::before,
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="16px"]::before { content: '16px'; }
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="16px"]::before { font-size: 16px; }

        .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="20px"]::before,
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="20px"]::before { content: '20px'; }
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="20px"]::before { font-size: 20px; }

        .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="28px"]::before,
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="28px"]::before { content: '28px'; }
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="28px"]::before { font-size: 28px; }

        .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="40px"]::before,
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="40px"]::before { content: '40px'; }
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="40px"]::before { font-size: 40px; }

        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.35);
            z-index: 100;
        }

        .modal-backdrop.open {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            width: min(640px, 90vw);
            box-shadow: 0 8px 32px rgba(0,0,0,.15);
        }

        .modal-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .modal .add-form { margin-bottom: 0; }

        .modal-actions {
            display: flex;
            gap: .5rem;
            justify-content: flex-end;
            margin-top: .75rem;
        }
    </style>
</head>
<body>

<header class="page-header">
    <h1>Daybook</h1>
    <?php if ($has_logo): ?>
        <img src="assets/logo.png" alt="Logo" class="logo">
    <?php else: ?>
        <div class="logo-placeholder">Place logo here</div>
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
    <tbody>
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

<script src="assets/quill.js"></script>
<script>
(function () {
    var SizeStyle = Quill.import('attributors/style/size');
    SizeStyle.whitelist = ['11px', '13px', '16px', '20px', '28px', '40px'];
    Quill.register(SizeStyle, true);
    Quill.register(Quill.import('attributors/style/color'),      true);
    Quill.register(Quill.import('attributors/style/background'), true);

    var toolbar = [
        ['bold', 'italic', 'link'],
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

    document.querySelectorAll('.edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit-id').value    = btn.dataset.id;
            document.getElementById('edit-date').value  = btn.dataset.date;
            editQuill.root.innerHTML                    = btn.dataset.content;
            document.getElementById('edit-backdrop').classList.add('open');
        });
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

    var EMOJIS = [
        { label: 'Smileys', items: ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','😉','😌','😍','🥰','😘','😋','😛','😝','😜','🤓','😎','🥳','😏','😒','😞','😔','😟','😕','😣','😫','😩','🥺','😢','😭','😤','😠','😡','🤯','😳','😱','😨','😰','😥','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😮','😲','🥱','😴','🤤','😵','🤢','🤮','🤧','😷','🤒','🤕'] },
        { label: 'Hands',   items: ['👍','👎','👏','🙌','🤝','👌','🤞','🤟','🤘','🤙','👈','👉','👆','👇','👋','✋','🖖','💪','🤲','✊','👊'] },
        { label: 'Hearts',  items: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💕','💞','💓','💗','💖','💝','💘','💟','❣️','💔'] },
        { label: 'Symbols', items: ['⭐','🌟','✨','💫','🎉','🎊','🎁','🔥','💯','✅','❌','⚡','💡','🌈','🏆','🎯','💎','🔑','🎵','🎶','📝','📌','💬','❓','❗'] },
    ];

    var activeQuill     = null;
    var activeSelection = null;

    var emojiPanel = (function () {
        var p = document.createElement('div');
        p.className    = 'emoji-panel';
        p.style.display = 'none';

        EMOJIS.forEach(function (cat) {
            var heading = document.createElement('p');
            heading.className   = 'emoji-cat';
            heading.textContent = cat.label;
            p.appendChild(heading);

            var grid = document.createElement('div');
            grid.className = 'emoji-grid';

            cat.items.forEach(function (emoji) {
                var btn = document.createElement('button');
                btn.type        = 'button';
                btn.className   = 'emoji-btn';
                btn.textContent = emoji;
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (activeQuill && activeSelection !== null) {
                        activeQuill.insertText(activeSelection.index, emoji, 'user');
                        activeQuill.setSelection(activeSelection.index + emoji.length, 0);
                    }
                    p.style.display = 'none';
                });
                grid.appendChild(btn);
            });

            p.appendChild(grid);
        });

        document.body.appendChild(p);
        return p;
    }());

    document.addEventListener('click', function () { emojiPanel.style.display = 'none'; });
    emojiPanel.addEventListener('click', function (e) { e.stopPropagation(); });

    function addEmojiToggle(q) {
        var toolbar = q.getModule('toolbar').container;
        var span    = document.createElement('span');
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
            if (emojiPanel.style.display !== 'none') {
                emojiPanel.style.display = 'none';
                return;
            }
            var rect = btn.getBoundingClientRect();
            emojiPanel.style.top     = (rect.bottom + 4) + 'px';
            emojiPanel.style.left    = Math.min(rect.left, window.innerWidth - 292) + 'px';
            emojiPanel.style.display = 'block';
        });

        span.appendChild(btn);
        toolbar.appendChild(span);
    }

    addEmojiToggle(quill);
    addEmojiToggle(editQuill);
}());
</script>
</body>
</html>
