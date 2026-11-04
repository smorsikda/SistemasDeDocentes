CREATE DATABASE IF NOT EXISTS docentes1_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE docentes1_db;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS attendance_items;
DROP TABLE IF EXISTS attendances;
DROP TABLE IF EXISTS group_students;
DROP TABLE IF EXISTS student_grades;
DROP TABLE IF EXISTS student_profiles;
DROP TABLE IF EXISTS student_organizations;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS municipalities;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS schools;
DROP TABLE IF EXISTS groups_table;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- TABLA: users
-- =========================================================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: schools
-- =========================================================
CREATE TABLE schools (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: departments
-- =========================================================
CREATE TABLE departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: municipalities
-- =========================================================
CREATE TABLE municipalities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_department_municipality (department_id, name),
  INDEX idx_municipalities_department_id (department_id),
  CONSTRAINT fk_municipalities_department
    FOREIGN KEY (department_id)
    REFERENCES departments(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: students
-- Deja identidad, contacto, formación y estado
-- =========================================================
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  school_id INT NULL,
  department_id INT NULL,
  municipality_id INT NULL,

  student_code VARCHAR(20) NULL UNIQUE,
  full_name VARCHAR(200) NOT NULL,

  sex ENUM('masculino','femenino') NULL,
  education_level ENUM('secundaria','tecnico','universitario') NULL,
  profession VARCHAR(150) NULL,
  nationality VARCHAR(100) NULL,

  phone VARCHAR(20) NULL,
  cedula VARCHAR(20) NULL,

  course_type ENUM('barismo','catacion') NOT NULL DEFAULT 'barismo',
  course_level ENUM('basico','avanzado','intensivo') NOT NULL DEFAULT 'basico',
  enrolled_at DATE NULL,

  community VARCHAR(120) NULL,

  final_grade DECIMAL(7,2) NULL,
  status ENUM('pendiente','aprobado','desaprobado') NOT NULL DEFAULT 'pendiente',
  is_historical TINYINT(1) NOT NULL DEFAULT 0,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_students_teacher_id (teacher_id),
  INDEX idx_students_school_id (school_id),
  INDEX idx_students_department_id (department_id),
  INDEX idx_students_municipality_id (municipality_id),
  INDEX idx_students_course_type (course_type),
  INDEX idx_students_course_level (course_level),
  INDEX idx_students_status (status),

  CONSTRAINT fk_students_teacher
    FOREIGN KEY (teacher_id)
    REFERENCES users(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_students_school
    FOREIGN KEY (school_id)
    REFERENCES schools(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_students_department
    FOREIGN KEY (department_id)
    REFERENCES departments(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_students_municipality
    FOREIGN KEY (municipality_id)
    REFERENCES municipalities(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: student_organizations
-- Organización / emprendimiento del estudiante
-- =========================================================
CREATE TABLE student_organizations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  organization_type ENUM('institucion','privado','emprendimiento','estudiante','productor') NULL,
  organization_name VARCHAR(200) NULL,
  organization_phone VARCHAR(20) NULL,
  organization_location VARCHAR(200) NULL,
  characterization VARCHAR(200) NULL,
  trademark_registration ENUM('si','no') NULL,
  number_of_members INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY unique_student_organization (student_id),
  INDEX idx_student_organizations_student_id (student_id),

  CONSTRAINT fk_student_organizations_student
    FOREIGN KEY (student_id)
    REFERENCES students(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: student_profiles
-- Seguimiento, propósito, observaciones
-- =========================================================
CREATE TABLE student_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_purpose TEXT NULL,
  future_projection TEXT NULL,
  observations TEXT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY unique_student_profile (student_id),
  INDEX idx_student_profiles_student_id (student_id),

  CONSTRAINT fk_student_profiles_student
    FOREIGN KEY (student_id)
    REFERENCES students(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: groups_table
-- =========================================================
CREATE TABLE groups_table (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_code VARCHAR(30) NOT NULL,
  teacher_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  capacity INT NULL,
  schedule VARCHAR(120) NULL,
  location VARCHAR(200) NULL,
  course_type ENUM('barismo','catacion') NOT NULL,
  course_level ENUM('basico','avanzado','intensivo') NOT NULL,
  status ENUM('activo','finalizado','cancelado') NOT NULL DEFAULT 'activo',
  notes TEXT NULL,
  department VARCHAR(120) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_group_code (group_code),
  INDEX idx_groups_teacher_id (teacher_id),
  INDEX idx_groups_status (status),

  CONSTRAINT fk_groups_teacher
    FOREIGN KEY (teacher_id)
    REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: group_students
-- =========================================================
CREATE TABLE group_students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  student_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_group_student (group_id, student_id),
  INDEX idx_group_students_group_id (group_id),
  INDEX idx_group_students_student_id (student_id),

  CONSTRAINT fk_group_students_group
    FOREIGN KEY (group_id)
    REFERENCES groups_table(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_group_students_student
    FOREIGN KEY (student_id)
    REFERENCES students(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: attendances
-- =========================================================
CREATE TABLE attendances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  teacher_id INT NULL,
  attendance_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_group_attendance_date (group_id, attendance_date),
  INDEX idx_attendances_group_id (group_id),
  INDEX idx_attendances_teacher_id (teacher_id),
  INDEX idx_attendances_date (attendance_date),

  CONSTRAINT fk_attendances_group
    FOREIGN KEY (group_id)
    REFERENCES groups_table(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_attendances_teacher
    FOREIGN KEY (teacher_id)
    REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: attendance_items
-- =========================================================
CREATE TABLE attendance_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attendance_id INT NOT NULL,
  student_id INT NOT NULL,
  present TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_attendance_student (attendance_id, student_id),
  INDEX idx_attendance_items_attendance_id (attendance_id),
  INDEX idx_attendance_items_student_id (student_id),

  CONSTRAINT fk_attendance_items_attendance
    FOREIGN KEY (attendance_id)
    REFERENCES attendances(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_attendance_items_student
    FOREIGN KEY (student_id)
    REFERENCES students(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- TABLA: student_grades
-- Se mantiene sin normalizar evaluaciones por ahora
-- =========================================================
CREATE TABLE student_grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  group_id INT NOT NULL,
  teacher_id INT NULL,
  exam1 DECIMAL(7,2) NULL,
  exam2 DECIMAL(7,2) NULL,
  exam3 DECIMAL(7,2) NULL,
  exam4 DECIMAL(7,2) NULL,
  exam5 DECIMAL(7,2) NULL,
  final_grade DECIMAL(7,2) NULL,
  status ENUM('pendiente','aprobado','desaprobado') NOT NULL DEFAULT 'pendiente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY unique_student_grade (student_id, group_id),
  INDEX idx_student_grades_student_id (student_id),
  INDEX idx_student_grades_group_id (group_id),
  INDEX idx_student_grades_teacher_id (teacher_id),

  CONSTRAINT fk_student_grades_student
    FOREIGN KEY (student_id)
    REFERENCES students(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_student_grades_group
    FOREIGN KEY (group_id)
    REFERENCES groups_table(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_student_grades_teacher
    FOREIGN KEY (teacher_id)
    REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- DATOS INICIALES: escuelas
-- =========================================================
INSERT INTO schools (name) VALUES
('Escuela Café Boutique Managua'),
('Cra. Natividad Martínez Sanchez'),
('Cra. Eudosia Abdulia Gomez Chavarria La Docha'),
('Cro. Gabriel Martínez Herrera San Juan de Río Coco Madriz');

-- =========================================================
-- DATOS INICIALES: departments y municipalities
-- =========================================================
INSERT INTO departments (name) VALUES
('Boaco'),
('Carazo'),
('Chinandega'),
('Chontales'),
('Estelí'),
('Granada'),
('Jinotega'),
('León'),
('Madriz'),
('Managua'),
('Masaya'),
('Matagalpa'),
('Nueva Segovia'),
('Río San Juan'),
('Rivas'),
('RAAN'),
('RAAS');

-- Municipios por departamento para el selector del formulario de estudiantes
-- BOACO
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Boaco', 1 FROM departments WHERE name='Boaco';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Camoapa', 1 FROM departments WHERE name='Boaco';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San José de los Remates', 1 FROM departments WHERE name='Boaco';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Lorenzo', 1 FROM departments WHERE name='Boaco';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa Lucía', 1 FROM departments WHERE name='Boaco';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Teustepe', 1 FROM departments WHERE name='Boaco';

-- CARAZO
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Diriamba', 1 FROM departments WHERE name='Carazo';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Dolores', 1 FROM departments WHERE name='Carazo';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Rosario', 1 FROM departments WHERE name='Carazo';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Jinotepe', 1 FROM departments WHERE name='Carazo';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Conquista', 1 FROM departments WHERE name='Carazo';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Paz de Carazo', 1 FROM departments WHERE name='Carazo';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Marcos', 1 FROM departments WHERE name='Carazo';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa Teresa', 1 FROM departments WHERE name='Carazo';

-- CHINANDEGA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Chichigalpa', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Chinandega', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Cinco Pinos', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Corinto', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Realejo', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Viejo', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Posoltega', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Puerto Morazán', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Francisco del Norte', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Pedro del Norte', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santo Tomás del Norte', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Somotillo', 1 FROM departments WHERE name='Chinandega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Villanueva', 1 FROM departments WHERE name='Chinandega';

-- CHONTALES
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Acoyapa', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Comalapa', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Cuapa', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Coral', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Juigalpa', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Libertad', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Francisco de Cuapa', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Pedro de Lóvago', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santo Domingo', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santo Tomás', 1 FROM departments WHERE name='Chontales';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Villa Sandino', 1 FROM departments WHERE name='Chontales';

-- ESTELI
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Condega', 1 FROM departments WHERE name='Estelí';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Estelí', 1 FROM departments WHERE name='Estelí';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Trinidad', 1 FROM departments WHERE name='Estelí';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Pueblo Nuevo', 1 FROM departments WHERE name='Estelí';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan de Limay', 1 FROM departments WHERE name='Estelí';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Nicolás', 1 FROM departments WHERE name='Estelí';

-- GRANADA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Diriá', 1 FROM departments WHERE name='Granada';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Diriomo', 1 FROM departments WHERE name='Granada';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Granada', 1 FROM departments WHERE name='Granada';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nandaime', 1 FROM departments WHERE name='Granada';

-- JINOTEGA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Cuá', 1 FROM departments WHERE name='Jinotega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Jinotega', 1 FROM departments WHERE name='Jinotega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Concordia', 1 FROM departments WHERE name='Jinotega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San José de Bocay', 1 FROM departments WHERE name='Jinotega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Rafael del Norte', 1 FROM departments WHERE name='Jinotega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Sebastián de Yalí', 1 FROM departments WHERE name='Jinotega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa María de Pantasma', 1 FROM departments WHERE name='Jinotega';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Wiwilí de Jinotega', 1 FROM departments WHERE name='Jinotega';

-- LEON
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Achuapa', 1 FROM departments WHERE name='León';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Jicaral', 1 FROM departments WHERE name='León';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Paz Centro', 1 FROM departments WHERE name='León';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Larreynaga', 1 FROM departments WHERE name='León';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'León', 1 FROM departments WHERE name='León';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nagarote', 1 FROM departments WHERE name='León';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Quezalguaque', 1 FROM departments WHERE name='León';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa Rosa del Peñón', 1 FROM departments WHERE name='León';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Telica', 1 FROM departments WHERE name='León';

-- MADRIZ
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Las Sabanas', 1 FROM departments WHERE name='Madriz';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Palacagüina', 1 FROM departments WHERE name='Madriz';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San José de Cusmapa', 1 FROM departments WHERE name='Madriz';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan de Río Coco', 1 FROM departments WHERE name='Madriz';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Lucas', 1 FROM departments WHERE name='Madriz';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Somoto', 1 FROM departments WHERE name='Madriz';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Telpaneca', 1 FROM departments WHERE name='Madriz';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Totogalpa', 1 FROM departments WHERE name='Madriz';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Yalagüina', 1 FROM departments WHERE name='Madriz';

-- MANAGUA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ciudad Sandino', 1 FROM departments WHERE name='Managua';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Crucero', 1 FROM departments WHERE name='Managua';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Managua', 1 FROM departments WHERE name='Managua';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Francisco Libre', 1 FROM departments WHERE name='Managua';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Rafael del Sur', 1 FROM departments WHERE name='Managua';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Tipitapa', 1 FROM departments WHERE name='Managua';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ticuantepe', 1 FROM departments WHERE name='Managua';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Villa Carlos Fonseca', 1 FROM departments WHERE name='Managua';

-- MASAYA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Catarina', 1 FROM departments WHERE name='Masaya';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Concepción', 1 FROM departments WHERE name='Masaya';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Masatepe', 1 FROM departments WHERE name='Masaya';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Masaya', 1 FROM departments WHERE name='Masaya';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nandasmo', 1 FROM departments WHERE name='Masaya';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nindirí', 1 FROM departments WHERE name='Masaya';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Niquinohomo', 1 FROM departments WHERE name='Masaya';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan de Oriente', 1 FROM departments WHERE name='Masaya';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Tisma', 1 FROM departments WHERE name='Masaya';

-- MATAGALPA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ciudad Darío', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Tuma - La Dalia', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Esquipulas', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Matagalpa', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Matiguás', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Muy Muy', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Rancho Grande', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Río Blanco', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Dionisio', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Isidro', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Ramón', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Sébaco', 1 FROM departments WHERE name='Matagalpa';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Terrabona', 1 FROM departments WHERE name='Matagalpa';

-- NUEVA SEGOVIA
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ciudad Antigua', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Dipilto', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Jícaro', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Jalapa', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Macuelizo', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Mozonte', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Murra', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Ocotal', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Quilalí', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Fernando', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Santa María', 1 FROM departments WHERE name='Nueva Segovia';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Wiwilí de Nueva Segovia', 1 FROM departments WHERE name='Nueva Segovia';

-- RIO SAN JUAN
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Almendro', 1 FROM departments WHERE name='Río San Juan';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Castillo', 1 FROM departments WHERE name='Río San Juan';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Morrito', 1 FROM departments WHERE name='Río San Juan';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Carlos', 1 FROM departments WHERE name='Río San Juan';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan de Nicaragua', 1 FROM departments WHERE name='Río San Juan';

-- RIVAS
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Altagracia', 1 FROM departments WHERE name='Rivas';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Belén', 1 FROM departments WHERE name='Rivas';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Buenos Aires', 1 FROM departments WHERE name='Rivas';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Cárdenas', 1 FROM departments WHERE name='Rivas';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Moyogalpa', 1 FROM departments WHERE name='Rivas';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Potosí', 1 FROM departments WHERE name='Rivas';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Rivas', 1 FROM departments WHERE name='Rivas';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Jorge', 1 FROM departments WHERE name='Rivas';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'San Juan del Sur', 1 FROM departments WHERE name='Rivas';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Tola', 1 FROM departments WHERE name='Rivas';

-- RAAN
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Bonanza', 1 FROM departments WHERE name='RAAN';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Mulukukú', 1 FROM departments WHERE name='RAAN';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Prinzapolka', 1 FROM departments WHERE name='RAAN';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Puerto Cabezas', 1 FROM departments WHERE name='RAAN';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Rosita', 1 FROM departments WHERE name='RAAN';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Siuna', 1 FROM departments WHERE name='RAAN';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Waslala', 1 FROM departments WHERE name='RAAN';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Waspam', 1 FROM departments WHERE name='RAAN';

-- RAAS
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Bluefields', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Corn Island', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Desembocadura de Río Grande', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Ayote', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'El Rama', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Kukra Hill', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'La Cruz de Río Grande', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Laguna de Perlas', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Muelle de los Bueyes', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Nueva Guinea', 1 FROM departments WHERE name='RAAS';
INSERT INTO municipalities (department_id, name, is_active)
SELECT id, 'Paiwas', 1 FROM departments WHERE name='RAAS';

-- =========================================================
-- TABLA: login_attempts
-- Control de intentos de login
-- =========================================================
CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempts INT NOT NULL DEFAULT 1,
  last_attempt_at DATETIME NOT NULL,
  blocked_until DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_email (email),
  INDEX idx_ip (ip_address),
  INDEX idx_login_attempts_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =========================================================
-- TABLA: activity_logs
-- Registro de acciones del sistema
-- =========================================================
CREATE TABLE activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  description TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_activity_created_at (created_at),
  INDEX idx_activity_certificate_ip_time (action, ip_address, created_at),

  CONSTRAINT fk_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
