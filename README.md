# Sistema Académico CONATRADEC

Aplicación web para administrar docentes, estudiantes, grupos, asistencias y notas de los cursos de CONATRADEC. Está construida con PHP y MariaDB, y organizada como un monolito web sencillo de desplegar con XAMPP o Docker.

[![Docker CI/CD](https://github.com/smorsikda/SistemasDeDocentes/actions/workflows/docker-ci.yml/badge.svg?branch=develop)](https://github.com/smorsikda/SistemasDeDocentes/actions/workflows/docker-ci.yml)
![PHP 8.2](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MariaDB 10.11](https://img.shields.io/badge/MariaDB-10.11-003545?logo=mariadb&logoColor=white)

## Objetivo

Centralizar el registro académico y el seguimiento de estudiantes en cursos de Barismo y Catación, manteniendo relacionados docentes, grupos, asistencia, notas e historial.

## Módulos

- **Acceso y administración:** inicio de sesión, roles de administrador y docente, cuentas de docentes, cambio y restablecimiento de contraseña.
- **Estudiantes:** registro por secciones, perfil, ubicación, importación y exportación CSV, archivo histórico y constancias.
- **Grupos:** creación y administración de grupos, asignación de estudiantes y cierre de grupo.
- **Seguimiento académico:** asistencia, notas, historial y reportes imprimibles.
- **Administración:** dashboard, guía del sistema y bitácora de actividad.

## Arquitectura

Aplicación PHP renderizada en el servidor. Apache sirve las páginas y recursos, PHP procesa formularios y reglas de negocio usando PDO, y MariaDB conserva los registros.

```text
Navegador ──HTML/CSS/JavaScript──► Apache + PHP ──PDO──► MariaDB
```

## Tecnologías

| Área | Tecnologías |
| --- | --- |
| Interfaz | HTML, CSS, JavaScript |
| Servidor | PHP 8.2, PDO, Apache |
| Base de datos | MariaDB 10.11 / MySQL compatible |
| Desarrollo local | XAMPP o Docker Compose |
| Integración continua | GitHub Actions: sintaxis PHP y construcción de imagen Docker |

No se requiere Composer ni Node.js. La imagen de `main` se publica en GitHub Container Registry (GHCR); el despliegue en Render es opcional y se describe más adelante.

## Ejecutar localmente con XAMPP

1. Copia el proyecto a `C:\xampp\htdocs\docentes`.
2. Inicia Apache y MySQL/MariaDB en XAMPP.
3. Importa `sql/schema1.sql` en phpMyAdmin.
4. Copia `backend/config/db.local.example.php` como `backend/config/db.local.php` y configura el puerto, usuario y contraseña de tu instalación.
5. Abre `http://localhost/docentes/public/`.

Si Dolibarr usa el puerto 80 o 3306, configura Apache/MySQL de XAMPP en otros puertos y actualiza `db.local.php` con el puerto real de MariaDB.

## Ejecutar localmente con Docker

Requisitos: Docker Desktop con Docker Compose habilitado.

Desde PowerShell, dentro de la carpeta del repositorio:

```powershell
Copy-Item .env.example .env
docker compose up --build
```

Abre `http://localhost:8080/docentes/public/`. La aplicación usa Apache/PHP en un contenedor y MariaDB en otro. La base de datos no publica un puerto en Windows, así que no choca con el MySQL de XAMPP. El puerto web se cambia con `APP_PORT` en `.env`.

La primera inicialización de MariaDB importa `sql/schema1.sql` y crea un volumen persistente. Para detener los servicios usa `Ctrl+C` y luego:

```powershell
docker compose down
```

`docker compose down` conserva la información de la base en el volumen. **No uses `docker compose down -v` si quieres conservarla**: `-v` elimina el volumen y los datos locales.

El archivo `schema1.sql` contiene `DROP TABLE` y solo se debe usar para crear una base local nueva. No se vuelve a importar automáticamente en un volumen ya inicializado. No uses ese archivo para actualizar una base con información que quieras conservar; para una base existente, aplica la migración correspondiente de `sql/migrations/` después de un respaldo.

La migración `003_seed_nicaraguan_municipalities.sql` agrega los municipios iniciales al catálogo sin borrar registros existentes. Aplícala también en bases existentes para habilitar el selector de municipios del formulario de estudiantes.

`public/health.php` ofrece una comprobación sencilla de PHP y conexión a la base de datos: `/docentes/public/health.php`.

## Integración continua y ramas

El flujo `.github/workflows/docker-ci.yml` revisa la sintaxis PHP y construye la imagen Docker en cada push y en pull requests hacia `develop` o `main`. Cuando se actualiza `main`, publica la imagen en GHCR.

```text
main (versión final estable)
└── develop (integración del equipo durante el proyecto)
    ├── feature/nombre-de-tarea
    └── fix/nombre-del-error
```

Cada integrante trabaja en su propia rama, creada desde `develop`. No hagan cambios directamente en `develop` o `main`. Usen `feature/` para nuevas funciones y `fix/` para corregir errores. Al terminar, abran un pull request hacia `develop`; integren `develop` en `main` cuando el equipo confirme que el proyecto está listo para entregar.

### Tarea propuesta para Shurone: registrar la reactivación en la bitácora

La aplicación ya guarda en la bitácora el archivado de estudiantes, pero la reactivación por el administrador no queda registrada. Esta tarea completa la trazabilidad de ese cambio.

**Rama:** `feature/auditoria-reactivacion-estudiantes`

**Alcance:** actualizar `public/student_reactivate.php` para comprobar que el estudiante exista y esté archivado, reactivarlo y registrar la acción mediante el helper existente en `backend/helpers/log.php`. Mantener el acceso exclusivo del administrador, el método POST y la protección CSRF.

**Criterios de aceptación:**

- Una reactivación válida deja al estudiante activo y crea una entrada `student.reactivate` con el usuario administrador y el ID del estudiante.
- Un ID inválido o un estudiante inexistente no crea una entrada falsa de auditoría.
- Repetir la acción sobre un estudiante ya activo no duplica registros ni reporta una reactivación exitosa.
- El cambio se puede comprobar desde la bitácora y la lista de estudiantes.

Para iniciar, Shurone debe tener el repositorio clonado y ejecutar desde PowerShell:

```powershell
git fetch origin
git switch develop
git pull --ff-only origin develop
git switch -c feature/auditoria-reactivacion-estudiantes
```

Al terminar, debe revisar sus cambios, crear el commit y subir la rama:

```powershell
git status
git add public/student_reactivate.php
git commit -m "feat: registra reactivacion de estudiantes en auditoria"
git push -u origin feature/auditoria-reactivacion-estudiantes
```

En GitHub: **Pull requests → New pull request**, base `develop` y compare `feature/auditoria-reactivacion-estudiantes`. En la descripción, incluir qué cambió y cómo se verificó. Asigna a Shurone en GitHub si su cuenta está disponible; el nombre de rama no asigna personas. Cada integrante debe usar su propia cuenta Git para que sus commits tengan la autoría correcta.

Otras mejoras para priorizar después: ampliar las pruebas automatizadas de reglas de negocio; revisar la consistencia de validaciones entre formularios e importación CSV; y mejorar accesibilidad y presentación móvil de reportes y pantallas administrativas. Conviene tomar una mejora por rama y acordar criterios de aceptación antes de empezar.

Cuando el equipo considere que `develop` está listo, abre otro pull request de `develop` hacia `main`.

## Despliegue en Render

Configura un servicio web para PHP y un servicio privado MySQL con disco persistente. Render compila el `Dockerfile` de la raíz para la aplicación. Para la base de datos puedes seguir la guía oficial de [despliegue MySQL en Render](https://render.com/docs/deploy-mysql); el esquema del proyecto es compatible con MySQL/MariaDB.

1. **Base de datos:** crea un servicio privado MySQL siguiendo la guía de Render. Configura su base inicial como `docentes1_db`, un usuario y contraseñas seguras. Adjunta un disco persistente montado en `/var/lib/mysql`; la base no debe exponerse públicamente.
2. **Aplicación:** crea un Web Service desde el repositorio GitHub, elige `main` como rama estable y selecciona `Docker` como entorno. Render encuentra el `Dockerfile` de la raíz. No configures otro comando de inicio: el contenedor ajusta Apache al puerto `PORT` que Render asigna.
3. En Variables del servicio web, configura la conexión con el hostname interno y credenciales del servicio MySQL:

   ```text
   DOCENTES_DB_HOST=<hostname-interno-del-servicio-mysql>
   DOCENTES_DB_PORT=3306
   DOCENTES_DB_NAME=docentes1_db
   DOCENTES_DB_USER=<usuario-mysql>
   DOCENTES_DB_PASSWORD=<contraseña-segura>
   ```

4. En la configuración avanzada del Web Service, usa `/docentes/public/health.php` como ruta de health check.
5. Inicializa la base MySQL **una sola vez** en la instancia vacía importando `sql/schema1.sql`. El archivo contiene `DROP TABLE`; no lo ejecutes de nuevo sobre una base con información que quieras conservar.
6. Activa el dominio público del Web Service. La aplicación conserva su prefijo de URL: abre `https://<servicio>.onrender.com/docentes/public/`.

Render despliega la app desde el repositorio y construye el Dockerfile. `compose.yaml` es solo para desarrollo local; la base de datos alojada se crea como servicio privado independiente con disco persistente. En Render selecciona **After CI Checks Pass** como política de Auto-Deploy para que espere el resultado de GitHub Actions antes de desplegar los cambios de `main`.

No subas `.env`, contraseñas, datos personales ni `backend/config/db.local.php` al repositorio. Guarda las credenciales en Variables de Render, nunca en el código.

## Estructura del proyecto

```text
backend/              Conexión a la base, helpers y middleware
frontend/             Estilos, imágenes y componentes compartidos
public/               Páginas PHP y recursos accesibles por el navegador
sql/                  Esquemas iniciales y migraciones
docker/               Configuración de Apache y PHP para Docker
docs/                 Guías del proyecto y de despliegue
.github/workflows/    Validación y publicación de imagen
Dockerfile            Imagen de la aplicación
compose.yaml          Entorno local PHP + MariaDB
README.md             Guía principal del repositorio
```

## Instalación de base de datos

`sql/schema1.sql` corresponde al esquema actual `docentes1_db`. `sql/schema.sql` es de una versión anterior y no debe usarse para una instalación nueva de esta versión. Para una base existente, revisa las migraciones disponibles en `sql/migrations/` y respalda los datos antes de aplicarlas.

