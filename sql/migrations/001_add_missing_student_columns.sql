-- Completa columnas que necesita la aplicación en bases creadas con un esquema anterior.
-- Selecciona docentes1_db antes de ejecutar este archivo en phpMyAdmin.
USE docentes1_db;

SET @has_is_historical := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'students'
    AND COLUMN_NAME = 'is_historical'
);
SET @ddl := IF(
  @has_is_historical = 0,
  'ALTER TABLE students ADD COLUMN is_historical TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_grade_status := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'student_grades'
    AND COLUMN_NAME = 'status'
);
SET @ddl := IF(
  @has_grade_status = 0,
  'ALTER TABLE student_grades ADD COLUMN status ENUM(''pendiente'',''aprobado'',''desaprobado'') NOT NULL DEFAULT ''pendiente''',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

-- Calcula el estado inicial usando la nota final que ya existe.
UPDATE student_grades
SET status = CASE
  WHEN final_grade IS NULL THEN 'pendiente'
  WHEN final_grade >= 60 THEN 'aprobado'
  ELSE 'desaprobado'
END;
