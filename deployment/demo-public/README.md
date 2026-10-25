# Commerce Public Demo Guard — D1

Módulo MU opcional, independiente de la marca. Se instala **sólo en una instalación limpia destinada a demostración**. No modifica WooCommerce, Commerce Core, Commerce Base ni los seis ZIP aprobados en H11. No forma parte de una nueva distribución comercial.

## Activación explícita

1. Mantener la instalación cerrada al público a nivel del hosting mientras se prepara. Crear WordPress/DB/uploads/administrador nuevos; importar únicamente fixtures revisados. Mercado Pago debe estar ausente (preferido) o completamente inactivo.
2. Copiar únicamente `commerce-demo-public.php` de este directorio a `wp-content/mu-plugins/commerce-demo-public.php`.
3. Con el flag todavía ausente/false, en esa DB limpia: `wp option update commerce_demo_public_installation public-demo-v1`. Este marcador no autoriza borrar o transformar una tienda existente.
4. Añadir a `wp-config.php`, antes de cargar WordPress:

   ```php
   define('WP_ENVIRONMENT_TYPE', 'staging');
   define('COMMERCE_DEMO_PUBLIC', true);
   define('COMMERCE_DEMO_ALLOWED_HOST', 'trama-demo.ramita.dev'); // candidato: no creado ni aprobado
   define('DISALLOW_FILE_EDIT', true);
   define('DISABLE_WP_CRON', true);
   ```

   Cada instalación tiene su hostname exacto y configuración propia. En el laboratorio se usa `local` + `localhost`. No se aceptan esquemas, puertos, comodines o alias de hostname. La opción de DB, hostname de `home_url`, Host HTTP y entorno `local`/`staging` deben coincidir. No usar `production` para una demo pública: el tipo `staging` identifica su finalidad, aunque tenga un enlace accesible desde internet.

5. Validar por HTTP y CLI antes de retirar la protección de preparación. Una configuración habilitada incorrecta responde 503. No se permite publicar con un 503 pendiente ni usar el fallo como sustituto de un control de acceso del hosting.

Sin `COMMERCE_DEMO_PUBLIC === true` no registra hooks ni cambia opciones. La desactivación recupera las funciones comerciales originales; las opciones persistentes de una fixture DEMO, por ejemplo gateways apagados/registro deshabilitado, siguen apagadas hasta reconfigurarlas expresamente. **Nunca desactivar el guard en una URL pública para mostrar un checkout.** Primero restringir acceso desde el servidor; usar un laboratorio desechable para pruebas de restauración funcional.

## Política aplicada en el servidor

- Catálogo, búsqueda, filtros, fotografías, variaciones, carrito, cantidades, eliminación y cupones: disponibles. No cambia `is_purchasable`.
- Checkout normal y directo, `order-pay`, confirmaciones y `wc-api`: 403 con explicación. WC AJAX sólo permite las acciones concretas de catálogo/carrito revisadas.
- REST: sólo lecturas públicas revisadas de Core, catálogo Store API y operaciones concretas del carrito. Checkout se bloquea también en GET, evitando drafts. Los batches sólo pueden contener operaciones permitidas; los batches anidados, rutas desconocidas y escrituras a administración/cuentas/pedidos se rechazan. Las altas, modificaciones y pagos de pedidos tienen además barrera en los hooks oficiales de WooCommerce antes de persistir, tanto HPOS como CPT, incluso con CLI/cron/administrador. La única excepción adicional es `OPTIONS /wc/v2/system_status/tools` **dentro de WP-CLI**, para que el puente nativo registre el esquema de sus comandos; no permite ejecutar herramientas ni abre esa ruta por HTTP.
- `cart/update-customer`, extensiones arbitrarias del carrito y APIs de listas de compradores: cerrados. No se pide dirección, email ni información bancaria para simular una compra. El filtro oficial `wc_shipping_enabled` desactiva envíos/cálculo de dirección sólo mientras está activo el guard, tanto para Blocks como para el carrito clásico; las promociones editoriales de envío se identifican como ilustrativas. Los productos permanecen comprables para el carrito. El texto introductorio del carrito aclara que no se pueden finalizar compras.
- `wp-login`, `wp-admin` (incluido admin-ajax/admin-post), alta/recuperación/cambios de cuenta, XML-RPC, cron HTTP y formularios públicos ajenos al carrito: cerrados. No hay acceso administrativo web mientras el guard está activo. Mantenimiento por CLI/hPanel con protección externa durante cualquier ventana que requiera desactivarlo.
- El formulario legal y la identidad comercial se sustituyen por una explicación. Se elimina únicamente el handler HTTP de recepción de Core durante este modo. La implementación original queda intacta para tiendas reales; las páginas legales reciben una aclaración de demostración. En `staging`, sólo las páginas designadas en la configuración legal que el helper haya marcado `_commerce_public_demo_reviewed=1` pueden superar temporalmente la guarda de Core para fixtures históricas. La configuración DEMO debe ser válida y el guard estar activo; el marcador original de Core permanece en DB y vuelve a aplicarse al apagar el modo. No se habilita la recepción de solicitudes.
- `wp_mail` devuelve false antes del transporte; no se simula una entrega exitosa. También se rechaza toda salida HTTP mediante la API de WordPress, incluyendo webhooks y actualizaciones automáticas. No cubre código ajeno que use `mail()`, sockets, cURL directo o servicios del sistema: no se deben instalar transportes ni integraciones externas. El hosting debe aplicar límites de salida y mantenerse sin credenciales/transporte de email; el laboratorio no tiene relé de correo.
- Si Mercado Pago aparece en la lista de plugins activos, la demo responde 503. Esto **no reemplaza** su ausencia/desactivación y la inspección de recursos en navegador; los archivos del plugin no se distribuyen con estas demos.
- `noindex,nofollow,noarchive` en HTML/HTTP/REST, lectura pública forzada a noindex, sitemaps nativos apagados y JSON-LD comercial WooCommerce desactivado. `robots.txt` permite que el rastreador lea el noindex. Los canonicals de Commerce Base siguen apuntando a la propia demo; no se canonicaliza contenido ficticio al portfolio. No instalar otro proveedor SEO sin revisar esta política.
- Aviso permanente discreto al pie, con tokens visuales de Commerce Base; no usa popup ni scripts. Padding inferior evita tapar el contenido. El checkout muestra una respuesta de bloqueo; no solicita datos. Sólo durante este modo, los bloques de header/footer aclaran la demostración y sustituyen la ficha comercial vacía por una nota breve, conservando sus enlaces y el diseño original. Se conserva Unicode nativo y se desactiva su sustitución por imágenes de emoji desde un CDN externo.

## Límites y mantenimiento

Esto reduce la superficie funcional de una **instalación de código confiable y datos ficticios**. No es un sandbox PHP ni una frontera de aislamiento entre cuentas del hosting, ni protege ante credenciales de servidor comprometidas. Archivos estáticos, uploads, logs, backups, versiones, permisos, SSL y acceso a directorios sensibles requieren configuración del servidor según el runbook. `noindex` no constituye autenticación.

No instalar plugins desconocidos o una integración de email/pagos. No subir archivos PHP en uploads. Proteger logs, respaldos y secretos fuera del document root. Los archivos ajenos que no arrancan WordPress no ejecutan este guard.

Mientras está activo se bloquean también las descargas de actualizaciones vía HTTP de WordPress y el cron HTTP. El responsable debe programar mantenimiento: restringir acceso externo, respaldar y verificar restauración, desactivar temporalmente el flag sólo dentro de esa restricción, actualizar con fuentes oficiales, reactivar, ejecutar QA de catálogo/carrito/bloqueos y recién después reabrir la demo. No modificar `ramita.dev` ni sitios de clientes para realizarlo.

Para una demo privada guiada futura utilizar otra instalación desechable protegida a nivel servidor, usuarios ficticios y sin servicios reales. D1 no implementa ese sistema.
