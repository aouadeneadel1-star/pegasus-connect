<?php
/**
 * @var array<string, list<array{cle: string, libelle: string, type: string, min: mixed, max: mixed, valeur: mixed}>> $groupes
 */
$this->layout('admin', ['titre' => 'Paramètres', 'menu' => 'parametres']);
?>
<div class="page-head"><h1>Paramètres</h1></div>
<form class="stack stack-lg" method="post" action="/admin/parametres">
    <?= csrf_field() ?>
    <?php foreach ($groupes as $groupe => $parametres): ?>
        <section class="card">
            <h2 class="card-header"><?= e($groupe) ?></h2>
            <div class="form-grid">
                <?php foreach ($parametres as $p): ?>
                    <?php $id = 'p-' . str_replace('.', '-', $p['cle']); $nom = 'parametres[' . $p['cle'] . ']'; ?>
                    <?php if ($p['type'] === 'bool'): ?>
                        <div class="field span-2">
                            <input type="hidden" name="<?= e($nom) ?>" value="0">
                            <label class="check"><input type="checkbox" id="<?= e($id) ?>" name="<?= e($nom) ?>" value="1"<?= coche($p['valeur'] === true) ?>> <?= e($p['libelle']) ?></label>
                        </div>
                    <?php elseif ($p['type'] === 'choix'): ?>
                        <div class="field"><label for="<?= e($id) ?>"><?= e($p['libelle']) ?></label>
                            <select class="select" id="<?= e($id) ?>" name="<?= e($nom) ?>">
                                <?php foreach ($p['min'] as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($p['valeur'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?>
                            </select></div>
                    <?php else: ?>
                        <div class="field"><label for="<?= e($id) ?>"><?= e($p['libelle']) ?></label>
                            <input class="input" id="<?= e($id) ?>" name="<?= e($nom) ?>"
                                   type="<?= $p['type'] === 'int' ? 'number' : ($p['type'] === 'email' ? 'email' : 'text') ?>"
                                   value="<?= e(is_scalar($p['valeur']) ? (string) $p['valeur'] : '') ?>"
                                   <?= $p['type'] === 'int' ? 'min="' . e($p['min']) . '" max="' . e($p['max']) . '"' : 'maxlength="' . e($p['max']) . '"' ?>>
                        </div>
                    <?php endif ?>
                <?php endforeach ?>
            </div>
        </section>
    <?php endforeach ?>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
</form>
