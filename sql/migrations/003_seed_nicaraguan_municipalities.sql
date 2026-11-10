-- Agrega los municipios iniciales de Nicaragua sin borrar registros existentes.
-- Es repetible y conserva activos los municipios ya registrados.

-- BOACO
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Boaco', 1 FROM departments WHERE name='Boaco' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Camoapa', 1 FROM departments WHERE name='Boaco' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San José de los Remates', 1 FROM departments WHERE name='Boaco' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Lorenzo', 1 FROM departments WHERE name='Boaco' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa Lucía', 1 FROM departments WHERE name='Boaco' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Teustepe', 1 FROM departments WHERE name='Boaco' ON DUPLICATE KEY UPDATE is_active = 1;

-- CARAZO
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Diriamba', 1 FROM departments WHERE name='Carazo' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Dolores', 1 FROM departments WHERE name='Carazo' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Rosario', 1 FROM departments WHERE name='Carazo' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Jinotepe', 1 FROM departments WHERE name='Carazo' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Conquista', 1 FROM departments WHERE name='Carazo' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Paz de Carazo', 1 FROM departments WHERE name='Carazo' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Marcos', 1 FROM departments WHERE name='Carazo' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa Teresa', 1 FROM departments WHERE name='Carazo' ON DUPLICATE KEY UPDATE is_active = 1;

-- CHINANDEGA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Chichigalpa', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Chinandega', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Cinco Pinos', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Corinto', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Realejo', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Viejo', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Posoltega', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Puerto Morazán', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Francisco del Norte', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Pedro del Norte', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santo Tomás del Norte', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Somotillo', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Villanueva', 1 FROM departments WHERE name='Chinandega' ON DUPLICATE KEY UPDATE is_active = 1;

-- CHONTALES
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Acoyapa', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Comalapa', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Cuapa', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Coral', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Juigalpa', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Libertad', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Francisco de Cuapa', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Pedro de Lóvago', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santo Domingo', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santo Tomás', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Villa Sandino', 1 FROM departments WHERE name='Chontales' ON DUPLICATE KEY UPDATE is_active = 1;

-- ESTELI
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Condega', 1 FROM departments WHERE name='Estelí' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Estelí', 1 FROM departments WHERE name='Estelí' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Trinidad', 1 FROM departments WHERE name='Estelí' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Pueblo Nuevo', 1 FROM departments WHERE name='Estelí' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan de Limay', 1 FROM departments WHERE name='Estelí' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Nicolás', 1 FROM departments WHERE name='Estelí' ON DUPLICATE KEY UPDATE is_active = 1;

-- GRANADA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Diriá', 1 FROM departments WHERE name='Granada' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Diriomo', 1 FROM departments WHERE name='Granada' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Granada', 1 FROM departments WHERE name='Granada' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nandaime', 1 FROM departments WHERE name='Granada' ON DUPLICATE KEY UPDATE is_active = 1;

-- JINOTEGA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Cuá', 1 FROM departments WHERE name='Jinotega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Jinotega', 1 FROM departments WHERE name='Jinotega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Concordia', 1 FROM departments WHERE name='Jinotega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San José de Bocay', 1 FROM departments WHERE name='Jinotega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Rafael del Norte', 1 FROM departments WHERE name='Jinotega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Sebastián de Yalí', 1 FROM departments WHERE name='Jinotega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa María de Pantasma', 1 FROM departments WHERE name='Jinotega' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Wiwilí de Jinotega', 1 FROM departments WHERE name='Jinotega' ON DUPLICATE KEY UPDATE is_active = 1;

-- LEON
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Achuapa', 1 FROM departments WHERE name='León' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Jicaral', 1 FROM departments WHERE name='León' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Paz Centro', 1 FROM departments WHERE name='León' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Larreynaga', 1 FROM departments WHERE name='León' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'León', 1 FROM departments WHERE name='León' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nagarote', 1 FROM departments WHERE name='León' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Quezalguaque', 1 FROM departments WHERE name='León' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa Rosa del Peñón', 1 FROM departments WHERE name='León' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Telica', 1 FROM departments WHERE name='León' ON DUPLICATE KEY UPDATE is_active = 1;

-- MADRIZ
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Las Sabanas', 1 FROM departments WHERE name='Madriz' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Palacagüina', 1 FROM departments WHERE name='Madriz' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San José de Cusmapa', 1 FROM departments WHERE name='Madriz' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan de Río Coco', 1 FROM departments WHERE name='Madriz' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Lucas', 1 FROM departments WHERE name='Madriz' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Somoto', 1 FROM departments WHERE name='Madriz' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Telpaneca', 1 FROM departments WHERE name='Madriz' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Totogalpa', 1 FROM departments WHERE name='Madriz' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Yalagüina', 1 FROM departments WHERE name='Madriz' ON DUPLICATE KEY UPDATE is_active = 1;

-- MANAGUA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ciudad Sandino', 1 FROM departments WHERE name='Managua' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Crucero', 1 FROM departments WHERE name='Managua' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Managua', 1 FROM departments WHERE name='Managua' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Francisco Libre', 1 FROM departments WHERE name='Managua' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Rafael del Sur', 1 FROM departments WHERE name='Managua' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Tipitapa', 1 FROM departments WHERE name='Managua' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ticuantepe', 1 FROM departments WHERE name='Managua' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Villa Carlos Fonseca', 1 FROM departments WHERE name='Managua' ON DUPLICATE KEY UPDATE is_active = 1;

-- MASAYA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Catarina', 1 FROM departments WHERE name='Masaya' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Concepción', 1 FROM departments WHERE name='Masaya' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Masatepe', 1 FROM departments WHERE name='Masaya' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Masaya', 1 FROM departments WHERE name='Masaya' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nandasmo', 1 FROM departments WHERE name='Masaya' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nindirí', 1 FROM departments WHERE name='Masaya' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Niquinohomo', 1 FROM departments WHERE name='Masaya' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan de Oriente', 1 FROM departments WHERE name='Masaya' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Tisma', 1 FROM departments WHERE name='Masaya' ON DUPLICATE KEY UPDATE is_active = 1;

-- MATAGALPA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ciudad Darío', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Tuma - La Dalia', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Esquipulas', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Matagalpa', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Matiguás', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Muy Muy', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Rancho Grande', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Río Blanco', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Dionisio', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Isidro', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Ramón', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Sébaco', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Terrabona', 1 FROM departments WHERE name='Matagalpa' ON DUPLICATE KEY UPDATE is_active = 1;

-- NUEVA SEGOVIA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ciudad Antigua', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Dipilto', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Jícaro', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Jalapa', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Macuelizo', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Mozonte', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Murra', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ocotal', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Quilalí', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Fernando', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa María', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Wiwilí de Nueva Segovia', 1 FROM departments WHERE name='Nueva Segovia' ON DUPLICATE KEY UPDATE is_active = 1;

-- RIO SAN JUAN
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Almendro', 1 FROM departments WHERE name='Río San Juan' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Castillo', 1 FROM departments WHERE name='Río San Juan' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Morrito', 1 FROM departments WHERE name='Río San Juan' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Carlos', 1 FROM departments WHERE name='Río San Juan' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan de Nicaragua', 1 FROM departments WHERE name='Río San Juan' ON DUPLICATE KEY UPDATE is_active = 1;

-- RIVAS
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Altagracia', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Belén', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Buenos Aires', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Cárdenas', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Moyogalpa', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Potosí', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Rivas', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Jorge', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan del Sur', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Tola', 1 FROM departments WHERE name='Rivas' ON DUPLICATE KEY UPDATE is_active = 1;

-- RAAN
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Bonanza', 1 FROM departments WHERE name='RAAN' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Mulukukú', 1 FROM departments WHERE name='RAAN' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Prinzapolka', 1 FROM departments WHERE name='RAAN' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Puerto Cabezas', 1 FROM departments WHERE name='RAAN' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Rosita', 1 FROM departments WHERE name='RAAN' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Siuna', 1 FROM departments WHERE name='RAAN' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Waslala', 1 FROM departments WHERE name='RAAN' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Waspam', 1 FROM departments WHERE name='RAAN' ON DUPLICATE KEY UPDATE is_active = 1;

-- RAAS
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Bluefields', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Corn Island', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Desembocadura de Río Grande', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Ayote', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Rama', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Kukra Hill', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Cruz de Río Grande', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Laguna de Perlas', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Muelle de los Bueyes', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nueva Guinea', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Paiwas', 1 FROM departments WHERE name='RAAS' ON DUPLICATE KEY UPDATE is_active = 1;
