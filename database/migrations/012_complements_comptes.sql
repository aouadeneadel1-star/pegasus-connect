-- Anti-rejeu TOTP : dernier horodatage accepté.
-- Version de session : l'incrémenter invalide toutes les sessions ouvertes de l'utilisateur
-- (changement de mot de passe, désactivation du compte).
ALTER TABLE utilisateurs
  ADD COLUMN totp_dernier_horodatage INT UNSIGNED NULL AFTER totp_actif,
  ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER statut;
