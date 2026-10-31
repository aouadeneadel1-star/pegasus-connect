<?php
/**
 * @var array<string, mixed>|null $site
 * @var array<string, string> $defauts
 * @var list<array{id: int, nom: string}> $clients
 * @var list<array<string, mixed>> $types
 * @var list<int> $qualificationsRequises
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $ancien
 */
$creation = $site === null;
$existant = $site ?? $defauts;
$v = static fn (string $champ): string => valeur($ancien, $existant, $champ);
$coche = static fn (string $champ): bool => $ancien === [] ? (bool) ($existant[$champ] ?? false) : !empty($ancien[$champ]);
$titre = $creation ? 'Nouveau site' : 'Modifier ' . $site['nom'];
$this->layout('admin', ['titre' => $titre, 'menu' => 'sites']);
$champ = static function (string $nom, string $libelle, string $type = 'text', string $attributs = '') use ($v, $erreurs): string {
    return '<div class="field"><label for="' . e($nom) . '">' . e($libelle) . '</label><input class="input" id="' . e($nom) . '" name="' . e($nom) . '" type="' . e($type) . '" value="' . e($v($nom)) . '" ' . $attributs . aria_erreur($erreurs, $nom) . '>' . erreur_champ($erreurs, $nom) . '</div>';
};
?>
<p class="fil-ariane"><a href="/admin/sites">Sites</a> ›</p>
<div class="page-head"><h1><?= e($titre) ?></h1></div>

<form class="stack stack-lg" method="post" action="<?= $creation ? '/admin/sites' : '/admin/sites/' . e($site['id']) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="card">
        <h2 class="card-header">Identification</h2>
        <div class="form-grid">
            <div class="field">
                <label for="client_id">Client *</label>
                <select class="select" id="client_id" name="client_id" required<?= aria_erreur($erreurs, 'client_id') ?>>
                    <option value="">Choisir…</option>
                    <?php foreach ($clients as $client): ?><option value="<?= e($client['id']) ?>"<?= selectionne((int) $v('client_id') === $client['id']) ?>><?= e($client['nom']) ?></option><?php endforeach ?>
                </select>
                <?= erreur_champ($erreurs, 'client_id') ?>
            </div>
            <?= $champ('nom', 'Nom du site *', 'text', 'required maxlength="120"') ?>
            <?= $champ('code', 'Code court *', 'text', 'required maxlength="20" placeholder="nice-port"') ?>
            <p class="help span-2">Lettres minuscules, chiffres et tirets. Il apparaît dans l'adresse de la borne : /borne/code.</p>
            <?= $champ('telephone_poste', 'Téléphone du poste', 'tel') ?>
        </div>
    </section>

    <section class="card">
        <h2 class="card-header">Adresse et position</h2>
        <div class="form-grid">
            <div class="span-2"><?= $champ('adresse', 'Adresse *', 'text', 'required') ?></div>
            <?= $champ('code_postal', 'Code postal *', 'text', 'required inputmode="numeric"') ?>
            <?= $champ('ville', 'Ville *', 'text', 'required') ?>
            <?= $champ('latitude', 'Latitude *', 'text', 'required inputmode="decimal" placeholder="43.695"') ?>
            <?= $champ('longitude', 'Longitude *', 'text', 'required inputmode="decimal" placeholder="7.272"') ?>
            <?= $champ('rayon_metres', 'Rayon de pointage (m) *', 'number', 'required min="20" max="2000"') ?>
        </div>
        <p class="help">Les coordonnées se relèvent sur un service de cartographie (clic droit sur le lieu). Un pointage au-delà du rayon, augmenté de la précision du GPS, est placé « à vérifier ».</p>
    </section>

    <section class="card">
        <h2 class="card-header">Pointage</h2>
        <fieldset class="field">
            <legend>Mode *</legend>
            <label class="check"><input type="radio" name="mode_pointage" value="dynamique"<?= coche($v('mode_pointage') === 'dynamique') ?>> Borne sur site (QR renouvelé chaque minute)</label>
            <label class="check"><input type="radio" name="mode_pointage" value="statique"<?= coche($v('mode_pointage') === 'statique') ?>> QR fixe imprimé (site sans écran, géolocalisation obligatoire)</label>
        </fieldset>
        <label class="check"><input type="checkbox" name="badge_nfc_actif" value="1"<?= coche($coche('badge_nfc_actif')) ?>> Autoriser le pointage par badge NFC sur les bornes équipées</label>
        <label class="check"><input type="checkbox" name="badge_pin_requis" value="1"<?= coche($coche('badge_pin_requis')) ?>> Exiger le code PIN avec le badge</label>
    </section>

    <section class="card">
        <h2 class="card-header">Consignes et qualifications</h2>
        <div class="field">
            <label for="consignes">Consignes du site</label>
            <textarea class="textarea" id="consignes" name="consignes" rows="5" maxlength="5000"><?= e($v('consignes')) ?></textarea>
        </div>
        <fieldset class="field">
            <legend>Qualifications requises</legend>
            <div class="choices choices-larges">
                <?php foreach ($types as $type): ?>
                    <label class="chip"><input type="checkbox" name="qualifications_requises[]" value="<?= e($type['id']) ?>"<?= coche(in_array((int) $type['id'], $qualificationsRequises, true)) ?>> <?= e($type['libelle']) ?></label>
                <?php endforeach ?>
            </div>
            <p class="help">Le planning signale une vacation confiée à un agent qui ne les détient pas.</p>
        </fieldset>
        <label class="check"><input type="checkbox" name="actif" value="1"<?= coche($coche('actif')) ?>> Site actif</label>
    </section>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= $creation ? '/admin/sites' : '/admin/sites/' . e($site['id']) ?>">Annuler</a>
        <button class="btn btn-primary" type="submit"><?= $creation ? 'Créer le site' : 'Enregistrer' ?></button>
    </div>
</form>
