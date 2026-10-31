CREATE TABLE IF NOT EXISTS roles (
  id            TINYINT UNSIGNED PRIMARY KEY,
  code          VARCHAR(20)  NOT NULL UNIQUE,      -- agent, chef, rh, admin, client
  libelle       VARCHAR(50)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(60)  NOT NULL UNIQUE,      -- ex. agent.nir.reveler
  module        VARCHAR(30)  NOT NULL,
  libelle       VARCHAR(150) NOT NULL,
  verrouillee   TINYINT(1)   NOT NULL DEFAULT 0     -- non modifiable via l'UI
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id       TINYINT UNSIGNED  NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  portee        ENUM('soi','equipe','client','tout') NOT NULL DEFAULT 'tout',
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raison_sociale   VARCHAR(150) NOT NULL,
  siret            CHAR(14)     NULL,
  contact_nom      VARCHAR(120) NULL,
  contact_email    VARCHAR(190) NULL,
  contact_tel      VARCHAR(20)  NULL,
  options_portail  JSON         NULL,   -- {"noms_agents":false,"validite_carte_pro":false}
  actif            TINYINT(1)   NOT NULL DEFAULT 1,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_clients_actif (actif)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS utilisateurs (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id              TINYINT UNSIGNED NOT NULL,
  client_id            INT UNSIGNED NULL,               -- rôle client uniquement
  email                VARCHAR(190) NOT NULL,
  nom_complet          VARCHAR(120) NOT NULL,           -- affichage (comptes RH, admin, client sans fiche agent)
  password_hash        VARCHAR(255) NULL,               -- NULL tant que l'invitation n'est pas acceptée
  totp_secret_chiffre  VARBINARY(255) NULL,
  totp_actif           TINYINT(1)   NOT NULL DEFAULT 0,
  codes_secours        JSON         NULL,               -- tableau de hashes
  statut               ENUM('invite','actif','suspendu','desactive') NOT NULL DEFAULT 'invite',
  echecs_connexion     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  verrouille_jusqu_a   DATETIME     NULL,
  mdp_modifie_le       DATETIME     NULL,
  auth_forte_le        DATETIME     NULL,               -- dernière réauthentification
  derniere_connexion   DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_util_email (email),
  INDEX idx_util_role (role_id),
  INDEX idx_util_client (client_id),
  CONSTRAINT fk_util_role   FOREIGN KEY (role_id)   REFERENCES roles(id),
  CONSTRAINT fk_util_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jetons_compte (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NOT NULL,
  type            ENUM('invitation','reinitialisation','appareil_confiance','enrolement_borne') NOT NULL,
  jeton_hash      CHAR(64)     NOT NULL,               -- SHA-256 du jeton envoyé
  expire_le       DATETIME     NOT NULL,
  utilise_le      DATETIME     NULL,
  meta            JSON         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_jeton (jeton_hash),
  INDEX idx_jeton_util (utilisateur_id, type),
  CONSTRAINT fk_jeton_util FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tentatives_connexion (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_hash  CHAR(64)       NOT NULL,
  ip          VARBINARY(16)  NOT NULL,
  succes      TINYINT(1)     NOT NULL,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tc_email (email_hash, created_at),
  INDEX idx_tc_ip (ip, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS consentements (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NOT NULL,
  type            ENUM('geolocalisation','information_salarie','charte_utilisation') NOT NULL,
  version_texte   VARCHAR(20)  NOT NULL,
  accorde         TINYINT(1)   NOT NULL,
  ip              VARBINARY(16) NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_consent (utilisateur_id, type, created_at),
  CONSTRAINT fk_consent_util FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appareils (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appareil_uuid CHAR(36)     NOT NULL UNIQUE,          -- généré côté client (localStorage)
  user_agent    VARCHAR(255) NULL,
  plateforme    VARCHAR(40)  NULL,
  premier_vu    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dernier_vu    DATETIME     NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appareils_utilisateurs (
  appareil_id     INT UNSIGNED NOT NULL,
  utilisateur_id  INT UNSIGNED NOT NULL,
  premier_vu      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dernier_vu      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (appareil_id, utilisateur_id),
  INDEX idx_au_util (utilisateur_id),
  CONSTRAINT fk_au_app  FOREIGN KEY (appareil_id)    REFERENCES appareils(id) ON DELETE CASCADE,
  CONSTRAINT fk_au_util FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;
