<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\Mail\FileEmails;

/**
 * Notification in-app, doublée d'un e-mail si un gabarit est fourni.
 * Le contenu reste générique : jamais de montant, de NIR ni de donnée sensible.
 */
final class Notifications
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly FileEmails $emails,
        private readonly string $appUrl,
    ) {
    }

    /**
     * @param list<int> $utilisateurIds
     * @param array<string, scalar|null> $donneesEmail
     */
    public function envoyer(
        array $utilisateurIds,
        string $type,
        string $titre,
        ?string $message = null,
        ?string $lien = null,
        ?string $gabaritEmail = null,
        array $donneesEmail = [],
    ): void {
        foreach (array_unique($utilisateurIds) as $utilisateurId) {
            $this->notifications->creer($utilisateurId, $type, $titre, $message, $lien);

            if ($gabaritEmail === null) {
                continue;
            }

            $utilisateur = $this->utilisateurs->trouver($utilisateurId);
            if ($utilisateur === null || $utilisateur['statut'] !== 'actif') {
                continue;
            }

            $this->emails->ajouter((string) $utilisateur['email'], $titre, $gabaritEmail, [
                'nom' => (string) $utilisateur['nom_complet'],
                'titre' => $titre,
                'message' => $message,
                'lien' => $lien === null ? $this->appUrl : $this->appUrl . $lien,
                ...$donneesEmail,
            ]);
        }
    }
}
