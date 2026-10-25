# D1 — Runbook de despliegue futuro de demos comerciales

Preparado el **24/10/2026, Argentina**. **Procedimiento documental: NO EJECUTADO EN HOSTINGER.** Requiere autorización posterior sobre nombres, destinos, cambios y riesgos concretos. Crear una web, subdominio o dominio temporal ya es aprovisionar/publicar un recurso remoto: no hacerlo durante D1. No usar este runbook contra el portfolio ni contra webs de clientes.

La recomendación y las incertidumbres están en [el plan D1](demo-commercial-plan.md); la ejecución local y sus límites, en [validación D1](demo-commercial-validation.md). El alcance público termina en el carrito. El bloqueo productivo de Mercado Pago H7–H11 permanece abierto; el plugin estará ausente.

## 0. Condiciones y aprobación antes de cualquier escritura remota

Registrar por escrito: titular/cuenta y suscripción Premium legacy; dos nombres aprobados; responsable de instalación/mantenimiento; ventana; riesgo de cuenta compartida; umbrales de retirada; backups y coste aceptado. Candidatos, todavía **sin aprobar**: `trama-demo.ramita.dev` y `origen-demo.ramita.dev`.

Primero hacer una inspección de lectura del panel con acceso autorizado. Guardar fuera de Git un inventario de las ocho webs con sus raíces/DB y un registro sin secretos que identifique únicamente las dos nuevas. Confirmar cupo de sitios/DB, CPU/RAM/I/O/IOPS/workers/procesos y siete días de carga base. No inferir margen a partir de «25 sitios». Si hay saturaciones recurrentes o falta de espacio temporal/recuperación, NO-GO.

Confirmar PHP servido ≥8.3 y compatible con el código probado, MySQL ≥8.0 o MariaDB ≥10.6, memoria WordPress ≥256 MB y extensiones requeridas. Preferir la línea local PHP 8.4. Comprobar versión exacta/parche/seguridad vigente antes de publicar; D1 no declara seguras indefinidamente las versiones congeladas. [Requisitos oficiales WooCommerce](https://woocommerce.com/document/server-requirements/).

**No cambiar PHP globalmente desde hPanel:** el proveedor indica que el selector del plan afecta las webs de esa suscripción. Si el PHP existente no cumple, estudiar un override exclusivo del document root de la demo y comprobarlo protegido mediante el SAPI web, incluyendo módulos y límites. Si exige tocar otras webs, detenerse. [Alcance del selector](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/), [override específico](https://www.hostinger.com/support/4047803-how-to-change-the-php-version-for-subfolders-or-subdomains-in-hostinger/).

## 1. Preparar los insumos fuera del servidor público

1. Seleccionar el commit D1 revisado en `codex/d1-commercial-demos-preparation` y registrar su SHA. No desplegar una rama cambiante. Conservar `main`, `template-v1.0.0-rc1` y los seis ZIP H11 sin alteración.
2. Verificar los seis SHA-256 contra el manifiesto original `.local/h11-release/1.0.0/manifest.json` en almacenamiento privado y comparar las versiones de esta tabla. No reconstruir esos ZIP para añadir D1. El módulo se copia por separado.
3. Preparar **dos carpetas de contenido independientes** a partir de los archivos versionados indicados abajo, nunca de `.local/local`, `.local/origen-casa`, uploads históricos ni backups. Revisar datos y licencias; escanear secretos/PII. No incluir Git completo, tests, docker, logs, configuración ni runtime en el destino.
4. Si se necesita un archivo importable, generarlo en el laboratorio D1 limpio y revisar su contenido: productos CSV nativos y medios de ese laboratorio, o carga manual según el mapa de la sección 5. No exportar usuarios, pedidos, notas, solicitudes legales, sesiones, opciones completas ni SQL. Los IDs de laboratorio no son identificadores de producción.

| Paquete H11 aprobado | Versión | SHA-256 esperado |
| --- | --- | --- |
| `commerce-base-1.9.1.zip` | 1.9.1 | `2cc57f2c460bdbdc3ba2313e07078dbbd2077af209fa057318cb6445d986b6fe` |
| `commerce-core-1.1.2.zip` | 1.1.2 | `12811c31b0b4f4463dcd0adb49712c7738e3bf2bc1d43364b0bd49bb46afdddc` |
| `commerce-communications-1.0.1.zip` | 1.0.1 | `3bfb42e01985fc5f41af59dd50cd9e19e86b00aba3a5af2e92629e74ece87e70` |
| `commerce-order-integrity-1.0.0.zip` | 1.0.0 | `f7e0d132f79fa65719254756ef34c31ef29ff9ca581c1ac53992a2a6cfebc119` |
| `commerce-account-navigation-1.0.0.zip` | 1.0.0 | `ea8e7395b3b2c49bf80153aeab4d63158788429098db886f3ac573bca1538673` |
| `commerce-lookbook-1.0.0.zip` | 1.0.0 | `e95e6c0ae7e88d7a7c731b21add2b64dae1f230cca61cd87cd4bb5ba4ef512b4` |

WordPress 7.1.3 y WooCommerce 11.2.1 son la referencia validada; WooCommerce procede de su distribución oficial, no de una copia de plugin runtime. Verificar checksums nativos y revisar avisos de seguridad al momento del despliegue. Una versión nueva exige QA focalizada antes de exposición. No instalar Mercado Pago, SMTP, analítica, marketing ni otros gateways. El modo público restringe HTTP saliente WordPress: todas las descargas/instalaciones se completan en mantenimiento privado antes de activarlo.

## 2. Crear sólo los dos destinos aprobados y mantenerlos cerrados

Ejecutar primero para **una demo**. En hPanel → Websites, seleccionar el Premium correcto → Crear sitio → WordPress limpio o Custom PHP/HTML vacío para instalación manual. Introducir **el subdominio aprobado como web independiente**. No clonar ni elegir «copiar/migrar sitio», Builder o multisite; no añadir una subcarpeta al portfolio. Si el asistente obliga a instalar extras o cambiar PHP del plan, detenerse y revisar el método manual. Repetir para la segunda sólo después de la admisión gradual. [Alta de web](https://www.hostinger.com/support/1583214-how-to-add-a-website-in-hostinger/).

Antes de instalar contenido, cerrar el nuevo document root con protección HTTP del servidor. Probar sin credenciales desde otro navegador que Home, `/wp-login.php`, `/wp-admin/`, REST y medios responden 401/403. Evitar dominios temporales alternativos desprotegidos y desactivar accesos por alias/IP que salteen el cierre. Mantener este bloqueo hasta terminar QA y recibir aprobación de apertura. [Protección de directorios Hostinger](https://www.hostinger.com/support/1583470-how-to-password-protect-a-website-in-hostinger/).

Registrar la raíz absoluta que devuelve hPanel/SSH, por ejemplo bajo el directorio asignado al sitio; **no adoptar una ruta ilustrativa como real**. Comprobar su `realpath`, que no coincide con ninguna raíz existente y que no contiene symlinks a ellas. Cada destino tendrá su WordPress, `wp-content/uploads`, configuración, salts y administrador propios. No usar redirecciones ni aparcar ambos nombres en una sola instalación.

La guía pública admite dos modalidades de subdominio; la independiente es la que se selecciona aquí. Si el dominio utiliza nameservers Hostinger, el alta puede crear DNS automáticamente; si son externos hará falta un registro del host nuevo en el proveedor correspondiente. **La creación puede tener efectos DNS sin un paso separado**, por eso debe estar aprobada. No cambiar nameservers, registros del apex, `www`, MX, SPF/DKIM ni otros sitios. [Subdominios y conexión](https://www.hostinger.com/support/1583405-how-to-create-and-delete-subdomains-in-hostinger/).

## 3. Bases, WordPress, SSL y permisos

1. hPanel → Dashboard **de la demo seleccionada** → Databases → Management: crear DB y usuario nuevos con contraseña aleatoria independiente, o verificar los que creó el instalador. Guardar fuera de Git los nombres reales y comprobar que no están asociados a una web existente. No reutilizar una DB con prefijo distinto como sustituto de aislamiento. [Crear DB oficial](https://www.hostinger.com/support/1583542-how-to-create-a-new-mysql-database-in-hostinger/).
2. Instalar WordPress limpio con admin único no publicado, sin clientes/pedidos. Usar nombre de acceso aleatorio distinto en cada demo, contraseña propia y email reservado terminado en `.test` (exigido por `setup.php`); el operador conserva acceso por hPanel/SSH y no depende de recuperación por email. Configurar URL final HTTPS propia, timezone Argentina y español. Deshabilitar registro (`users_can_register=0`), comentarios/pings si no se usan y avatares externos. No copiar `wp-config.php`/salts de otra tienda.
3. **Antes de instalar WordPress**, crear en el `wp-content/mu-plugins` de la nueva raíz un archivo temporal `000-demo-mail-deny.php` con `<?php add_filter('pre_wp_mail', '__return_false', PHP_INT_MAX);`. Es una barrera de preparación contra `wp_mail`, sin SMTP ni entrega. Conservarla hasta validar el guard público y retirarla sólo bajo mantenimiento privado después de comprobar el short-circuit del módulo. Usar `--skip-email` en instalación WP-CLI. No instalar transportes/servicios de correo ni enviar un email externo «para probar». No cubre código que llame directamente sockets o `mail()`: sólo código confiable y sin integraciones reales.
4. Configurar `DISALLOW_FILE_EDIT=true`, `WP_DEBUG_DISPLAY=false`, logging privado y `FORCE_SSL_ADMIN=true`; limitar permisos a lo necesario (habitualmente directorios 755, archivos 644 y configuración más restrictiva según UID/servidor), sin 777. Denegar ejecución en uploads, listados, dotfiles, `.git`, backups y logs mediante controles validados de ese vhost. Nunca copiar a ciegas la configuración Apache del Docker si el host no admite sus directivas.
5. Verificar certificado en hPanel → SSL para **ese hostname**; Hostinger publica SSL incluido y emisión automática, pero no darlo por instalado hasta probarlo. Conectar sin `-k`/ignorar errores, revisar cadena, SAN, caducidad, renovación y redirect HTTP→HTTPS sin bucle. No activar HSTS `includeSubDomains` en `ramita.dev` ni tocar SSL existente. [SSL oficial](https://www.hostinger.com/support/1575761-how-to-claim-a-free-ssl-that-comes-with-a-hostinger-hosting-plan/).
6. Instalar WooCommerce y los seis paquetes aprobados; activar Commerce Base y plugins. Validar checksums de WooCommerce y versiones. Ejecutar `deployment/preflight.php` fuera del webroot contra **esta raíz**, y comprobar también PHP web de forma privada; retirar toda sonda después. Ejecutar `deployment/initialize.php` sólo sobre la instalación nueva vacía. Sus guardas deben aceptar sin excepciones; genera la línea segura, no el catálogo. No usarlo sobre otras webs.
7. WooCommerce → Ajustes → Avanzado: HPOS habilitado, páginas nativas Carrito/Checkout Blocks/Mi Cuenta asignadas. Ajustar enlaces permanentes a `/%postname%/` y guardar; confirmar categoría/producto/REST y `?rest_route=`. El checkout existe como destino de explicación, nunca como formulario público.

Los ZIP H11 son distribución limpia y excluyen recursos de marca. Para estas demos copiar aparte **sólo los recursos visuales versionados necesarios** desde `commerce-base/assets/images/`, el preset `commerce-base/styles/origen-casa.json` para esa demo y las carpetas `demo` aprobadas, con inventario/hash y destino de medios/theme identificado; no añadir `.local`, todo el repositorio o archivos de QA para completar imágenes faltantes. Los archivos de marca no se incorporan ni regeneran dentro de los ZIP aprobados.

## 4. Preparar el módulo opcional y su activación

Copiar **sólo** `deployment/demo-public/commerce-demo-public.php` a `wp-content/mu-plugins/commerce-demo-public.php`. Un MU plugin carga por el archivo en la raíz de `mu-plugins`; copiar sólo una subcarpeta no lo activa. Revisar su SHA frente al commit D1. No modificar theme/Core/WooCommerce/ZIPs para integrarlo. Definir primero `WP_ENVIRONMENT_TYPE='staging'` en el `wp-config.php` de esta demo, con el flag aún ausente/false, para que el helper valide el entorno. El bloque de configuración final siguiente se completa **sólo después** de la carga de la sección 5 y `setup.php`; no activar `COMMERCE_DEMO_PUBLIC` antes de terminar las descargas/administración privadas.

Con la demo todavía bloqueada externamente, configurar el marcador mediante WP-CLI en la raíz verificada:

```sh
wp --path="<RAIZ_VERIFICADA_DE_ESTA_DEMO>" option update commerce_demo_public_installation public-demo-v1
```

Completar la carga nativa de la sección 5 con flag apagado. Copiar `deployment/demo-public/setup.php` a un directorio privado **fuera del webroot** y ejecutar `wp --path="<RAIZ_VERIFICADA_DE_ESTA_DEMO>" eval-file "<RUTA_PRIVADA>/setup.php"` una vez sobre ese contenido limpio. Requiere entorno `local`/`staging`, marcador, un solo administrador `.test`, cero pedidos/solicitudes/eventos y carpeta Mercado Pago ausente; no desactivar sus guardas para aceptar otro destino. Apaga gateways/emails/registro, elimina datos de ubicación comercial, crea/adapta cinco páginas informativas DEMO y FAQ, y corrige la frase de Home sobre checkout. Después revisar manualmente Contacto, enlaces, bancos, QR y cualquier texto que el helper no administre. El helper actualiza páginas/opciones: no volver a usarlo como rutina sobre contenido editado sin revisar el diff y respaldar.

En el `wp-config.php` exclusivo de esa demo, antes de cargar WordPress, definir una sola vez:

```php
define('WP_ENVIRONMENT_TYPE', 'staging');
define('COMMERCE_DEMO_ALLOWED_HOST', '<hostname-demo-aprobado>'); // Minúsculas, sin esquema, puerto ni barra.
define('COMMERCE_DEMO_PUBLIC', true); // Booleano, no string 'true'.
```

El entorno `staging` identifica la naturaleza DEMO aunque la navegación sea pública; no usar `production` para saltear una protección. Son necesarias las cuatro condiciones: flag explícito, entorno permitido, host exacto y marcador DB. Solicitar activar el flag con configuración incorrecta debe producir **503**, conservando bloqueo de correo/pedidos. No convertir 503 en funcionamiento abierto quitando guardas. Ver [README del módulo](../deployment/demo-public/README.md).

El modo activo bloquea `wp-login`/`wp-admin`, incluso para gestión habitual. Completar la carga de contenido mediante administración privada con flag apagado **antes** de activar; una vez activo, usar SSH/WP-CLI seguro. El módulo impide también guardar pedidos desde CLI; no se necesita crear pedidos para preparar la demo pública. No activar ni desactivar mediante una opción pública o un parámetro URL.

El guard desactiva envíos mediante `wc_shipping_enabled` sólo mientras está activo, para evitar el calculador de dirección en carrito clásico/Blocks. No admitir `cart/update-customer` ni `select-shipping-rate` como excepción. Carrito muestra artículos/precios/descuentos ficticios; promociones editoriales de envío siguen claramente rotuladas como ilustrativas.

## 5. Importar sólo identidad y contenido ficticios

La carga se realiza bajo protección externa y sin correo/pagos. Es una instalación limpia; no importa SQL, una copia completa ni un backup «sanitizado» de las tiendas históricas. La ruta manual nativa evita convertir los seeds H3–H5/H10, limitados a localhost, en instaladores remotos. **No quitar sus guardas ni ejecutar sus scripts remotos.** El laboratorio D1 sirve para consultar el resultado de fixtures versionados, nunca para copiar sus credenciales/DB.

### Fuentes permitidas y mapa de carga

| Área | TRAMA | ORIGEN CASA | Aplicación nativa en la DB destino |
| --- | --- | --- | --- |
| Catálogo | `demo/catalog/h5-catalog.json`; imágenes `demo/catalog/images/`; alt/manifiesto en el mismo directorio | `demo/origen-casa/content.json` → products/categories/attributes; `assets/` | Productos y términos propios; SKU como clave de relación, no ID antiguo |
| Variaciones | Color y Talle de cada producto, `color_codes`; guías `demo/catalog/size-guides.json` | Material/Acabado, Capacidad/Acabado y valores de `attributes` | Atributos globales y términos; combinaciones con precio/stock de la fuente, sin inventar talles para hogar |
| Marca/estilo | Identidad y composiciones versionadas de Commerce Base, contenidos de `docker/tools/h3-demo-content.php` y `h4-demo-content.php` usados **como referencia** | `demo/origen-casa/client.json` → brand; preset `commerce-base/styles/origen-casa.json` | Nombre/logo/favicon por Medios/Identidad; Global Styles propios, sin duplicar theme |
| Home/editorial | Bloques/recursos versionados H4; fotografías locales aprobadas | `content.json` → hero/benefits/editorial/categories | Recrear bloques en Home, actualizar IDs de medios/términos/productos y URLs locales del destino |
| Lookbook | Composición/medios versionados de `docker/tools/h5-1-lookbook.php` como referencia | Composición Lookbook del fixture H10 y sus medios/productos | Bloque Commerce Lookbook; asignar producto local por SKU y medio local, probar cada hotspot |
| Información y legales | Contenido DEMO versionado, con ajuste público mínimo | `content.json` → pages; `client.json` → legal sólo como identidad ficticia | Páginas explicativas DEMO, enlazadas por slug real y opciones nativas Commerce Core; sin recepción de trámites |

**Carga de productos:** cargar primero fotos en Medios de esta instalación, conservar nombre/alt, comprobar que URLs pertenezcan al host demo y retirar metadatos personales. Crear categorías y atributos globales, luego productos simples/variables y cada variación. Se puede usar Productos → Importar CSV con el esquema nativo: `Type`, `SKU`, `Name`, `Published`, `Short description`, `Description`, `Regular price`, `Sale price`, `In stock?`, `Stock`, `Categories`, `Images`, `Attribute N name`, `Attribute N value(s)`, `Attribute N visible`, `Attribute N global` y `Parent` (SKU del padre para filas `variation`). Usar exportación nativa del laboratorio limpio como modelo de columnas/escape UTF-8; no activar «actualizar existentes» en primera carga. Mapear `price→Regular price`, `sale>0→Sale price`, `out_of_stock→In stock?`, `images→URLs locales` y los atributos/variaciones declarados. Revisar manualmente agotados, ofertas, precio de cada variante, galerías, SKU y default seleccionado. [CSV oficial WooCommerce](https://woocommerce.com/document/product-csv-importer-exporter/).

Los IDs de medios, términos, páginas, productos, variaciones y hotspots se resuelven en destino; no reutilizar referencias numéricas. Las opciones propias (`commerce_base_color_swatches`, guías de talle y referencias `_cb_size_guide`, cuidados `_cb_care`, cross-sells por SKU) se cargan únicamente cuando la fuente correspondiente las define. Los bloques serializados se editan mediante WordPress; no hacer sustituciones globales sobre SQL. Publicar Home y asignarla en Lectura; construir menús con páginas/términos locales y revisar enlaces de footer. Mantener las dos identidades sin rediseñar.

### Cambios mínimos de copy y opciones obligatorios para exposición pública

Los fixtures locales antiguos describen pruebas de BACS, pedidos, Mailpit, registro y recepción legal. **No copiarlos literalmente al público.** En cada demo:

- Contacto: «Esta tienda es una demostración y no recibe consultas ni operaciones comerciales. No ingreses datos personales». Retirar `mailto`, WhatsApp, teléfonos, banco, QR fiscal y canales operativos; conservar sólo identidad ficticia y navegación.
- FAQ/Términos: sustituir afirmaciones de BACS o confirmación de pedido por «Podés explorar productos y carrito. El checkout y la creación de pedidos están bloqueados». No pedir direcciones ni recomendar usar emails `.test` en un formulario público.
- Envíos: conservar importes ilustrativos del catálogo si hacen falta para la presentación, rotulados DEMO; aclarar «No se realizan despachos ni se calculan envíos para datos de destino de visitantes». No afirmar que un checkout calculará cobertura real.
- Privacidad: explicar sesiones/carrito y logs técnicos efectivamente usados, ausencia de cuentas/pedidos/forms/marketing y datos ficticios. Quitar afirmaciones de Mailpit local en un hosting y las invitaciones a enviar datos de prueba. Completar información del responsable del sitio de presentación según revisión pertinente antes de abrir acceso.
- Cambios/devoluciones/Arrepentimiento: mantener enlace visible con explicación «Demo: no se reciben solicitudes reales y no hay compras realizadas en este sitio». El módulo elimina el formulario y rechaza también el envío directo. Conservar Commerce Core legal original en código para futuras tiendas reales.
- Aviso global: comprobar el texto DEMO en Home, catálogo, ficha y carrito, desktop/mobile/claro/oscuro, sin popup reiterado ni solapamientos.

Deshabilitar en opciones **todos** los gateways, incluido BACS; vaciar cuentas bancarias; quitar claves webhooks/API/application passwords que no sean necesarias (en una instalación nueva no debería haberlas). `users_can_register=0`, registro Mi Cuenta y checkout en `no`, restablecimiento bloqueado por módulo. Moneda ARS, precios ficticios, HPOS y Blocks permanecen. Deshabilitar atribución de campaña y coming-soon sólo cuando los bloqueos DEMO estén listos; `blog_public=0`. No configurar SMTP, Mercado Pago, ventas ni correos reales. La defensa del módulo no reemplaza este baseline seguro.

## 6. SEO, caché, cron y mantenimiento

Verificar en cada host final y después de la caché: meta robots y `X-Robots-Tag` con `noindex, nofollow`; sitemaps desactivados/no anunciados; sin JSON-LD de ofertas/negocio comprable; canonical propio coherente, sin URLs localhost, del otro demo o portfolio. Sin Search Console ni inclusión en sitemap principal. No aplicar reglas en la raíz del dominio madre. Para archivos estáticos, añadir el header desde el vhost demo usando directivas soportadas por el host y comprobar una imagen directamente.

`robots.txt` no sustituye noindex: permitir que el crawler lea la respuesta noindex en páginas públicas y no anunciar sitemaps. Si una opción devuelve `Disallow: /`, no afirmar que eso garantiza desindexación. Para privado, la defensa principal es autenticación de servidor en todas las rutas. [Google: noindex y rastreo](https://developers.google.com/search/docs/crawling-indexing/block-indexing).

Cachear sólo Home/catálogo/fichas tras comprobar ausencia de datos de sesión. Excluir carrito, APIs de carrito, cualquier `wc-ajax`/mutación, cuenta/checkout/bloqueos y rutas con cookies WooCommerce. La política pública del módulo puede exigir `no-store`; respetarla y medir si limita el ahorro esperado. Probar A/B que carrito/token/cookies no se mezclan y que la caché no sirve páginas antiguas de checkout tras activar el módulo. Purgar **sólo la demo** al cambiar modo/código. No activar CDN o caché global de la cuenta como parte de este runbook.

Evitar cron por visita si el host ofrece tarea real fiable. Configurar una tarea PHP exclusiva de la demo, con ruta/binario verificados, por ejemplo cada 15 minutos, escalonando ambas para no competir; confirmar ejecución y limpieza de sesiones/Action Scheduler antes de `DISABLE_WP_CRON=true`. No deshabilitar cron sin reemplazo ni ejecutar colas de sitios existentes. Hostinger permite definir comando y horario en Cron Jobs; la programación usa UTC. [Cron oficial](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/).

El modo activo rechaza HTTP saliente WordPress: no confiar en actualización o chequeos de versiones automáticos. Para actualizar: cerrar externamente todo el host demo, verificar 401/403, apagar flag temporalmente sin quitar baseline sin pagos/correo, instalar código revisado en ventana privada, reactivar flag, purgar caché, repetir QA y sólo después abrir. El marcador/nombre de host no son secretos; salts, usuarios y claves sí. No quitar el módulo públicamente para actualizar.

## 7. QA de aceptación bajo acceso restringido

Registrar resultados sanitizados por sitio y dos contextos anónimos separados. Conservar tokens/cookies/credenciales sólo en almacenamiento privado; screenshots sin datos de operador. Los tests automatizados D1 de localhost rechazan otros orígenes por diseño: **no cambiar sus guardas para apuntarlos a internet**. En la etapa remota usar el siguiente checklist de HTTP/UI dirigido únicamente a destinos aprobados, con acceso controlado y sin datos personales.

| Prueba | Resultado exigido |
| --- | --- |
| Home/categoría/búsqueda/filtro/ficha/Lookbook | Contenido correcto de esa marca, fotos locales sin roturas ni recursos MP, navegación mobile/desktop |
| Variaciones | TRAMA Color/Talle; ORIGEN atributos propios; precio/disponibilidad nativos y SKU correctos |
| Carrito A | Añadir simple y variable, cambiar cantidades, quitar, mini-cart y carrito Block sincronizados; sin pedir dirección/email |
| Carrito B / otra demo | Vacío inicialmente, después contenido propio; no hereda sesión ni carrito A |
| Checkout visible y URL directa | Aviso claro y respuesta de bloqueo; nunca formulario de compra ni datos bancarios |
| Store API | `GET /wc/store/v1/products` y operaciones necesarias de carrito disponibles; GET/POST/PUT checkout/order rechazados **antes de persistir** |
| Bypass de rutas | Probar pretty REST y `?rest_route=`, `wc-ajax=checkout`, `wc-api`, order-pay, aliases/namespaces admitidos por versión; lotes `/batch` no permiten envolver un checkout |
| Cuenta y trámites | Registro/restablecimiento/Mi Cuenta operativa denegados; legal sin form, POST directo rechazado; XML-RPC/login/admin no accesibles al público |
| REST privado y archivos | Usuarios/pedidos/administración y subida requieren bloqueo/permiso; config/logs/.git/backups/índices y PHP en uploads inaccesibles |
| Correo | Ejercitar `wp_mail` con destinatario reservado `.invalid` dentro de la prueba controlada y confirmar short-circuit, sin invocar transporte ni enviar; no un email externo real |
| Conteos | Antes/después de intentos: 0 pedidos/checkout-draft (HPOS y legado), 0 clientes nuevos, 0 solicitudes/eventos legales; sólo administrador propio |
| Mercado Pago | Plugin/carpeta ausentes, gateways vacíos, ningún SDK/recurso/request externo del plugin en Home/ficha/carrito/bloqueo |
| SEO | Noindex/nofollow cabecera/meta, sitemaps inactivos, sin Offer/Product comprable, canonical propio o política de supresión; imagen directa con header servidor |
| Privacidad | Sólo contenido fuente aprobado; sin PII histórica, claves, teléfonos/cuentas fiscales/bancarias ni exports/logs/runtime; revisar HTML, REST de catálogo y medios |
| Estado del módulo | Host exacto, entorno staging, marcador y flag correctos; configuración inválida falla cerrado. Prueba off sólo en laboratorio local o mantenimiento privado |
| Recursos | Sin nuevos límites/5xx/colas ni degradación atribuible en las webs actuales; ambas demos estables bajo uso representativo moderado |

Tras cualquier fallo de seguridad mantener protección externa y corregir; un botón oculto no es PASS. Las operaciones públicas de carro crean sesiones, **no pedidos**. Comparar conteos de pedidos antes y después prueba ausencia de persistencia incluso cuando una respuesta HTTP parece bloqueada.

## 8. Backups, recuperación y retirada

Antes de abrir, crear backup de **esa nueva demo** (archivos/configuración y DB) con secretos cifrados fuera del webroot/cuenta, manifest SHA-256 y propietario/destino claros. Verificar la restauración en un entorno aislado de esa demo, con correo/pagos bloqueados, y registrar RPO/RTO medidos. Los fixtures reproducen catálogo pero no sustituyen configuración privada. Comprobar frecuencia/retención legacy, no asumir diario ni comprarlo. [Backups Hostinger](https://www.hostinger.com/support/5981435-how-to-download-backups-at-hostinger/).

Ante fallo de instalación: conservar estado y logs privados, cerrar la demo y corregir el paso; no sobrescribir una DB ajena para «empezar de nuevo». Ante degradación del plan: restringir/detener primero la demo incorporada más recientemente, suspender sólo su cron, preservar evidencia y comprobar recuperación de las webs actuales. No modificar recursos o PHP global ni restaurar toda la cuenta.

Ante fallo tras actualización: cerrar externamente, preservar copia del estado actual, identificar backup propio anterior y reponer únicamente código/DB/medios de esa demo, conservando credenciales/salts específicos y verificando URLs serializadas de forma soportada. Repetir bloqueos, conteos, carrito y SEO antes de abrir. Si una restauración de hPanel no permite selección segura por sitio/DB, no usarla para este incidente. El proveedor documenta restauraciones parciales; hay que comprobar su alcance real en el panel. [Restore oficial](https://support.hostinger.com/en/articles/4283700-how-to-restore-backups-at-hostinger).

Ante compromiso: restringir la demo, preservar evidencia, rotar sus secretos y revisar posibles accesos cruzados con el proveedor antes de reabrir; restaurar un backup no demuestra eliminación de la causa. Ante fin de uso: restringir primero y retirar después **sólo los recursos listados como creados para la demo**, con aprobación para borrados; nada de reset de cuenta, wildcard de directorios, `docker system prune` ni limpieza de volúmenes históricos.

## 9. GO / NO-GO y acciones manuales

Todos los puntos son obligatorios para **abrir la demo correspondiente**. D1 entrega la preparación; no marca como hechos controles remotos aún no ejecutados.

- [ ] Autorización del titular para ejecutar y abrir, nombres/cuenta/alcance/costes aprobados.
- [ ] Inventario, raíces/DB/destinos nuevos comprobados; cupo y margen medidos; webs actuales sin riesgo operativo inaceptable.
- [ ] PHP web/DB/extensiones/memoria/rewrite/HTTPS compatibles sin cambiar sitios existentes.
- [ ] SSL válido del hostname, alias cerrados y protección externa mantenida durante instalación.
- [ ] Paquetes H11 intactos y verificados; módulo D1 separado, commit revisado; no MP/SMTP/servicios reales.
- [ ] Contenido/medios cargados desde fuentes DEMO limpias y referencias remapeadas; copy contradictorio corregido.
- [ ] Usuarios/DB/salts/uploads/configuración propios; sin historial privado ni credenciales publicadas.
- [ ] Cuatro condiciones del modo activo verificadas; aviso desktop/mobile/claro/oscuro.
- [ ] Carrito/variaciones funcionan; checkout y bypass API/lotes bloqueados; conteos cero tras intentos.
- [ ] Sin registro, recuperación, recepción legal, emails externos ni recursos de pago.
- [ ] Noindex/meta/header/medios y política de sitemaps/canonical/estructurados comprobados tras caché.
- [ ] Caché no mezcla sesiones; cron/colas limpios; PHP/uploads/archivos sensibles y administración restringidos.
- [ ] Backup y recuperación propios probados; mantenimiento, monitorización y responsable asignados.
- [ ] Primera demo admitida gradualmente; segunda sólo después de reevaluar recursos; ambas sin degradación reproducible.
- [ ] Revisión final y aprobación explícita para quitar protección externa y compartir enlaces.

**NO-GO:** cualquier bloqueo de compra salteable, pedido/draft/cuenta/trámite creado por visitante, correo o recurso de pago externo, contenido privado, ausencia de noindex, acceso administrativo público, TLS inválido, destino/aislamiento ambiguo o capacidad insuficiente. Mantener acceso restringido y llevar la evidencia al titular; no compensar con un upgrade automático o con una advertencia visual.

Acciones manuales pendientes del operador/titular: aportar lecturas de hPanel y condiciones legacy; aprobar hostnames y cambios limitados; definir acceso/admin/mantenimiento y presupuesto de trabajo; cargar o revisar contenido nativo; validar política de privacidad de la presentación; comprobar SSL, caché, cron y backups reales; ejecutar QA remoto autorizado y tomar la decisión GO/NO-GO. Ninguna de estas acciones se ejecutó sobre Hostinger en D1.

**PUBLIC DEPLOYMENT: NOT PERFORMED.**
