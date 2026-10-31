<?php
/**
 * @var array<string, mixed> $agent
 * @var array<string, mixed>|null $badge
 * @var list<array<string, mixed>> $historiqueBadges
 * @var bool $peutGererBadges
 * @var list<array<string, mixed>> $bornes bornes équipées d'un lecteur, sur les sites de l'agent
 * @var array<string, mixed>|null $carte
 */
$statuts = [
    'stock' => ['En stock', ''],
    'actif' => ['Actif', 'badge-success'],
    'perdu' => ['Perdu', 'badge-warning'],
    'vole' => ['Volé', 'badge-danger'],
    'desactive' => ['Désactivé', ''],
    'restitue' => ['Restitué', ''],
];
$enAttente = $badge !== null && $badge['association_expire_le'] !== null
    && \App\Core\Clock::fromSql((string) $badge['association_expire_le']) > \App\Core\Clock::now();

$selectBornes = static function (string $id) use ($bornes): string {
    $html = '<select class="select select-sm" id="' . e($id) . '" name="borne_id" required>';
    foreach ($bornes as $borne) {
        $html .= '<option value="' . e($borne['id']) . '">' . e($borne['site'] . ' · ' . $borne['libelle']) . '</option>';
    }

    return $html . '</select>';
};
?>
<div class="grid-2-1">
    <div class="stack stack-lg">
        <section class="card">
            <h2 class="card-header">Badge actuel</h2>
            <?php if ($badge === null): ?>
                <p class="muted">Aucun badge actif. L'agent pointe uniquement par QR code.</p>
            <?php else: ?>
                <div class="row row-top row-wrap">
                    <div class="carte-badge" aria-hidden="true">
                        <div class="bandeau"><img src="<?= e(asset('img/ecusson.png')) ?>" alt="">PEGASUS INSURE</div>
                        <div class="corps">
                            <span class="photo"></span>
                            <span>
                                <span class="lib">Agent de sécurité</span><br>
                                <span class="nom"><?= e(mb_strtoupper((string) $agent['nom'])) ?> <?= e($agent['prenom']) ?></span><br>
                                <span class="lib">Carte pro</span><br>
                                <span class="mono small"><?= e($badge['carte_pro_numero'] ?? $carte['numero'] ?? '—') ?></span><br>
                                <span class="lib">N° <?= e($badge['numero']) ?></span>
                            </span>
                        </div>
                    </div>
                    <dl class="dl grow">
                        <dt>Numéro</dt><dd class="mono"><?= e($badge['numero']) ?></dd>
                        <dt>Statut</dt><dd><?= badge(...$statuts[$badge['statut']]) ?></dd>
                        <dt>Puce NFC</dt>
                        <dd><?= $badge['uid_fin'] === null ? badge('Non associée', 'badge-warning') : '<span class="mono">…' . e($badge['uid_fin']) . '</span>' ?></dd>
                        <dt>Code PIN</dt>
                        <dd><?= $badge['pin_hash'] === null || $badge['pin_a_changer'] ? badge('À définir par l\'agent', 'badge-warning') : badge('Défini', 'badge-success') ?>
                            <?php if ($badge['verrouille_jusqu_a'] !== null && \App\Core\Clock::fromSql((string) $badge['verrouille_jusqu_a']) > \App\Core\Clock::now()): ?>
                                <?= badge('Verrouillé jusqu\'à ' . heure((string) $badge['verrouille_jusqu_a']), 'badge-danger') ?>
                            <?php endif ?>
                        </dd>
                        <dt>Émis le</dt><dd><?= e(date_courte((string) $badge['emis_le'])) ?></dd>
                        <dt>Dernière utilisation</dt><dd><?= $badge['derniere_utilisation'] === null ? '<span class="muted">Jamais</span>' : e(date_heure((string) $badge['derniere_utilisation'])) ?></dd>
                    </dl>
                </div>

                <?php if ($enAttente): ?>
                    <div class="alert alert-info mt-4" role="status" data-attente-association="/admin/badges/<?= e($badge['id']) ?>/etat">
                        <?= icone('nfc') ?>
                        <span class="alert-body">En attente : présentez la carte sur la borne choisie avant <?= e(heure((string) $badge['association_expire_le'])) ?>.</span>
                    </div>
                <?php endif ?>
            <?php endif ?>
        </section>

        <section class="card">
            <h2 class="card-header">Historique</h2>
            <?php if ($historiqueBadges === []): ?>
                <p class="muted">Aucun badge émis pour cet agent.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th scope="col">Numéro</th><th scope="col">Émis le</th><th scope="col">Statut</th><th scope="col">Fin</th><th scope="col">Motif</th></tr></thead>
                        <tbody>
                            <?php foreach ($historiqueBadges as $ancien): ?>
                                <tr>
                                    <td class="mono"><?= e($ancien['numero']) ?></td>
                                    <td><?= e(date_courte((string) $ancien['emis_le'])) ?></td>
                                    <td><?= badge(...($statuts[$ancien['statut']] ?? [$ancien['statut'], ''])) ?></td>
                                    <td><?= $ancien['desactive_le'] === null ? '—' : e(date_courte((string) $ancien['desactive_le'])) ?></td>
                                    <td><?= e($ancien['motif_desactivation'] ?? '') ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>
    </div>

    <?php if ($peutGererBadges): ?>
        <section class="card stack">
            <?php if ($badge === null): ?>
                <h2 class="card-header">Émettre un badge</h2>
                <?php if ($agent['statut'] !== 'actif'): ?>
                    <p class="muted">Le compte doit être activé avant l'émission d'un badge.</p>
                <?php else: ?>
                    <form class="stack" method="post" action="/admin/agents/<?= e($agent['id']) ?>/badge">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label for="b-numero">Numéro imprimé *</label>
                            <input class="input mono" id="b-numero" name="numero" required maxlength="20" pattern="B-[0-9]{6}" placeholder="B-000154">
                        </div>
                        <?php if ($bornes !== []): ?>
                            <div class="field">
                                <label for="b-borne">Associer la puce sur la borne</label>
                                <?= $selectBornes('b-borne') ?>
                                <p class="help">La borne attend la carte pendant 2 minutes.</p>
                            </div>
                        <?php else: ?>
                            <p class="help">Aucune borne équipée d'un lecteur NFC sur les sites de l'agent : l'association de la puce se fera plus tard.</p>
                        <?php endif ?>
                        <button class="btn btn-primary btn-sm" type="submit"><?= icone('nfc', 'icon-sm') ?>Émettre</button>
                    </form>
                <?php endif ?>
            <?php else: ?>
                <h2 class="card-header">Actions</h2>
                <?php if ($bornes !== []): ?>
                    <form class="stack stack-sm" method="post" action="/admin/badges/<?= e($badge['id']) ?>/associer">
                        <?= csrf_field() ?>
                        <label for="b-reassocier"><?= $badge['uid_fin'] === null ? 'Associer la puce' : 'Associer une nouvelle puce' ?></label>
                        <?= $selectBornes('b-reassocier') ?>
                        <button class="btn btn-outline btn-sm" type="submit"><?= icone('nfc', 'icon-sm') ?>Lancer l'association</button>
                    </form>
                <?php endif ?>
                <form method="post" action="/admin/badges/<?= e($badge['id']) ?>/pin" data-confirmer="Réinitialiser le code PIN ? L'agent devra en définir un nouveau dans son application.">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-sm btn-block" type="submit"><?= icone('key', 'icon-sm') ?>Réinitialiser le PIN</button>
                </form>
                <form class="stack stack-sm" method="post" action="/admin/badges/<?= e($badge['id']) ?>/statut" data-confirmer="Le badge sera immédiatement refusé par toutes les bornes. Confirmer ?">
                    <?= csrf_field() ?>
                    <label for="b-statut">Mettre fin au badge</label>
                    <select class="select select-sm" id="b-statut" name="statut">
                        <option value="perdu">Déclaré perdu</option>
                        <option value="vole">Déclaré volé</option>
                        <option value="restitue">Restitué (départ, remplacement)</option>
                        <option value="desactive">Désactivé</option>
                    </select>
                    <label class="sr-only" for="b-motif">Motif</label>
                    <input class="input input-sm" id="b-motif" name="motif" maxlength="255" placeholder="Motif (facultatif)">
                    <button class="btn btn-danger btn-sm" type="submit"><?= icone('power', 'icon-sm') ?>Désactiver le badge</button>
                </form>
            <?php endif ?>
        </section>
    <?php endif ?>
</div>
