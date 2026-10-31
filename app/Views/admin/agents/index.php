<?php

use App\Services\Agents\Echeance;

/**
 * @var list<array<string, mixed>> $agents
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array{q: string, site: int, statut: string, echeance: string} $filtres
 * @var list<array{id: int, nom: string}> $sites
 * @var bool $peutGerer
 */
$this->layout('admin', ['titre' => 'Agents', 'menu' => 'agents']);
?>
<div class="page-head">
    <h1>Agents <span class="muted">(<?= e($total) ?>)</span></h1>
    <?php if ($peutGerer): ?>
        <a class="btn btn-primary btn-sm" href="/admin/agents/nouveau"><?= icone('user-plus', 'icon-sm') ?>Nouvel agent</a>
    <?php endif ?>
</div>

<form class="filtres" method="get" action="/admin/agents">
    <div class="field">
        <label for="f-q">Recherche</label>
        <input class="input input-sm" id="f-q" name="q" type="search" value="<?= e($filtres['q']) ?>" placeholder="Nom, matricule…">
    </div>
    <div class="field">
        <label for="f-site">Site</label>
        <select class="select select-sm" id="f-site" name="site">
            <option value="">Tous les sites</option>
            <?php foreach ($sites as $site): ?>
                <option value="<?= e($site['id']) ?>"<?= selectionne($filtres['site'] === $site['id']) ?>><?= e($site['nom']) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="field">
        <label for="f-statut">Statut</label>
        <select class="select select-sm" id="f-statut" name="statut">
            <option value="">Tous</option>
            <?php foreach (['actif' => 'Actifs', 'invite' => 'Invités', 'suspendu' => 'Suspendus', 'desactive' => 'Désactivés'] as $valeur => $libelle): ?>
                <option value="<?= e($valeur) ?>"<?= selectionne($filtres['statut'] === $valeur) ?>><?= e($libelle) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="field">
        <label for="f-echeance">Carte pro</label>
        <select class="select select-sm" id="f-echeance" name="echeance">
            <option value="">Toutes</option>
            <?php foreach (['expire' => 'Expirées', '7' => 'Moins de 7 jours', '30' => 'Moins de 30 jours', '90' => 'Moins de 90 jours'] as $valeur => $libelle): ?>
                <option value="<?= e($valeur) ?>"<?= selectionne($filtres['echeance'] === $valeur) ?>><?= e($libelle) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
</form>

<?php if ($agents === []): ?>
    <div class="card empty">Aucun agent ne correspond à ces critères.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Matricule</th>
                    <th scope="col">Nom</th>
                    <th scope="col">Fonction</th>
                    <th scope="col">Site principal</th>
                    <th scope="col">Carte pro</th>
                    <th scope="col">SST</th>
                    <th scope="col">Compte</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $agent): ?>
                    <tr>
                        <td class="mono"><?= e($agent['matricule']) ?></td>
                        <td><a href="/admin/agents/<?= e($agent['id']) ?>"><?= e($agent['nom']) ?> <?= e($agent['prenom']) ?></a></td>
                        <td><?= e($agent['fonction']) ?><?= $agent['role_code'] === 'chef' ? ' · <span class="muted">Chef d\'équipe</span>' : '' ?></td>
                        <td><?= e($agent['site_principal'] ?? '—') ?></td>
                        <td><?= $agent['carte_pro_expiration'] === null ? badge('Absente', 'badge-danger') : badge_echeance(Echeance::statut((string) $agent['carte_pro_expiration'])) ?></td>
                        <td><?= $agent['sst_echeance'] === null ? '<span class="muted">—</span>' : badge_echeance(Echeance::statut((string) $agent['sst_echeance'])) ?></td>
                        <td><?= badge_compte((string) $agent['statut']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pages">
            <span>Page <?= e($page) ?> sur <?= e($pages) ?></span>
            <span class="row">
                <?php if ($page > 1): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(url_page('/admin/agents', $filtres, $page - 1)) ?>">Précédente</a>
                <?php endif ?>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(url_page('/admin/agents', $filtres, $page + 1)) ?>">Suivante</a>
                <?php endif ?>
            </span>
        </nav>
    <?php endif ?>
<?php endif ?>
