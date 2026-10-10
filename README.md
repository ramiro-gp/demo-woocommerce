# demo-woocommerce

Base local reproducible para una futura demo comercial de WordPress y WooCommerce. **Esta entrega corresponde únicamente a H1:** instala WordPress y prepara su entorno. El catálogo, el diseño propio, WooCommerce y Mercado Pago se incorporarán en hitos posteriores.

La identidad ficticia aprobada es **TRAMA**. El futuro theme se llamará **Commerce Base** (`commerce-base`) y el futuro plugin, cuando corresponda, **Commerce Core** (`commerce-core`). H1 todavía no los crea.

## Antes de empezar

- Windows con PowerShell y Docker Desktop instalado y abierto.
- Docker Desktop configurado para ejecutar contenedores Linux, con Docker Compose disponible.
- Conexión a Internet para descargar las imágenes y el idioma de WordPress en la primera instalación.
- Puertos locales 8080 y 8025 libres, o elegir otros al inicializar.

No hace falta un dominio, HTTPS público, hosting ni cuentas de Mercado Pago para H1. PHP, MariaDB y WP-CLI se ejecutan dentro de Docker; no hace falta instalarlos en Windows.

Una **imagen** contiene el software y su versión; un **contenedor** lo ejecuta. Un **volumen** conserva los datos aunque se detenga o se vuelva a crear el contenedor. Este proyecto usa volúmenes separados para WordPress, MariaDB y los correos de Mailpit.

## Primera instalación

Abrí PowerShell en esta carpeta, con Docker Desktop en funcionamiento:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 doctor
powershell -NoProfile -File .\scripts\dev.ps1 init
powershell -NoProfile -File .\scripts\dev.ps1 start
```

`init` prepara las credenciales y valida la configuración local. `start` descarga las imágenes, inicia los servicios e instala WordPress si todavía no está instalado. Se pueden repetir: conservan una instalación existente y sus opciones. El primer arranque puede tardar varios minutos mientras Docker descarga las imágenes.

Luego abrí:

| Acceso | URL predeterminada |
| --- | --- |
| Sitio | <http://localhost:8080> |
| Administración | <http://localhost:8080/wp-admin> |
| Correos de prueba | <http://localhost:8025> |

El script guarda la configuración y las credenciales en `.local/local/`, que queda fuera de Git. El usuario administrador se genera con el formato `local_owner_<8 caracteres hexadecimales>` y figura en `LOCAL_ADMIN_USER` dentro de `.local/local/.env`. Su contraseña está en `.local/local/secrets/wp-admin-password.txt`; consultala localmente cuando necesites ingresar. No la publiques ni la pegues en incidencias o capturas compartidas.

`.env.example` es una referencia de las variables; no lo copies como configuración activa. El script genera el archivo privado de cada instancia.

La instalación inicial usa español argentino, zona horaria `America/Argentina/Buenos_Aires` y visibilidad para buscadores desactivada. Estas opciones solo se establecen durante la primera instalación.

Para elegir otros puertos desde el comienzo:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 init -WordPressPort 8081 -MailpitPort 8026
powershell -NoProfile -File .\scripts\dev.ps1 start
```

No edites el puerto de una instalación existente sin considerar que WordPress guarda su URL en la base. Usá una instancia nueva para un entorno con otros puertos.

## Uso diario

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 start
powershell -NoProfile -File .\scripts\dev.ps1 status
powershell -NoProfile -File .\scripts\dev.ps1 stop
```

`start` vuelve a levantar el entorno; `stop` lo detiene y conserva sus datos. Ninguno importa contenido ni elimina volúmenes. `start` preserva WordPress cuando ya está instalado y completa la instalación inicial cuando la base está vacía.

Para revisar problemas o comprobar el correo:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 logs
powershell -NoProfile -File .\scripts\dev.ps1 logs -Service db
powershell -NoProfile -File .\scripts\dev.ps1 mail-test
```

Mailpit captura los correos dentro del entorno local. No se configura un servidor SMTP real y la base ni el puerto SMTP se publican en Windows.

Para ejecutar WP-CLI usá la llamada directa al script, porque `powershell -File` no transmite arrays de la misma manera:

```powershell
.\scripts\dev.ps1 wp -WpArgs @('core', 'version')
.\scripts\dev.ps1 wp -WpArgs @('option', 'get', 'home')
```

Si la política de PowerShell impide ejecutar scripts, consultá [Operación local](docs/operations.md#powershell-y-requisitos) antes de modificar la política del equipo.

## Respaldo y recuperación

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 backup
```

El respaldo incluye un dump de la base, los archivos de WordPress y un manifiesto con hashes. No incluye los archivos de secretos. Se guarda en `.local/local/backups/<id>/`. Los datos de la base y WordPress pueden contener información sensible: no subas estos respaldos a Git y conservá una copia fuera de esta carpeta si necesitás protegerte frente a una falla del disco.

Restaurar reemplaza los datos de la misma instancia. Exige elegir un respaldo, conservar las versiones compatibles y escribir el nombre completo del proyecto local. La documentación explica el respaldo preventivo y los resets antes de usarlos:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 restore -BackupId '<id>' -ConfirmName 'demo-woocommerce-local'
```

Ver [procedimientos de respaldo, restauración y reset](docs/operations.md#respaldo-y-restauración).

## Alcance y versiones

| Componente | Versión inicial |
| --- | --- |
| WordPress | 7.1.3 |
| PHP | 8.4, variante Apache para WordPress |
| MariaDB | 10.11.19 |
| WP-CLI | 2.12.0, PHP 8.4 |
| Mailpit | 1.31.4 |

Las imágenes se fijan por versión y digest en `compose.yaml`. PHP y MariaDB son elecciones **provisionales** hasta verificar las versiones de la cuenta Hostinger Premium. No se declara paridad exacta entre Apache local y LiteSpeed del hosting.

Si el WordPress conservado en el volumen no coincide con la versión fijada, `start` se detiene para exigir una migración deliberada; no lo sobrescribe silenciosamente.

No se instalan SEOPress, FluentSMTP ni LiteSpeed Cache en H1. No hay theme comercial, plugin comercial, túnel, checkout, pagos ni deploy.

## Documentación

- [Decisiones de H0 y orden de hitos](docs/h0-decisions.md).
- [Arquitectura local y límites de esta entrega](docs/architecture.md).
- [Comandos, instancias aisladas y recuperación](docs/operations.md).
- [Cierre de H1 y pruebas ejecutadas](docs/h1-validation.md).

H1 quedó verificado localmente el 10/10/2026: instalación nueva, persistencia, correo, respaldo/restauración y ambos resets en una instancia aislada. El registro enlazado detalla las comprobaciones y sus límites. H2 queda pendiente de una nueva indicación.
