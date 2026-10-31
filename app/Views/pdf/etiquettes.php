<?php
/**
 * Planche A4 de 12 étiquettes (3 × 4) pour les points de contrôle.
 *
 * @var array<string, mixed> $site
 * @var list<array{point: array<string, mixed>, qr: string}> $etiquettes
 */
$lignes = array_chunk($etiquettes, 3);
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Étiquettes</title>
<style>
    @page { size: A4 portrait; margin: 10mm 8mm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #111214; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td { width: 33.3%; height: 66mm; border: 0.3pt dashed #b0b5bd; text-align: center; vertical-align: middle; padding: 3mm; }
    .qr { width: 40mm; height: 40mm; }
    .nom { font-size: 9pt; font-weight: bold; margin-top: 1mm; }
    .ident { font-size: 7pt; color: #5a606b; }
    .marque { font-size: 6pt; letter-spacing: 1pt; color: #a51d24; }
    .saut { page-break-after: always; }
</style></head>
<body>
<?php foreach (array_chunk($lignes, 4) as $index => $page): ?>
    <table class="<?= $index < count(array_chunk($lignes, 4)) - 1 ? 'saut' : '' ?>">
        <?php foreach ($page as $ligne): ?>
            <tr>
                <?php foreach ($ligne as $etiquette): ?>
                    <td>
                        <div class="marque">PEGASUS · POINT DE RONDE</div>
                        <img class="qr" src="<?= e($etiquette['qr']) ?>" alt="">
                        <div class="nom"><?= e($etiquette['point']['nom']) ?></div>
                        <div class="ident"><?= e($etiquette['point']['identifiant']) ?> · <?= e($site['nom']) ?> · v<?= e($etiquette['point']['qr_version']) ?></div>
                    </td>
                <?php endforeach ?>
                <?php for ($i = count($ligne); $i < 3; ++$i): ?><td></td><?php endfor ?>
            </tr>
        <?php endforeach ?>
    </table>
<?php endforeach ?>
</body>
</html>
