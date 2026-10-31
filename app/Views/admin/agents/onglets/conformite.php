<?php

use App\Services\Agents\Echeance;

/**
 * @var array<string, mixed> $agent
 * @var array<string, mixed>|null $carte
 * @var list<array<string, mixed>> $qualifications
 * @var list<array<string, mixed>> $typesQualification
 * @var bool $peutGerer
 */
$base = '/admin/agents/' . $agent['id'];
?>
<div class="grid-2-1">
    <div class="stack stack-lg">
        <section class="card">
            <div class="row row-between">
                <h2 class="card-header">Carte professionnelle CNAPS</h2>
                <?php if ($carte !== null): ?>
                    <?= badge_echeance(Echeance::statut((string) $carte['date_expiration'])) ?>
                <?php endif ?>
            </div>
            <?php if ($carte === null): ?>
                <div class="alert alert-danger"><?= icone('alert') ?><span class="alert-body">Aucune carte professionnelle enregistrée. Un agent sans carte valide ne peut pas exercer.</span></div>
            <?php else: ?>
                <dl class="dl">
                    <dt>Numéro</dt><dd class="mono"><?= e($carte['numero']) ?></dd>
                    <dt>Activité</dt><dd><?= e($carte['type_activite']) ?></dd>
                    <dt>Délivrée le</dt><dd><?= e(date_courte((string) $carte['date_delivrance'])) ?></dd>
                    <dt>Expire le</dt><dd><?= e(date_courte((string) $carte['date_expiration'])) ?></dd>
                    <dt>Justificatif</dt>
                    <dd><?= $carte['recto_document_id'] === null ? '<span class="muted">Non déposé</span>' : 'Recto' . ($carte['verso_document_id'] === null ? '' : ' et verso') . ' déposés (onglet Documents)' ?></dd>
                </dl>
            <?php endif ?>

            <?php if ($peutGerer): ?>
                <details class="mt-4">
                    <summary class="btn btn-outline btn-sm"><?= icone('edit', 'icon-sm') ?><?= $carte === null ? 'Enregistrer la carte' : 'Mettre à jour la carte' ?></summary>
                    <form class="stack mt-4" method="post" action="<?= e($base) ?>/carte-pro" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="field">
                                <label for="cp-numero">Numéro de carte *</label>
                                <input class="input mono" id="cp-numero" name="numero" required maxlength="40" value="<?= e($carte['numero'] ?? '') ?>" placeholder="CAR-006-2031-…">
                            </div>
                            <div class="field">
                                <label for="cp-activite">Activité *</label>
                                <input class="input" id="cp-activite" name="type_activite" required maxlength="100" value="<?= e($carte['type_activite'] ?? 'Surveillance humaine ou gardiennage') ?>">
                            </div>
                            <div class="field">
                                <label for="cp-delivrance">Délivrée le *</label>
                                <input class="input" id="cp-delivrance" name="date_delivrance" type="date" required value="<?= e($carte['date_delivrance'] ?? '') ?>">
                            </div>
                            <div class="field">
                                <label for="cp-expiration">Expire le *</label>
                                <input class="input" id="cp-expiration" name="date_expiration" type="date" required value="<?= e($carte['date_expiration'] ?? '') ?>">
                            </div>
                            <div class="field">
                                <label for="cp-recto">Recto (PDF, JPG, PNG)</label>
                                <input class="input" id="cp-recto" name="recto" type="file" accept="application/pdf,image/jpeg,image/png,image/webp">
                            </div>
                            <div class="field">
                                <label for="cp-verso">Verso</label>
                                <input class="input" id="cp-verso" name="verso" type="file" accept="application/pdf,image/jpeg,image/png,image/webp">
                            </div>
                        </div>
                        <?php if ($carte !== null): ?>
                            <label class="check"><input type="checkbox" name="nouvelle" value="1"> Il s'agit d'une nouvelle carte (renouvellement) : conserver l'ancienne en historique</label>
                        <?php endif ?>
                        <div class="form-actions"><button class="btn btn-primary btn-sm" type="submit">Enregistrer</button></div>
                    </form>
                </details>
            <?php endif ?>
        </section>

        <section class="card">
            <h2 class="card-header">Qualifications et recyclages</h2>
            <?php if ($qualifications === []): ?>
                <p class="muted">Aucune qualification enregistrée.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th scope="col">Qualification</th><th scope="col">Obtenue le</th><th scope="col">Échéance</th><th scope="col">Statut</th><?php if ($peutGerer): ?><th scope="col"><span class="sr-only">Actions</span></th><?php endif ?></tr></thead>
                        <tbody>
                            <?php foreach ($qualifications as $qualification): ?>
                                <tr>
                                    <td>
                                        <b><?= e($qualification['type_libelle']) ?></b>
                                        <?php if ($qualification['intitule'] || $qualification['numero']): ?>
                                            <br><span class="muted small"><?= e(trim(($qualification['intitule'] ?? '') . ' ' . ($qualification['numero'] ? 'n° ' . $qualification['numero'] : ''))) ?></span>
                                        <?php endif ?>
                                    </td>
                                    <td><?= e(date_courte((string) $qualification['date_obtention'])) ?></td>
                                    <td><?= $qualification['date_echeance'] === null ? '<span class="muted">—</span>' : e(date_courte((string) $qualification['date_echeance'])) ?></td>
                                    <td><?= badge_echeance(Echeance::statut($qualification['date_echeance'] === null ? null : (string) $qualification['date_echeance'])) ?></td>
                                    <?php if ($peutGerer): ?>
                                        <td class="row-end">
                                            <form method="post" action="<?= e($base) ?>/qualifications/<?= e($qualification['id']) ?>/supprimer" data-confirmer="Supprimer cette qualification ?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-ghost btn-icon btn-sm" type="submit" aria-label="Supprimer <?= e($qualification['type_libelle']) ?>"><?= icone('trash', 'icon-sm') ?></button>
                                            </form>
                                        </td>
                                    <?php endif ?>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>
    </div>

    <?php if ($peutGerer): ?>
        <section class="card">
            <h2 class="card-header">Ajouter une qualification</h2>
            <form class="stack" method="post" action="<?= e($base) ?>/qualifications" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="q-type">Type *</label>
                    <select class="select" id="q-type" name="type_qualification_id" required>
                        <?php foreach ($typesQualification as $type): ?>
                            <option value="<?= e($type['id']) ?>"><?= e($type['libelle']) ?><?= $type['validite_mois'] ? ' (' . e($type['validite_mois']) . ' mois)' : '' ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="field">
                    <label for="q-intitule">Précision</label>
                    <input class="input" id="q-intitule" name="intitule" maxlength="150">
                </div>
                <div class="field">
                    <label for="q-numero">N° de certificat</label>
                    <input class="input" id="q-numero" name="numero" maxlength="60">
                </div>
                <div class="field">
                    <label for="q-organisme">Organisme</label>
                    <input class="input" id="q-organisme" name="organisme" maxlength="150">
                </div>
                <div class="field">
                    <label for="q-obtention">Obtenue le *</label>
                    <input class="input" id="q-obtention" name="date_obtention" type="date" required>
                </div>
                <div class="field">
                    <label for="q-echeance">Échéance</label>
                    <input class="input" id="q-echeance" name="date_echeance" type="date" aria-describedby="aide-echeance">
                    <p class="help" id="aide-echeance">Laissée vide, elle est calculée d'après la durée de validité du type.</p>
                </div>
                <div class="field">
                    <label for="q-justificatif">Justificatif</label>
                    <input class="input" id="q-justificatif" name="justificatif" type="file" accept="application/pdf,image/jpeg,image/png,image/webp">
                </div>
                <button class="btn btn-primary btn-sm" type="submit"><?= icone('plus', 'icon-sm') ?>Ajouter</button>
            </form>
        </section>
    <?php endif ?>
</div>
