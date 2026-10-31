<?php
/**
 * @var string $chemin
 * @var array<string, scalar|null> $filtres
 * @var int $page
 * @var int $pages
 * @var int $total
 */
if ($pages <= 1) {
    return;
}
?>
<nav class="pagination" aria-label="Pages">
    <span><?= e(nombre($total)) ?> résultat(s) · page <?= e($page) ?> sur <?= e($pages) ?></span>
    <span class="row">
        <?php if ($page > 1): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url_page($chemin, $filtres, $page - 1)) ?>">Précédente</a>
        <?php endif ?>
        <?php if ($page < $pages): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url_page($chemin, $filtres, $page + 1)) ?>">Suivante</a>
        <?php endif ?>
    </span>
</nav>
