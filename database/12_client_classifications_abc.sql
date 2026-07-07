-- Migracion 12: clasificaciones A/B/C (usadas por la base de regalos de Buadnet)
INSERT INTO `client_classifications` (`name`, `description`) VALUES
('A', 'Clasificacion A (base de regalos)'),
('B', 'Clasificacion B (base de regalos)'),
('C', 'Clasificacion C (base de regalos)')
ON DUPLICATE KEY UPDATE `name` = `name`;
