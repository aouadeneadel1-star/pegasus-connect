CREATE TABLE IF NOT EXISTS documents_societe (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titre           VARCHAR(150) NOT NULL,
  categorie       ENUM('reglement','consignes_generales','consignes_site','note_service','procedure','autre') NOT NULL,
  description     TEXT         NULL,
  accuse_requis   TINYINT(1)   NOT NULL DEFAULT 1,
  ciblage_global  TINYINT(1)   NOT NULL DEFAULT 1,  -- 1 = tous
  statut          ENUM('brouillon','publie','archive') NOT NULL DEFAULT 'brouillon',
  version_courante_id INT UNSIGNED NULL,
  created_by      INT UNSIGNED NOT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ds_statut (statut, categorie)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documents_societe_versions (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_societe_id  INT UNSIGNED NOT NULL,
  version              SMALLINT UNSIGNED NOT NULL,
  document_id          BIGINT UNSIGNED NOT NULL,
  note_version         VARCHAR(255) NULL,
  date_effet           DATE         NULL,
  publiee_le           DATETIME     NULL,
  publiee_par          INT UNSIGNED NULL,
  UNIQUE KEY uq_dsv (document_societe_id, version),
  CONSTRAINT fk_dsv_ds  FOREIGN KEY (document_societe_id) REFERENCES documents_societe(id),
  CONSTRAINT fk_dsv_doc FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documents_societe_cibles (
  document_societe_id  INT UNSIGNED NOT NULL,
  cible_type           ENUM('site','equipe','role') NOT NULL,
  cible_id             INT UNSIGNED NOT NULL,
  PRIMARY KEY (document_societe_id, cible_type, cible_id),
  CONSTRAINT fk_dsc_ds FOREIGN KEY (document_societe_id) REFERENCES documents_societe(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS accuses_lecture (
  version_id      INT UNSIGNED NOT NULL,
  utilisateur_id  INT UNSIGNED NOT NULL,
  ouvert_le       DATETIME     NULL,
  accuse_le       DATETIME     NULL,
  ip              VARBINARY(16) NULL,
  PRIMARY KEY (version_id, utilisateur_id),
  INDEX idx_al_util (utilisateur_id),
  CONSTRAINT fk_al_v FOREIGN KEY (version_id)     REFERENCES documents_societe_versions(id),
  CONSTRAINT fk_al_u FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;
