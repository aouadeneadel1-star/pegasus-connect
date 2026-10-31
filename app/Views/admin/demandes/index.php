<?php
/**
 * @var list<array<string, mixed>> $demandes
 * @var array{statut: string, categorie: string, assignee: int} $filtres
 * @var array<string, string> $categories
 */
use App\Repositories\DemandeRepository;

$this->layout('admin', ['titre' => 'Demandes', 'menu' => 'demandes']);
$priorites = ['urgente' => 'badge-danger', 'haute' => 'badge-warning', 'normale' => ''];
?>
<div class="page-head"><h1>Demandes de contact</h1></div>
<form class="filtres" method="get" action="/admin/demandes">
    <div class="field"><label for="d-statut">Statut</label><select class="select select-sm" id="d-statut" name="statut"><option value="">En cours</option>
        <?php foreach (DemandeRepository::STATUTS as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($filtres['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="d-cat">Catégorie</label><select class="select select-sm" id="d-cat" name="categorie"><option value="">Toutes</option>
        <?php foreach ($categories as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($filtres['categorie'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
    <label class="check"><input type="checkbox" name="miennes" value="1"<?= coche($filtres['assignee'] > 0) ?>> Qui me sont attribuées</label>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
</form>
<?php if ($demandes === []): ?>
    <div class="card empty">Aucune demande.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Objet</th><th scope="col">Demandeur</th><th scope="col">Catégorie</th><th scope="col">Reçue</th><th scope="col">Attribuée à</th><th scope="col">Statut</th></tr></thead>
            <tbody>
                <?php foreach ($demandes as $demande): ?>
                    <tr>
                        <td><a href="/admin/demandes/<?= e($demande['id']) ?>"><?= e($demande['objet']) ?></a> <?= $demande['priorite'] !== 'normale' ? badge(ucfirst((string) $demande['priorite']), $priorites[$demande['priorite']]) : '' ?></td>
                        <td><?= e($demande['demandeur']) ?></td>
                        <td><?= e(DemandeRepository::CATEGORIES[$demande['categorie']] ?? '') ?></td>
                        <td><?= e(date_heure((string) $demande['created_at'])) ?></td>
                        <td><?= e($demande['assignee'] ?? '—') ?></td>
                        <td><?= badge(DemandeRepository::STATUTS[$demande['statut']] ?? $demande['statut'], $demande['statut'] === 'nouvelle' ? 'badge-info' : '') ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
