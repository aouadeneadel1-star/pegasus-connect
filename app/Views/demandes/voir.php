<?php

use App\Repositories\DemandeRepository;

/**
 * @var string $espace
 * @var string $prefixe
 * @var array<string, mixed> $demande
 * @var list<array<string, mixed>> $messages
 * @var \App\Models\UtilisateurConnecte $utilisateur
 */
$this->layout($espace, ['titre' => 'Demande', 'menu' => 'contact', 'onglet' => 'plus', 'retour' => $espace === 'agent' ? '/contact' : null]);
?>
<?php if ($espace !== 'agent'): ?>
    <p class="fil-ariane"><a href="<?= e($prefixe) ?>/contact">Contact</a> ›</p>
<?php endif ?>
<div class="page-head">
    <div>
        <h1><?= e($demande['objet']) ?></h1>
        <p class="muted"><?= e(DemandeRepository::CATEGORIES[$demande['categorie']] ?? '') ?> · ouverte le <?= e(date_heure((string) $demande['created_at'])) ?></p>
    </div>
    <?= badge(DemandeRepository::STATUTS[$demande['statut']] ?? $demande['statut']) ?>
</div>

<ol class="fil" aria-label="Échanges">
    <?php foreach ($messages as $message): ?>
        <?php $moi = (int) $message['auteur_id'] === $utilisateur->id; ?>
        <li class="message<?= $moi ? ' moi' : '' ?>">
            <p class="auteur"><b><?= e($moi ? 'Vous' : $message['auteur']) ?></b> · <span class="muted"><?= e(date_heure((string) $message['created_at'])) ?></span></p>
            <div><?= nl2br(e($message['message'])) ?></div>
            <?php if ($message['url'] !== null): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e($message['url']) ?>" target="_blank" rel="noopener"><?= icone('clip', 'icon-sm') ?>Pièce jointe</a>
            <?php endif ?>
        </li>
    <?php endforeach ?>
</ol>

<?php if ($demande['statut'] !== 'close'): ?>
    <form class="card stack mt-4" method="post" action="<?= e($prefixe) ?>/contact/<?= e($demande['id']) ?>/message">
        <?= csrf_field() ?>
        <label for="reponse">Répondre</label>
        <textarea class="textarea" id="reponse" name="message" rows="3" maxlength="5000" required></textarea>
        <button class="btn btn-primary btn-sm" type="submit"><?= icone('send', 'icon-sm') ?>Envoyer</button>
    </form>
<?php endif ?>
