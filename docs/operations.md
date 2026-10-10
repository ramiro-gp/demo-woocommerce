# Operación local

Todos los comandos se ejecutan desde la carpeta raíz de `demo-woocommerce`. Usan `scripts/dev.ps1`, que identifica el proyecto y su estado antes de invocar Docker Compose.

## PowerShell y requisitos

Abrí Docker Desktop y esperá a que el motor esté listo. Debe usar contenedores Linux. Luego:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 doctor
```

`doctor` sirve para comprobar herramientas y disponibilidad del entorno. No instala programas en Windows.

Las acciones sin arrays se pueden ejecutar con `powershell -NoProfile -File`, como muestran los ejemplos. Si la política del equipo bloquea scripts, no cambies la política global para este proyecto. En un equipo propio y después de revisar el script, se puede abrir una sesión temporal con:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass
```

Dentro de esa ventana, usá `.\scripts\dev.ps1 <acción>`. Al cerrar la ventana termina esa elección de política. Una política administrada por la organización puede seguir restringiendo la ejecución; en ese caso corresponde resolverla con quien administra el equipo.

## Inicialización e instancias

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 init
```

El comando genera contraseñas aleatorias y las guarda en `.local/local/secrets/`; no las muestra. Prepara y valida la configuración; todavía no inicia servicios. Para descargar las imágenes, iniciar la base, WordPress y Mailpit e instalar WordPress si la base está vacía, ejecutá:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 start
```

Repetir `init` conserva la configuración y los secretos. Repetir `start` no resetea la base ni pisa las opciones de una instalación existente.

Accesos predeterminados:

- Sitio: <http://localhost:8080>.
- Administración: <http://localhost:8080/wp-admin>.
- Bandeja de prueba: <http://localhost:8025>.

El usuario administrador tiene el formato `local_owner_<8 caracteres hexadecimales>` y queda registrado en `.local/local/.env` como `LOCAL_ADMIN_USER`. Para consultarlo y leer su contraseña únicamente en tu terminal:

```powershell
Select-String -LiteralPath .\.local\local\.env -Pattern '^LOCAL_ADMIN_USER='
Get-Content -LiteralPath .\.local\local\secrets\wp-admin-password.txt
```

No compartas la contraseña. El archivo `.local/local/.env` conserva la configuración de la instancia y Git excluye toda `.local/`. `.env.example` es solo una referencia; no se copia ni usa directamente con Compose. El correo local del administrador es `owner@example.test`; los mensajes se capturan en Mailpit. En la primera instalación se configuran español argentino, zona horaria `America/Argentina/Buenos_Aires` y visibilidad para buscadores desactivada.

Se puede crear una segunda instancia aislada para comprobar el procedimiento sin tocar la principal:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 init -Instance h1-check -WordPressPort 8081 -MailpitPort 8026
powershell -NoProfile -File .\scripts\dev.ps1 start -Instance h1-check
powershell -NoProfile -File .\scripts\dev.ps1 status -Instance h1-check
```

La configuración, los secretos y los backups estarán en `.local/h1-check/`. El nombre completo que exige una acción destructiva será `demo-woocommerce-h1-check`. Si dos instancias funcionan a la vez, sus puertos deben ser distintos. Los puertos elegidos en `init` se conservan para las siguientes acciones de esa instancia.

No cambies arbitrariamente `.env` ni borres secretos de un entorno existente: el acceso a MariaDB depende de las credenciales con las que se inicializó su volumen.

## Operación cotidiana

| Acción | Comando | Efecto |
| --- | --- | --- |
| Iniciar | `powershell -NoProfile -File .\scripts\dev.ps1 start` | Levanta los servicios; instala solo si falta WordPress |
| Detener | `powershell -NoProfile -File .\scripts\dev.ps1 stop` | Detiene y conserva volúmenes |
| Estado | `powershell -NoProfile -File .\scripts\dev.ps1 status` | Muestra el estado de los servicios |
| Logs de WordPress | `powershell -NoProfile -File .\scripts\dev.ps1 logs` | Consulta WordPress, servicio predeterminado |
| Logs de MariaDB | `powershell -NoProfile -File .\scripts\dev.ps1 logs -Service db` | Consulta MariaDB |
| Logs de Mailpit | `powershell -NoProfile -File .\scripts\dev.ps1 logs -Service mailpit` | Consulta captura de correos |
| Prueba de correo | `powershell -NoProfile -File .\scripts\dev.ps1 mail-test` | Envía un mensaje local que debe verse en Mailpit |

Agregá `-Instance h1-check` a cualquiera para operar sobre la segunda instancia. Los comandos rutinarios no eliminan datos. Los logs quedan siguiendo nuevas entradas; `Ctrl+C` termina esa lectura sin detener los contenedores.

## WP-CLI

WP-CLI ejecuta tareas de WordPress dentro de Docker. La acción `wp` recibe sus argumentos como un array de PowerShell:

```powershell
.\scripts\dev.ps1 wp -WpArgs @('core', 'version')
.\scripts\dev.ps1 wp -WpArgs @('option', 'get', 'siteurl')
.\scripts\dev.ps1 wp -Instance h1-check -WpArgs @('core', 'is-installed')
```

Usá la llamada directa al script para `-WpArgs`; no copies esos arrays detrás de `powershell -File`, porque su paso de argumentos puede convertirlos en texto. Esta acción da acceso al poder completo de WP-CLI: elegí conscientemente los comandos que escriben o borran datos.

## Respaldo y restauración

Para crear un respaldo:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 backup
```

Se genera un identificador para la carpeta de respaldo con fecha, hora y un sufijo aleatorio (`yyyyMMdd-HHmmss-<6 caracteres hexadecimales>`). También se puede elegir uno explícito:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 backup -BackupId 'antes-de-prueba'
```

Un identificador elegido puede tener hasta 80 caracteres: comienza con una letra o número y continúa con letras, números, guiones o guiones bajos. No se usan rutas como identificadores.

La carpeta `.local/local/backups/<id>/` contiene:

- `database.sql`, dump SQL de MariaDB.
- Archivo `wordpress.tar.gz` con los archivos de WordPress, incluidos uploads.
- `manifest.json`, con instancia, versiones y hashes para validar la integridad.

WordPress se detiene durante la copia para evitar escrituras mientras se respaldan base y archivos. Si estaba en funcionamiento, vuelve a iniciarse después; si estaba detenido, continúa detenido. La base y Mailpit pueden quedar iniciados para completar la operación. No se respaldan los secretos de `.local/`, el mu-plugin montado de correo local ni la bandeja de Mailpit. Conservá las credenciales originales para recuperar una instalación; no borres esa carpeta mientras tenga datos que necesites.

El respaldo no está cifrado. Aunque los archivos de secretos estén fuera, los datos pueden contener información privada. Una copia en el mismo disco ayuda a deshacer una prueba, pero no protege frente a pérdida o falla de ese disco. Para conservarla, copiá la carpeta del respaldo a un destino privado separado.

Para restaurar, sustituí `<id>` por la carpeta elegida:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 restore -BackupId '<id>' -ConfirmName 'demo-woocommerce-local'
```

La acción valida el respaldo y su integridad antes de sustituir datos. El manifiesto debe corresponder a la misma instancia/proyecto y a los mismos digests de WordPress y MariaDB. También se rechazan rutas inseguras o tipos de archivo no admitidos en el archivo comprimido. Después crea un respaldo preventivo; para ello necesita una instalación local que pueda exportarse. Si partís de volúmenes nuevos, ejecutá primero `start` para preparar esa instalación vacía y luego restaurá.

La confirmación no se puede omitir y debe coincidir exactamente con la instancia elegida. Al terminar, el sitio queda iniciado. Si la sustitución falla, WordPress permanece detenido y el mensaje identifica el respaldo preventivo para recuperación.

Una restauración repone base y archivos del respaldo; no combina los cambios posteriores. Es un procedimiento local, no una herramienta para publicar staging encima de una tienda con pedidos reales.

## Resets deliberados

No se necesita un reset para apagar, reiniciar o actualizar una descarga fallida. Primero revisá `doctor`, `status` y los logs.

`reset-db` crea un respaldo, detiene WordPress, limpia **solo la base de esa instancia** mediante `wp db reset --yes` y vuelve a iniciar WordPress con una instalación nueva. Conserva el volumen de MariaDB, los archivos de WordPress, los uploads y Mailpit. Esos archivos conservados pueden contener medios que la nueva base ya no referencia:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 reset-db -ConfirmName 'demo-woocommerce-local'
```

`reset-all` comprueba que los volúmenes pertenecen al proyecto, crea un respaldo y elimina **solo los tres volúmenes de esa instancia**: base, WordPress y Mailpit. Después inicia automáticamente una instalación nueva. Es una vuelta a cero, incluidos los uploads y correos capturados:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 reset-all -ConfirmName 'demo-woocommerce-local'
```

Los respaldos bajo `.local/` quedan fuera de los volúmenes y se conservan. Para `h1-check`, agregá `-Instance h1-check` y usá `-ConfirmName 'demo-woocommerce-h1-check'`.

No uses `docker volume prune`, limpiezas globales ni `docker compose down --volumes` como rutina de este proyecto. Esos comandos pueden saltarse el respaldo y los controles que ofrece el script.

## Verificación de H1

Esta lista es el criterio de aceptación; no es un registro de pruebas ya ejecutadas:

1. En una instancia nueva, ejecutar `doctor`, `init` y `start`; abrir sitio y wp-admin.
2. Crear un dato reconocible en WordPress y subir un archivo de prueba.
3. Ejecutar `stop` y `start`; comprobar que ambos se conservan.
4. Ejecutar `init` y `start` de nuevo; comprobar que no cambia la configuración ni duplica contenido.
5. Ejecutar `mail-test`; comprobar la recepción en Mailpit, sin transporte externo.
6. Crear un backup, modificar el dato y restaurar el backup con la confirmación correcta; comprobar dato y archivo originales.
7. Comprobar que una confirmación incorrecta o un respaldo con hash inválido se rechazan antes de sustituir datos.
8. En la instancia de ensayo, verificar ambos resets y que la instancia principal conserva sus datos.

La prueba automatizada recorre estos casos en una instancia nueva `h1-*` y ejecuta los dos resets únicamente allí. Elegí un nombre no usado y puertos libres:

```powershell
powershell -NoProfile -File .\tests\verify-h1.ps1 -Instance h1-ensayo -WordPressPort 18080 -MailpitPort 18025
```

Al terminar deja esa instancia detenida, conserva sus respaldos y escribe `.local/h1-ensayo/verification.json`. Una nueva ejecución requiere otro nombre de instancia. No contiene productos ni datos comerciales.

Durante exportación, importación o reset puede aparecer `option --ssl-verify-server-cert is disabled, because of an insecure passwordless login`. Es un [aviso conocido de WP-CLI/MariaDB](https://github.com/wp-cli/wp-cli/issues/6073): [WP-CLI 2.12.0 transmite la contraseña mediante `MYSQL_PWD`](https://github.com/wp-cli/wp-cli/blob/v2.12.0/php/utils.php#L563-L566), que no cuenta para esa detección. El aviso no demuestra un acceso sin contraseña; las credenciales siguen siendo obligatorias. La conexión de base queda en la red interna local de Docker. Se conservan los errores y códigos de salida para distinguir un aviso de una operación fallida.

## Problemas frecuentes

| Síntoma | Qué comprobar |
| --- | --- |
| Docker no responde | Docker Desktop abierto, motor listo y contenedores Linux |
| No descarga imágenes | Conexión, acceso al registro y logs de Docker; repetir `start` sin resetear |
| Puerto ocupado | Usar otra instancia con puertos libres desde `init` |
| Error de base | `logs -Service db`; conservar `.env`, secretos y volumen juntos |
| WordPress conservado difiere de la versión fijada | Planificar la migración y actualizar versiones de forma deliberada; `start` no sobrescribe el Core persistido |
| Sitio no responde | `status` y `logs`; esperar al arranque de MariaDB y WordPress |
| Correo no visible | `mail-test`, `logs -Service mailpit` y URL de Mailpit de esa instancia |
| `-WpArgs` no llega correctamente | Ejecutar `.\scripts\dev.ps1` directamente desde PowerShell |
| Cambios desaparecen después de restaurar | La restauración repone el estado del backup; revisar el respaldo preventivo |

Los logs pueden contener datos privados. Revisalos antes de compartirlos y ocultá credenciales, correos o información de usuarios.
