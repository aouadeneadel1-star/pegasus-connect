INSERT INTO roles (id, code, libelle) VALUES
(1, 'agent', 'Agent'),
(2, 'chef', 'Chef d''équipe'),
(3, 'rh', 'RH'),
(4, 'admin', 'Administrateur'),
(5, 'client', 'Client')
ON DUPLICATE KEY UPDATE code = VALUES(code), libelle = VALUES(libelle);
