# Checklist de despliegue en Render

Esta guía sirve para configurar y comprobar el despliegue de Sistema de Docentes. Los cuadros se marcan durante el despliegue; documentar un paso no significa que ya se haya ejecutado.

## 1. Antes de empezar

- [ ] Tener acceso al repositorio de GitHub y permiso para configurar sus servicios en Render.
- [ ] Confirmar que la rama `main` contiene la versión que se quiere desplegar.
- [ ] Crear en Render un servicio privado para MySQL y un Web Service para la aplicación.
- [ ] Tener disponible un cliente MySQL para importar el esquema inicial.
- [ ] Revisar `README.md`, `Dockerfile`, `compose.yaml`, `docker/apache-entrypoint.sh`, `public/health.php`, `.github/workflows/docker-ci.yml`, `app/config/db.php` y `sql/schema1.sql` antes de aplicar cambios operativos.

## 2. Crear y configurar la base de datos

- [ ] Crear un servicio MySQL privado en Render. Mantenerlo sin acceso público.
- [ ] Crear la base `docentes1_db` y un usuario de aplicación con permisos sobre esa base. Guardar las credenciales en un gestor seguro o en la configuración protegida del proveedor; no escribirlas en esta guía ni en Git.
- [ ] Adjuntar un disco persistente montado en `/var/lib/mysql` para conservar los datos de MySQL cuando se reinicie o despliegue el servicio.
- [ ] Obtener del servicio los datos de conexión **internos/privados**: hostname, puerto, base, usuario y contraseña. Para esta aplicación el puerto MySQL esperado es `3306`; usar el hostname interno que Render muestre para el servicio.
- [ ] Comprobar que el Web Service podrá alcanzar la base por la red privada de Render.

## 3. Crear el Web Service

- [ ] Conectar el repositorio de GitHub a Render.
- [ ] Seleccionar `main` como rama de despliegue.
- [ ] Elegir el entorno `Docker` y usar el `Dockerfile` ubicado en la raíz del repositorio.
- [ ] Dejar que Render arranque el contenedor con el `CMD` del `Dockerfile`. El entrypoint `docker/apache-entrypoint.sh` adapta Apache al valor de `PORT` que Render asigna; no sustituir el comando de inicio.
- [ ] Configurar la ruta de health check como `/docentes/public/health.php`.
- [ ] Una vez publicado el servicio, verificar la aplicación en `https://<servicio>.onrender.com/docentes/public/`.

## 4. Variables de conexión de la aplicación

En la configuración del Web Service, agregar las variables siguientes. Los valores reales se obtienen de la base privada y se guardan en Render como variables protegidas; no se incluyen aquí.

| Variable | Propósito |
| --- | --- |
| `DOCENTES_DB_HOST` | Hostname privado del servicio MySQL. |
| `DOCENTES_DB_PORT` | Puerto MySQL del servicio; normalmente `3306`. |
| `DOCENTES_DB_NAME` | Nombre de la base, normalmente `docentes1_db`. |
| `DOCENTES_DB_USER` | Usuario de aplicación autorizado para esa base. |
| `DOCENTES_DB_PASSWORD` | Contraseña de ese usuario. |

`app/config/db.php` lee estas variables de entorno y las utiliza para crear la conexión PDO. Si falta una variable, el archivo conserva valores locales predeterminados; en Render, configura las cinco explícitamente para evitar depender de esos valores.

## 5. Inicializar el esquema

- [ ] Confirmar que `docentes1_db` está vacía y que se seleccionó la base correcta.
- [ ] Importar `sql/schema1.sql` una sola vez usando un cliente MySQL y las credenciales de la base privada.
- [ ] Verificar que se crearon las tablas antes de probar el servicio web.

**Advertencia:** `sql/schema1.sql` contiene instrucciones `DROP TABLE IF EXISTS`. Importarlo de nuevo puede borrar y recrear tablas, perdiendo los datos existentes. No lo ejecutes sobre una base con información que se quiera conservar. Para una base existente, respalda los datos y revisa las migraciones de `sql/migrations/`; esta checklist no reemplaza un procedimiento de actualización de esquema.

## 6. Health check y URL

El endpoint `/docentes/public/health.php` carga la configuración de la base y ejecuta `SELECT 1`. Devuelve estado `ok` cuando la conexión funciona; si no puede conectar, registra el error en el log, devuelve HTTP 503 y estado `unavailable`. Por eso, un health check fallido puede indicar tanto un problema de arranque como uno de conexión a la base.

- [ ] Guardar `/docentes/public/health.php` como ruta de health check del Web Service.
- [ ] Confirmar que Render marca el servicio como healthy.
- [ ] Abrir `https://<servicio>.onrender.com/docentes/public/` y comprobar que la aplicación carga.

## 7. CI/CD y despliegues

El workflow `.github/workflows/docker-ci.yml` valida la sintaxis PHP y construye la imagen Docker. Se ejecuta en pushes y en pull requests hacia `develop` o `main`. La publicación a GHCR ocurre únicamente en un push a `main` y después de que pase el trabajo de validación.

- [ ] En Render, seleccionar la política de despliegue automático **After CI Checks Pass** para esperar los checks de GitHub Actions.
- [ ] Antes de desplegar cambios en `main`, comprobar en GitHub Actions que el workflow asociado al commit terminó correctamente.
- [ ] Si el check está pendiente o falló, resolverlo antes de considerar el commit listo para desplegar.
- [ ] Confirmar en Render que el despliegue terminó correctamente y corresponde al commit esperado.

## 8. Comprobación final

- [ ] El workflow de GitHub Actions del commit está verde.
- [ ] El Web Service terminó su despliegue en Render.
- [ ] El health check responde correctamente con la conexión a MySQL activa.
- [ ] La URL `/docentes/public/` abre la aplicación.
- [ ] Se confirmó que la base usa conexión privada y disco persistente.
- [ ] No se guardaron contraseñas, tokens, datos personales ni archivos locales de configuración en Git.
- [ ] Se dejó constancia de cualquier paso que no se pudo ejecutar o verificar.

## 9. Problemas frecuentes

| Síntoma | Qué verificar |
| --- | --- |
| El contenedor no arranca o Apache no escucha | Revisar los logs del Web Service y que se dejó el `CMD` del Dockerfile. `docker/apache-entrypoint.sh` espera un `PORT` numérico entre 1 y 65535 y configura Apache para ese puerto. |
| Health check HTTP 503 o estado `unavailable` | El endpoint prueba la conexión a la base con `SELECT 1`. Revisar los logs de la aplicación, que la base esté disponible y que las cinco variables `DOCENTES_DB_*` estén definidas correctamente. |
| No se puede resolver o alcanzar el host de MySQL | Confirmar que `DOCENTES_DB_HOST` usa el hostname privado del servicio y que ambos servicios están configurados para comunicarse por la red privada. No usar `localhost` para una base que está en otro servicio. |
| Error de acceso o autenticación | Revisar `DOCENTES_DB_PORT`, `DOCENTES_DB_NAME`, `DOCENTES_DB_USER` y `DOCENTES_DB_PASSWORD` contra la configuración de MySQL. Actualizar el valor en Render sin copiar secretos a Git o a los logs compartidos. |
| La aplicación no encuentra tablas | Confirmar que se importó `sql/schema1.sql` en `docentes1_db` y que la importación terminó sin errores. |
| La ruta de health check devuelve 404 | Revisar que la ruta configurada sea exactamente `/docentes/public/health.php`, incluyendo el prefijo `/docentes`. |
| Se perdieron datos después de reiniciar MySQL | Verificar que el disco persistente está adjunto y montado en `/var/lib/mysql`. No repetir la importación del esquema sobre una base con datos. |

## 10. Seguridad

- Mantener MySQL como servicio privado, sin exposición pública.
- Guardar credenciales únicamente en las variables protegidas del proveedor o en un gestor seguro; nunca en commits, `.env`, capturas o esta checklist.
- No importar `sql/schema1.sql` sobre una base que contenga datos que deban conservarse.
- No marcar una casilla como completada hasta haber realizado y comprobado ese paso.
