# H11 — Runbook de despliegue y recuperación

Revisión: **23/10/2026, Argentina**. Esta guía prepara una instalación independiente en hosting PHP/MySQL. Los comandos remotos son procedimientos para una ejecución futura autorizada; no acreditan que se hayan ejecutado. Los resultados efectivos del laboratorio y los bloqueos se registran en [preparación H11](h11-production-readiness.md). H11 no autoriza contratar, cambiar DNS, enviar correo real, cobrar ni publicar.

Complementa [portabilidad H10](h10-rebranding-portability.md), [operación local](operations.md), [correo H7](h7-email-production.md), [seguridad H9](h9-1-security-hardening.md) y [caché H9](h9-2-performance-accessibility-seo.md#estrategia-de-caché-woocommerce). No ejecutar los aprovisionadores DEMO H0–H10 sobre una tienda de cliente.

## 1. Identificar destino y responsabilidades

Antes de cualquier escritura completar una ficha privada: titular y cuenta del hosting, sitio/document root, base exclusiva, dominio canónico, proveedor DNS, entorno, versión instalada/deseada, ventana, operador, aprobador, responsable de pedidos, backup y reversión. Confirmar que no es TRAMA, ORIGEN CASA, otra tienda activa ni un volumen compartido. Registrar los comandos y sus resultados redactados; guardar secretos y material personal fuera de Git.

El cliente controla titularidad, proveedores, datos comerciales, fiscalidad, pagos y autorización de lanzamiento. El implementador controla distribución, configuración acordada, QA y recuperación contratada. El hosting responde por las prestaciones de su contrato; que anuncie backups no delega en él la comprobación de recuperación de la aplicación. No se presume soporte 24/7.

## 2. Comprobar compatibilidad real

Solicitar al hosting la versión y límites efectivos de PHP web y CLI, DB, memoria, CPU, I/O, inodos, procesos/conexiones, almacenamiento libre, SSH/WP-CLI, cron, SSL, salidas HTTPS/SMTP, caché y backups. Conservar el informe de **WooCommerce → Estado**, sin secretos. Revisar [decisión de hosting](h11-hosting-decision.md); no equiparar un Premium heredado al producto actual.

La recomendación oficial consultada para WooCommerce 10.8+ es WordPress ≥6.9, PHP ≥8.3 (probado hasta 8.4), MySQL ≥8.0 o MariaDB ≥10.6, HTTPS y memoria WP ≥256 MB. Son requisitos recomendados, no una garantía de rendimiento. La combinación exacta probada por este proyecto está en la evidencia H11; no actualizar versiones mayores por cumplir una tabla. [Requisitos oficiales](https://woocommerce.com/document/server-requirements/).

Comprobar `mysqli/mysqlnd`, `curl`, `mbstring`, `openssl`, `dom/xml`, `zip`, `fileinfo`, GD/Imagick y soporte de los formatos usados; `intl`/`exif` según funcionalidad. Verificar DB InnoDB/utf8mb4, transacciones y capacidad de crear/alterar sus tablas. Comparar PHP de CLI con el SAPI web y sus módulos/límites. No dejar `phpinfo()` público. Confirmar subida del ZIP, importación de medios, espacio temporal de descompresión y memoria suficiente. OPcache y Redis no se consideran incluidos por defecto.

WP-CLI puede sobrescribir memoria y visualización de errores: un diagnóstico CLI con `memory_limit=-1` o `display_errors` activo no demuestra los límites/errores del visitante. Evaluar separadamente el SAPI HTTP y la configuración efectiva del host. El preflight lo señala expresamente; el helper temporal de QA recoge sólo ajustes permitidos, nunca un `phpinfo()` completo. Los valores locales no deben presentarse como recursos contractuales remotos.

Si no hay SSH, usar instalador nativo, SFTP/administrador de archivos, tareas del panel y exportación/restauración soportadas. La ausencia de WP-CLI no se resuelve exponiendo una consola PHP web. Si una comprobación sólo se hizo en Docker, queda pendiente en el hosting.

## 3. Distribución limpia

Construir desde una revisión identificada con `scripts/build-release.ps1 -ReleaseVersion 1.0.0`; los artefactos privados generados se guardan bajo `.local/h11-release/1.0.0/`. El builder usa los bytes canónicos del código confirmado en `HEAD`; confirmar primero los cambios que se pretende distribuir, sin incorporar archivos privados. Revisar su manifiesto, versiones, SHA-256, lista de entradas y resultado del control de exclusiones antes de copiar. La versión `1.0.0` identifica la distribución; cada theme/plugin conserva su versión propia. La reproducción local exacta está al final de esta guía.

| Se distribuye como código | Se obtiene o configura por instalación | Se excluye |
| --- | --- | --- |
| ZIP instalable `commerce-base` y ZIP individuales de plugins propios admitidos en el manifiesto | WordPress y WooCommerce oficiales en las versiones verificadas, base vacía, usuario administrador nuevo, salts, configuración del hosting | `.env`, `wp-config.php`, bases/dumps, backups, logs, `.local`, credenciales, cookies, enlaces privados y herramientas QA |
| Scripts de preflight/inicialización expresamente incluidos y documentación | Páginas nativas, marca, opciones comerciales, catálogo, medios con derechos y políticas aprobadas | Usuarios/pedidos/solicitudes legales QA, datos personales, uploads históricos y contenido exclusivo TRAMA/ORIGEN CASA |
| Código funcional compartido, sin identidad ficticia | Un solo transporte de correo y sólo los proveedores contratados/autorizados | Docker Compose, Mailpit/local-mail, montajes DEV y gateways ajenos al alcance |

Esta distribución genera **seis ZIP**: Commerce Base y los cinco plugins Commerce Core, Commerce Communications, Commerce Order Integrity, Commerce Account Navigation y Commerce Lookbook. Excluye el preset ORIGEN CASA, los patrones editoriales hero/story, fotos editoriales DEMO y `inc/showcase.php`, una exhibición visual de desarrollo. Commerce Base **1.9.1** admite su ausencia: el template de exhibición sólo se carga si existe y la ruta `?commerce-base-showcase=1` responde 404 cuando falta. Esto no modifica ni sincroniza el contenido de TRAMA/ORIGEN CASA. Los scripts de `deployment/` y documentación se entregan por separado desde la revisión Git; los archivos `lab-*` pertenecen sólo al laboratorio y no se copian a producción.

El paquete de código no es un backup ni una tienda ya configurada. No incluye un dump “limpio” derivado de TRAMA: se parte de una DB nueva. Los fixtures optativos son exclusivamente material DEMO explícito, no contenido para habilitar ventas. No usar búsqueda/reemplazo de nombres sobre un dump histórico para generar un cliente.

## 4. Instalar sin Docker en producción

1. Provisionar el sitio bajo acceso restringido y sin indexación. Crear DB y credencial exclusiva con permisos sólo sobre esa base. Instalar WordPress desde el proveedor/oficial, con administrador nominado y contraseña generada mediante un canal privado. No incluir contraseñas literales en comandos ni historial.
2. Instalar la versión oficial verificada de WooCommerce; completar el asistente sin contratar extensiones ni conectar cuentas. Instalar los ZIP propios con **Apariencia → Temas → Añadir → Subir** y **Plugins → Añadir → Subir**. Verificar versiones y activar primero WooCommerce, luego los módulos requeridos; activar Commerce Base.
3. Mantener Mercado Pago completamente inactivo y sin credenciales en una instalación nueva. No activar plugins de captación, campañas, telemetría, caché, SMTP o seguridad como conjunto automático. Elegir cada componente por necesidad y compatibilidad.
4. Ejecutar `wp --path="$WP_ROOT" eval-file "$TOOLS_DIR/preflight.php"` con el script revisado fuera del webroot. Es un reporte de sólo lectura: revisar todos sus checks/extensiones y la configuración web, no interpretar el código de salida como acreditación automática del host. Compara además la línea base del proyecto, más estricta que mínimos genéricos. Sólo con instalación nueva, sin usuarios/contenido/configuración comercial existentes, ejecutar explícitamente `wp --path="$WP_ROOT" eval-file "$TOOLS_DIR/initialize.php"`. Crea baseline noindex/Coming Soon, páginas WC/Blocks y desactiva gateways; no crea identidad, catálogo ni legales de un cliente. Su guard no sustituye identificar un destino realmente vacío. No ejecutar `seed` de TRAMA ni fixture ORIGEN CASA en el cliente.
5. Completar manualmente los ajustes y contenido del siguiente apartado. Guardar los enlaces permanentes desde **Ajustes → Enlaces permanentes** o ejecutar `wp rewrite flush`; revisar que Apache/LiteSpeed tenga sus rewrites o que el proveedor haya instalado las reglas Nginx. No copiar `.htaccess` de Docker ni usar `--hard` indiscriminadamente en otro servidor.

Alternativa WP-CLI para **ZIP ya verificados y alojados fuera del webroot**; reemplazar las rutas simbólicas según la ficha y comprobar destino antes:

```sh
wp --path="$WP_ROOT" core version
wp --path="$WP_ROOT" plugin list --fields=name,status,version
wp --path="$WP_ROOT" theme install "$RELEASE_DIR/commerce-base-1.9.1.zip" --activate
# Usar los nombres/versiones exactos del manifiesto de esa distribución.
wp --path="$WP_ROOT" plugin install "$RELEASE_DIR/commerce-core-1.1.2.zip" --activate
wp --path="$WP_ROOT" rewrite flush
```

No usar `--force` en la primera instalación para ocultar una colisión. `WP_ROOT`/`RELEASE_DIR` son variables del operador, sin valor predeterminado ni secreto. Los demás ZIP se instalan individualmente según manifiesto y dependencias; no instalar todos los archivos de un directorio genérico.

## 5. Configurar cada comercio

Seguir [playbook H10](h10-new-client-playbook.md) para identidad, estilos, logo/favicon, navegación, Home, categorías, atributos, swatches y Lookbook optativo. Usar el importador CSV nativo para un catálogo revisado por SKU. Revisar alt, fotos, peso/medidas, stock y precios de cada variante. Las páginas de carrito/checkout deben contener sus Blocks y estar asignadas en WooCommerce; Mi Cuenta usa la página y endpoints nativos.

Confirmar país, moneda, impuestos con el asesor, zona horaria, idioma, cobertura, tarifas, retiro, plazos y zona sin cobertura. Configurar BACS sólo con titularidad real y aprobación del comercio en el lanzamiento autorizado; un comprobante no confirma acreditación. En QA usar exclusivamente datos bancarios ficticios y correo capturado. Registrar HPOS y sincronización desde la interfaz soportada; no forzar opciones ni intercambiar tablas autoritativas mediante SQL. [HPOS oficial](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/).

Configurar **Ajustes → Identidad y legales**: datos auténticos, destinatario atendido y cinco páginas publicadas propias. Crear el formulario `[commerce_legal_form]` y seleccionar su página; no desmarcar metadatos DEMO para aparentar aprobación. Los textos ficticios son un bloqueo, aun cuando dejen de mostrarse por el entorno. Comprobar sincronización con privacidad/términos nativos, botón visible, constancia, historial y permisos. La gestión de una solicitud no cancela ni reembolsa automáticamente un pedido.

## 6. Configuración productiva de referencia

Fragmento conceptual a adaptar en el `wp-config.php` privado ya generado por el hosting, antes de cargar WordPress; no sustituye la configuración completa ni contiene DB/salts:

```php
define('WP_ENVIRONMENT_TYPE', 'production');
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', false);
define('SCRIPT_DEBUG', false);
define('DISALLOW_FILE_EDIT', true);
define('FORCE_SSL_ADMIN', true); // Sólo después de validar HTTPS/proxy.
// Sólo cuando el plan permite estas memorias y el PHP web las respeta:
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '256M');
// Añadir únicamente después de probar el cron externo:
// define('DISABLE_WP_CRON', true);
```

Evitar constantes duplicadas; no fijar `DISALLOW_FILE_MODS` sin un canal alternativo de actualizaciones probado. No definir `COOKIE_DOMAIN` ni reescribir atributos de todas las cookies por rutina: comprobar el comportamiento nativo en HTTPS, sesión, login y checkout. El entorno staging usa `staging`, acceso restringido y transporte bloqueado/capturado; cambiar esta constante no es un mecanismo de autorización. [Configuración WP](https://developer.wordpress.org/advanced-administration/wordpress/wp-config/).

En PHP web configurar `display_errors=Off`, `log_errors=On`, log privado con rotación y acceso mínimo; límites de subida/POST coherentes y tamaño máximo de imagen razonable. WooCommerce y el servidor conservan diagnósticos aun con WP_DEBUG apagado: limitar acceso, contenido y retención. No registrar cuerpos de emails, payloads legales, direcciones completas, cookies, order keys ni enlaces de recuperación.

Usar secretos diferentes por comercio y entorno, desde el almacenamiento privado del hosting o archivo de configuración protegido; nunca `.env` público ni Git. No imprimir `wp config list`, credenciales de DB/SMTP o dumps de opciones en evidencia. Permisos orientativos `0755` directorios/`0644` archivos y configuración más restringida cuando el modelo del host lo permita; revisar propietario/grupo. No `chmod 777`, cambios recursivos sobre otras tiendas ni `FS_METHOD=direct` como arreglo universal. [Hardening WP](https://developer.wordpress.org/advanced-administration/security/hardening/).

En el servidor/CDN: denegar dotfiles, config, dumps, backups y listados; impedir ejecución PHP en uploads sin impedir imágenes/descargas autorizadas. Guardar backups fuera del webroot. Probar 403/404 de un archivo señuelo sin secretos. No publicar DB, Mailpit, herramientas QA ni consola. Mantener REST/Store API y endpoints WC funcionales. Ajustar WAF/rate limit con evidencia; un bloqueo de POST del checkout o del formulario legal es un fallo.

Habilitar `X-Content-Type-Options: nosniff` y una política de referrer acorde; probar embeds, imágenes y flujos antes de una CSP estricta. HSTS sólo tras validar certificados/renovación y todos los hosts afectados; no activar `includeSubDomains`/preload por defecto. El proxy debe informar HTTPS de forma fiable; confiar únicamente en cabeceras saneadas por el proxy conocido. [HTTPS WP](https://developer.wordpress.org/advanced-administration/security/https/).

## 7. Cron y Action Scheduler

WordPress cron depende de visitas por defecto. Configurar un único disparador periódico del panel para los eventos vencidos, inicialmente cada 5 minutos si lo permite el plan. Verificar ruta del ejecutable PHP/WP-CLI, versión, usuario, permisos, lock y un log privado rotado. Ejemplo **Linux**, con rutas verificadas y `flock` disponible; no pegarlo en Windows ni asumir rutas del proveedor:

```cron
*/5 * * * * /usr/bin/flock -n /home/ACCOUNT/private/wp-cron.lock /usr/local/bin/wp --path=/home/ACCOUNT/site/public_html cron event run --due-now >> /home/ACCOUNT/private/wp-cron.log 2>&1
```

Las rutas son marcadores y deben cambiarse. Si el host provee cron PHP directo, usar su ruta real a `wp-cron.php`; si sólo admite HTTP, usar HTTPS canónico y su mecanismo soportado, comprobar acceso detrás de autenticación/WAF. No incluir un secreto en una URL pública/log. Después de verificar ejecución real, añadir `DISABLE_WP_CRON=true`; antes, conservar el runner por visitas. No instalar varios disparadores equivalentes. [Cron WP](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/).

Consultar `wp cron event list` y **WooCommerce → Estado → Acciones programadas**: vencidas, fallidas, hooks, fechas, frecuencia y backlog. Comprobar un evento QA inocuo y su ejecución sin visitas; observar tareas WC de sesiones, stock pendiente y procesamiento programado. Action Scheduler usa su runner normal; no sustituirlo ni instalar otro scheduler por defecto. Ante volumen alto, evaluar WP-CLI con límites usando `wp help action-scheduler run`; un ejemplo de diagnóstico controlado es `wp action-scheduler run --batch-size=25 --batches=1`. Ejecuta acciones reales: sólo en ventana aprobada o laboratorio aislado. No usar `--force`, limpiar fallidas ni segmentar hooks con dependencias para ocultar backlog. [Action Scheduler](https://actionscheduler.org/wp-cli/).

## 8. Caché sin mezclar clientes

Inicialmente un solo mecanismo de caché de página soportado por el hosting; registrar todas las capas (plugin, servidor, CDN). No apilar plugins. Probar sin minificación/diferido agresivo de JS y activar optimizaciones individualmente. OPcache es caché de bytecode y puede usarse si el host lo admite; Redis/objetos persistentes requieren servicio real, compatibilidad, clave/scope por sitio y capacidad medidos.

| Bypass obligatorio en cada capa anterior a PHP | Comprobación |
| --- | --- |
| Carrito, checkout y sus endpoints, Mi Cuenta/direcciones/password, pago/recibo/confirmación privada | Nunca servir HTML o totales de otro visitante; sin HIT compartido |
| Formulario, errores y constancia de arrepentimiento | No-store, nonce/cookie propios y sin respuesta persistida en caché |
| Store API `wc/store/*` de cart/checkout/order/batch; WC REST privado, `?rest_route=`, `wc-ajax`, `wc-api`, notificaciones | Preservar autorización, Nonce y Cart-Token; no cachear mutaciones |
| Login, admin, `admin-ajax.php`, `admin-post.php`; métodos distintos de GET/HEAD | No cachear administración ni POST/PATCH/PUT/DELETE |
| `wordpress_logged_in_*`, `wp_woocommerce_session_*`, `woocommerce_cart_hash`, `woocommerce_items_in_cart`; Authorization/Set-Cookie | Bypass de sesión/autenticación; no eliminar cookies para lograr HIT |
| `?add-to-cart=`, cupones y contenido privado/personalizado | Respetar cambios de stock, envío, moneda y usuario |

Usar URLs reales asignadas y slugs traducidos del cliente, no sólo `/cart` y `/checkout`. La tabla aplica reglas del proyecto más estrictas que las exclusiones básicas oficiales. [Guía WC](https://developer.woocommerce.com/docs/best-practices/performance/configuring-caching-plugins/).

Verificar contra el host/CDN con dos navegadores A/B y un anónimo: Home/catálogo admiten MISS→HIT cuando corresponde; carrito, checkout, cuenta, REST y legal deben mostrar BYPASS/MISS privado, contenido separado y cookies intactas. Cambiar cantidad, variante, cupón, envío y stock; completar un BACS QA, consultar cuenta A y denegar pedido A a B. Revisar cabeceras y contenido; un `no-store` de origen no demuestra que un CDN lo respete. Purgar código/assets después de actualizar; no vaciar sesiones comerciales como rutina. HPOS no sustituye estas exclusiones. Sin proveedor real validado, caché queda condicional.

## 9. Backup completo y conservación

Definir por contrato **RPO** (pérdida de datos tolerable) y **RTO** (tiempo objetivo de recuperación), operador, suplente, ventana, costo y canal de incidentes. Objetivos iniciales orientativos: ecommerce pequeño DB cada hora/RPO ≤1 h y RTO 4–8 h dentro de cobertura; negocio que tolere menos, DB cada 15 minutos o recuperación a punto temporal comprobada/RPO ≤15 min y RTO 1–4 h con proveedor y soporte adecuados. No prometerlo con backup semanal/diario ni sin ensayo. El RTO incluye diagnóstico, restauración, DNS cuando aplique, conciliación y QA.

| Parte de un conjunto coherente | Debe conservar |
| --- | --- |
| **Base completa**, sin lista parcial de tablas | Productos/variantes/meta/taxonomías, clientes/roles/direcciones, opciones WC/tema/plugins, cupones, stock y sesiones; HPOS `wc_orders`, `wc_order_addresses`, `wc_order_operational_data`, `wc_orders_meta`; items/itemmeta, notas, refunds y tablas auxiliares/analíticas |
| Tablas propias y colas | Con el prefijo real: `commerce_legal_requests`, `commerce_legal_events`, `commerce_legal_limits`, opciones `commerce_core_legal`/schema; Action Scheduler acciones/grupos/claims/logs y restantes tablas instaladas |
| Archivos del mismo punto | Uploads/medios, theme/plugins/MU productivos, traducciones, núcleo o versión verificable, configuración privada y reglas del servidor |
| Manifiesto privado | Fecha/huso e instante de corte, DB/instancia de origen, revisiones/versiones, conteos/fingerprints agregados, SHA-256, cifrado, ubicación, fecha/resultado de verificación y responsable |

Conservar ambas formas de almacenamiento WC si existen y su configuración autoritativa/sync. Respaldar sólo `posts/postmeta` pierde HPOS; sólo las cuatro tablas HPOS tampoco permite reconstruir pedidos completos. Una sincronización HPOS no es un backup externo. [Modelo HPOS](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/), [backups WP](https://developer.wordpress.org/advanced-administration/security/backup/).

Propuesta de retención a validar según datos/contrato: DB horaria 48 h, diaria 30 días y semanal 12 semanas; archivos incrementales diarios y conjunto completo semanal, más backup previo a cambios. Al menos una copia cifrada fuera del hosting/cuenta de sistema y otra ubicación independiente. Claves de descifrado separadas, acceso mínimo con MFA, alertas de fallo/cuota/caducidad y protección de borrado cuando exista. Una copia dentro del mismo plan no cubre suspensión o compromiso de cuenta. No publicar dumps ni usar el repositorio para retenerlos. Acordar eliminación al finalizar retención y revisar conservación legal con el titular.

Para consistencia, detener escrituras en una ventana breve o usar snapshots/backup coherente soportado; el modo mantenimiento de WordPress por sí solo no frena webhooks, cron, integraciones ni toda escritura. Coordinar pedidos, admin, colas y productores externos; registrar corte. Para DB exclusivamente InnoDB, sin DDL concurrente, un export `--single-transaction` aporta consistencia transaccional; no hace atómicos DB+medios ni protege cambios posteriores. Exportar toda la DB de uso exclusivo mediante el cliente del host/WP-CLI, sin contraseña en argumentos, y copiar archivos del mismo conjunto. [Export WP-CLI](https://developer.wordpress.org/cli/commands/db/export/).

Verificar integridad del contenedor/cifrado, hashes, listado seguro del archivo, tamaño y presencia de tablas; comprobar restauración real, no sólo que el job termine. Ejecutar ensayo mensual inicialmente y después de cambiar versión, plugin con datos, host o método de backup. Guardar evidencia agregada de recuperación de productos, variantes, usuarios, pedidos HPOS/items/stock, solicitudes/notas/eventos, opciones y medios; no adjuntar datos personales al informe.

## 10. Restaurar primero en aislamiento

1. Identificar conjunto, origen, destino **vacío/desechable**, fecha y aprobación; comprobar hashes y descifrado. Crear un backup preventivo del destino si contiene algo. Rechazar archivos con rutas absolutas, `..`, symlinks u objetos inesperados. No reutilizar DB/volumen de producción.
2. Bloquear acceso público, indexación, entrega de correo y llamadas a pagos/integraciones antes de arrancar WordPress. Usar credenciales locales nuevas y captura de emails. Restituir archivos y DB completa en el entorno separado; adaptar config de destino sin revelar ni activar secretos productivos restaurados. Revisar jobs/webhooks antes de permitir ejecución.
3. Si cambia origen, usar `wp search-replace` compatible con serializados, sobre **esa copia**, primero `--dry-run`, con `--skip-columns=guid` y tablas del sitio verificadas. Revisar las URLs custom; no usar reemplazo de texto global sobre SQL. Guardar permalinks y purgar caché del clon. [Search-replace](https://developer.wordpress.org/cli/commands/search-replace/).
4. Validar identidad, catálogo/medios, variantes, usuarios/roles, pedidos/refunds/items/stock/cupones, HPOS y tablas legales con sus eventos y destinatarios. Probar login, cuenta con propiedad A/B, BACS capturado, formulario/constancia, administración y cron inocuo. No reejecutar cobros, reembolsos, envíos ni emails antiguos para “comprobar” la copia.
5. Registrar instante recuperado, pérdida observada, duración medida y limitaciones. La prueba local demuestra ese conjunto/procedimiento local; cifrado, offsite, permisos y restore del hosting siguen pendientes hasta verificarlos allí.

## 11. Recuperar una tienda activa sin perder actividad posterior

Ante fallo, bloquear ventas nuevas y escrituras con el proveedor; preservar evidencia y obtener **backup completo del estado actual**, aunque esté degradado. Identificar pedidos, pagos/reembolsos, stock, cuentas, catálogo y solicitudes legales recibidos después del punto que se pretende restaurar. Mantener también registros de proveedores externos y asignar conciliación comercial. Nunca importar un backup antiguo encima de la tienda activa como primera acción.

Preferir reparar o revertir sólo código compatible. Si hace falta volver datos, restaurar en otra DB/sitio aislado y comparar contra el estado actual. Diseñar y ensayar una recuperación selectiva con CRUD/APIs y relaciones completas, sin copiar filas sueltas de pedidos HPOS ni asumir que un CSV de órdenes conserva todo. Verificar IDs, pagos, items, stock y eventos. Si no es posible garantizar reconciliación, mantener la venta detenida y escalar al proveedor/especialista; el titular decide y aprueba cualquier pérdida conocida antes de sustituir el sitio. Conservar las dos copias hasta cerrar el incidente.

Antes del cambio final verificar un punto de corte, ausencia de nuevos productores, restore validado, conciliación y responsable de reversión. Habilitar gradualmente correo/colas/integraciones para evitar duplicar acciones anteriores; conciliación manual de efectos externos que un restore no revierte. Probar checkout y atención, avisar por los canales acordados y registrar causa y medidas. No rotar salts por rutina de restore; hacerlo deliberadamente ante compromiso, sabiendo que invalida sesiones/tokens asociados.

## 12. Actualizar código sin reemplazar la base comercial

1. Fijar revisión/ZIP y leer cambios/migraciones/dependencias. Obtener backup íntegro, verificar restauración reciente y ensayar con copia protegida o datos sintéticos. Anotar baseline de pedidos/usuarios/stock/legales y compatibilidad de rollback.
2. Preparar ventana breve y canal de mantenimiento del host para una actualización multicomponente. Subir ZIP autenticados y verificados. Cambiar únicamente los directorios del theme/plugins afectados mediante mecanismo soportado; conservar `uploads`, config, DB y personalizaciones aprobadas. No publicar todo staging ni importar su DB.
3. Para un componente existente, WordPress permite subir ZIP y revisar **reemplazar actual**; con WP-CLI, tras confirmar slug/destino y backup, `wp theme install "$ZIP" --force` o `wp plugin install "$ZIP" --force`. No usar comodines ni desplegar código parcialmente copiado bajo tráfico. No ejecutar uninstall ni seeds para actualizar.
4. Si una versión exige migración, ejecutar sólo su mecanismo soportado, probar y registrar resultado; separar migración de cambios de contenido. Comprobar DB/schema y cola WC. No bajar código sobre schema incompatible: en ese caso rollback de datos exige el circuito de conciliación anterior.
5. Purgar caché de código/assets según el host y probar Home, producto variable, carrito/checkout BACS capturado en staging, cuenta/permisos, legal y administración. En producción usar pruebas aprobadas que no cobren ni notifiquen compradores reales. Comparar agregados: pedidos posteriores siguen presentes y no fueron reemplazados por QA. Registrar versión final y retirar mantenimiento sólo si pasan los controles.

Para cambios editoriales/configuración, preparar una migración específica revisada por IDs/SKU y propietarios de los datos, o usar UI nativa. No trasladar `options`, `users`, `posts`, tablas HPOS ni todas las plantillas de staging de forma indiscriminada. El ensayo H11 crea un **pedido sintético posterior al backup** y una segunda referencia lógica para comprobar que reponer código preserva ese pedido y el resto de entidades previas. No representa una actualización mayor con migración de schema. Ver sus resultados efectivos en [preparación H11](h11-production-readiness.md).

## 13. Correo transaccional: transición de Mailpit

Mantener `wp_mail()`/eventos WC y Commerce Communications; elegir **un** conector SMTP/API compatible. Retirar local-mail/Mailpit del paquete productivo. Verificar servicio contratado, cuota real, coste/renovación, dominio, ubicación/retención de datos y responsable; un dominio comprado no implica buzón. Hostinger Mail autenticado puede evaluarse para volumen bajo; un proveedor transaccional permite separar reputación/cuota del correo humano. SMTP o API son transportes, no motores nuevos de pedidos. Los detalles y alternativas están en [H7 correo](h7-email-production.md).

Configurar From del dominio autorizado, nombre del comercio, Reply-To atendido y destinatarios internos correctos. Nunca From del comprador. Guardar credenciales fuera de Git y recordar que, si el conector las almacena en DB, el backup completo también las contiene. Usar TLS con verificación, límites/reintentos acotados y visibilidad de rebotes, supresiones y errores. No fallback silencioso a PHP mail ni buzón personal como solución definitiva. Separar campañas/promociones y su consentimiento de mensajes necesarios para la compra; no convertir compradores en suscriptores automáticamente.

Conservar MX existentes si sólo se agrega envío transaccional. SPF debe incorporar los emisores legítimos en **un solo registro** y respetar sus límites; DKIM usa el selector/destino del proveedor efectivo. DMARC comienza con observación acordada y buzón `rua` controlado; endurecer después de revisar alineación y emisores. No copiar valores de ejemplo a una zona real. [DNS Hostinger Mail](https://www.hostinger.com/support/8671319-set-up-a-domain-for-hostinger-email-manually/).

| Evento QA | Validar en Mailpit/local | Validación real posterior, con autorización y destinatarios aprobados |
| --- | --- | --- |
| Nuevo pedido / BACS on-hold | Comprador/admin habilitados, importes/variantes/envío, instrucciones ficticias | Una emisión por evento, banco real aprobado, enlaces HTTPS y reply correcto |
| Processing y Completed | Cambio manual sintético y destinatarios esperados | Recepción, marca y estado correctos; no marcar producción pagada para probar |
| Cancelación / fallido | Emails efectivamente habilitados por WC y destinatario de cada tipo | No asumir que cada cancelación envía un aviso al comprador |
| Reembolso parcial / total | Avisos sintéticos, importe y texto; sin movimiento monetario | Sólo prueba autorizada sin cobro/reembolso real durante H11 |
| Alta / recuperación de contraseña | Usuario .test propio, link sin divulgar token | Link válido y de uso limitado a buzón de prueba autorizado, sin loops de caché |
| Constancia de arrepentimiento | Código, recepción, consumidor/admin, registro conservado ante fallo | Bandeja/headers, respuesta atendida, reintento controlado sin duplicación ciega |
| Error de transporte / límite / rebote | Estado visible, evidencia privada sin cuerpo/secretos | Alertas, ID del proveedor, backoff y resolución/reenvío explícito |

Para cerrar correo real comprobar TLS, respuesta del proveedor, recepción en buzones controlados (incluido spam), HTML/texto/móvil/imágenes bloqueadas, From/Reply-To/Return-Path y `Authentication-Results` con SPF/DKIM/DMARC y alineación. Registrar sólo IDs y evidencia redactada. `wp_mail()` exitoso acredita aceptación por el transporte, no entrega a bandeja. No hacer estas pruebas a compradores históricos. Sin proveedor/permiso/destinatarios, **NO CONFIGURADOS**.

## 14. Dominio, DNS y HTTPS sin cortar otros servicios

Inventariar titular, registrador, renovación, DNS autoritativos, zona/export actual, TTL, A/AAAA/CNAME, MX, TXT/SPF/DKIM/DMARC, CAA, DNSSEC/DS, verificaciones y subdominios. Identificar mail, aplicaciones y terceros existentes. Preparar un diff y reversión con valores originales; cualquier aplicación requiere aprobación explícita. Comprar un dominio, delegarlo y contratar correo son acciones distintas.

| Origen del dominio | Procedimiento preparado |
| --- | --- |
| NIC Argentina | El titular opera desde NIC/TAD o representación autorizada. Si hace falta cambiar delegación, NIC pide al menos dos nameservers del DNS elegido. A/AAAA/CNAME/TXT se administran en esa zona autoritativa, no se pegan como nameservers. No autodelegar salvo que se opere DNS propio y se sepa configurar glue. [Instructivo NIC](https://nic.ar/es/ayuda/instructivos/delegacion-de-dominios) |
| Hostinger | Verificar **Dominios → dominio → DNS/Nameservers** y dónde está delegada realmente la zona. Si usa DNS externo, editar allí con autorización; no restablecer zona ni cambiar nameservers por comodidad |
| Otro registrador | Mantener su registro/titularidad; seguir su panel/documentación y el proveedor DNS autoritativo. No hace falta transferir dominio para alojar la tienda en otro host |

Preferir, cuando sea suficiente, modificar sólo A/AAAA del hostname web y CNAME `www` en la zona existente. No borrar un AAAA válido ni dejar uno apuntando al servidor anterior: confirmar IPv6 soportado o preparar su retirada aprobada. No poner CNAME convencional en la raíz que ya usa MX/TXT; usar el tipo/apex soportado por el DNS/proveedor. Los valores se obtienen del hosting asignado, nunca de un ejemplo. Conservar correo y verificaciones; si se cambia nameserver, copiar y validar **toda** la zona, DNSSEC/DS y servicios antes de delegar.

Antes del cambio autorizado preparar virtual host, certificado para raíz y `www`, redirección única al canónico HTTPS y renovación automática comprobada. Ensayar por método soportado del hosting o resolución local controlada sin publicar la tienda. No añadir un redirect HTTPS genérico que genere loops con el proxy; excluir validación ACME según el proveedor. Mantener ruta/query y evitar cadenas; verificar URLs WP (`home`/`siteurl`), medios, Store API, cookies y enlaces de emails. Un certificado de staging no valida el dominio productivo.

Tras el cambio autorizado, consultar DNS autoritativo y resolvers independientes, IPv4/IPv6, TLS/SAN/fecha, HTTP→HTTPS, www/no-www, recursos mixtos, REST/checkout y correo existente. Respetar TTL anteriores: no garantizar propagación en minutos. Mantener el host previo durante la ventana acordada, sin dos tiendas aceptando pedidos divergentes. La reversión DNS puede tardar por caches y no reconcilia pedidos por sí sola. Renovaciones/dominio/SSL y sus alertas tienen responsables nominados. En H11 sólo se prepara este recorrido, sin cambios DNS ni transferencia.

## 15. SEO, acceso y cierre de lanzamiento

DEMO/staging: acceso restringido, `blog_public=0`, noindex y ausencia de campañas/cuentas analíticas reales. Probar la respuesta anónima, no sólo el ajuste guardado. `robots.txt` no protege datos y `Disallow` puede impedir que Google vea un noindex; autenticación es el control de acceso. [Noindex Google](https://developers.google.com/search/docs/crawling-indexing/block-indexing), [límites de robots](https://developers.google.com/search/docs/crawling-indexing/robots/intro).

Sólo con autorización final, revisar el checklist y levantar restricciones en el dominio definitivo: indexación, canonical consistente, sitemap, títulos/descripciones, Open Graph, favicon, precios/disponibilidad y structured data coherentes con productos reales. Excluir cuenta, checkout, recibos y datos privados; no publicar tokens en sitemap. Verificar Search Console y analítica únicamente con cuenta/permiso del titular, objetivo, privacidad/consentimiento aplicable y separación de entornos. No agregar un segundo generador que duplique metadatos/schema; no prometer ranking por instalar plugins.

## 16. Operación, alertas e incidentes

| Frecuencia | Técnico asignado | Comercio |
| --- | --- | --- |
| Diaria durante cobertura | Uptime/HTTPS, último backup y copia externa, errores PHP/WC, cron/Action Scheduler vencidas/fallidas, transporte/cuota/rebotes, fallos de checkout, integración y alertas de seguridad | Conciliar pagos/BACS, pedidos, stock real, atención y solicitudes legales; avisar discrepancias |
| Semanal | Revisar actualizaciones/avisos y aplicar cambios probados, espacio/inodos/límites, logs redactados, accesos y salud de cache; prueba sintética aprobada en staging | Revisar productos, precios, plazos, promociones y contacto |
| Mensual | Restauración de muestra, revisión de RPO/RTO, cuentas/MFA/permisos, capacidad/renovación, certificado, rendimiento y lista de terceros | Validar responsables, condiciones comerciales y retención con asesoría |
| Ante incidente/cambio relevante | Contener acceso/ventas, preservar evidencia y backup actual, diagnosticar alcance entre sitios, recuperar/conciliar, rotar secretos comprometidos y documentar causa | Decidir contingencia y pérdida tolerable, comunicaciones/reclamos/notificaciones aplicables con asesoría |

Las alertas necesitan destino, horario, umbral, suplente y plazo de respuesta acordados; uptime solo no prueba checkout. Como punto de partida, alertar por backup vencido a más de dos intervalos, cron sin ejecutar a más de dos intervalos, aumento sostenido de acciones vencidas/fallidas, errores de pago/correo o agotamiento de recursos; ajustar umbrales al negocio. No registrar una compra real automática para monitorear.

Actualizar WordPress/WC/plugins con changelog, respaldo y prueba de los flujos afectados; priorizar parches de seguridad según exposición. Separar cuentas por persona, mínimo de administradores, shop manager para operación, contraseñas únicas y MFA en hosting/DNS/email/WordPress mediante mecanismo compatible. Retirar QA y accesos temporales antes de lanzamiento, con reasignación de contenido si corresponde. Bases diferentes bajo el mismo usuario de sistema no aíslan los archivos/secretos: registrar el riesgo y limitar el alcance entre clientes.

La preparación final y el playbook del cliente están en [checklist H11](h11-client-launch-checklist.md). Ni este runbook ni un PASS local autorizan una tienda pública.

## Anexo: reproducción del laboratorio local H11

Este laboratorio usa Docker **sólo para simular** un host PHP/DB en una instalación nueva. WordPress recibe los ZIP, sin bind de los directorios reutilizables del theme/plugins. Conserva herramientas/montajes del laboratorio, Apache y correo capturado propios: no demuestra compatibilidad del hPanel ni ejecuta la composición en producción. No requiere copiar la DB/medios de las DEMO existentes.

El laboratorio deja config/código sin escritura para el proceso web mediante propietario `root:33`, permisos de lectura y padres protegidos; conserva uploads escribibles. `update-code` abre temporalmente sólo los seis paquetes y sus padres en el contenedor y vuelve a protegerlos en `finally`, incluso ante fallo. El propietario/UID y este mecanismo son **exclusivos del laboratorio**: no aplicar sus comandos `chown` al hosting ni interpretar esto como aislamiento entre cuentas de sistema de un proveedor. En hosting real, acordar el mecanismo de despliegue/permisos con su operador.

Precondiciones: rama/revisión revisada, PowerShell 5.1+, Docker Desktop/Compose funcionando sin modificar servicios ajenos, puertos libres `8184–8189`, suficiente disco y Node.js con Playwright/Chromium disponibles. Usar `node` del runtime autorizado y `NODE_PATH` a su directorio de dependencias si no está en PATH; sus rutas dependen de la máquina y no forman parte de la distribución. No instalar paquetes productivos para ejecutar este QA. Si ya existen `.local/h11/`, estado privado o volúmenes, preservarlos e inspeccionar: los fixtures y snapshots rechazan reejecuciones sobre datos existentes.

```powershell
# Desde la raíz del repositorio, con los cambios distribuibles ya confirmados.
./scripts/build-release.ps1 -ReleaseVersion 1.0.0
./scripts/h11.ps1 -Action install -Instance qa
./scripts/h11.ps1 -Action inspect -Instance qa
./scripts/h11.ps1 -Action wp -Instance qa -WpArgs @('eval-file','/opt/h11-tests/h11-fixture.php','prepare')

# Helper temporal: sólo el webroot del laboratorio, nunca una tienda de cliente.
docker compose --project-directory . --project-name commerce-h11-qa --env-file .local/h11/qa/.env -f compose.h11.yaml cp tests/h11-sapi-probe.php h11-web:/var/www/html/h11-sapi-probe.php
# Flujo nativo de navegador: pedido BACS y trámite legal sintéticos.
node tests/verify-h11-browser.cjs
node tests/verify-h11-http.cjs
./scripts/h11.ps1 -Action wp -Instance qa -WpArgs @('eval-file','/opt/h11-tests/h11-fixture.php','exercise-email')
$env:H11_EMAIL_ONLY = '1'
node tests/verify-h11-browser.cjs
Remove-Item Env:H11_EMAIL_ONLY
docker compose --project-directory . --project-name commerce-h11-qa --env-file .local/h11/qa/.env -f compose.h11.yaml exec -T --user 0 h11-web rm /var/www/html/h11-sapi-probe.php

# Prueba operativa local: WP-Cron y Action Scheduler ejecutan callbacks nativos
# contra un transient y una sesión sintéticos vencidos, sin jobs comerciales.
./scripts/h11.ps1 -Action wp -Instance qa -WpArgs @('eval-file','/opt/h11-tests/h11-fixture.php','exercise-operations')

# Baseline lógico privado; backup completo del origen desechable con web detenido.
./scripts/h11.ps1 -Action wp -Instance qa -WpArgs @('eval-file','/opt/h11-tests/h11-verify.php','snapshot')
./scripts/h11.ps1 -Action backup -Instance qa

# Pedido posterior al backup: segunda referencia independiente para código.
./scripts/h11.ps1 -Action wp -Instance qa -WpArgs @('eval-file','/opt/h11-tests/h11-fixture.php','exercise-code-update')
./scripts/h11.ps1 -Action wp -Instance qa -WpArgs @('eval-file','/opt/h11-tests/h11-verify.php','snapshot-code')
# Reposición desde los ZIP: comparar con el segundo baseline (incluye pedido nuevo).
# No importa DB; el ensayo de una versión distinta exige su release y QA propios.
./scripts/h11.ps1 -Action update-code -Instance qa
./scripts/h11.ps1 -Action wp -Instance qa -WpArgs @('eval-file','/opt/h11-tests/h11-verify.php','compare-code')

# Destino independiente recién instalado y vacío; nunca una tienda activa.
./scripts/h11.ps1 -Action install -Instance restore
./scripts/h11.ps1 -Action restore -Instance restore
# Compara el punto original, anterior al pedido posterior: no se espera recuperarlo.
./scripts/h11.ps1 -Action wp -Instance restore -WpArgs @('eval-file','/opt/h11-tests/h11-verify.php','compare')
docker compose --project-directory . --project-name commerce-h11-restore --env-file .local/h11/restore/.env -f compose.h11.yaml cp tests/h11-sapi-probe.php h11-web:/var/www/html/h11-sapi-probe.php
$env:H11_RESTORE_SMOKE = '1'
$env:H11_URL = 'http://localhost:8185'
node tests/verify-h11-browser.cjs
Remove-Item Env:H11_RESTORE_SMOKE
Remove-Item Env:H11_URL
docker compose --project-directory . --project-name commerce-h11-restore --env-file .local/h11/restore/.env -f compose.h11.yaml exec -T --user 0 h11-web rm /var/www/html/h11-sapi-probe.php

# Tercera instalación vacía con el script final; sin fixture, catálogo ni pedidos QA.
./scripts/h11.ps1 -Action install -Instance repeat
./scripts/h11.ps1 -Action inspect -Instance repeat

# Detener sólo estos proyectos; conserva volúmenes y artefactos privados.
./scripts/h11.ps1 -Action stop -Instance qa
./scripts/h11.ps1 -Action stop -Instance restore
./scripts/h11.ps1 -Action stop -Instance repeat
```

`qa` es `http://localhost:8184`, Mailpit `http://localhost:8186`; `restore` es `http://localhost:8185`, Mailpit propio `8187`; `repeat` es `http://localhost:8188`, Mailpit propio `8189`. El smoke de restauración no vuelve a enviar emails: el runner consulta la captura fuente `8186` para el modo de correo. No publicar estos puertos ni abrir túneles. El helper SAPI no se distribuye: retirarlo y comprobar su 404 aun si una prueba falla, antes de snapshot/backup o detener el entorno.

Los secretos están en `.local/h11/<qa|restore|repeat>/secrets/`; el backup fuente en `.local/h11/qa/backups/` incluye DB, archivos y checksums; los archivos con credenciales/tokens y referencias lógicas privadas están en `.local/h11/private/`. `h11-snapshot.json` es el punto para restaurar; `h11-code-snapshot.json` incluye el pedido posterior y sirve únicamente para comparar la reposición de código en source. Restaurar el primer backup **no recupera actividad posterior** y jamás se aplica a `qa`: el guard limita el destino a `restore`. Esto demuestra la necesidad de preservar/conciliar datos nuevos antes de cualquier recuperación real, no una recuperación selectiva automática.

El restore exige manifiesto SHA-256 completo, archivo tar sin rutas inseguras/links y un fingerprint del destino recién instalado: usuarios/posts, HPOS/items, legales y opciones seleccionadas deben coincidir con su estado inicial. Un usuario o dato nuevo hace rechazar la operación; no borrar datos de cliente para superar el guard. Conservar el entorno ante rechazo y revisar. Preserva la configuración/salts del destino y adapta serializados de `8184` a `8185`; no puede usarse como migrador remoto genérico. El backup local no está cifrado ni enviado offsite: es QA sintética y no cierra los controles de backup productivo.

**Excepción exclusiva del laboratorio:** el cliente MariaDB instalado exige SSL por defecto y el servidor H11 está en una red Docker aislada sin TLS de DB. `h11.ps1` exporta con WP-CLI y `--skip-ssl`. Para restaurar usa directamente el cliente nativo `mariadb`: en el diagnóstico local de WP-CLI 2.12, su consulta previa de modos SQL no propagó ese flag, aun evitando optimizaciones. El wrapper nativo lee la credencial privada desde `/run/secrets/db` y la entrega al proceso mediante `MYSQL_PWD`, sin ponerla en argumentos ni salida; no activa depuración de shell. Esa excepción sólo resuelve la red aislada QA. No trasladar `--skip-ssl` ni este supuesto de TLS a una receta productiva o conexión remota: verificar con el host su socket privado o TLS, validación de certificado/hostname y manejo de credenciales admitidos. Un fallo de exportación invalida ese intento de backup; no reutilizar sus archivos parciales. La acción `backup` reinicia el web del laboratorio en `finally` aun ante ese fallo. Ante fallo de restore, el web de destino permanece detenido para preservar y diagnosticar su estado.

Los reportes aptos para revisión se generan en `docs/evidence/h11/`. `h11-verify.php` compara esquema/fingerprints de tablas comerciales, IDs/estados y hashes de medios, normalizando sólo el origen local; las sesiones/colas/transients de ejecución no se comparan byte a byte. El backup completo sí debe incluir esas tablas. `exercise-operations` verifica ejecución local nativa, más que la mera presencia de tareas; todavía no prueba el cron autónomo/sin visitas del hosting. `verify-h11-http.cjs` recoge las exclusiones de caché/noindex de origen, rutas sensibles y cabeceras locales en `http-cache.json`; deja explícito que no probó HTTPS ni caché del proveedor. Consultar [preparación H11](h11-production-readiness.md) para saber qué comandos se ejecutaron, resultados y límites efectivos; la secuencia anterior es una receta, no un registro de PASS.
