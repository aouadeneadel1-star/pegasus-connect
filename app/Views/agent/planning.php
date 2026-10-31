<?php
/**
 * @var array<string, array{date: DateTimeImmutable, vacations: list<array<string, mixed>>}> $jours
 * @var string $aujourdhui
 * @var string $precedente
 * @var string $suivante
 * @var int $total
 */
$this->layout('agent', ['titre' => 'Mon planning', 'onglet' => 'planning']);
$premier = reset($jours)['date'];
$dernier = end($jours)['date'];
?>
<nav class="row row-between" aria-label="Semaine">
    <a class="btn btn-ghost btn-icon" href="/planning?date=<?= e($precedente) ?>" aria-label="Semaine précédente"><?= icone('back') ?></a>
    <div class="center">
        <b>Du <?= e($premier->format('d/m')) ?> au <?= e($dernier->format('d/m')) ?></b><br>
        <span class="muted small"><?= e(duree($total)) ?> planifiées</span>
    </div>
    <a class="btn btn-ghost btn-icon" href="/planning?date=<?= e($suivante) ?>" aria-label="Semaine suivante"><?= icone('next') ?></a>
</nav>

<ul class="jours card">
    <?php foreach ($jours as $cle => $jour): ?>
        <li class="jour<?= $cle === $aujourdhui ? ' aujourdhui' : '' ?>">
            <span class="jour-date"><?= e(jour_court($jour['date'])) ?><b><?= e($jour['date']->format('d')) ?></b></span>
            <div>
                <?php if ($jour['vacations'] === []): ?>
                    <span class="muted small">Repos</span>
                <?php endif ?>
                <?php foreach ($jour['vacations'] as $vacation): ?>
                    <div class="creneau">
                        <b class="tnum"><?= e(heure((string) $vacation['debut'])) ?> – <?= e(heure((string) $vacation['fin'])) ?></b>
                        <?= $vacation['poste'] ? ' · ' . e($vacation['poste']) : '' ?><br>
                        <span class="small"><?= e($vacation['site']) ?> — <?= e($vacation['adresse']) ?>, <?= e($vacation['ville']) ?></span>
                        <?php if ($vacation['telephone_poste']): ?>
                            <br><a class="small" href="tel:<?= e(preg_replace('/[^+0-9]/', '', (string) $vacation['telephone_poste'])) ?>"><?= icone('phone', 'icon-sm') ?> Poste : <?= e($vacation['telephone_poste']) ?></a>
                        <?php endif ?>
                        <?php if ((int) $vacation['pause_minutes'] > 0): ?>
                            <br><span class="muted small">Pause : <?= e(duree((int) $vacation['pause_minutes'])) ?></span>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        </li>
    <?php endforeach ?>
</ul>
<a class="btn btn-ghost btn-sm" href="/planning?date=<?= e($aujourdhui) ?>"><?= icone('cal', 'icon-sm') ?>Revenir à cette semaine</a>
