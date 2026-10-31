-- Durées de validité par défaut, modifiables qualification par qualification sur la fiche agent.
INSERT INTO types_qualification (code, libelle, categorie, validite_mois) VALUES
('SST', 'Sauveteur secouriste du travail', 'secourisme', 24),
('SSIAP1', 'SSIAP 1 — agent de service de sécurité incendie', 'incendie', 36),
('SSIAP2', 'SSIAP 2 — chef d''équipe de service de sécurité incendie', 'incendie', 36),
('SSIAP3', 'SSIAP 3 — chef de service de sécurité incendie', 'incendie', 36),
('CQP_APS', 'CQP Agent de prévention et de sécurité', 'surveillance', NULL),
('TFP_APS', 'TFP Agent de prévention et de sécurité', 'surveillance', NULL),
('CYNO', 'Agent cynophile de sécurité', 'surveillance', NULL),
('H0B0', 'Habilitation électrique H0B0', 'electrique', 36),
('AUTRE', 'Autre qualification', 'autre', NULL)
ON DUPLICATE KEY UPDATE libelle = VALUES(libelle), categorie = VALUES(categorie);
