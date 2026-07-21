USE tinder_cows;

-- Fictional demonstration data. Do not run this file in production.
INSERT INTO producers (identification_type, identification_number, name, farm_name, phone, email, address, status) VALUES
    ('NATIONAL_ID', '101110111', 'Maria Fernandez Solano', 'El Roble Farm', '88881111', 'maria.fernandez@example.test', 'San Carlos, Alajuela', 'ACTIVE'),
    ('LEGAL_ID', '3101111111', 'Valle Verde Cattle Ranch S.A.', 'Valle Verde', '22221111', 'contacto.valleverde@example.test', 'Tilaran, Guanacaste', 'ACTIVE')
ON DUPLICATE KEY UPDATE
    name = VALUES(name), farm_name = VALUES(farm_name), phone = VALUES(phone), address = VALUES(address);
