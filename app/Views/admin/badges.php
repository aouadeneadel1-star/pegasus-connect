<?php
/**
 * @var list<array<string, mixed>> $badges
 * @var array{q: string, statut: string} $filtres
 * @var string $prochainNumero
 */
$this->layout('admin', ['titre' => 'Badges', 'menu' => 'badges']);
$statuts = ['actif' => ['Actif', 'badge-success'], 'stock' => ['En stock', ''], 'perdu' => ['Perdu', 'badge-warning'], 'vole' => ['Volé', 'badge-danger'], 'desactive' => ['Désactivé', ''], 'restitue' => ['Restitué', '']];
?>
<div class="page-head">
    <h1>Badges NFC</h1>
    <p class="muted small">Prochain numéro disponible : <span class="mono"><?= e($prochainNumero) ?></span>. Un badge s'émet depuis la fiche de l'agent, onglet « Badge NFC ».</p>
</div>

<form class="filtres" method="get" action="/admin/badges">
    <div class="field"><label for="b-q">Recherche</label><input class="input input-sm" id="b-q" name="q" type="search" value="<?= e($filtres['q']) ?>" placeholder="Numéro, nom, matricule"></div>
    <div class="field"><label for="b-statut">Statut</label>
        <select class="select select-sm" id="b-statut" name="statut">
            <option value="">Tous</option>
            <?php foreach ($statuts as $code => [$libelle]): ?><option value="<?= e($code) ?>"<?= selectionne($filtres['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?>
        </select>
    </div>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
</form>

<?php if ($badges === []): ?>
    <div class="card empty">Aucun badge.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Numéro</th><th scope="col">Agent</th><th scope="col">Puce</th><th scope="col">PIN</th><th scope="col">Statut</th><th scope="col">Dernière utilisation</th></tr></thead>
            <tbody>
                <?php foreach ($badges as $badge): ?>
                    <?php [$libelle, $classe] = $statuts[$badge['statut']] ?? [$badge['statut'], '']; ?>
                    <tr>
                        <td class="mono"><?= e($badge['numero']) ?></td>
                        <td><?php if ($badge['agent_id'] !== null): ?><a href="/admin/agents/<?= e($badge['agent_id']) ?>?onglet=badge"><?= e($badge['nom']) ?> <?= e($badge['prenom']) ?></a><?php else: ?>—<?php endif ?></td>
                        <td class="mono"><?= $badge['uid_fin'] === null ? badge('Non associée', 'badge-warning') : '…' . e($badge['uid_fin']) ?></td>
                        <td><?= $badge['pin_defini'] ? badge('Défini', 'badge-success') : badge('À définir') ?></td>
                        <td><?= badge($libelle, $classe) ?><?= $badge['motif_desactivation'] ? '<br><span class="muted small">' . e($badge['motif_desactivation']) . '</span>' : '' ?></td>
                        <td><?= $badge['derniere_utilisation'] === null ? '—' : e(date_heure((string) $badge['derniere_utilisation'])) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
