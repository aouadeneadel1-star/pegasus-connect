<?php
/**
 * @var list<array<string, mixed>> $clients
 */
$this->layout('admin', ['titre' => 'Clients', 'menu' => 'clients']);
?>
<div class="page-head">
    <h1>Clients</h1>
    <a class="btn btn-primary btn-sm" href="/admin/clients/nouveau"><?= icone('plus', 'icon-sm') ?>Nouveau client</a>
</div>
<?php if ($clients === []): ?>
    <div class="card empty">Aucun client.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Raison sociale</th><th scope="col">Contact</th><th scope="col">Sites actifs</th><th scope="col">Comptes portail</th></tr></thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><a href="/admin/clients/<?= e($client['id']) ?>"><?= e($client['raison_sociale']) ?></a><?= $client['actif'] ? '' : ' ' . badge('Inactif') ?></td>
                        <td><?= e($client['contact_nom'] ?? '') ?><?php if ($client['contact_email']): ?><br><a class="small" href="mailto:<?= e($client['contact_email']) ?>"><?= e($client['contact_email']) ?></a><?php endif ?></td>
                        <td class="tnum"><?= e($client['nb_sites']) ?></td>
                        <td class="tnum"><?= e($client['nb_comptes']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
