<?php
/**
 * @var array<string, mixed>|null $client
 * @var array{noms_agents: bool, validite_carte_pro: bool} $options
 * @var list<array<string, mixed>> $sites
 * @var list<array<string, mixed>> $comptes
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $ancien
 */
$creation = $client === null;
$v = static fn (string $champ): string => valeur($ancien, $client, $champ);
$titre = $creation ? 'Nouveau client' : $client['raison_sociale'];
$this->layout('admin', ['titre' => $titre, 'menu' => 'clients']);
$actif = $ancien === [] ? ($client === null || (bool) $client['actif']) : !empty($ancien['actif']);
?>
<p class="fil-ariane"><a href="/admin/clients">Clients</a> ›</p>
<div class="page-head"><h1><?= e($titre) ?></h1></div>

<div class="grid-2-1">
    <form class="card stack" method="post" action="<?= $creation ? '/admin/clients' : '/admin/clients/' . e($client['id']) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field span-2"><label for="raison_sociale">Raison sociale *</label><input class="input" id="raison_sociale" name="raison_sociale" value="<?= e($v('raison_sociale')) ?>" required maxlength="150"<?= aria_erreur($erreurs, 'raison_sociale') ?>><?= erreur_champ($erreurs, 'raison_sociale') ?></div>
            <div class="field"><label for="siret">SIRET</label><input class="input mono" id="siret" name="siret" value="<?= e($v('siret')) ?>" inputmode="numeric" maxlength="14"<?= aria_erreur($erreurs, 'siret') ?>><?= erreur_champ($erreurs, 'siret') ?></div>
            <div class="field"><label for="contact_nom">Contact</label><input class="input" id="contact_nom" name="contact_nom" value="<?= e($v('contact_nom')) ?>" maxlength="120"></div>
            <div class="field"><label for="contact_email">E-mail du contact</label><input class="input" id="contact_email" name="contact_email" type="email" value="<?= e($v('contact_email')) ?>"<?= aria_erreur($erreurs, 'contact_email') ?>><?= erreur_champ($erreurs, 'contact_email') ?></div>
            <div class="field"><label for="contact_tel">Téléphone du contact</label><input class="input" id="contact_tel" name="contact_tel" type="tel" value="<?= e($v('contact_tel')) ?>"<?= aria_erreur($erreurs, 'contact_tel') ?>><?= erreur_champ($erreurs, 'contact_tel') ?></div>
        </div>
        <fieldset class="field">
            <legend>Portail client</legend>
            <label class="check"><input type="checkbox" name="noms_agents" value="1"<?= coche($options['noms_agents']) ?>> Afficher le prénom et l'initiale des agents</label>
            <label class="check"><input type="checkbox" name="validite_carte_pro" value="1"<?= coche($options['validite_carte_pro']) ?>> Indiquer que les agents présents ont une carte professionnelle valide</label>
            <p class="help">Par défaut le client ne voit que des données anonymes : présence, rondes, anomalies qui lui sont destinées.</p>
        </fieldset>
        <label class="check"><input type="checkbox" name="actif" value="1"<?= coche($actif) ?>> Client actif</label>
        <div class="form-actions">
            <a class="btn btn-ghost" href="/admin/clients">Annuler</a>
            <button class="btn btn-primary" type="submit"><?= $creation ? 'Créer' : 'Enregistrer' ?></button>
        </div>
    </form>

    <?php if (!$creation): ?>
        <div class="stack stack-lg">
            <section class="card">
                <div class="row row-between"><h2 class="card-header">Sites</h2><a class="btn btn-ghost btn-sm" href="/admin/sites/nouveau?client=<?= e($client['id']) ?>"><?= icone('plus', 'icon-sm') ?>Ajouter</a></div>
                <?php if ($sites === []): ?><p class="muted">Aucun site.</p><?php else: ?>
                    <ul class="list"><?php foreach ($sites as $site): ?><li><a class="list-item" href="/admin/sites/<?= e($site['id']) ?>"><span class="grow"><?= e($site['nom']) ?></span><span class="muted small"><?= e($site['ville']) ?></span></a></li><?php endforeach ?></ul>
                <?php endif ?>
            </section>
            <section class="card">
                <div class="row row-between"><h2 class="card-header">Comptes portail</h2><a class="btn btn-ghost btn-sm" href="/admin/utilisateurs/nouveau?client=<?= e($client['id']) ?>"><?= icone('user-plus', 'icon-sm') ?>Ajouter</a></div>
                <?php if ($comptes === []): ?><p class="muted">Aucun compte.</p><?php else: ?>
                    <ul class="list"><?php foreach ($comptes as $compte): ?><li><a class="list-item" href="/admin/utilisateurs/<?= e($compte['id']) ?>"><span class="grow"><span class="list-title"><?= e($compte['nom_complet']) ?></span><span class="list-sub"><?= e($compte['email']) ?></span></span><?= badge_compte((string) $compte['statut']) ?></a></li><?php endforeach ?></ul>
                <?php endif ?>
            </section>
        </div>
    <?php endif ?>
</div>
