<?php
/**
 * @var string $espace
 * @var string $prefixe
 * @var list<array<string, mixed>> $notifications
 */
$this->layout($espace, ['titre' => 'Notifications', 'menu' => 'notifications', 'onglet' => '', 'retour' => $espace === 'agent' ? '/' : null]);
$nonLues = count(array_filter($notifications, static fn (array $n): bool => $n['lue_le'] === null));
?>
<?php if ($espace !== 'agent'): ?>
    <div class="page-head">
        <h1>Notifications</h1>
    </div>
<?php endif ?>

<?php if ($nonLues > 0): ?>
    <form method="post" action="<?= e($prefixe) ?>/notifications/lues" class="row row-end mb-4">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit"><?= icone('check', 'icon-sm') ?>Tout marquer comme lu</button>
    </form>
<?php endif ?>

<?php if ($notifications === []): ?>
    <div class="card empty"><?= icone('bell', 'icon-lg') ?><p>Aucune notification pour le moment.</p></div>
<?php else: ?>
    <ul class="list card">
        <?php foreach ($notifications as $notification): ?>
            <li>
                <a class="list-item<?= $notification['lue_le'] === null ? ' is-current' : '' ?>" href="<?= e($prefixe) ?>/notifications/<?= e($notification['id']) ?>">
                    <span class="list-icon<?= $notification['lue_le'] === null ? ' brand' : '' ?>"><?= icone(str_contains((string) $notification['type'], 'anomalie') || str_contains((string) $notification['type'], 'absence') ? 'alert' : 'bell') ?></span>
                    <span class="grow">
                        <span class="list-title"><?= e($notification['titre']) ?></span>
                        <?php if (!empty($notification['message'])): ?>
                            <span class="list-sub"><?= e($notification['message']) ?></span>
                        <?php endif ?>
                        <span class="list-sub small"><?= e(date_heure((string) $notification['created_at'])) ?></span>
                    </span>
                    <?php if ($notification['lue_le'] === null): ?><span class="sr-only">Non lue</span><?php endif ?>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
