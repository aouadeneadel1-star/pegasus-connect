<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Env;
use App\Core\Log;
use App\Core\View;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Envoi de la file d'e-mails par SMTP (tâche planifiée, toutes les 5 minutes).
 */
final class EnvoiEmails
{
    public function __construct(
        private readonly FileEmails $file,
        private readonly View $vues,
        private readonly string $appUrl,
    ) {
    }

    /**
     * @return array{envoyes: int, echecs: int}
     */
    public function traiter(int $limite = 50): array
    {
        $resultat = ['envoyes' => 0, 'echecs' => 0];
        $messages = $this->file->prochains($limite);
        if ($messages === []) {
            return $resultat;
        }

        $mailer = $this->mailer();
        foreach ($messages as $message) {
            try {
                $donnees = json_decode((string) $message['donnees'], true, 512, JSON_THROW_ON_ERROR);
                [$html, $texte] = $this->rendre((string) $message['gabarit'], (string) $message['sujet'], $donnees);

                $mailer->clearAddresses();
                $mailer->addAddress((string) $message['destinataire']);
                $mailer->Subject = (string) $message['sujet'];
                $mailer->Body = $html;
                $mailer->AltBody = $texte;
                $mailer->send();

                $this->file->marquerEnvoye((int) $message['id']);
                ++$resultat['envoyes'];
            } catch (Throwable $exception) {
                $this->file->marquerEchec((int) $message['id'], (int) $message['tentatives'], $exception->getMessage());
                Log::warning('Envoi d\'e-mail impossible', ['id' => (int) $message['id'], 'erreur' => $exception->getMessage()]);
                ++$resultat['echecs'];
            }
        }

        return $resultat;
    }

    /**
     * @param array<string, mixed> $donnees
     * @return array{0: string, 1: string}
     */
    public function rendre(string $gabarit, string $sujet, array $donnees): array
    {
        $gabarit = preg_replace('/[^a-z0-9-]/', '', $gabarit) ?? 'notification';
        $variables = [...$donnees, 'sujet' => $sujet, 'appUrl' => $this->appUrl];
        $html = $this->vues->render('emails/' . $gabarit, $variables);
        $texte = $this->vues->render('emails/texte/' . $gabarit, $variables);

        return [$html, $texte];
    }

    private function mailer(): PHPMailer
    {
        $mailer = new PHPMailer(true);
        // Sans serveur SMTP renseigné, l'envoi passe par la messagerie locale de l'hébergeur.
        if ((string) Env::get('MAIL_HOST', '') === '') {
            $mailer->isMail();
            $mailer->CharSet = 'UTF-8';
            $mailer->Encoding = 'base64';
            $mailer->setFrom((string) Env::get('MAIL_FROM', 'no-reply@pegasus-insure.fr'), (string) Env::get('MAIL_FROM_NAME', 'Pegasus Insure'));
            $mailer->isHTML(true);

            return $mailer;
        }
        $mailer->isSMTP();
        $mailer->Host = (string) Env::get('MAIL_HOST');
        $mailer->Port = (int) Env::get('MAIL_PORT', '465');
        $mailer->SMTPAuth = (string) Env::get('MAIL_USER', '') !== '';
        $mailer->Username = (string) Env::get('MAIL_USER', '');
        $mailer->Password = (string) Env::get('MAIL_PASSWORD', '');
        $mailer->SMTPSecure = match (Env::get('MAIL_ENCRYPTION', 'ssl')) {
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            default => '',
        };
        $mailer->Timeout = 15;
        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'base64';
        $mailer->setFrom((string) Env::get('MAIL_FROM', 'no-reply@pegasus-insure.fr'), (string) Env::get('MAIL_FROM_NAME', 'Pegasus Insure'));
        $mailer->isHTML(true);

        return $mailer;
    }
}
