<?php
/**
 * @var string $statut
 */
$statuts = [
    'a_venir' => ['À venir', ''],
    'en_cours' => ['En cours', 'badge-info'],
    'terminee' => ['Terminée', 'badge-success'],
    'incomplete' => ['Incomplète', 'badge-warning'],
    'manquee' => ['Manquée', 'badge-danger'],
    'annulee' => ['Annulée', ''],
];
[$libelle, $classe] = $statuts[$statut] ?? [$statut, ''];
echo badge($libelle, $classe);
