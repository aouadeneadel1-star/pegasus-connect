<?php
/**
 * @var list<array<string, mixed>> $demandes
 */
$this->layout('admin', ['titre' => 'Modifications de profil', 'menu' => 'demandes-profil']);
$libelles = ['adresse' => 'Adresse', 'code_postal' => 'Code postal', 'ville' => 'Ville', 'telephone' => 'Téléphone', 'email' => 'E-mail'];
?>
<div class="page-head"><h1>Demandes de modification de profil</h1></div>

<?php if ($demandes === []): ?>
    <div class="card empty">Aucune demande en attente.</div>
<?php else: ?>
    <div class="stack stack-lg">
        <?php foreach ($demandes as $demande): ?>
            <section class="card stack">
                <div class="row row-between">
                    <h2 class="card-header"><a href="/admin/agents/<?= e($demande['agent_id']) ?>"><?= e($demande['prenom']) ?> <?= e($demande['nom']) ?></a> <span class="muted mono small"><?= e($demande['matricule']) ?></span></h2>
                    <span class="muted small">Le <?= e(date_heure((string) $demande['created_at'])) ?></span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th scope="col">Champ</th><th scope="col">Valeur actuelle</th><th scope="col">Nouvelle valeur</th></tr></thead>
                        <tbody>
                            <?php foreach ($demande['changements'] as $champ => $changement): ?>
                                <tr><td><?= e($libelles[$champ] ?? $champ) ?></td><td class="muted"><?= e($changement['avant']) ?></td><td><b><?= e($changement['apres']) ?></b></td></tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($demande['url'] !== null): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e($demande['url']) ?>" target="_blank" rel="noopener"><?= icone('clip', 'icon-sm') ?>Voir le justificatif</a>
                <?php endif ?>
                <div class="row row-wrap row-between">
                    <form method="post" action="/admin/demandes-profil/<?= e($demande['id']) ?>" class="row row-wrap">
                        <?= csrf_field() ?>
                        <input type="hidden" name="decision" value="refuser">
                        <label class="sr-only" for="motif-<?= e($demande['id']) ?>">Motif du refus</label>
                        <input class="input input-sm" id="motif-<?= e($demande['id']) ?>" name="motif" maxlength="255" placeholder="Motif du refus" required>
                        <button class="btn btn-outline btn-sm" type="submit">Refuser</button>
                    </form>
                    <form method="post" action="/admin/demandes-profil/<?= e($demande['id']) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="decision" value="valider">
                        <button class="btn btn-primary btn-sm" type="submit"><?= icone('check', 'icon-sm') ?>Valider et mettre à jour la fiche</button>
                    </form>
                </div>
            </section>
        <?php endforeach ?>
    </div>
<?php endif ?>
