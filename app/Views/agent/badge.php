<?php
/**
 * @var array<string, mixed>|null $badge
 * @var list<array<string, mixed>> $historique
 */
$this->layout('agent', ['titre' => 'Mon badge', 'onglet' => 'plus', 'retour' => '/profil']);
$pinDefini = $badge !== null && $badge['pin_hash'] !== null && !$badge['pin_a_changer'];
?>
<?php if ($badge === null): ?>
    <div class="card empty stack">
        <?= icone('nfc', 'icon-xl') ?>
        <p>Vous n'avez pas de badge actif. Vous pouvez pointer avec votre téléphone en scannant le QR code de la borne.</p>
    </div>
<?php else: ?>
    <section class="card">
        <dl class="dl">
            <dt>Numéro</dt><dd class="mono"><?= e($badge['numero']) ?></dd>
            <dt>Émis le</dt><dd><?= e(date_courte((string) $badge['emis_le'])) ?></dd>
            <dt>Code PIN</dt><dd><?= $pinDefini ? badge('Défini', 'badge-success') : badge('À définir', 'badge-warning') ?></dd>
            <dt>Dernier badgeage</dt><dd><?= $badge['derniere_utilisation'] === null ? '—' : e(date_heure((string) $badge['derniere_utilisation'])) ?></dd>
        </dl>
    </section>

    <form class="card stack" method="post" action="/profil/badge/pin" autocomplete="off">
        <?= csrf_field() ?>
        <h2 class="card-header"><?= $pinDefini ? 'Changer mon code PIN' : 'Définir mon code PIN' ?></h2>
        <p class="help">4 chiffres, à garder pour vous. Il vous est demandé à la borne pour confirmer que vous êtes bien le titulaire du badge.</p>
        <div class="field">
            <label for="pin">Nouveau code</label>
            <input class="input otp-input" id="pin" name="pin" type="password" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="new-password">
        </div>
        <div class="field">
            <label for="pin-confirmation">Confirmation</label>
            <input class="input otp-input" id="pin-confirmation" name="pin_confirmation" type="password" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary" type="submit"><?= icone('key') ?>Enregistrer</button>
    </form>

    <form class="card stack" method="post" action="/profil/badge/perte" data-confirmer="Déclarer la perte de votre badge ? Il sera désactivé immédiatement et ne pourra plus être utilisé.">
        <?= csrf_field() ?>
        <h2 class="card-header">Badge perdu ou volé ?</h2>
        <p class="help">Déclarez-le tout de suite : il sera refusé par toutes les bornes.</p>
        <button class="btn btn-danger" type="submit"><?= icone('alert') ?>Déclarer la perte</button>
    </form>
<?php endif ?>
