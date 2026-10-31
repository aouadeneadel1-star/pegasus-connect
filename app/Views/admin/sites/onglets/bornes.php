<?php
/**
 * @var array<string, mixed> $site
 * @var bool $peutGerer
 * @var list<array<string, mixed>> $bornes
 * @var string $urlBorne
 * @var array{borne: string, code: string}|null $codeEnrolement
 * @var DateTimeImmutable $limiteSilence
 */

use App\Core\Clock;

$base = '/admin/sites/' . $site['id'];
$lecteurs = ['aucun' => 'Sans lecteur de badge', 'usb' => 'Lecteur USB', 'web_nfc' => 'NFC de la tablette (Android)'];
$statuts = ['en_attente' => ['En attente d\'enrôlement', 'badge-info'], 'active' => ['Active', 'badge-success'], 'revoquee' => ['Révoquée', '']];
?>
<?php if ($codeEnrolement !== null): ?>
    <div class="alert alert-info" role="status">
        <?= icone('key') ?>
        <span class="alert-body">
            Code d'enrôlement de la borne « <?= e($codeEnrolement['borne']) ?> » : <b class="mono borne-code-affiche"><?= e($codeEnrolement['code']) ?></b><br>
            Ouvrez <span class="mono"><?= e($urlBorne) ?></span> sur la tablette et saisissez ce code dans les 15 minutes. Il ne s'affiche qu'une fois.
        </span>
    </div>
<?php endif ?>

<div class="grid-2-1">
    <section class="card">
        <h2 class="card-header">Bornes du site</h2>
        <?php if ($bornes === []): ?>
            <p class="muted">Aucune borne. <?= $site['mode_pointage'] === 'dynamique' ? 'Ajoutez-en une pour permettre le pointage.' : 'Ce site utilise un QR fixe ; une borne reste possible pour le badge.' ?></p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($bornes as $borne): ?>
                    <?php
                    [$libelle, $classe] = $statuts[$borne['statut']];
                    $activite = Clock::fromSql($borne['derniere_activite'] === null ? null : (string) $borne['derniere_activite']);
                    $muette = $borne['statut'] === 'active' && ($activite === null || $activite < $limiteSilence);
                    ?>
                    <li class="list-item row-top">
                        <span class="list-icon<?= $muette ? ' danger' : ($borne['statut'] === 'active' ? ' success' : '') ?>"><?= icone($muette ? 'wifi-off' : 'qr') ?></span>
                        <span class="grow">
                            <span class="list-title"><?= e($borne['libelle']) ?> <?= badge($libelle, $classe) ?><?= $muette ? ' ' . badge('Hors ligne', 'badge-danger') : '' ?></span>
                            <span class="list-sub">
                                <?= e($lecteurs[$borne['lecteur_nfc']]) ?><?= $borne['lecteur_nfc'] !== 'aucun' && $borne['lecteur_nfc_ok'] !== null ? ($borne['lecteur_nfc_ok'] ? ', fonctionnel' : ', en défaut') : '' ?>
                                <?= $activite === null ? '' : ' · dernier signal ' . e(date_heure($activite)) ?>
                            </span>
                            <?php if ($peutGerer && $borne['statut'] !== 'revoquee'): ?>
                                <details class="mt-2">
                                    <summary class="small">Gérer</summary>
                                    <div class="stack stack-sm mt-2">
                                        <form class="row row-wrap" method="post" action="/admin/bornes/<?= e($borne['id']) ?>/modifier">
                                            <?= csrf_field() ?>
                                            <label class="sr-only" for="borne-libelle-<?= e($borne['id']) ?>">Libellé</label>
                                            <input class="input input-sm" id="borne-libelle-<?= e($borne['id']) ?>" name="libelle" value="<?= e($borne['libelle']) ?>" maxlength="80" required>
                                            <label class="sr-only" for="borne-lecteur-<?= e($borne['id']) ?>">Lecteur</label>
                                            <select class="select select-sm" id="borne-lecteur-<?= e($borne['id']) ?>" name="lecteur_nfc">
                                                <?php foreach ($lecteurs as $code => $texte): ?><option value="<?= e($code) ?>"<?= selectionne($borne['lecteur_nfc'] === $code) ?>><?= e($texte) ?></option><?php endforeach ?>
                                            </select>
                                            <button class="btn btn-outline btn-sm" type="submit">Enregistrer</button>
                                        </form>
                                        <div class="row row-wrap">
                                            <form method="post" action="/admin/bornes/<?= e($borne['id']) ?>/code" data-confirmer="Un nouveau code d'enrôlement remplacera la tablette actuelle dès qu'il sera utilisé. Continuer ?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-ghost btn-sm" type="submit"><?= icone('key', 'icon-sm') ?>Nouveau code d'enrôlement</button>
                                            </form>
                                            <form method="post" action="/admin/bornes/<?= e($borne['id']) ?>/revoquer" data-confirmer="Révoquer cette borne ? Ses QR codes et badgeages seront refusés immédiatement.">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= icone('power', 'icon-sm') ?>Révoquer</button>
                                            </form>
                                        </div>
                                    </div>
                                </details>
                            <?php endif ?>
                        </span>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <?php if ($peutGerer): ?>
        <section class="card">
            <h2 class="card-header">Ajouter une borne</h2>
            <form class="stack" method="post" action="<?= e($base) ?>/bornes">
                <?= csrf_field() ?>
                <div class="field"><label for="nouvelle-borne">Libellé *</label><input class="input" id="nouvelle-borne" name="libelle" maxlength="80" required placeholder="Poste PC sécurité"></div>
                <div class="field"><label for="nouveau-lecteur">Lecteur de badge</label>
                    <select class="select" id="nouveau-lecteur" name="lecteur_nfc">
                        <?php foreach ($lecteurs as $code => $texte): ?><option value="<?= e($code) ?>"><?= e($texte) ?></option><?php endforeach ?>
                    </select></div>
                <button class="btn btn-primary btn-sm" type="submit"><?= icone('plus', 'icon-sm') ?>Créer et obtenir le code</button>
            </form>
            <p class="help mt-4">Adresse de la borne : <span class="mono"><?= e($urlBorne) ?></span>. Sur la tablette, activez le mode kiosque et désactivez la mise en veille.</p>
        </section>
    <?php endif ?>
</div>
