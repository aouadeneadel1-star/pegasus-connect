-- Anti-doublon des alertes émises par les tâches planifiées (absence, départ manquant, borne muette).
CREATE TABLE IF NOT EXISTS alertes_envoyees (
  cle        CHAR(64)  NOT NULL PRIMARY KEY,   -- SHA-256 de « type:objet:occurrence »
  envoye_le  DATETIME  NOT NULL,
  INDEX idx_ae_date (envoye_le)
) ENGINE=InnoDB;
