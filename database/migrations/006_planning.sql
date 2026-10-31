CREATE TABLE IF NOT EXISTS publications_planning (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  periode_debut  DATE NOT NULL,
  periode_fin    DATE NOT NULL,
  perimetre      JSON NOT NULL,                    -- {"sites":[…],"equipes":[…]}
  nb_vacations   INT UNSIGNED NOT NULL,
  publiee_par    INT UNSIGNED NOT NULL,
  publiee_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vacations (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id         INT UNSIGNED NOT NULL,
  site_id          INT UNSIGNED NOT NULL,
  debut            DATETIME     NOT NULL,          -- UTC
  fin              DATETIME     NOT NULL,          -- UTC, > debut
  poste            VARCHAR(60)  NULL,
  pause_minutes    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  statut           ENUM('brouillon','publiee','annulee') NOT NULL DEFAULT 'brouillon',
  publication_id   INT UNSIGNED NULL,
  force_motif      VARCHAR(255) NULL,              -- si dépassement de plafond forcé
  commentaire      VARCHAR(255) NULL,
  created_by       INT UNSIGNED NOT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vac_agent (agent_id, debut),
  INDEX idx_vac_site (site_id, debut),
  INDEX idx_vac_statut (statut, debut),
  CONSTRAINT fk_vac_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_vac_site  FOREIGN KEY (site_id)  REFERENCES sites(id),
  CONSTRAINT fk_vac_pub   FOREIGN KEY (publication_id) REFERENCES publications_planning(id),
  CONSTRAINT ck_vac_duree CHECK (fin > debut)
) ENGINE=InnoDB;
