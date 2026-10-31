-- Taux par défaut de la convention collective Prévention-sécurité (IDCC 1351).
-- À faire valider par le gestionnaire de paie avant la mise en service.
INSERT IGNORE INTO regles_majoration (code, libelle, taux, plage_debut, plage_fin, seuil_heures, cumulable, date_effet) VALUES
('nuit', 'Heures de nuit (21 h – 6 h)', 10.00, '21:00:00', '06:00:00', NULL, 1, '2026-01-01'),
('dimanche', 'Heures du dimanche', 10.00, NULL, NULL, NULL, 1, '2026-01-01'),
('ferie', 'Heures de jour férié', 100.00, NULL, NULL, NULL, 1, '2026-01-01'),
('hs25', 'Heures supplémentaires à 25 %', 25.00, NULL, NULL, 8.00, 1, '2026-01-01'),
('hs50', 'Heures supplémentaires à 50 %', 50.00, NULL, NULL, NULL, 1, '2026-01-01');
