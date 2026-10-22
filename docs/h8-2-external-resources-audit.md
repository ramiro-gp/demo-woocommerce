# H8.2 — Recursos externos y almacenamiento

Auditoría del 21/10/2026 (Argentina). Alcance: plugin instalado Mercado Pago **8.9.6**, WooCommerce 11.2.1 y WordPress 7.1.3. No hubo pagos, modificación del plugin oficial ni cambios de credenciales/configuración de gateways. Pro permanece deshabilitado.

## Evidencia y método

Chromium real, contextos independientes y llamadas externas interceptadas antes de continuarlas. [Reporte de navegador](evidence/h8-2-final/browser-audit.json) registra host, ruta, método y nombres de parámetros, sin valores de credenciales ni cuerpos personales. Los hashes de avatares están redactados. [Hashes de fuente instalada](evidence/h8-2-final/mp-source-hashes.json) fijan los archivos inspeccionados.

Se observaron cargas en **carrito ID8 y checkout ID9** con productos. En Home, formulario legal, privacidad, catálogo y ficha de producto probados no aparecieron estos recursos MP. El código amplía la elegibilidad a páginas de pago, confirmación, agregar medio de pago y ver pedido; eso se determinó por fuente, sin abrir pedidos de otros consumidores. No se afirma que todos esos contextos se hayan ejecutado.

El [repositorio oficial](https://github.com/mercadopago/cart-woocommerce) y [su módulo Scripts](https://github.com/mercadopago/cart-woocommerce/blob/main/src/Hooks/Scripts.php) son referencias primarias de contexto. Los enlaces remotos a tags 8.9.6 no pudieron recuperarse con el navegador de investigación; la evidencia específica de versión es el paquete instalado y sus hashes, no una presunción de igualdad con `main` remoto.

## Qué se carga y por qué

| Recurso observado | Origen en el plugin instalado | Finalidad/datos identificados |
| --- | --- | --- |
| `sdk.mercadopago.com/js/v2` | `BasicGateway::registerCheckoutScripts`, dependencia `wc_mercadopago_sdk`; `Hooks/Scripts.php:265` la incorpora a Melidata si está registrada | SDK de pagos. No se ejecutó el recurso externo ni se habilitó un cobro |
| `www.mercadopago.com/v2/security.js` | `assets/js/session.min.js`, mediante creación de un script al cargar la página | Componente de seguridad/sesión; su carga comunica al menos datos normales de conexión. El contenido externo no fue ejecutado por QA |
| `http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts/behavior-tracking.min.js` | `assets/js/scripts/mp-behavior-tracking.min.js`, registrado desde `AbstractGateway::loadMpWooCommerceScripts` | Seguimiento de comportamiento del plugin. Se observó el intento de carga; no se ejecutó su código remoto |
| `/scripts/config/super-token-variants.js` y `/scripts/v1/super-token.bundle.min.{js,css}` en `http2.mlstatic.com` | `assets/js/checkouts/super-token-loader.min.js` | Configuración A/B y recursos SuperToken; fallback a variante predeterminada aun cuando falla la carga de configuración |
| `api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/*` | Loaders de comportamiento, SuperToken y monitor de salud | Beacons de carga/errores/variante. Campos en código: valor, mensaje, versión de plugin, plataforma/versión/tema, URL origen+ruta y, según emisor, site_id, entorno y cust_id del vendedor. No se publican los valores |
| `http2.mlstatic.com/storage/v1/plugins/melidata/woocommerce.min.js` | `Hooks/Scripts.php:260–337`, `assets/js/melidata/melidata-client.min.js` | Registrado en fuente. Su carga difiere según inicialización/tiempo de Blocks; no está presente en la captura focalizada como descarga efectuada. El manejador de error puede enviar ruta **y query string** a `/v1/plugins/melidata/errors` |

`AbstractGateway::__construct` llama a `loadMelidataStoreScripts()` y `loadMpWooCommerceScripts()` después de inicializar opciones, sin condicionar esos registros a `enabled=yes`. `AbstractBlock::__construct` también prioriza Melidata. `Scripts::isPaymentsRelatedPage()` controla la página, no la habilitación del gateway. Hay verificaciones de habilitación en otras rutas, pero no cubren estas cargas. Esa es la causa de que deshabilitar Pro no las elimine.

**Límite de observación:** algunos beacons son la reacción del propio plugin al bloqueo de red del runner. No se presentan como transferencia exitosa ni como prueba de su frecuencia en navegación normal. Por la fuente, un script remoto ejecutado podría acceder al contexto de la página; eso es una capacidad potencial, no una afirmación de captura de tarjetas o datos concretos. IP, user-agent, referente y cookies dependen de navegador, servidor y políticas efectivas; requieren evaluación antes de producción.

## Cookies y configuración soportada

El [inventario previo](evidence/h8-2-final/storage-inventory.json) guarda nombres y características, nunca valores. Detectó sesiones/carrito WooCommerce; `sbjs_*` de atribución; `mp_st_variant` con vencimiento aproximado de 2 horas; claves de sesión `_mp_flow_id` y `_mp_public_key`; claves StoreAPI y soporte de emojis de WordPress.

**WooCommerce:** la atribución de campañas estaba en `yes`. No hay un caso real de campañas en esta demo. Se aplicó la [opción oficial de atribución](https://woocommerce.com/document/order-attribution-tracking/), `woocommerce_feature_order_attribution_enabled=no`, disponible en Ajustes → Avanzado → Características. En contexto nuevo desaparecieron `sbjs_*` y SourceBuster, con BACS y términos nativos funcionando. [Verificación](evidence/h8-2-final/privacy-setting-verification.json). `woocommerce_allow_tracking` ya estaba en `no` y se conservó. No se borró metadata histórica de pedidos ni se afirmó borrar cookies antiguas de navegadores existentes.

**Mercado Pago:** no se encontró una opción específica y soportada para impedir todas estas cargas manteniendo activo el plugin 8.9.6 y su configuración de pruebas. Se revisaron constructores, registro de scripts, configuración Store y campos de gateways. Test mode, debug y `enabled=no` no son controles de consentimiento ni detienen los registros descritos. La cookie `mp_st_variant` continúa apareciendo tras desactivar la atribución de WooCommerce.

No se alteró el plugin oficial, no se inyectaron dequeue/patches de pagos y no se desactivó globalmente el plugin en la tienda principal. Desactivar el plugin completo es una posibilidad operativa cuando no se usa, pero cambiaría el entorno H7.1 y exige decidir la operación futura; no se adoptó como una supuesta corrección granular.

**Bloqueo productivo explícito:** resolver la carga de SDK, comportamiento, A/B y telemetría con una solución soportada o una decisión revisada sobre instalación/consentimiento antes de publicar. Revisar finalidades, datos, destinatarios, retención y transferencias, además de los bloqueos H7.1 de autenticidad, replay, temporalidad, validación y compatibilidad. El texto de privacidad informa el hallazgo, pero no legitima por sí mismo estos tratamientos.

WordPress también solicitó Gravatar y un recurso de emojis `s.w.org` durante QA. Son servicios existentes, no añadidos por H8.2. Gravatar queda declarado y los datos de conexión deben revisarse al adaptar un comercio. No se instaló un banner de cookies ni un CAPTCHA externo como sustituto de esta evaluación.
