<?php
/**
 * @var list<array<string, mixed>> $lignes
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array<string, scalar> $filtres
 * @var list<string> $actions
 */
use App\Core\Clock;

$this->layout('admin', ['titre' => 'Journal d\'audit', 'menu' => 'audit']);
$query = http_build_query(array_filter($filtres, static fn ($v): bool => $v !== '' && $v !== 0));
?>
<div class="page-head">
    <h1>Journal d'audit</h1>
    <div class="row">
        <a class="btn btn-outline btn-sm" href="/admin/audit/export.csv<?= $query === '' ? '' : '?' . e($query) ?>"><?= icone('dl', 'icon-sm') ?>Exporter</a>
        <form method="post" action="/admin/audit/verifier"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= icone('shield', 'icon-sm') ?>Vérifier l'intégrité</button></form>
    </div>
</div>

<form class="filtres" method="get" action="/admin/audit">
    <div class="field"><label for="au-du">Du</label><input class="input input-sm" id="au-du" name="du" type="date" value="<?= e($filtres['du']) ?>"></div>
    <div class="field"><label for="au-au">Au</label><input class="input input-sm" id="au-au" name="au" type="date" value="<?= e($filtres['au']) ?>"></div>
    <div class="field"><label for="au-action">Action</label><input class="input input-sm" id="au-action" name="action" list="actions" value="<?= e($filtres['action']) ?>" placeholder="agent.nir">
        <datalist id="actions"><?php foreach ($actions as $action): ?><option value="<?= e($action) ?>"><?php endforeach ?></datalist></div>
    <div class="field"><label for="au-type">Objet</label><input class="input input-sm" id="au-type" name="objet_type" value="<?= e($filtres['objet_type']) ?>" placeholder="agent"></div>
    <div class="field"><label for="au-id">N°</label><input class="input input-sm" id="au-id" name="objet_id" type="number" min="1" value="<?= $filtres['objet_id'] ? e($filtres['objet_id']) : '' ?>"></div>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th scope="col">Date</th><th scope="col">Action</th><th scope="col">Objet</th><th scope="col">Utilisateur</th><th scope="col">Détails</th><th scope="col">IP</th></tr></thead>
        <tbody>
            <?php foreach ($lignes as $ligne): ?>
                <tr>
                    <td class="tnum nowrap"><?= e(Clock::fromSql((string) $ligne['created_at'])->setTimezone(Clock::local())->format('d/m/Y H:i:s')) ?></td>
                    <td class="mono small"><?= e($ligne['action']) ?></td>
                    <td class="small"><?= $ligne['objet_type'] ? e($ligne['objet_type']) . ' n° ' . e($ligne['objet_id']) : '—' ?></td>
                    <td class="small"><?= e($ligne['nom_complet'] ?? ($ligne['utilisateur_id'] ? 'n° ' . $ligne['utilisateur_id'] : 'Système')) ?><?= $ligne['role_code'] ? '<br><span class="muted">' . e($ligne['role_code']) . '</span>' : '' ?></td>
                    <td class="mono small detail-audit"><?= e($ligne['details'] ?? '') ?></td>
                    <td class="mono small"><?= e($ligne['ip'] ?? '') ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?= $this->insert('partials/pagination', ['chemin' => '/admin/audit', 'filtres' => $filtres, 'page' => $page, 'pages' => $pages, 'total' => $total]) ?>
