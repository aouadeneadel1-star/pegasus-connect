<?php
/**
 * @var list<array<string, mixed>> $imports
 * @var string $periodeDefaut
 */
$this->layout('admin', ['titre' => 'Paie', 'menu' => 'paie']);
$statuts = ['analyse' => ['Analyse', ''], 'a_valider' => ['À valider', 'badge-warning'], 'publie' => ['Publié', 'badge-success'], 'annule' => ['Annulé', '']];
?>
<div class="page-head"><h1>Bulletins de paie</h1></div>

<div class="grid-2-1">
    <section class="card">
        <h2 class="card-header">Imports</h2>
        <?php if ($imports === []): ?>
            <p class="muted">Aucun import pour le moment.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Période</th><th scope="col">Fichiers</th><th scope="col">Rapprochés</th><th scope="col">À traiter</th><th scope="col">Statut</th><th scope="col">Par</th></tr></thead>
                    <tbody>
                        <?php foreach ($imports as $import): ?>
                            <?php [$libelle, $classe] = $statuts[$import['statut']]; ?>
                            <tr>
                                <td><a href="/admin/paie/import/<?= e($import['id']) ?>"><?= e(ucfirst(nom_mois((int) $import['mois']))) ?> <?= e($import['annee']) ?></a></td>
                                <td class="tnum"><?= e($import['nb_fichiers']) ?></td>
                                <td class="tnum"><?= e($import['nb_associes']) ?></td>
                                <td class="tnum"><?= (int) $import['nb_erreurs'] > 0 ? badge((string) $import['nb_erreurs'], 'badge-warning') : '0' ?></td>
                                <td><?= badge($libelle, $classe) ?></td>
                                <td class="small"><?= e($import['cree_par_nom']) ?><br><span class="muted"><?= e(date_heure((string) $import['created_at'])) ?></span></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <section class="card">
        <h2 class="card-header">Nouvel import</h2>
        <form class="stack" method="post" action="/admin/paie/import" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field"><label for="periode">Période *</label><input class="input" id="periode" name="periode" type="month" value="<?= e($periodeDefaut) ?>" required></div>
            <div class="field">
                <label for="fichiers">Bulletins PDF *</label>
                <input class="input" id="fichiers" name="fichiers[]" type="file" accept="application/pdf" multiple required>
                <p class="help">Un PDF par agent, 5 Mo au plus. Nommez les fichiers avec le matricule (PGS-0042.pdf) ou NOM_Prénom.pdf : le rapprochement est automatique.</p>
            </div>
            <button class="btn btn-primary" type="submit"><?= icone('up') ?>Analyser</button>
            <p class="help">Rien n'est visible des agents avant la publication.</p>
        </form>
    </section>
</div>
