# SRICU-ULS API

Backend del Sistema de Repositorio Inteligente de Conocimiento Universitario. Es una API REST con Laravel 12, Sanctum, PostgreSQL y Docker Compose. La documentación interactiva está en Swagger.

## Requisitos

- Docker y Docker Compose
- La imagen local `php:82-apache-jovial` (PHP 8.2 con Apache, `pdo_pgsql` y `mbstring`)
- La imagen local `postgres:13-es`

## Puesta en marcha

```sh
cp .env.example .env
docker compose up -d --build
```

El contenedor ejecuta las migraciones y la carga inicial. La API queda en `http://localhost:8080` y PostgreSQL en el puerto `5434`.

Swagger: [http://localhost:8080/api/documentation](http://localhost:8080/api/documentation)

Especificación: [http://localhost:8080/api/openapi.yaml](http://localhost:8080/api/openapi.yaml)

## Cuentas iniciales

| Correo | Contraseña | Rol |
| --- | --- | --- |
| admin@uls.edu.sv | Admin123! | administrador |
| gestor@uls.edu.sv | Gestor123! | gestor |
| revisor@uls.edu.sv | Revisor123! | revisor |
| docente@uls.edu.sv | Docente123! | depositante |

El inicio de sesión es `POST /api/login`. Las demás operaciones protegidas usan `Authorization: Bearer {access_token}`.

## Qué cubre la API

- Consulta pública, filtros y ficha de recursos publicados.
- Borrador, envío a revisión, observación, aprobación, publicación y retiro.
- Autores, categorías, colecciones, palabras clave y licencias.
- Carga y descarga según el estado, la licencia y el nivel de acceso (`publico`, `autenticado`, `restringido`).
- Usuarios y roles. Solo el administrador administra cuentas.
- Estadísticas y auditoría para gestor y administrador.
- La búsqueda semántica responde deshabilitada mientras `SEMANTIC_SEARCH_ENABLED=false`. La búsqueda por texto sigue funcionando.

## Pruebas

```sh
docker run --rm -v "$PWD":/var/www/html -w /var/www/html php:82-apache-jovial php artisan test
```

## Publicar en Render

El archivo `render.yaml` crea la API y una base PostgreSQL gratis. La imagen de ese despliegue es la pública de PHP 8.2. Tu `docker compose` local sigue usando `Dockerfile.local`.

1. Sube este proyecto a un repositorio de GitHub.
2. Entra a [render.com](https://render.com) y crea una cuenta.
3. Elige **New** → **Blueprint** y conecta el repositorio. Render lee `render.yaml`.
4. Confirma el despliegue. La primera vez tarda varios minutos.
5. Cuando el servicio quede en verde, abre `https://tu-servicio.onrender.com/api/documentation`.

Las cuentas de prueba son las de la tabla de arriba. La base gratis dura 30 días. El servicio web se duerme si nadie lo usa y la siguiente visita tarda cerca de un minuto.

Los archivos subidos viven en el disco del contenedor. Un despliegue nuevo no los conserva. La base de datos sí se mantiene mientras no expire.

Si quieres que los tokens de sesión no se invaliden en cada despliegue, genera una clave y ponla en Render como variable `APP_KEY`:

```sh
docker run --rm -v "$PWD":/var/www/html -w /var/www/html php:82-apache-jovial php artisan key:generate --show
```

## Respaldo y restauración

```sh
docker compose exec postgres pg_dump -U sricu sricu > respaldo.sql
docker compose exec -T postgres psql -U sricu -d sricu < respaldo.sql
```

Los archivos cargados están en `storage/app/private`. Hay que copiar esa carpeta junto con el volcado de la base.
