# Sistema de Docentes CONATRADEC

Aplicación web para administrar docentes, estudiantes, grupos, asistencias y notas. El backend está desarrollado en PHP con PDO; Apache sirve las páginas y MariaDB almacena los datos.

## Tecnologías

- PHP 8.2 y Apache.
- MariaDB 10.11 (compatible con MySQL).
- Docker y Docker Compose para desarrollo local.
- GitHub Actions para revisar sintaxis PHP y construir la imagen Docker.
- GitHub Container Registry (GHCR) para publicar la imagen de `main`.
- Render para desplegar la aplicación y los servicios Docker.

No se requiere Composer ni Node.js.

## Ejecutar localmente con XAMPP

1. Copia el proyecto a `C:\xampp\htdocs\docentes`.
2. Inicia Apache y MySQL/MariaDB en XAMPP.
3. Importa `sql/schema1.sql` en phpMyAdmin.
4. Copia `app/config/db.local.example.php` como `app/config/db.local.php` y configura el puerto, usuario y contraseña de tu instalación.
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

`public/health.php` ofrece una comprobación sencilla de PHP y conexión a la base de datos: `/docentes/public/health.php`.

## GitHub Actions y ramas

El flujo `.github/workflows/docker-ci.yml`:

- En cada push, revisa la sintaxis de los archivos PHP y construye la imagen Docker.
- En pull requests hacia `develop` o `main`, repite esas validaciones.
- Solo cuando se actualiza `main`, publica la imagen en GHCR con las etiquetas `latest` y `sha-<commit>`.

Flujo de trabajo recomendado:

```text
main (versión estable / despliegue)
└── develop (integración)
    ├── feature/nombre-de-tarea (tu trabajo)
    └── feature/nombre-de-tarea (trabajo de Shurone)
```

Comandos para empezar una tarea desde `develop`:

```powershell
git push -u origin develop
```

Ese comando publica la rama de integración la primera vez. Después, para iniciar cada tarea:

```powershell
git switch develop
git pull origin develop
git switch -c feature/nombre-de-tarea
```

Al terminar, sube la rama y abre un pull request hacia `develop`:

```powershell
git push -u origin feature/nombre-de-tarea
```

Cuando el equipo considere que `develop` está listo, abre otro pull request de `develop` hacia `main`. Asigna a Shurone como responsable/revisor en el pull request de GitHub; una rama por sí sola no asigna tareas ni personas. Cada integrante debe usar su propia cuenta y autoría Git para que los commits reflejen quién hizo cada cambio.

### Tarea sugerida para Shurone

Desde `develop`, Shurone puede crear `feature/render-deploy-checklist` y preparar `docs/RENDER_DEPLOYMENT_CHECKLIST.md` con los pasos para crear los servicios de aplicación y MySQL, configurar variables y health check, e importar el esquema inicial. La entrega es un pull request hacia `develop`; no debe incluir credenciales ni datos personales. Esto permite que cada persona trabaje en una rama independiente y que el PR muestre su trabajo real.

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

No subas `.env`, contraseñas, datos personales ni `app/config/db.local.php` al repositorio. Guarda las credenciales en Variables de Render, nunca en el código.

## Estructura del proyecto

```text
app/                 Configuración, helpers y middleware PHP
assets/              CSS, imágenes y recursos del frontend
public/              Páginas públicas y endpoints PHP
sql/                 Esquema inicial y migraciones
docker/              Configuración auxiliar de la imagen PHP/Apache
.github/workflows/   Automatización CI/CD de GitHub Actions
Dockerfile           Imagen de la aplicación
compose.yaml         Entorno local PHP + MariaDB
```

## Instalación de base de datos

`sql/schema1.sql` corresponde al esquema actual `docentes1_db`. `sql/schema.sql` es de una versión anterior y no debe usarse para una instalación nueva de esta versión. Para una base existente, revisa las migraciones disponibles en `sql/migrations/` y respalda los datos antes de aplicarlas.

