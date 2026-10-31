<?php
/**
 * @var array<string, mixed>|null $ronde
 * @var array<string, mixed>|null $service
 * @var list<array<string, mixed>> $points
 * @var int $pointId
 * @var array<string, string> $categories
 * @var array<string, string> $gravites
 */
$this->layout('agent', ['titre' => 'Signaler une anomalie', 'onglet' => 'accueil', 'retour' => $ronde !== null ? '/rondes/' . $ronde['id'] : '/', 'scripts' => ['anomalie.js']]);
?>
<?php if ($ronde === null && $service === null): ?>
    <div class="alert alert-warning"><?= icone('alert') ?><span class="alert-body">Pointez votre arrivée sur le site avant de signaler une anomalie. En cas d'urgence, appelez la permanence.</span></div>
<?php else: ?>
    <form class="stack" data-anomalie enctype="multipart/form-data" novalidate>
        <?php if ($ronde !== null): ?>
            <input type="hidden" name="ronde_id" value="<?= e($ronde['id']) ?>">
        <?php endif ?>
        <fieldset class="field">
            <legend>Catégorie *</legend>
            <div class="choices">
                <?php foreach ($categories as $code => $libelle): ?>
                    <label class="chip"><input type="radio" name="categorie" value="<?= e($code) ?>" required> <?= e($libelle) ?></label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <fieldset class="field">
            <legend>Gravité *</legend>
            <div class="segmented">
                <?php foreach ($gravites as $code => $libelle): ?>
                    <label><input type="radio" name="gravite" value="<?= e($code) ?>" required<?= coche($code === 'mineure') ?>><span><?= e($libelle) ?></span></label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <?php if ($points !== []): ?>
            <div class="field">
                <label for="anomalie-point">Point de contrôle</label>
                <select class="select" id="anomalie-point" name="point_id">
                    <option value="">Aucun en particulier</option>
                    <?php foreach ($points as $point): ?>
                        <option value="<?= e($point['id']) ?>"<?= selectionne($pointId === (int) $point['id']) ?>><?= e($point['nom']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        <?php endif ?>
        <div class="field">
            <label for="anomalie-description">Description *</label>
            <textarea class="textarea" id="anomalie-description" name="description" rows="5" maxlength="5000" required placeholder="Ce que vous avez constaté, où, et ce que vous avez fait."></textarea>
        </div>
        <div class="field">
            <label for="anomalie-photos">Photos <span class="muted">(5 au plus)</span></label>
            <input class="input" id="anomalie-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" multiple>
            <div class="photos-apercu" data-apercu></div>
            <p class="help">Les métadonnées des photos (dont la position) sont supprimées à l'envoi.</p>
        </div>
        <p class="field-error" data-erreur hidden role="alert"></p>
        <button class="btn btn-primary btn-xl btn-block" type="submit"><?= icone('send') ?>Envoyer le signalement</button>
    </form>
<?php endif ?>
