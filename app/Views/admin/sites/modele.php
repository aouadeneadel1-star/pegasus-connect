<?php
/**
 * @var array<string, mixed> $site
 * @var array<string, mixed>|null $modele
 * @var array<string, string> $defauts
 * @var list<array<string, mixed>> $points
 * @var list<array{point_id: int, ordre: int, obligatoire: bool}> $selection
 * @var array<int, string> $jours
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $ancien
 */
$creation = $modele === null;
$existant = $modele ?? $defauts;
$v = static fn (string $champ): string => substr(valeur($ancien, $existant, $champ), 0, in_array($champ, ['heure_debut', 'heure_fin'], true) ? 5 : 255);
$masque = isset($ancien['jours']) && is_array($ancien['jours']) ? array_sum(array_map('intval', $ancien['jours'])) : (int) ($existant['jours_semaine'] ?? 127);
$coche = static fn (string $champ): bool => $ancien === [] ? (bool) ($existant[$champ] ?? false) : !empty($ancien[$champ]);
$selectionnes = [];
foreach ($selection as $s) {
    $selectionnes[$s['point_id']] = $s;
}
// Les points déjà choisis d'abord, dans l'ordre du parcours, puis les autres.
usort($points, static fn (array $a, array $b): int => [isset($selectionnes[(int) $a['id']]) ? 0 : 1, $selectionnes[(int) $a['id']]['ordre'] ?? (int) $a['ordre']]
    <=> [isset($selectionnes[(int) $b['id']]) ? 0 : 1, $selectionnes[(int) $b['id']]['ordre'] ?? (int) $b['ordre']]);
$titre = $creation ? 'Nouvelle ronde planifiée' : $modele['nom'];
$this->layout('admin', ['titre' => $titre, 'menu' => 'sites', 'scripts' => ['ordre-points.js']]);
$champ = static function (string $nom, string $libelle, string $type, string $attributs = '') use ($v, $erreurs): string {
    return '<div class="field"><label for="' . e($nom) . '">' . e($libelle) . '</label><input class="input" id="' . e($nom) . '" name="' . e($nom) . '" type="' . e($type) . '" value="' . e($v($nom)) . '" ' . $attributs . aria_erreur($erreurs, $nom) . '>' . erreur_champ($erreurs, $nom) . '</div>';
};
?>
<p class="fil-ariane"><a href="/admin/sites/<?= e($site['id']) ?>?onglet=modeles"><?= e($site['nom']) ?></a> ›</p>
<div class="page-head"><h1><?= e($titre) ?></h1></div>

<form class="stack stack-lg" method="post" action="<?= $creation ? '/admin/sites/' . e($site['id']) . '/modeles-ronde' : '/admin/modeles-ronde/' . e($modele['id']) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="card">
        <h2 class="card-header">Planification</h2>
        <div class="form-grid">
            <div class="span-2"><?= $champ('nom', 'Nom *', 'text', 'required maxlength="80" placeholder="Ronde de nuit"') ?></div>
            <?= $champ('heure_debut', 'Première ronde *', 'time', 'required') ?>
            <?= $champ('heure_fin', 'Fin de la plage *', 'time', 'required') ?>
            <?= $champ('frequence_minutes', 'Toutes les (minutes) *', 'number', 'required min="15" max="1440" step="5"') ?>
            <?= $champ('duree_max_minutes', 'Durée maximale (minutes) *', 'number', 'required min="5" max="480"') ?>
            <?= $champ('tolerance_demarrage_min', 'Tolérance de démarrage (minutes) *', 'number', 'required min="0" max="240"') ?>
            <?= $champ('temps_min_entre_points_s', 'Temps minimal entre deux points (s) *', 'number', 'required min="0" max="600"') ?>
        </div>
        <p class="help">Une plage qui se termine avant son heure de début passe minuit (21:00 – 06:00).</p>
        <fieldset class="field mt-4">
            <legend>Jours *</legend>
            <div class="choices">
                <?php foreach ($jours as $bit => $libelle): ?>
                    <label class="chip"><input type="checkbox" name="jours[]" value="<?= e($bit) ?>"<?= coche(($masque & $bit) !== 0) ?>> <?= e($libelle) ?></label>
                <?php endforeach ?>
            </div>
            <?= erreur_champ($erreurs, 'jours') ?>
        </fieldset>
        <label class="check"><input type="checkbox" name="ordre_impose" value="1"<?= coche($coche('ordre_impose')) ?>> Imposer l'ordre de passage</label>
        <label class="check"><input type="checkbox" name="actif" value="1"<?= coche($coche('actif')) ?>> Modèle actif</label>
    </section>

    <section class="card">
        <h2 class="card-header">Points à contrôler</h2>
        <?= erreur_champ($erreurs, 'points') ?>
        <?php if ($points === []): ?>
            <p class="muted">Créez d'abord les points de contrôle du site.</p>
        <?php else: ?>
            <p class="help">Cochez les points de la ronde. Les flèches modifient l'ordre de passage.</p>
            <ol class="list" data-ordre-points>
                <?php foreach ($points as $point): ?>
                    <?php $choisi = isset($selectionnes[(int) $point['id']]); ?>
                    <li class="list-item" data-point>
                        <input type="checkbox" name="points[]" value="<?= e($point['id']) ?>" id="pt-<?= e($point['id']) ?>"<?= coche($choisi) ?>>
                        <label class="grow" for="pt-<?= e($point['id']) ?>"><?= e($point['nom']) ?> <span class="muted small"><?= e($point['zone'] ?? '') ?></span></label>
                        <label class="check small"><input type="checkbox" name="obligatoires[]" value="<?= e($point['id']) ?>"<?= coche(!$choisi || $selectionnes[(int) $point['id']]['obligatoire']) ?>> obligatoire</label>
                        <button class="btn btn-ghost btn-icon btn-sm" type="button" data-monter aria-label="Monter <?= e($point['nom']) ?>"><?= icone('chevron-down', 'icon-sm rotation-180') ?></button>
                        <button class="btn btn-ghost btn-icon btn-sm" type="button" data-descendre aria-label="Descendre <?= e($point['nom']) ?>"><?= icone('chevron-down', 'icon-sm') ?></button>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    </section>

    <div class="form-actions">
        <a class="btn btn-ghost" href="/admin/sites/<?= e($site['id']) ?>?onglet=modeles">Annuler</a>
        <button class="btn btn-primary" type="submit"><?= $creation ? 'Créer le modèle' : 'Enregistrer' ?></button>
    </div>
</form>
