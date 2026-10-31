<?php
/**
 * @var array<int, list<array<string, mixed>>> $parAnnee
 */
$this->layout('agent', ['titre' => 'Bulletins de paie', 'onglet' => 'documents', 'retour' => '/documents']);
?>
<?php if ($parAnnee === []): ?>
    <div class="card empty">Aucun bulletin publié pour le moment.</div>
<?php else: ?>
    <?php foreach ($parAnnee as $annee => $fiches): ?>
        <h2 class="list-heading"><?= e($annee) ?></h2>
        <ul class="list card">
            <?php foreach ($fiches as $fiche): ?>
                <li>
                    <a class="list-item" href="/paie/<?= e($fiche['id']) ?>/telecharger" target="_blank" rel="noopener">
                        <span class="list-icon"><?= icone('euro') ?></span>
                        <span class="grow"><span class="list-title"><?= e(ucfirst(nom_mois((int) $fiche['mois']))) ?> <?= e($annee) ?></span>
                            <span class="list-sub">Publié le <?= e(date_courte((string) $fiche['publiee_le'])) ?><?= (int) $fiche['version'] > 1 ? ' · version corrigée' : '' ?></span></span>
                        <?php if ($fiche['premiere_consultation'] === null): ?><?= badge('Nouveau', 'badge-brand') ?><?php endif ?>
                        <?= icone('dl', 'icon-sm') ?>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endforeach ?>
    <p class="help">Conservez vos bulletins : téléchargez-les régulièrement ou déposez-les sur votre compte personnel d'activité.</p>
<?php endif ?>
