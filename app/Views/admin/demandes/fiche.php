<?php
/**
 * @var array<string, mixed> $demande
 * @var list<array<string, mixed>> $messages
 * @var list<array<string, mixed>> $gestionnaires
 * @var \App\Models\UtilisateurConnecte $utilisateur
 */
use App\Models\Role;
use App\Repositories\DemandeRepository;

$this->layout('admin', ['titre' => $demande['objet'], 'menu' => 'demandes']);
$gestionnaires = array_filter($gestionnaires, static fn (array $g): bool => in_array($g['role_code'], ['chef', 'rh', 'admin'], true));
?>
<p class="fil-ariane"><a href="/admin/demandes">Demandes</a> ›</p>
<div class="page-head">
    <div>
        <h1><?= e($demande['objet']) ?></h1>
        <p class="muted"><?= e(DemandeRepository::CATEGORIES[$demande['categorie']] ?? '') ?> · <?= e($demande['demandeur']) ?> (<?= e(Role::fromId((int) $demande['demandeur_role'])->libelle()) ?>) · <a href="mailto:<?= e($demande['demandeur_email']) ?>"><?= e($demande['demandeur_email']) ?></a></p>
    </div>
    <?= badge(DemandeRepository::STATUTS[$demande['statut']] ?? $demande['statut']) ?>
</div>

<div class="grid-2-1">
    <div class="stack">
        <ol class="fil" aria-label="Échanges">
            <?php foreach ($messages as $message): ?>
                <li class="message<?= (int) $message['auteur_id'] !== (int) $demande['demandeur_id'] ? ' moi' : '' ?><?= $message['interne'] ? ' interne' : '' ?>">
                    <p class="auteur"><b><?= e($message['auteur']) ?></b> · <?= e(date_heure((string) $message['created_at'])) ?><?= $message['interne'] ? ' · note interne' : '' ?></p>
                    <div><?= nl2br(e($message['message'])) ?></div>
                    <?php if ($message['url']): ?><a class="btn btn-ghost btn-sm" href="<?= e($message['url']) ?>" target="_blank" rel="noopener"><?= icone('clip', 'icon-sm') ?>Pièce jointe</a><?php endif ?>
                </li>
            <?php endforeach ?>
        </ol>
        <form class="card stack" method="post" action="/admin/demandes/<?= e($demande['id']) ?>/message">
            <?= csrf_field() ?>
            <label for="reponse">Réponse</label>
            <textarea class="textarea" id="reponse" name="message" rows="4" maxlength="5000" required></textarea>
            <div class="row row-wrap row-between">
                <label class="check"><input type="checkbox" name="interne" value="1"> Note interne (non visible du demandeur)</label>
                <label class="check"><input type="checkbox" name="statut_apres" value="resolue"> Marquer comme résolue</label>
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><?= icone('send', 'icon-sm') ?>Envoyer</button>
        </form>
    </div>

    <form class="card stack" method="post" action="/admin/demandes/<?= e($demande['id']) ?>/statut">
        <?= csrf_field() ?>
        <h2 class="card-header">Suivi</h2>
        <div class="field"><label for="d-statut">Statut</label><select class="select" id="d-statut" name="statut">
            <?php foreach (DemandeRepository::STATUTS as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($demande['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
        <div class="field"><label for="d-assignee">Attribuée à</label><select class="select" id="d-assignee" name="assignee"><option value="">Personne</option>
            <?php foreach ($gestionnaires as $gestionnaire): ?><option value="<?= e($gestionnaire['id']) ?>"<?= selectionne((int) $demande['assignee_a'] === (int) $gestionnaire['id']) ?>><?= e($gestionnaire['nom_complet']) ?></option><?php endforeach ?></select></div>
        <button class="btn btn-outline btn-sm" type="submit">Enregistrer</button>
    </form>
</div>
