<?php
/**
 * @var array<string, string> $erreurs
 * @var string $secret
 * @var string $qr data URI de l'image
 * @var bool $obligatoire
 */
$this->layout('auth', ['titre' => 'Double authentification']);
?>
<div class="stack center">
    <h1 class="auth-title">Double authentification</h1>
    <p class="muted">
        <?= $obligatoire ? 'Elle est obligatoire pour votre rôle.' : 'Elle protège votre compte même si votre mot de passe est découvert.' ?>
        Installez une application d'authentification (Google Authenticator, Microsoft Authenticator, FreeOTP…), puis scannez ce code.
    </p>
    <img class="qr-image" src="<?= e($qr) ?>" alt="QR code à scanner avec l'application d'authentification" width="260" height="260">
    <details>
        <summary class="small">Impossible de scanner ? Saisir la clé manuellement</summary>
        <p class="secret-box mt-2"><?= e(trim(chunk_split($secret, 4, ' '))) ?></p>
    </details>
</div>

<form method="post" action="/compte/2fa" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="code">Code affiché par l'application</label>
        <input class="input otp-input" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
               maxlength="6" required<?= aria_erreur($erreurs, 'code') ?>>
        <?= erreur_champ($erreurs, 'code') ?>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Activer</button>
</form>
