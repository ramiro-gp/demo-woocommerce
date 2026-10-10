# Cierre de H1

Estado: **completo y verificado localmente el 10/10/2026**. La implementación termina en el entorno reproducible; H2 no comenzó.

## Entorno registrado

| Componente | Versión comprobada |
| --- | --- |
| Docker Desktop, motor Linux/amd64 | Docker 29.6.1 |
| Docker Compose | 5.3.0 |
| WordPress | 7.1.3 |
| PHP de WordPress | 8.4.26 |
| MariaDB | 10.11.19 |
| WP-CLI | 2.12.0 |
| Mailpit | 1.31.4 |

Las imágenes están fijadas por tag y digest en `compose.yaml`, con registro en `config/dependencies.json`. PHP y MariaDB siguen siendo elecciones provisionales hasta verificar la cuenta real de Hostinger Premium. No se comprobó paridad con producción.

## Evidencia ejecutada

Se ejecutó de principio a fin:

```powershell
powershell -NoProfile -File .\tests\verify-h1.ps1 -Instance h1-final -WordPressPort 18082 -MailpitPort 18027
```

Resultado: código de salida **0**, todas las comprobaciones aprobadas, usando Windows PowerShell 5.1. El informe privado quedó en `.local/h1-final/verification.json` y la instancia de ensayo quedó detenida.

| Comprobación | Resultado observado |
| --- | --- |
| Instalación desde volúmenes nuevos | Sitio y wp-admin responden HTTP 200; español argentino, zona horaria y visibilidad inicial correctos |
| Credenciales | Usuario aleatorio; contraseña del archivo privado validada contra WordPress; secretos fuera de Git |
| Repetir `init` y `start` | Conserva secretos, contenido y preferencias de una instalación existente |
| Detener e iniciar | Conserva dato en base y archivo de uploads |
| Correo | `wp_mail()` llega a Mailpit; mensajes persisten tras detener e iniciar |
| Backup | SQL, archivo de WordPress y manifiesto con SHA-256; WordPress recupera su estado previo |
| Restauración | Repone base y upload originales; elimina archivo creado después del backup; crea respaldo preventivo |
| Confirmación incorrecta | Rechazo antes de reemplazar datos |
| Backup alterado | Rechazo por checksum antes de reemplazar datos |
| Reset de base | Reinstala WordPress conservando uploads y credenciales privadas |
| Reset completo | Borra solo los tres volúmenes identificados de la instancia de ensayo y reinstala; conserva backups privados |

También se verificaron la sintaxis PowerShell, PHP y shell, la configuración Compose, la exclusión de `.local/` mediante Git y la ausencia de las credenciales generadas en los archivos fuente. No se ejecutaron limpiezas globales de Docker.

## Instancia principal entregada

La instancia `demo-woocommerce-local` conserva una instalación inicial de WordPress y está disponible en:

- Sitio: <http://localhost:8080>.
- Administración: <http://localhost:8080/wp-admin/>.
- Mailpit: <http://localhost:8025>.

Se comprobó HTTP 200, captura de correo y configuración inicial de la instancia principal después de las pruebas aisladas. El respaldo inicial privado es `.local/local/backups/h1-initial/`; incluye un manifiesto de integridad y queda excluido de Git.

El usuario está en `.local/local/.env` y la contraseña en `.local/local/secrets/wp-admin-password.txt`. La contraseña inicial de la primera prueba fue renovada después de corregir el eco del prompt de WP-CLI; las instalaciones reproducibles actuales suprimen ese transcript.

## Límites y siguiente hito

No se instaló WooCommerce, Mercado Pago, SEOPress, FluentSMTP ni LiteSpeed Cache. No se creó `commerce-base` ni `commerce-core`; el mu-plugin de captura de correo pertenece exclusivamente al entorno local. No hay catálogo, diseño, pruebas de pagos, dominio HTTPS público ni deploy.

El aviso conocido del cliente MariaDB durante algunas operaciones WP-CLI está explicado en [Operación local](operations.md#verificación-de-h1). Las operaciones de respaldo, restauración y reset terminaron correctamente.

H1 se completó sin un dominio HTTPS público. La matriz real de WooCommerce/HPOS/Blocks/Mercado Pago y Checkout Pro corresponde a H2.

## Reconciliación en la ruta canónica — 10/10/2026

La carpeta `C:\Codigo\demo-woocommerce` estaba vacía, sin `.git`. El remoto solicitado, `https://github.com/ramiro-gp/demo-woocommerce.git`, ya contenía H1 completo en `main`, commit `1174a8da7d8ed612fafb727dc7c1ca0fa97d8bba`. Se clonó directamente ese remoto en la carpeta canónica. La comparación con los archivos fuente anteriores no requirió recuperar código adicional. No se copió otro `.git` ni runtime, bases, backups, logs o secretos.

Los contenedores existentes todavía apuntaban a la carpeta anterior. Se conservaron los tres volúmenes `demo-woocommerce-local_*`, se generaron credenciales locales nuevas en la carpeta canónica y se renovaron las contraseñas de los mismos usuarios locales. Se recrearon únicamente los tres contenedores de este proyecto desde el Compose canónico. La carpeta anterior queda como referencia histórica; no debe utilizarse para operar el proyecto y sus credenciales anteriores ya no son válidas.

Se verificaron `doctor`, WordPress 7.1.3, PHP 8.4.26, MariaDB 10.11.19, acceso HTTP al sitio, inicio de sesión real en wp-admin, Mailpit, captura de `wp_mail`, exclusión Git de `.env` y secretos, y persistencia de un marcador en la base después de `stop`/`start`. El fixture `fresh` validó idioma, preferencias y contraseña del administrador, sin plugins funcionales activos. Se creó el respaldo privado `.local/local/backups/h1-canonical-before-h2/` desde la ruta correcta. No se repitieron resets destructivos sobre la instancia principal.

H1 queda consolidado antes de introducir H2. La referencia histórica de pruebas aisladas anterior conserva su alcance original; esta sección registra las comprobaciones adicionales realizadas desde la ruta canónica.
