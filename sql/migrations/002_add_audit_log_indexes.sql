-- Agrega índices para consultas del historial y el límite de consulta de certificados.
-- La migración no borra ni modifica registros.
USE docentes1_db;

SET @has_created_at_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'activity_logs'
    AND INDEX_NAME = 'idx_activity_created_at'
);
SET @ddl := IF(
  @has_created_at_index = 0,
  'CREATE INDEX idx_activity_created_at ON activity_logs (created_at)',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_certificate_rate_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'activity_logs'
    AND INDEX_NAME = 'idx_activity_certificate_ip_time'
);
SET @ddl := IF(
  @has_certificate_rate_index = 0,
  'CREATE INDEX idx_activity_certificate_ip_time ON activity_logs (action, ip_address, created_at)',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_login_created_at_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'login_attempts'
    AND INDEX_NAME = 'idx_login_attempts_created_at'
);
SET @ddl := IF(
  @has_login_created_at_index = 0,
  'CREATE INDEX idx_login_attempts_created_at ON login_attempts (created_at)',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
