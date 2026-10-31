<?php
/**
 * @var string $espace
 * @var string $prefixe
 * @var array<string, string> $categories
 * @var string $categorie
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $ancien
 */
$this->layout($espace, ['titre' => 'Nouvelle demande', 'menu' => 'contact', 'onglet' => 'plus', 'retour' => $espace === 'agent' ? '/contact' : null]);
$choix = (string) ($ancien['categorie'] ?? $categorie);
?>
<?php if ($espace !== 'agent'): ?>
    <p class="fil-ariane"><a href="<?= e($prefixe) ?>/contact">Contact</a> ›</p>
    <div class="page-head"><h1>Nouvelle demande</h1></div>
<?php endif ?>

<form class="card stack" method="post" action="<?= e($prefixe) ?>/contact" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <fieldset class="field">
        <legend>Sujet *</legend>
        <div class="choices">
            <?php foreach ($categories as $code => $libelle): ?>
                <label class="chip">
                    <input type="radio" name="categorie" value="<?= e($code) ?>" required<?= coche($choix === $code) ?>>
                    <?= e($libelle) ?>
                </label>
            <?php endforeach ?>
        </div>
        <?= erreur_champ($erreurs, 'categorie') ?>
    </fieldset>
    <div class="field">
        <label for="objet">Objet *</label>
        <input class="input" id="objet" name="objet" maxlength="150" required value="<?= e((string) ($ancien['objet'] ?? '')) ?>"<?= aria_erreur($erreurs, 'objet') ?>>
        <?= erreur_champ($erreurs, 'objet') ?>
    </div>
    <div class="field">
        <label for="message">Message *</label>
        <textarea class="textarea" id="message" name="message" rows="6" maxlength="5000" required<?= aria_erreur($erreurs, 'message') ?>><?= e((string) ($ancien['message'] ?? '')) ?></textarea>
        <?= erreur_champ($erreurs, 'message') ?>
    </div>
    <div class="field">
        <label for="piece_jointe">Pièce jointe <span class="muted">(PDF ou photo, 10 Mo max.)</span></label>
        <input class="input" id="piece_jointe" name="piece_jointe" type="file" accept="application/pdf,image/jpeg,image/png,image/webp"<?= aria_erreur($erreurs, 'piece_jointe') ?>>
        <?= erreur_champ($erreurs, 'piece_jointe') ?>
    </div>
    <p class="help">Une urgence en cours sur site ? Appelez la permanence plutôt que d'écrire.</p>
    <button class="btn btn-primary<?= $espace === 'agent' ? ' btn-block' : '' ?>" type="submit"><?= icone('send') ?>Envoyer</button>
</form>
