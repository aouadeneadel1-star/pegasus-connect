<?php
/**
 * @var string $vue
 * @var string $jour
 * @var array{site: int, statut: string, agent: int} $filtres
 * @var list<array{id: int, nom: string}> $sites
 * @var int $nbAVerifier
 * @var bool $peutCorriger
 * @var bool $peutValider
 * @var bool $appliqueDirectement
 * @var array<string, string> $motifs
 */
use App\Core\Clock;
use App\Services\Pointage\PointageService;

$this->layout('admin', ['titre' => 'Pointages', 'menu' => 'pointages', 'scripts' => ['rafraichir.js']]);
$onglets = ['jour' => 'Journée', 'a_verifier' => 'À vérifier (' . $nbAVerifier . ')', 'services' => 'Services en cours'];
if ($peutValider) {
    $onglets['corrections'] = 'Corrections à valider';
}
$modes = ['qr_dynamique' => 'QR borne', 'qr_statique' => 'QR fixe', 'badge_nfc' => 'Badge', 'manuel' => 'Manuel'];
$statuts = ['valide' => ['Valide', 'badge-success'], 'a_verifier' => ['À vérifier', 'badge-warning'], 'rejete' => ['Rejeté', 'badge-danger'], 'annule' => ['Annulé', ''], 'corrige' => ['Corrigé', 'badge-info']];
$retour = '/admin/pointages?vue=' . $vue;
$maintenantLocal = Clock::now()->setTimezone(Clock::local())->format('Y-m-d\TH:i');

$motifsSelect = static function (string $id, string $defaut = '') use ($motifs): string {
    $html = '<label class="sr-only" for="' . e($id) . '">Motif</label><select class="select select-sm" id="' . e($id) . '" name="motif_code" required>';
    foreach ($motifs as $code => $libelle) {
        $html .= '<option value="' . e($code) . '"' . selectionne($code === $defaut) . '>' . e($libelle) . '</option>';
    }

    return $html . '</select>';
};
$indicateurs = static fn (?string $valeur): string => implode(' ', array_map(
    static fn (string $code): string => badge(PointageService::libelleIndicateur($code), in_array($code, ['hors_zone', 'vitesse', 'appareil_partage', 'pin_hors_ligne', 'carte_pro_expiree'], true) ? 'badge-warning' : ''),
    PointageService::indicateurs($valeur),
));
?>
<div class="page-head">
    <h1>Pointages</h1>
    <a class="btn btn-outline btn-sm" href="/admin/pointages/export.csv<?= $filtres['site'] > 0 ? '?site=' . e($filtres['site']) : '' ?>"><?= icone('dl', 'icon-sm') ?>Exporter le mois (CSV)</a>
</div>

<nav class="tabs" aria-label="Vues">
    <?php foreach ($onglets as $cle => $libelle): ?>
        <a href="/admin/pointages?vue=<?= e($cle) ?>"<?= $vue === $cle ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
    <?php endforeach ?>
</nav>

<?php if ($vue === 'jour'): ?>
    <form class="filtres" method="get" action="/admin/pointages">
        <input type="hidden" name="vue" value="jour">
        <div class="field"><label for="f-date">Date</label><input class="input input-sm" id="f-date" name="date" type="date" value="<?= e($jour) ?>"></div>
        <div class="field"><label for="f-site">Site</label><select class="select select-sm" id="f-site" name="site"><option value="">Tous</option>
            <?php foreach ($sites as $site): ?><option value="<?= e($site['id']) ?>"<?= selectionne($filtres['site'] === $site['id']) ?>><?= e($site['nom']) ?></option><?php endforeach ?></select></div>
        <div class="field"><label for="f-statut">Statut</label><select class="select select-sm" id="f-statut" name="statut"><option value="">Tous</option>
            <?php foreach ($statuts as $code => [$libelle]): ?><option value="<?= e($code) ?>"<?= selectionne($filtres['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
        <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
    </form>
    <?php if ($pointages === []): ?>
        <div class="card empty">Aucun pointage ce jour.</div>
    <?php else: ?>
        <div class="table-wrap" data-rafraichir="<?= $jour === Clock::now()->setTimezone(Clock::local())->format('Y-m-d') ? '30' : '' ?>">
            <table class="table">
                <thead><tr><th scope="col">Heure</th><th scope="col">Agent</th><th scope="col">Site</th><th scope="col">Type</th><th scope="col">Mode</th><th scope="col">Distance</th><th scope="col">Statut</th><th scope="col">Indicateurs</th><?php if ($peutCorriger): ?><th scope="col"><span class="sr-only">Corriger</span></th><?php endif ?></tr></thead>
                <tbody>
                    <?php foreach ($pointages as $p): ?>
                        <?php [$libelle, $classe] = $statuts[$p['statut']] ?? [$p['statut'], '']; ?>
                        <tr>
                            <td class="tnum"><?= e(Clock::fromSql((string) $p['horodatage'])->setTimezone(Clock::local())->format('H:i:s')) ?><?= $p['hors_ligne'] ? ' ' . icone('wifi-off', 'icon-sm') : '' ?></td>
                            <td><a href="/admin/agents/<?= e($p['agent_id']) ?>?onglet=activite"><?= e($p['nom']) ?> <?= e($p['prenom']) ?></a></td>
                            <td><?= e($p['site']) ?><?= $p['borne'] ? '<br><span class="muted small">' . e($p['borne']) . '</span>' : '' ?></td>
                            <td><?= $p['type'] === 'arrivee' ? 'Arrivée' : 'Départ' ?></td>
                            <td><?= e($modes[$p['mode']] ?? $p['mode']) ?></td>
                            <td class="tnum"><?= $p['distance_site_m'] === null ? '—' : e($p['distance_site_m']) . ' m' ?></td>
                            <td><?= badge($libelle, $classe) ?></td>
                            <td><?= $indicateurs($p['indicateurs']) ?></td>
                            <?php if ($peutCorriger): ?>
                                <td>
                                    <?php if (!in_array($p['statut'], ['annule', 'rejete'], true)): ?>
                                        <details class="menu-compte">
                                            <summary class="btn btn-ghost btn-sm" aria-label="Corriger"><?= icone('edit', 'icon-sm') ?></summary>
                                            <form class="menu stack stack-sm" method="post" action="/admin/pointages/<?= e($p['id']) ?>/heure">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="retour" value="<?= e($retour) ?>">
                                                <label for="h-<?= e($p['id']) ?>">Heure corrigée</label>
                                                <input class="input input-sm" id="h-<?= e($p['id']) ?>" type="datetime-local" name="horodatage" max="<?= e($maintenantLocal) ?>" value="<?= e(Clock::fromSql((string) $p['horodatage'])->setTimezone(Clock::local())->format('Y-m-d\TH:i')) ?>" required>
                                                <?= $motifsSelect('mh-' . $p['id'], 'oubli') ?>
                                                <input class="input input-sm" name="motif_texte" maxlength="255" placeholder="Précision" required aria-label="Précision">
                                                <button class="btn btn-primary btn-sm" type="submit"><?= $appliqueDirectement ? 'Appliquer' : 'Proposer' ?></button>
                                            </form>
                                        </details>
                                    <?php endif ?>
                                </td>
                            <?php endif ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= $this->insert('partials/pagination', ['chemin' => '/admin/pointages', 'filtres' => [...$filtres, 'vue' => 'jour', 'date' => $jour], 'page' => $page, 'pages' => $pages, 'total' => $total]) ?>
    <?php endif ?>

<?php elseif ($vue === 'a_verifier'): ?>
    <?php if ($pointages === []): ?>
        <div class="card empty"><?= icone('check', 'icon-lg') ?><p>Aucun pointage à vérifier.</p></div>
    <?php else: ?>
        <div class="stack">
            <?php foreach ($pointages as $p): ?>
                <section class="card row row-wrap row-between">
                    <div>
                        <b><?= e($p['prenom']) ?> <?= e($p['nom']) ?></b> · <?= $p['type'] === 'arrivee' ? 'arrivée' : 'départ' ?> · <?= e($modes[$p['mode']] ?? $p['mode']) ?><br>
                        <span class="muted small"><?= e($p['site']) ?> · <?= e(date_heure((string) $p['horodatage'])) ?><?= $p['distance_site_m'] !== null ? ' · à ' . e($p['distance_site_m']) . ' m du site' : '' ?><?= $p['precision_m'] !== null ? ' (précision ' . e($p['precision_m']) . ' m)' : '' ?><?= $p['hors_ligne'] ? ' · hors ligne' : '' ?></span><br>
                        <?= $indicateurs($p['indicateurs']) ?: '<span class="small muted">Départ enregistré lors d\'un changement de site</span>' ?>
                    </div>
                    <?php if ($peutCorriger): ?>
                        <form class="row row-wrap" method="post" action="/admin/pointages/<?= e($p['id']) ?>/statuer">
                            <?= csrf_field() ?>
                            <input type="hidden" name="retour" value="<?= e($retour) ?>">
                            <?= $motifsSelect('ms-' . $p['id'], 'verification') ?>
                            <input class="input input-sm" name="motif_texte" maxlength="255" placeholder="Constat (obligatoire)" required aria-label="Constat">
                            <button class="btn btn-outline btn-sm" type="submit" name="decision" value="rejeter">Rejeter</button>
                            <button class="btn btn-primary btn-sm" type="submit" name="decision" value="valider"><?= icone('check', 'icon-sm') ?>Valider</button>
                        </form>
                    <?php endif ?>
                </section>
            <?php endforeach ?>
        </div>
    <?php endif ?>

<?php elseif ($vue === 'services'): ?>
    <div class="grid-2-1">
        <section class="card" data-rafraichir="60">
            <h2 class="card-header">Agents en service (<?= e(count($services)) ?>)</h2>
            <?php if ($services === []): ?>
                <p class="muted">Aucun service ouvert.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($services as $s): ?>
                        <li class="list-item row-top">
                            <span class="grow">
                                <span class="list-title"><?= e($s['prenom']) ?> <?= e($s['nom']) ?></span>
                                <span class="list-sub"><?= e($s['site']) ?> · depuis le <?= e(date_heure((string) $s['debut'])) ?><?= $s['fin_prevue'] ? ' · fin prévue ' . e(heure((string) $s['fin_prevue'])) : ' · hors planning' ?></span>
                                <?php if ($peutCorriger): ?>
                                    <details class="mt-2">
                                        <summary class="small">Saisir le départ oublié</summary>
                                        <form class="row row-wrap mt-2" method="post" action="/admin/services/<?= e($s['id']) ?>/depart">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="retour" value="<?= e($retour) ?>">
                                            <input class="input input-sm" type="datetime-local" name="horodatage" max="<?= e($maintenantLocal) ?>" required aria-label="Heure de départ">
                                            <?= $motifsSelect('md-' . $s['id'], 'oubli') ?>
                                            <input class="input input-sm" name="motif_texte" maxlength="255" placeholder="Précision" required aria-label="Précision">
                                            <button class="btn btn-primary btn-sm" type="submit"><?= $appliqueDirectement ? 'Appliquer' : 'Proposer' ?></button>
                                        </form>
                                    </details>
                                <?php endif ?>
                            </span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>

        <?php if ($peutCorriger): ?>
            <section class="card">
                <h2 class="card-header">Ajouter un service non pointé</h2>
                <form class="stack" method="post" action="/admin/services">
                    <?= csrf_field() ?>
                    <input type="hidden" name="retour" value="<?= e($retour) ?>">
                    <div class="field"><label for="as-agent">Agent *</label><select class="select" id="as-agent" name="agent_id" required>
                        <?php foreach ($agentsPerimetre as $agent): ?><option value="<?= e($agent['id']) ?>"><?= e($agent['nom']) ?></option><?php endforeach ?></select></div>
                    <div class="field"><label for="as-site">Site *</label><select class="select" id="as-site" name="site_id" required>
                        <?php foreach ($sites as $site): ?><option value="<?= e($site['id']) ?>"><?= e($site['nom']) ?></option><?php endforeach ?></select></div>
                    <div class="field"><label for="as-debut">Arrivée *</label><input class="input" id="as-debut" name="debut" type="datetime-local" max="<?= e($maintenantLocal) ?>" required></div>
                    <div class="field"><label for="as-fin">Départ *</label><input class="input" id="as-fin" name="fin" type="datetime-local" max="<?= e($maintenantLocal) ?>" required></div>
                    <div class="field"><label for="as-motif">Motif *</label><?= $motifsSelect('as-motif', 'panne_borne') ?></div>
                    <div class="field"><label for="as-texte">Précision *</label><input class="input" id="as-texte" name="motif_texte" maxlength="255" required></div>
                    <button class="btn btn-primary btn-sm" type="submit"><?= $appliqueDirectement ? 'Ajouter' : 'Proposer' ?></button>
                </form>
            </section>
        <?php endif ?>
    </div>

<?php else: ?>
    <?php if ($corrections === []): ?>
        <div class="card empty">Aucune correction en attente.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th scope="col">Demandée</th><th scope="col">Agent</th><th scope="col">Correction</th><th scope="col">Motif</th><th scope="col"><span class="sr-only">Décision</span></th></tr></thead>
                <tbody>
                    <?php foreach ($corrections as $c): ?>
                        <?php
                        $a = $c['apres'];
                        $description = match ($c['action']) {
                            'validation' => 'Valider le pointage',
                            'annulation' => 'Rejeter le pointage',
                            'modification' => 'Heure : ' . date_heure((string) $a['horodatage']),
                            default => ($a['type'] ?? '') === 'depart' ? 'Départ à ' . date_heure((string) $a['horodatage']) : 'Service du ' . date_heure((string) $a['debut']) . ' au ' . date_heure((string) $a['fin']),
                        };
                        ?>
                        <tr>
                            <td><?= e(date_heure((string) $c['created_at'])) ?><br><span class="muted small"><?= e($c['demandeur']) ?></span></td>
                            <td><?= e(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '')) ?></td>
                            <td><?= e($description) ?></td>
                            <td><?= e($motifs[$c['motif_code']] ?? $c['motif_code']) ?><br><span class="small"><?= e($c['motif_texte']) ?></span></td>
                            <td class="row row-end">
                                <form method="post" action="/admin/corrections/<?= e($c['id']) ?>/refuser"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">Refuser</button></form>
                                <form method="post" action="/admin/corrections/<?= e($c['id']) ?>/valider"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit">Appliquer</button></form>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
<?php endif ?>
