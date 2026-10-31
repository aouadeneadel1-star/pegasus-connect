<?php

declare(strict_types=1);

namespace App\Services\Stockage;

use App\Repositories\DocumentRepository;
use finfo;
use RuntimeException;

/**
 * Dépôt des fichiers hors de la racine web, sous un nom aléatoire.
 * Les images sont ré-encodées (métadonnées EXIF, dont la position GPS, supprimées)
 * et les PDF contenant du code actif sont refusés.
 */
final class Stockage
{
    public const PDF = ['application/pdf'];
    public const IMAGES = ['image/jpeg', 'image/png', 'image/webp'];

    private const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
    ];

    private const MOTIFS_PDF_DANGEREUX = ['/JavaScript', '/JS ', '/JS(', '/Launch', '/EmbeddedFile'];

    public function __construct(
        private readonly string $racine,
        private readonly DocumentRepository $documents,
    ) {
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $fichier
     * @param list<string> $typesAcceptes
     * @param array{date_document?: ?string, date_validite?: ?string, visible_agent?: bool, dimension_max?: int} $options
     */
    public function deposer(
        array $fichier,
        array $typesAcceptes,
        int $tailleMaxOctets,
        string $categorie,
        ?int $agentId,
        string $libelle,
        ?int $deposePar,
        array $options = [],
    ): int {
        $this->verifierEnvoi($fichier, $tailleMaxOctets);

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
        if (!in_array($mime, $typesAcceptes, true)) {
            throw new FichierRefuseException(sprintf('Format non accepté (%s). Formats acceptés : %s.', $mime, $this->libelleTypes($typesAcceptes)));
        }

        $contenu = (string) file_get_contents($fichier['tmp_name']);
        if ($mime === 'application/pdf') {
            $this->verifierPdf($contenu);
        } elseif (in_array($mime, self::IMAGES, true)) {
            [$contenu, $mime] = $this->reencoderImage($fichier['tmp_name'], $mime, $options['dimension_max'] ?? 2400);
        }

        return $this->enregistrer($contenu, $fichier['name'], $mime, $categorie, $agentId, $libelle, $deposePar, $options);
    }

    /**
     * Fichier produit par l'application (PDF de rapport, export) : aucun contrôle de contenu.
     *
     * @param array{date_document?: ?string, date_validite?: ?string, visible_agent?: bool} $options
     */
    public function enregistrer(
        string $contenu,
        string $nomOriginal,
        string $mime,
        string $categorie,
        ?int $agentId,
        string $libelle,
        ?int $deposePar,
        array $options = [],
    ): int {
        $extension = self::EXTENSIONS[$mime] ?? 'bin';
        $nom = bin2hex(random_bytes(16));
        $relatif = substr($nom, 0, 2) . '/' . substr($nom, 2, 2) . '/' . $nom . '.' . $extension;
        $absolu = $this->racine . '/' . $relatif;

        if (!is_dir(dirname($absolu)) && !mkdir(dirname($absolu), 0700, true) && !is_dir(dirname($absolu))) {
            throw new RuntimeException('Création du dossier de stockage impossible.');
        }
        if (file_put_contents($absolu, $contenu, LOCK_EX) === false) {
            throw new RuntimeException('Écriture du fichier impossible.');
        }
        chmod($absolu, 0600);

        return $this->documents->creer([
            'agent_id' => $agentId,
            'categorie' => $categorie,
            'libelle' => mb_substr($libelle, 0, 150),
            'nom_original' => mb_substr(basename($nomOriginal), 0, 255),
            'chemin' => $relatif,
            'mime' => $mime,
            'taille_octets' => strlen($contenu),
            'sha256' => hash('sha256', $contenu),
            'date_document' => $options['date_document'] ?? null,
            'date_validite' => $options['date_validite'] ?? null,
            'visible_agent' => ($options['visible_agent'] ?? true) ? 1 : 0,
            'depose_par' => $deposePar,
        ]);
    }

    /**
     * @param array<string, mixed> $document
     */
    public function chemin(array $document): string
    {
        $chemin = $this->racine . '/' . $document['chemin'];
        if (str_contains((string) $document['chemin'], '..') || !is_file($chemin)) {
            throw new RuntimeException('Fichier introuvable sur le disque.');
        }

        return $chemin;
    }

    public function contenu(int $documentId): string
    {
        $document = $this->documents->trouver($documentId) ?? throw new RuntimeException('Document introuvable.');

        return (string) file_get_contents($this->chemin($document));
    }

    public function effacer(string $cheminRelatif): void
    {
        $chemin = $this->racine . '/' . $cheminRelatif;
        if (!str_contains($cheminRelatif, '..') && is_file($chemin)) {
            unlink($chemin);
        }
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $fichier
     */
    private function verifierEnvoi(array $fichier, int $tailleMaxOctets): void
    {
        $message = match ($fichier['error']) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux.',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été reçu qu\'en partie. Réessayez.',
            default => 'Le fichier n\'a pas pu être reçu.',
        };
        if ($message !== null) {
            throw new FichierRefuseException($message);
        }
        if ($fichier['size'] > $tailleMaxOctets) {
            throw new FichierRefuseException(sprintf('Le fichier dépasse %d Mo.', intdiv($tailleMaxOctets, 1048576)));
        }
        if (!is_uploaded_file($fichier['tmp_name']) && !is_file($fichier['tmp_name'])) {
            throw new FichierRefuseException('Le fichier n\'a pas pu être reçu.');
        }
    }

    private function verifierPdf(string $contenu): void
    {
        if (!str_starts_with($contenu, '%PDF-')) {
            throw new FichierRefuseException('Ce fichier n\'est pas un PDF valide.');
        }
        foreach (self::MOTIFS_PDF_DANGEREUX as $motif) {
            if (str_contains($contenu, $motif)) {
                throw new FichierRefuseException('Ce PDF contient du contenu actif (script ou fichier intégré) et ne peut pas être accepté.');
            }
        }
    }

    /**
     * @return array{0: string, 1: string} contenu ré-encodé et type MIME
     */
    private function reencoderImage(string $chemin, string $mime, int $dimensionMax): array
    {
        $image = imagecreatefromstring((string) file_get_contents($chemin));
        if ($image === false) {
            throw new FichierRefuseException('Cette image est illisible.');
        }

        $image = $this->redresser($image, $chemin, $mime);
        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $ratio = min(1, $dimensionMax / max($largeur, $hauteur));

        if ($ratio < 1) {
            $reduite = imagescale($image, max(1, (int) round($largeur * $ratio)), max(1, (int) round($hauteur * $ratio)));
            if ($reduite !== false) {
                $image = $reduite;
            }
        }

        ob_start();
        if ($mime === 'image/png') {
            imagesavealpha($image, true);
            imagepng($image, null, 6);
        } else {
            imagejpeg($image, null, 82);
            $mime = 'image/jpeg';
        }

        return [(string) ob_get_clean(), $mime];
    }

    /**
     * Le ré-encodage perd l'orientation EXIF : on applique la rotation avant.
     */
    private function redresser(\GdImage $image, string $chemin, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = exif_read_data($chemin);
        } catch (\ErrorException) {
            // Métadonnées corrompues : l'image reste utilisable sans rotation.
            return $image;
        }

        $angle = match ((int) (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $tournee = imagerotate($image, $angle, 0);

        return $tournee === false ? $image : $tournee;
    }

    /**
     * @param list<string> $types
     */
    private function libelleTypes(array $types): string
    {
        return implode(', ', array_unique(array_map(static fn (string $type): string => strtoupper(self::EXTENSIONS[$type] ?? $type), $types)));
    }
}
