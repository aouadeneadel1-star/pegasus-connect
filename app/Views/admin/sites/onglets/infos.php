<?php
/**
 * @var array<string, mixed> $site
 * @var bool $peutGerer
 * @var list<array<string, mixed>> $responsables
 * @var list<array<string, mixed>> $chefs
 * @var list<array<string, mixed>> $agents
 * @var list<array<string, mixed>> $presents
 * @var list<string> $qualificationsRequises
 */
$base = '/admin/sites/' . $site['id'];
$idsResponsables = array_map(static fn (array $r): int => (int) $r['id'], $responsables);
?>
<div class="grid-2">
    <section class="card">
        <h2 class="card-header">Pointage</h2>
        <dl class="dl">
            <dt>Mode</dt><dd><?= $site['mode_pointage'] === 'dynamique' ? 'Borne sur site' : 'QR fixe imprimé' ?></dd>
            <dt>Badge NFC</dt><dd><?= $site['badge_nfc_actif'] ? 'Autorisé' . ($site['badge_pin_requis'] ? ', avec code PIN' : ', sans code PIN') : 'Non' ?></dd>
            <dt>Rayon</dt><dd><?= e($site['rayon_metres']) ?> m autour de <span class="mono small"><?= e($site['latitude']) ?>, <?= e($site['longitude']) ?></span></dd>
            <dt>Téléphone du poste</dt><dd><?= e($site['telephone_poste'] ?? '—') ?></dd>
            <dt>Qualifications requises</dt><dd><?= $qualificationsRequises === [] ? '—' : e(implode(', ', $qualificationsRequises)) ?></dd>
        </dl>
        <?php if ($site['mode_pointage'] === 'statique' && $peutGerer): ?>
            <div class="row row-wrap mt-4">
                <a class="btn btn-outline btn-sm" href="<?= e($base) ?>/qr-statique.pdf"><?= icone('print', 'icon-sm') ?>Imprimer l'étiquette (version <?= e($site['qr_statique_version']) ?>)</a>
                <form method="post" action="<?= e($base) ?>/qr-statique/regenerer" data-confirmer="L'étiquette actuelle sera refusée immédiatement. Générer un nouveau QR fixe ?">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= icone('refresh', 'icon-sm') ?>Remplacer le QR</button>
                </form>
            </div>
        <?php endif ?>
    </section>

    <section class="card">
        <h2 class="card-header">En service maintenant</h2>
        <?php if ($presents === []): ?>
            <p class="muted">Aucun agent en service.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($presents as $present): ?>
                    <li class="list-item"><span class="grow"><?= e($present['prenom']) ?> <?= e($present['nom']) ?></span><span class="tnum small">depuis <?= e(heure((string) $present['debut'])) ?></span></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <section class="card">
        <h2 class="card-header">Chefs d'équipe responsables</h2>
        <?php if ($peutGerer): ?>
            <form class="stack" method="post" action="<?= e($base) ?>/responsables">
                <?= csrf_field() ?>
                <?php if ($chefs === []): ?>
                    <p class="muted">Aucun chef d'équipe. Attribuez le rôle depuis la fiche d'un agent.</p>
                <?php endif ?>
                <?php foreach ($chefs as $chef): ?>
                    <label class="check"><input type="checkbox" name="responsables[]" value="<?= e($chef['id']) ?>"<?= coche(in_array((int) $chef['id'], $idsResponsables, true)) ?>> <?= e($chef['nom']) ?> <?= e($chef['prenom']) ?></label>
                <?php endforeach ?>
                <?php if ($chefs !== []): ?><button class="btn btn-outline btn-sm" type="submit">Enregistrer</button><?php endif ?>
            </form>
        <?php else: ?>
            <p><?= $responsables === [] ? '—' : e(implode(', ', array_map(static fn (array $r): string => $r['prenom'] . ' ' . $r['nom'], $responsables))) ?></p>
        <?php endif ?>
    </section>

    <section class="card">
        <h2 class="card-header">Agents affectés (<?= e(count($agents)) ?>)</h2>
        <?php if ($agents === []): ?>
            <p class="muted">Aucun agent affecté. L'affectation se fait depuis la fiche agent.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($agents as $agent): ?>
                    <li><a class="list-item" href="/admin/agents/<?= e($agent['id']) ?>"><span class="grow"><?= e($agent['nom']) ?> <?= e($agent['prenom']) ?></span><span class="mono small muted"><?= e($agent['matricule']) ?></span></a></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <?php if (!empty($site['consignes'])): ?>
        <section class="card span-2">
            <h2 class="card-header">Consignes</h2>
            <p><?= nl2br(e($site['consignes'])) ?></p>
        </section>
    <?php endif ?>
</div>
