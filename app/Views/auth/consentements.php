<?php
/**
 * @var array<string, string> $erreurs
 * @var ?bool $geolocalisation dernier choix enregistré
 * @var string $telephone
 */
$this->layout('auth', ['titre' => 'Notice d\'information', 'large' => true]);
?>
<div class="stack">
    <h1>Vos données dans Pegasus Connect</h1>

    <div class="notice" tabindex="0" aria-label="Notice d'information">
        <h2>Pourquoi cette application ?</h2>
        <p>Pegasus Insure utilise Pegasus Connect pour enregistrer vos prises et fins de service, prouver le passage des rondes auprès de nos clients, vous transmettre vos documents et fiches de paie, et préparer la paie.</p>

        <h2>Ce qui est enregistré</h2>
        <ul>
            <li>À chaque pointage ou scan de point de contrôle : l'heure, le site, le point, l'appareil utilisé.</li>
            <li>Si vous l'acceptez, votre position <strong>au seul moment du scan</strong>. Il n'y a aucun suivi continu, ni pendant ni en dehors du service.</li>
            <li>Les anomalies que vous signalez, avec leurs photos.</li>
        </ul>

        <h2>Combien de temps</h2>
        <p>Les coordonnées GPS sont effacées après 2 mois : seule la distance au site est conservée. Les pointages sont conservés 3 ans, les fiches de paie 5 ans, conformément à nos obligations.</p>

        <h2>Qui y a accès</h2>
        <p>Le service RH, l'administration et votre chef d'équipe pour votre périmètre. Nos clients voient uniquement les rondes de leur site, sans vos données personnelles.</p>

        <h2>Vos droits</h2>
        <p>Vous pouvez consulter vos données, demander leur rectification et modifier votre choix sur la géolocalisation à tout moment depuis « Mes données ». Pour toute question : service RH.</p>
    </div>

    <form method="post" action="/consentements" class="stack">
        <?= csrf_field() ?>

        <fieldset class="stack stack-sm">
            <legend>Géolocalisation au moment du scan</legend>
            <div class="choices">
                <label><input type="radio" name="geolocalisation" value="oui"<?= coche($geolocalisation !== false) ?>><span>J'accepte</span></label>
                <label><input type="radio" name="geolocalisation" value="non"<?= coche($geolocalisation === false) ?>><span>Je refuse</span></label>
            </div>
            <p class="help">Refuser ne vous empêche pas de pointer : certains pointages seront simplement vérifiés par votre chef d'équipe.</p>
        </fieldset>

        <label class="check">
            <input type="checkbox" name="information_lue" value="1"<?= aria_erreur($erreurs, 'information_lue') ?>>
            J'ai pris connaissance de cette notice d'information.
        </label>
        <?= erreur_champ($erreurs, 'information_lue') ?>

        <button class="btn btn-primary btn-block" type="submit">Continuer</button>
        <?php if ($telephone !== ''): ?>
            <p class="help center">Urgence opérationnelle : <?= e($telephone) ?></p>
        <?php endif ?>
    </form>
</div>
