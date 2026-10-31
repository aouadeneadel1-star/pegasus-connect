CREATE TABLE IF NOT EXISTS agents (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id           INT UNSIGNED NOT NULL,
  matricule                VARCHAR(20)  NOT NULL,
  civilite                 ENUM('M','Mme') NULL,
  nom                      VARCHAR(80)  NOT NULL,
  nom_naissance            VARCHAR(80)  NULL,
  prenom                   VARCHAR(80)  NOT NULL,
  date_naissance           DATE         NOT NULL,
  lieu_naissance           VARCHAR(120) NOT NULL,
  nationalite              VARCHAR(60)  NOT NULL,
  adresse                  VARCHAR(255) NOT NULL,
  code_postal              VARCHAR(10)  NOT NULL,
  ville                    VARCHAR(80)  NOT NULL,
  pays                     CHAR(2)      NOT NULL DEFAULT 'FR',
  telephone                VARCHAR(20)  NOT NULL,
  nir_chiffre              VARBINARY(255) NULL,          -- nonce + ciphertext (sodium secretbox)
  nir_index                CHAR(64)     NULL,            -- HMAC-SHA256 (index aveugle)
  nir_fin                  CHAR(2)      NULL,            -- 2 derniers chiffres pour l'affichage masqué
  fonction                 VARCHAR(80)  NOT NULL,        -- ADS, chef de poste, SSIAP 1…
  date_embauche            DATE         NOT NULL,
  date_sortie              DATE         NULL,
  type_contrat             ENUM('CDI','CDD','interim','apprentissage','autre') NOT NULL,
  temps_travail            ENUM('plein','partiel') NOT NULL DEFAULT 'plein',
  heures_mensuelles        DECIMAL(6,2) NOT NULL DEFAULT 151.67,
  taux_horaire_base        DECIMAL(8,4) NULL,
  equipe_id                INT UNSIGNED NULL,
  photo_document_id        BIGINT UNSIGNED NULL,
  created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_agents_util (utilisateur_id),
  UNIQUE KEY uq_agents_matricule (matricule),
  UNIQUE KEY uq_agents_nir (nir_index),
  INDEX idx_agents_nom (nom, prenom),
  INDEX idx_agents_equipe (equipe_id),
  CONSTRAINT fk_agents_util   FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
  CONSTRAINT fk_agents_equipe FOREIGN KEY (equipe_id)      REFERENCES equipes(id)
) ENGINE=InnoDB;

ALTER TABLE equipes ADD CONSTRAINT fk_equipes_chef
  FOREIGN KEY (chef_agent_id) REFERENCES agents(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS agent_sites (
  agent_id     INT UNSIGNED NOT NULL,
  site_id      INT UNSIGNED NOT NULL,
  date_debut   DATE         NOT NULL,
  date_fin     DATE         NULL,
  principal    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (agent_id, site_id, date_debut),
  INDEX idx_as_site (site_id, date_fin),
  CONSTRAINT fk_as_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_as_site  FOREIGN KEY (site_id)  REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sites_responsables (            -- périmètre des chefs d'équipe par site
  site_id   INT UNSIGNED NOT NULL,
  agent_id  INT UNSIGNED NOT NULL,           -- agent ayant le rôle chef
  PRIMARY KEY (site_id, agent_id),
  CONSTRAINT fk_sr_site  FOREIGN KEY (site_id)  REFERENCES sites(id) ON DELETE CASCADE,
  CONSTRAINT fk_sr_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documents (                      -- registre unique de tous les fichiers
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id         INT UNSIGNED NULL,
  categorie        ENUM('contrat','avenant','diplome','carte_pro','attestation','identite',
                        'paie','rapport_mensuel','photo_anomalie','photo_point','plan_site',
                        'societe','photo_agent','autre') NOT NULL,
  libelle          VARCHAR(150) NOT NULL,
  nom_original     VARCHAR(255) NOT NULL,
  chemin           VARCHAR(255) NOT NULL,      -- relatif à storage/fichiers, nom aléatoire
  mime             VARCHAR(100) NOT NULL,
  taille_octets    INT UNSIGNED NOT NULL,
  sha256           CHAR(64)     NOT NULL,
  date_document    DATE         NULL,
  date_validite    DATE         NULL,
  visible_agent    TINYINT(1)   NOT NULL DEFAULT 1,
  depose_par       INT UNSIGNED NULL,
  supprime_le      DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_doc_chemin (chemin),
  INDEX idx_doc_agent (agent_id, categorie, supprime_le),
  INDEX idx_doc_validite (date_validite),
  INDEX idx_doc_sha (sha256),
  CONSTRAINT fk_doc_agent FOREIGN KEY (agent_id)   REFERENCES agents(id),
  CONSTRAINT fk_doc_util  FOREIGN KEY (depose_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

ALTER TABLE sites  ADD CONSTRAINT fk_sites_plan   FOREIGN KEY (plan_document_id)  REFERENCES documents(id);

ALTER TABLE agents ADD CONSTRAINT fk_agents_photo FOREIGN KEY (photo_document_id) REFERENCES documents(id);

CREATE TABLE IF NOT EXISTS cartes_pro (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id           INT UNSIGNED NOT NULL,
  numero             VARCHAR(40)  NOT NULL,        -- ex. CAR-0XX-20XX-XX-XX-XXXXXXXXXXX
  type_activite      VARCHAR(100) NOT NULL,        -- surveillance humaine, cynophile…
  date_delivrance    DATE         NOT NULL,
  date_expiration    DATE         NOT NULL,
  recto_document_id  BIGINT UNSIGNED NULL,
  verso_document_id  BIGINT UNSIGNED NULL,
  courante           TINYINT(1)   NOT NULL DEFAULT 1,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cp_agent (agent_id, courante),
  INDEX idx_cp_expiration (date_expiration),
  CONSTRAINT fk_cp_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_cp_recto FOREIGN KEY (recto_document_id) REFERENCES documents(id),
  CONSTRAINT fk_cp_verso FOREIGN KEY (verso_document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS types_qualification (
  id              SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(30)  NOT NULL UNIQUE,   -- SST, SSIAP1, SSIAP2, SSIAP3, CQP_APS, H0B0…
  libelle         VARCHAR(120) NOT NULL,
  categorie       ENUM('secourisme','incendie','surveillance','electrique','autre') NOT NULL,
  validite_mois   SMALLINT UNSIGNED NULL,          -- durée par défaut avant recyclage
  actif           TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS qualifications (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id                INT UNSIGNED NOT NULL,
  type_qualification_id   SMALLINT UNSIGNED NOT NULL,
  intitule                VARCHAR(150) NULL,        -- précision libre
  numero                  VARCHAR(60)  NULL,        -- ex. n° certificat SST
  organisme               VARCHAR(150) NULL,
  date_obtention          DATE         NOT NULL,
  date_echeance           DATE         NULL,        -- validité ou recyclage
  document_id             BIGINT UNSIGNED NULL,
  created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_q_agent (agent_id),
  INDEX idx_q_echeance (date_echeance),
  CONSTRAINT fk_q_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_q_type  FOREIGN KEY (type_qualification_id) REFERENCES types_qualification(id),
  CONSTRAINT fk_q_doc   FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alertes_expiration_envois (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  objet_type     ENUM('carte_pro','qualification','document') NOT NULL,
  objet_id       BIGINT UNSIGNED NOT NULL,
  date_echeance  DATE         NOT NULL,
  seuil_jours    SMALLINT     NOT NULL,             -- 90, 30, 7, 0
  envoye_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alerte (objet_type, objet_id, date_echeance, seuil_jours)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandes_modification_profil (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id         INT UNSIGNED NOT NULL,
  changements      JSON         NOT NULL,           -- {"adresse":{"avant":"…","apres":"…"}}
  document_id      BIGINT UNSIGNED NULL,
  statut           ENUM('en_attente','validee','refusee') NOT NULL DEFAULT 'en_attente',
  motif_refus      VARCHAR(255) NULL,
  traite_par       INT UNSIGNED NULL,
  traite_le        DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dmp_statut (statut, created_at),
  CONSTRAINT fk_dmp_agent FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB;
