<?php
/** @var array{succes: ?string, erreur: ?string, info: ?string} $flash */
$types = ['succes' => ['alert-success', 'check'], 'erreur' => ['alert-danger', 'alert'], 'info' => ['alert-info', 'info']];
?>
<?php foreach ($types as $type => [$classe, $icone]): ?>
    <?php if (!empty($flash[$type])): ?>
        <div class="alert <?= $classe ?>" role="<?= $type === 'erreur' ? 'alert' : 'status' ?>">
            <?= icone($icone) ?>
            <span class="alert-body"><?= e($flash[$type]) ?></span>
        </div>
    <?php endif ?>
<?php endforeach ?>
