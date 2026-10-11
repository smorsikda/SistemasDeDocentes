# Sistema de Docentes CONATRADEC

Aplicación web para administrar información académica, docentes, estudiantes, grupos, asistencias y notas.

## Funcionalidades

- Panel con estadísticas académicas.
- Administración de docentes, estudiantes y grupos.
- Registro e historial de asistencias y notas.
- Importación y exportación de estudiantes mediante CSV.
- Consulta y descarga de certificados.
- Control de acceso por roles, protección CSRF y registro de actividad.

## Tecnologías

- PHP 8.2 o compatible.
- MySQL o MariaDB.
- Apache (la guía de este proyecto usa XAMPP).
- PDO con la extensión `pdo_mysql` habilitada.

No se requiere Composer ni Node.js para ejecutar la aplicación actualmente.

## Requisitos previos

1. Instala XAMPP y confirma que Apache y MySQL/MariaDB puedan iniciar.
2. Coloca la carpeta del proyecto en `C:\xampp\htdocs\docentes`.
3. Si otro programa, como Dolibarr, ya usa el puerto MySQL `3306`, configura XAMPP MySQL en `3307`. La configuración de phpMyAdmin también debe apuntar al mismo puerto.
4. Abre el proyecto en VS Code desde `C:\xampp\htdocs\docentes`.

## Preparar la base de datos

1. Inicia Apache y MySQL desde XAMPP.
2. Abre phpMyAdmin, normalmente en `http://localhost/phpmyadmin/`.
3. Importa `sql/schema1.sql`. El script crea la base `docentes1_db` y sus tablas.
4. Copia `app/config/db.local.example.php` como `app/config/db.local.php` y ajusta host, base de datos, usuario, contraseña y puerto para tu equipo. Este archivo local está excluido de Git. El ejemplo usa `3307`; si MariaDB escucha en `3306`, cambia el puerto en tu copia local.
5. Abre `http://localhost/docentes/public/`.
6. Si la base todavía no tiene administrador, la aplicación mostrará el formulario para crear el primero. Después podrás iniciar sesión con ese usuario.

> **Importante:** `sql/schema1.sql` contiene instrucciones `DROP TABLE`. Al importarlo sobre una base existente se eliminan y recrean varias tablas. Haz una copia de seguridad antes de volver a importarlo si ya hay información que quieras conservar.

Si ya tienes una base de datos de una versión anterior, no vuelvas a importar `schema1.sql`. Selecciona `docentes1_db` en phpMyAdmin e importa `sql/migrations/001_add_missing_student_columns.sql`; esta migración agrega las columnas faltantes sin borrar tablas ni registros.

Para bases de datos existentes, también puedes importar `sql/migrations/002_add_audit_log_indexes.sql`. Agrega índices para que las consultas del historial y del límite de certificados sigan respondiendo bien cuando crezcan los registros; no borra ni modifica información.

Los registros de auditoría se conservan sin borrado automático. Antes de programar una limpieza, la institución debe definir el plazo de conservación y respaldar la base.

### ¿Cuál archivo SQL debo usar?

Usa `sql/schema1.sql` para esta versión: crea `docentes1_db` e incluye las tablas actuales de perfiles, escuelas, departamentos y auditoría. `sql/schema.sql` corresponde a un esquema anterior llamado `docentes_db`; no lo uses para una instalación nueva de esta versión.

## Estructura del proyecto

```text
app/
  config/       Conexión a la base de datos
  helpers/      Funciones auxiliares, CSRF y auditoría
  middleware/   Sesiones y control de acceso
assets/         Hojas de estilo e imágenes
public/         Páginas accesibles desde el navegador
sql/            Esquemas de base de datos
```

## Desarrollo y commits

Trabaja desde la carpeta del repositorio y revisa los archivos antes de confirmar cambios:

```powershell
git status
git diff
```

Usa una rama para cada mejora y mensajes de commit breves que expliquen el cambio, por ejemplo:

```text
docs: documentar instalación local con XAMPP
fix: validar token CSRF al importar estudiantes
feat: agregar filtro por escuela
```

No incluyas contraseñas, datos personales reales ni ajustes locales de XAMPP en los commits. La página de diagnóstico `public/test_db.php` se retiró para no exponer detalles de conexión. Antes de subir, verifica que `origin` apunte al repositorio propio:

```powershell
git remote -v
```

## Origen del proyecto

Este repositorio conserva el historial Git del proyecto original: <https://github.com/poyito777/conatradec1>. Los cambios nuevos deben describir el trabajo realizado en sus propios commits.
