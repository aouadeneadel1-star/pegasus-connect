<?php
/**
 * @var list<array{id: int, nom: string}> $sites
 * @var string $du
 * @var string $au
 */
$this->layout('client', ['titre' => 'Rapports', 'menu' => 'rapports']);
?>
<div class="page-head"><h1>Rapports de rondes</h1></div>
<form class="card stack" method="get" action="/client/rapports">
    <div class="form-grid">
        <div class="field span-2"><label for="cr-site">Site *</label><select class="select" id="cr-site" name="site" required>
            <?php foreach ($sites as $site): ?><option value="<?= e($site['id']) ?>"><?= e($site['nom']) ?></option><?php endforeach ?></select></div>
        <div class="field"><label for="cr-du">Du *</label><input class="input" id="cr-du" name="du" type="date" value="<?= e($du) ?>" required></div>
        <div class="field"><label for="cr-au">Au *</label><input class="input" id="cr-au" name="au" type="date" value="<?= e($au) ?>" required></div>
    </div>
    <div class="row">
        <button class="btn btn-primary" type="submit" name="format" value="pdf"><?= icone('file') ?>Télécharger le PDF</button>
        <button class="btn btn-outline" type="submit" name="format" value="csv"><?= icone('dl') ?>CSV</button>
    </div>
</form>
