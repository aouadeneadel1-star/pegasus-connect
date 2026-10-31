<?php
/**
 * @var list<array{id: int, nom: string}> $sites
 * @var string $du
 * @var string $au
 */
$this->layout('admin', ['titre' => 'Rapport de rondes', 'menu' => 'rondes']);
?>
<p class="fil-ariane"><a href="/admin/rondes">Rondes</a> ›</p>
<div class="page-head"><h1>Rapport de rondes</h1></div>
<form class="card stack" method="get" action="/admin/rapports-rondes">
    <div class="form-grid">
        <div class="field span-2"><label for="rr-site">Site *</label><select class="select" id="rr-site" name="site" required>
            <?php foreach ($sites as $site): ?><option value="<?= e($site['id']) ?>"><?= e($site['nom']) ?></option><?php endforeach ?></select></div>
        <div class="field"><label for="rr-du">Du *</label><input class="input" id="rr-du" name="du" type="date" value="<?= e($du) ?>" required></div>
        <div class="field"><label for="rr-au">Au *</label><input class="input" id="rr-au" name="au" type="date" value="<?= e($au) ?>" required></div>
    </div>
    <p class="help">Période de 92 jours au plus. Le rapport présente chaque ronde, son taux de contrôle et les anomalies signalées.</p>
    <div class="row">
        <button class="btn btn-primary" type="submit" name="format" value="pdf"><?= icone('file') ?>PDF</button>
        <button class="btn btn-outline" type="submit" name="format" value="csv"><?= icone('dl') ?>CSV</button>
    </div>
</form>
