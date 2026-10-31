<?php
/**
 * @var list<array{id: int, nom: string}> $sites
 * @var array<string, mixed>|null $site
 * @var list<DateTimeImmutable> $jours
 * @var array<int, string> $agents
 * @var array<int, array<string, list<array<string, mixed>>>> $grille
 * @var string $lundi
 * @var string $precedente
 * @var string $suivante
 * @var int $brouillons
 * @var bool $peutGerer
 * @var bool $peutPublier
 * @var array<int, string> $agentsPlanifiables
 * @var array<string, mixed>|null $saisie
 */
use App\Core\Clock;

$this->layout('admin', ['titre' => 'Planning', 'menu' => 'planning', 'scripts' => ['planning.js']]);
$aujourdhui = Clock::now()->setTimezone(Clock::local())->format('Y-m-d');
?>
<div class="page-head">
    <h1>Planning</h1>
</div>

<?php if ($site === null): ?>
    <div class="card empty">Aucun site dans votre périmètre.</div>
<?php else: ?>
    <form class="filtres" method="get" action="/admin/planning">
        <div class="field"><label for="pl-site">Site</label>
            <select class="select select-sm" id="pl-site" name="site" data-soumettre>
                <?php foreach ($sites as $choix): ?><option value="<?= e($choix['id']) ?>"<?= selectionne((int) $site['id'] === $choix['id']) ?>><?= e($choix['nom']) ?></option><?php endforeach ?>
            </select></div>
        <input type="hidden" name="semaine" value="<?= e($lundi) ?>">
        <div class="row">
            <a class="btn btn-ghost btn-icon btn-sm" href="/admin/planning?site=<?= e($site['id']) ?>&amp;semaine=<?= e($precedente) ?>" aria-label="Semaine précédente"><?= icone('back', 'icon-sm') ?></a>
            <b>Semaine du <?= e((new DateTimeImmutable($lundi))->format('d/m/Y')) ?></b>
            <a class="btn btn-ghost btn-icon btn-sm" href="/admin/planning?site=<?= e($site['id']) ?>&amp;semaine=<?= e($suivante) ?>" aria-label="Semaine suivante"><?= icone('next', 'icon-sm') ?></a>
        </div>
        <noscript><button class="btn btn-outline btn-sm" type="submit">Afficher</button></noscript>
    </form>

    <?php if ($peutGerer): ?>
        <div class="row row-wrap row-between mb-4">
            <span class="muted small"><?= $brouillons > 0 ? e($brouillons) . ' vacation(s) en brouillon, non visibles des agents.' : 'Toutes les vacations de la semaine sont publiées.' ?></span>
            <div class="row row-wrap">
                <form method="post" action="/admin/planning/dupliquer" data-confirmer="Copier les vacations de cette semaine sur la semaine suivante (en brouillon) ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="site_id" value="<?= e($site['id']) ?>"><input type="hidden" name="semaine" value="<?= e($lundi) ?>">
                    <button class="btn btn-outline btn-sm" type="submit"><?= icone('copy', 'icon-sm') ?>Copier sur la semaine suivante</button>
                </form>
                <?php if ($peutPublier && $brouillons > 0): ?>
                    <form method="post" action="/admin/planning/publier" data-confirmer="Publier <?= e($brouillons) ?> vacation(s) ? Les agents concernés seront prévenus.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="site_id" value="<?= e($site['id']) ?>"><input type="hidden" name="semaine" value="<?= e($lundi) ?>">
                        <button class="btn btn-primary btn-sm" type="submit"><?= icone('send', 'icon-sm') ?>Publier la semaine</button>
                    </form>
                <?php endif ?>
            </div>
        </div>
    <?php endif ?>

    <div class="table-wrap">
        <table class="table calendrier">
            <thead>
                <tr>
                    <th scope="col">Agent</th>
                    <?php foreach ($jours as $jour): ?>
                        <th scope="col"<?= $jour->format('Y-m-d') === $aujourdhui ? ' class="aujourdhui"' : '' ?>><?= e(jour_court($jour)) ?> <?= e($jour->format('d/m')) ?></th>
                    <?php endforeach ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($agents === []): ?>
                    <tr><td colspan="8" class="muted">Aucun agent affecté à ce site.</td></tr>
                <?php endif ?>
                <?php foreach ($agents as $agentId => $nom): ?>
                    <tr>
                        <th scope="row"><a href="/admin/agents/<?= e($agentId) ?>"><?= e($nom) ?></a></th>
                        <?php foreach ($jours as $jour): ?>
                            <?php $cle = $jour->format('Y-m-d'); ?>
                            <td class="case<?= $cle === $aujourdhui ? ' aujourdhui' : '' ?>">
                                <?php foreach ($grille[$agentId][$cle] ?? [] as $vacation): ?>
                                    <button class="vacation<?= $vacation['statut'] === 'brouillon' ? ' brouillon' : '' ?><?= $vacation['force_motif'] ? ' forcee' : '' ?>" type="button"
                                            <?= $peutGerer ? '' : 'disabled' ?>
                                            data-vacation="<?= e(json_encode([
                                                'vacation_id' => (int) $vacation['id'],
                                                'agent_id' => (int) $vacation['agent_id'],
                                                'date' => $cle,
                                                'heure_debut' => heure((string) $vacation['debut']),
                                                'heure_fin' => heure((string) $vacation['fin']),
                                                'pause_minutes' => (int) $vacation['pause_minutes'],
                                                'poste' => (string) ($vacation['poste'] ?? ''),
                                                'commentaire' => (string) ($vacation['commentaire'] ?? ''),
                                            ], JSON_THROW_ON_ERROR)) ?>">
                                        <span class="tnum"><?= e(heure((string) $vacation['debut'])) ?>–<?= e(heure((string) $vacation['fin'])) ?></span>
                                        <?php if ($vacation['poste']): ?><span class="small"><?= e($vacation['poste']) ?></span><?php endif ?>
                                        <?php if ($vacation['statut'] === 'brouillon'): ?><span class="sr-only">(brouillon)</span><?php endif ?>
                                    </button>
                                <?php endforeach ?>
                                <?php if ($peutGerer): ?>
                                    <button class="ajouter-vacation" type="button" data-nouvelle-vacation="<?= e(json_encode(['agent_id' => $agentId, 'date' => $cle], JSON_THROW_ON_ERROR)) ?>" aria-label="Ajouter une vacation à <?= e($nom) ?> le <?= e($jour->format('d/m')) ?>"><?= icone('plus', 'icon-sm') ?></button>
                                <?php endif ?>
                            </td>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <p class="help">Légende : bordure pointillée = brouillon ; repère orange = vacation enregistrée malgré une alerte (repos, durée, qualification).</p>

    <?php if ($peutGerer): ?>
        <dialog class="dialog" data-dialog-vacation aria-labelledby="titre-vacation"<?= $saisie !== null ? ' data-ouvrir="' . e(json_encode($saisie, JSON_THROW_ON_ERROR)) . '"' : '' ?>>
            <form class="stack" method="post" action="/admin/planning/vacations" data-formulaire-vacation>
                <?= csrf_field() ?>
                <h2 id="titre-vacation">Vacation</h2>
                <div class="alert alert-warning" data-messages hidden><?= icone('alert') ?><ul class="alert-body"></ul></div>
                <input type="hidden" name="site_id" value="<?= e($site['id']) ?>">
                <input type="hidden" name="vacation_id" value="">
                <div class="field"><label for="v-agent">Agent *</label>
                    <select class="select" id="v-agent" name="agent_id" required>
                        <?php foreach ($agentsPlanifiables as $id => $nom): ?><option value="<?= e($id) ?>"><?= e($nom) ?></option><?php endforeach ?>
                    </select></div>
                <div class="form-grid">
                    <div class="field"><label for="v-date">Date *</label><input class="input" id="v-date" name="date" type="date" required></div>
                    <div class="field"><label for="v-pause">Pause (min)</label><input class="input" id="v-pause" name="pause_minutes" type="number" min="0" max="240" value="0"></div>
                    <div class="field"><label for="v-debut">Début *</label><input class="input" id="v-debut" name="heure_debut" type="time" required></div>
                    <div class="field"><label for="v-fin">Fin *</label><input class="input" id="v-fin" name="heure_fin" type="time" required></div>
                </div>
                <p class="help">Une fin antérieure au début correspond au lendemain.</p>
                <div class="field"><label for="v-poste">Poste</label><input class="input" id="v-poste" name="poste" maxlength="60" placeholder="Accueil, rondier, SSIAP…"></div>
                <div class="field"><label for="v-commentaire">Commentaire</label><input class="input" id="v-commentaire" name="commentaire" maxlength="255"></div>
                <div class="stack stack-sm" data-forcer hidden>
                    <label class="check"><input type="checkbox" name="forcer" value="1"> Enregistrer malgré les alertes</label>
                    <div class="field"><label for="v-motif">Motif *</label><input class="input" id="v-motif" name="motif" maxlength="255"></div>
                </div>
                <div class="row row-between">
                    <button class="btn btn-ghost text-danger" type="submit" form="supprimer-vacation" data-supprimer hidden><?= icone('trash', 'icon-sm') ?>Supprimer</button>
                    <span class="row">
                        <button class="btn btn-ghost" type="button" data-fermer>Annuler</button>
                        <button class="btn btn-primary" type="submit">Enregistrer</button>
                    </span>
                </div>
            </form>
            <form id="supprimer-vacation" method="post" action="" data-formulaire-suppression data-confirmer="Supprimer cette vacation ? Si elle est publiée, l'agent sera prévenu.">
                <?= csrf_field() ?>
            </form>
        </dialog>
    <?php endif ?>
<?php endif ?>
