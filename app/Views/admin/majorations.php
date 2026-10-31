<?php
/**
 * @var list<array<string, mixed>> $regles
 * @var array<string, string> $codes
 * @var int $annee
 * @var list<array<string, mixed>> $feries
 */
$this->layout('admin', ['titre' => 'Majorations', 'menu' => 'majorations']);
?>
<div class="page-head"><h1>Majorations et jours fériés</h1></div>
<div class="alert alert-info"><?= icone('info') ?><span class="alert-body">Taux par défaut de la convention collective Prévention-sécurité (IDCC 1351), à faire valider par votre gestionnaire de paie. Une règle n'est jamais modifiée : ajoutez-en une nouvelle avec sa date d'effet.</span></div>

<div class="grid-2-1">
    <section class="card">
        <h2 class="card-header">Règles</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th scope="col">Type</th><th scope="col">Libellé</th><th scope="col">Taux</th><th scope="col">Plage / seuil</th><th scope="col">Effet</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    <?php foreach ($regles as $regle): ?>
                        <tr<?= $regle['actif'] ? '' : ' class="muted"' ?>>
                            <td><?= e($codes[$regle['code']] ?? $regle['code']) ?></td>
                            <td><?= e($regle['libelle']) ?></td>
                            <td class="tnum"><?= e(nombre((float) $regle['taux'], 2)) ?> %</td>
                            <td class="tnum"><?= $regle['plage_debut'] ? e(substr((string) $regle['plage_debut'], 0, 5)) . ' – ' . e(substr((string) $regle['plage_fin'], 0, 5)) : '' ?><?= $regle['seuil_heures'] ? e(nombre((float) $regle['seuil_heures'], 0)) . ' h' : '' ?></td>
                            <td><?= e(date_courte((string) $regle['date_effet'])) ?></td>
                            <td><?php if ($regle['actif']): ?>
                                <form method="post" action="/admin/majorations/regles/<?= e($regle['id']) ?>/desactiver" data-confirmer="Désactiver cette règle ?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Désactiver</button></form>
                            <?php else: ?><?= badge('Inactive') ?><?php endif ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2 class="card-header">Nouvelle règle</h2>
        <form class="stack" method="post" action="/admin/majorations/regles">
            <?= csrf_field() ?>
            <div class="field"><label for="m-code">Type *</label><select class="select" id="m-code" name="code"><?php foreach ($codes as $code => $libelle): ?><option value="<?= e($code) ?>"><?= e($libelle) ?></option><?php endforeach ?></select></div>
            <div class="field"><label for="m-libelle">Libellé *</label><input class="input" id="m-libelle" name="libelle" maxlength="80" required></div>
            <div class="field"><label for="m-taux">Taux (%) *</label><input class="input" id="m-taux" name="taux" inputmode="decimal" required></div>
            <div class="form-grid">
                <div class="field"><label for="m-debut">Début de plage</label><input class="input" id="m-debut" name="plage_debut" type="time"></div>
                <div class="field"><label for="m-fin">Fin de plage</label><input class="input" id="m-fin" name="plage_fin" type="time"></div>
            </div>
            <div class="field"><label for="m-seuil">Seuil (heures, premier taux d'heures supplémentaires)</label><input class="input" id="m-seuil" name="seuil_heures" inputmode="decimal"></div>
            <div class="field"><label for="m-effet">Date d'effet *</label><input class="input" id="m-effet" name="date_effet" type="date" required></div>
            <label class="check"><input type="checkbox" name="cumulable" value="1" checked> Cumulable avec les autres majorations</label>
            <button class="btn btn-primary btn-sm" type="submit"><?= icone('plus', 'icon-sm') ?>Ajouter</button>
        </form>
    </section>
</div>

<section class="card mt-5">
    <div class="row row-between">
        <h2 class="card-header">Jours fériés <?= e($annee) ?></h2>
        <div class="row">
            <a class="btn btn-ghost btn-icon btn-sm" href="/admin/majorations?annee=<?= e($annee - 1) ?>" aria-label="Année précédente"><?= icone('back', 'icon-sm') ?></a>
            <a class="btn btn-ghost btn-icon btn-sm" href="/admin/majorations?annee=<?= e($annee + 1) ?>" aria-label="Année suivante"><?= icone('next', 'icon-sm') ?></a>
        </div>
    </div>
    <div class="grid-2-1">
        <ul class="list">
            <?php foreach ($feries as $ferie): ?>
                <li class="list-item">
                    <span class="grow"><b><?= e(date_courte((string) $ferie['date'])) ?></b> · <?= e($ferie['libelle']) ?></span>
                    <form method="post" action="/admin/majorations/feries/supprimer" data-confirmer="Supprimer ce jour férié ?"><?= csrf_field() ?><input type="hidden" name="date" value="<?= e($ferie['date']) ?>"><button class="btn btn-ghost btn-icon btn-sm" type="submit" aria-label="Supprimer"><?= icone('trash', 'icon-sm') ?></button></form>
                </li>
            <?php endforeach ?>
            <?php if ($feries === []): ?><li class="list-item muted">Aucun jour férié enregistré pour cette année.</li><?php endif ?>
        </ul>
        <form class="stack" method="post" action="/admin/majorations/feries">
            <?= csrf_field() ?>
            <div class="field"><label for="f-date">Date *</label><input class="input" id="f-date" name="date" type="date" required></div>
            <div class="field"><label for="f-libelle">Libellé *</label><input class="input" id="f-libelle" name="libelle" maxlength="60" required></div>
            <button class="btn btn-outline btn-sm" type="submit"><?= icone('plus', 'icon-sm') ?>Ajouter</button>
        </form>
    </div>
</section>
