<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';

apply_system_timezone();

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

$db            = get_db();
$sort          = ($_GET['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$show_archived = ($_GET['view'] ?? '') === 'archived';
$instructions  = fetch_instructions($db, $show_archived, $sort);

?>
<?php if ($instructions === []): ?>
<tr>
    <td colspan="<?= $show_archived ? 4 : 3 ?>" class="empty">
        <?= $show_archived ? 'No archived instructions.' : 'No instructions yet. Add one above.' ?>
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
                <form method="post" action="/index.php" style="display:inline">
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
                <form method="post" action="/index.php" style="display:inline">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="id"     value="<?= (int) $row['id'] ?>">
                    <button type="submit" class="btn btn-ghost">Archive</button>
                </form>
            <?php endif ?>
            <form method="post" action="/index.php" style="display:inline"
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
