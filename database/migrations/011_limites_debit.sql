-- Compteurs de limitation de débit (PIN de badge, enrôlement de borne, API).
-- La clé est une empreinte : aucune donnée personnelle n'y figure en clair.
CREATE TABLE IF NOT EXISTS limites_debit (
  cle          CHAR(64)     NOT NULL PRIMARY KEY,
  compteur     INT UNSIGNED NOT NULL DEFAULT 0,
  expire_le    DATETIME     NOT NULL,
  INDEX idx_ld_expire (expire_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
