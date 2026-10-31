<?php
/**
 * @var array<string, mixed>|null $agent
 * @var array<string, string> $defauts
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $ancien
 * @var list<array{id: int, nom: string}> $sites
 * @var list<array{id: int, nom: string}> $equipes
 * @var list<int> $sitesAffectes
 * @var int $sitePrincipal
 */
$creation = $agent === null;
$existant = $agent ?? $defauts;
$v = static fn (string $champ, string $defaut = ''): string => valeur($ancien, $existant, $champ, $defaut);
$titre = $creation ? 'Nouvel agent' : 'Modifier ' . $agent['prenom'] . ' ' . $agent['nom'];
$this->layout('admin', ['titre' => $titre, 'menu' => 'agents']);

$sitesCoches = isset($ancien['sites']) && is_array($ancien['sites']) ? array_map('intval', $ancien['sites']) : $sitesAffectes;
$principal = isset($ancien['site_principal']) ? (int) $ancien['site_principal'] : $sitePrincipal;
$role = $v('role', isset($agent['role_code']) ? (string) $agent['role_code'] : 'agent');

$champ = static function (string $nom, string $libelle, string $type = 'text', array $attributs = []) use ($v, $erreurs): string {
    $attr = '';
    foreach ($attributs as $cle => $valeur) {
        $attr .= ' ' . $cle . ($valeur === true ? '' : '="' . e((string) $valeur) . '"');
    }

    return '<div class="field"><label for="' . e($nom) . '">' . e($libelle) . '</label>'
        . '<input class="input" id="' . e($nom) . '" name="' . e($nom) . '" type="' . e($type) . '" value="' . e($v($nom)) . '"' . $attr . aria_erreur($erreurs, $nom) . '>'
        . erreur_champ($erreurs, $nom) . '</div>';
};
?>
<div class="page-head">
    <div>
        <p class="fil-ariane"><a href="/admin/agents">Agents</a> ›</p>
        <h1><?= e($titre) ?></h1>
    </div>
</div>

<?php if ($erreurs !== []): ?>
    <div class="alert alert-danger" role="alert"><?= icone('alert') ?><span class="alert-body">Le formulaire contient <?= e(count($erreurs)) ?> erreur(s). Corrigez les champs signalés.</span></div>
<?php endif ?>

<form method="post" action="<?= $creation ? '/admin/agents' : '/admin/agents/' . e($agent['id']) ?>" class="stack stack-lg" novalidate>
    <?= csrf_field() ?>

    <section class="card">
        <h2 class="card-header">Identité</h2>
        <div class="form-grid">
            <div class="field">
                <label for="civilite">Civilité</label>
                <select class="select" id="civilite" name="civilite">
                    <option value="">—</option>
                    <option value="M"<?= selectionne($v('civilite') === 'M') ?>>M.</option>
                    <option value="Mme"<?= selectionne($v('civilite') === 'Mme') ?>>Mme</option>
                </select>
            </div>
            <?= $champ('nom', 'Nom *', 'text', ['required' => true, 'autocomplete' => 'off']) ?>
            <?= $champ('prenom', 'Prénom *', 'text', ['required' => true, 'autocomplete' => 'off']) ?>
            <?= $champ('nom_naissance', 'Nom de naissance') ?>
            <?= $champ('date_naissance', 'Date de naissance *', 'date', ['required' => true]) ?>
            <?= $champ('lieu_naissance', 'Lieu de naissance *', 'text', ['required' => true]) ?>
            <?= $champ('nationalite', 'Nationalité *', 'text', ['required' => true]) ?>
            <div class="field">
                <label for="nir">N° de sécurité sociale <?= $creation ? '*' : '' ?></label>
                <input class="input mono" id="nir" name="nir" type="text" inputmode="text" autocomplete="off" maxlength="21"
                       aria-describedby="aide-nir<?= isset($erreurs['nir']) ? ' erreur-nir' : '' ?>"<?= isset($erreurs['nir']) ? ' aria-invalid="true"' : '' ?>>
                <p class="help" id="aide-nir"><?= icone('lock', 'icon-sm') ?> <?= $creation ? 'Chiffré en base, clé de contrôle vérifiée.' : 'Laisser vide pour conserver le numéro enregistré.' ?></p>
                <?= erreur_champ($erreurs, 'nir') ?>
            </div>
        </div>
    </section>

    <section class="card">
        <h2 class="card-header">Coordonnées</h2>
        <div class="form-grid">
            <div class="field span-2">
                <label for="adresse">Adresse *</label>
                <input class="input" id="adresse" name="adresse" value="<?= e($v('adresse')) ?>" required<?= aria_erreur($erreurs, 'adresse') ?>>
                <?= erreur_champ($erreurs, 'adresse') ?>
            </div>
            <?= $champ('code_postal', 'Code postal *', 'text', ['required' => true, 'inputmode' => 'numeric']) ?>
            <?= $champ('ville', 'Ville *', 'text', ['required' => true]) ?>
            <?= $champ('email', 'Adresse e-mail *', 'email', ['required' => true, 'autocomplete' => 'off']) ?>
            <?= $champ('telephone', 'Téléphone *', 'tel', ['required' => true]) ?>
        </div>
    </section>

    <section class="card">
        <h2 class="card-header">Contrat et affectations</h2>
        <div class="form-grid">
            <?= $champ('matricule', 'Matricule *', 'text', ['required' => true, 'class' => 'input mono']) ?>
            <div class="field">
                <label for="role">Rôle *</label>
                <select class="select" id="role" name="role">
                    <option value="agent"<?= selectionne($role === 'agent') ?>>Agent</option>
                    <option value="chef"<?= selectionne($role === 'chef') ?>>Chef d'équipe</option>
                </select>
            </div>
            <?= $champ('fonction', 'Fonction *', 'text', ['required' => true, 'placeholder' => 'ADS, SSIAP 1, rondier…']) ?>
            <?= $champ('date_embauche', 'Date d\'embauche *', 'date', ['required' => true]) ?>
            <?php if (!$creation): ?>
                <?= $champ('date_sortie', 'Date de sortie', 'date') ?>
            <?php endif ?>
            <div class="field">
                <label for="type_contrat">Type de contrat *</label>
                <select class="select" id="type_contrat" name="type_contrat">
                    <?php foreach (['CDI' => 'CDI', 'CDD' => 'CDD', 'interim' => 'Intérim', 'apprentissage' => 'Apprentissage', 'autre' => 'Autre'] as $valeur => $libelle): ?>
                        <option value="<?= e($valeur) ?>"<?= selectionne($v('type_contrat') === $valeur) ?>><?= e($libelle) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="field">
                <label for="temps_travail">Temps de travail *</label>
                <select class="select" id="temps_travail" name="temps_travail">
                    <option value="plein"<?= selectionne($v('temps_travail') === 'plein') ?>>Temps plein</option>
                    <option value="partiel"<?= selectionne($v('temps_travail') === 'partiel') ?>>Temps partiel</option>
                </select>
            </div>
            <?= $champ('heures_mensuelles', 'Heures mensuelles *', 'text', ['inputmode' => 'decimal']) ?>
            <?= $champ('taux_horaire_base', 'Taux horaire brut (facultatif)', 'text', ['inputmode' => 'decimal']) ?>
            <div class="field">
                <label for="equipe_id">Équipe</label>
                <select class="select" id="equipe_id" name="equipe_id">
                    <option value="">Aucune</option>
                    <?php foreach ($equipes as $equipe): ?>
                        <option value="<?= e($equipe['id']) ?>"<?= selectionne((int) $v('equipe_id') === $equipe['id']) ?>><?= e($equipe['nom']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <fieldset class="stack stack-sm mt-5">
            <legend>Sites d'affectation</legend>
            <?php if ($sites === []): ?>
                <p class="help">Aucun site n'est encore créé. Les affectations pourront être ajoutées plus tard.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th scope="col">Affecté</th><th scope="col">Site</th><th scope="col">Principal</th></tr></thead>
                        <tbody>
                            <?php foreach ($sites as $site): ?>
                                <tr>
                                    <td><input type="checkbox" name="sites[]" value="<?= e($site['id']) ?>" id="site-<?= e($site['id']) ?>"<?= coche(in_array($site['id'], $sitesCoches, true)) ?>></td>
                                    <td><label for="site-<?= e($site['id']) ?>"><?= e($site['nom']) ?></label></td>
                                    <td><input type="radio" name="site_principal" value="<?= e($site['id']) ?>" aria-label="Site principal : <?= e($site['nom']) ?>"<?= coche($principal === $site['id']) ?>></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </fieldset>
    </section>

    <?php if ($creation): ?>
        <label class="check">
            <input type="checkbox" name="inviter" value="1"<?= coche(!isset($ancien['_csrf']) || !empty($ancien['inviter'])) ?>>
            Envoyer l'invitation par e-mail dès la création (lien valable 72 heures)
        </label>
    <?php endif ?>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= $creation ? '/admin/agents' : '/admin/agents/' . e($agent['id']) ?>">Annuler</a>
        <button class="btn btn-primary" type="submit"><?= $creation ? 'Créer l\'agent' : 'Enregistrer' ?></button>
    </div>
</form>
