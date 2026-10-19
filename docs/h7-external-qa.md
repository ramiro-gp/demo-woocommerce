# H7.1 — Prueba externa con cuentas ficticias

Sesión del 18/10/2026 sobre la instancia principal, rama `codex/h7-1-payments`. Complementa la [implementación](h7-1-payments.md), la [guía reproducible](h7-payment-testing.md) y la [auditoría](h7-mp-audit.md). No habilita producción ni comienza H7.2.

**Cierre 19/10/2026:** evidencia histórica aprobada para desarrollo, preservada sin nuevos pagos externos. Los límites de autenticidad, replay, tiempo, validaciones independientes y compatibilidad siguen bloqueando producción. El cierre posterior de Git se documenta en H7.1; este informe conserva los resultados originales y sus correcciones auditadas.

## Entorno y protección

WooCommerce 11.2.1, Mercado Pago oficial 8.9.6, WordPress 7.1.3 y PHP 8.4.26. HPOS, invitado y Checkout Blocks conservados. Ambos plugins volvieron a pasar checksums oficiales estrictos (2/2).

El usuario completó el onboarding oficial del vendedor. Antes de habilitar Pro se contrastaron mediante el lector autenticado: nickname esperado, etiqueta `test_user` y país `MLA`. El comprador de prueba argentino fue distinto del vendedor; se utilizaron sus accesos privados existentes, sin copiar credenciales desde H2 a la tienda principal. No hubo cuentas reales, tarjetas personales, transferencias, devoluciones ni reembolsos.

Backup nuevo `h7-1-before-external-tests-20261018`, con base/volumen y hashes verificados. Cloudflare Quick Tunnel sirvió la principal con TLS válido; su hostname permaneció constante durante la prueba. El overlay usa `LOCAL_SITE_URL`/`WP_HOME`/`WP_SITEURL`: no escribe URLs temporales en base ni contenido. Portada HTTP 200, login/administrador/XML-RPC HTTP 403, Store API y 22 enlaces en HTTPS del mismo origen, cabecera noindex. Base, Mailpit y métricas permanecieron en loopback/red Docker.

El primer arranque falló porque la imagen no cargaba `mod_headers`; el helper cerró el túnel y restauró localhost. Se añadió la carga condicional sólo al overlay y se verificó Apache antes del segundo arranque. El healthcheck público consulta una imagen estática porque login está bloqueado. No se cambió el core ni el plugin.

## Operaciones efectuadas

Todos los importes son ARS. Cada fila corresponde a una intención de prueba deliberadamente distinta, no a duplicados automáticos.

| Pedido | Caso | Total / envío / descuento | Resultado comprobado |
| --- | --- | --- | --- |
| #275 | Simple, abandono | 54.500 / 4.500 / 0 | Sin enviar pago; “Volver a la tienda” canceló el pedido, conservó carrito y no redujo stock |
| #276 | Simple promocionado, APRO | 54.500 / 4.500 / 0 | MP `approved/accredited`; WooCommerce `processing`, fecha de pago y stock simple 8→7; retorno automático y recibo confirmado |
| #277 | Variación S ×2, cupón, OTHE | 98.100 / 4.500 / 10.400 | MP `rejected/cc_rejected_other_reason`; consulta autenticada verificó receptor, comprador distinto y referencia. Antes de volver, WooCommerce seguía pendiente; el retorno nativo de fallo lo canceló y conservó carrito. Sin reducción |
| #278 | Variación S ×2, cupón, CONT y retiro | 93.600 / 0 / 10.400 | MP `in_process/pending_contingency`; callback HTTP 200 y pago asociado antes de volver. Pedido agregado `pending`, sin fecha ni reducción; reserva nativa de dos unidades |
| #279 | Variación S ×2, APRO, envío gratis | 104.000 / 0 / 0 | MP `approved/accredited`; tras dos respuestas 503 llegó reintento externo HTTP 200. WooCommerce pasó automáticamente a `processing` antes del retorno; stock variación 6→4 |
| #280 | BACS adicional, simple y retiro | 50.000 / 0 / 0 | `on-hold`, sin fecha ni transacción, instrucciones demo; reducción nativa simple 7→6; sin transferir dinero |

Los totales coinciden entre Checkout Blocks, CRUD WooCommerce y las consultas autenticadas de pagos. Se conservaron los precios promocionales y la variación S. Dos variaciones suman $104.000 y ofrecen envío gratis; el cupón del 10% baja a $93.600 y restaura el estándar de $4.500, total $98.100. Retiro produce $93.600 sin envío. No se modificó la lógica H6 ni se duplicó el costo de envío.

La primera preferencia se abrió con la sesión vendedora aún activa. Se cambió al comprador mediante el login oficial y se verificó la cuenta antes de enviar un pago. El selector externo devolvió un error transitorio COW00, recuperado por su botón de reintento; esa operación #275 se abandonó sin pagar y las compras siguientes utilizaron preferencias nuevas. No se eludió autenticación ni se utilizó una tarjeta real.

El identificador MP se guarda en los metadatos nativos `_Mercado_Pago_Payment_IDs`; el campo genérico `WC_Order::get_transaction_id()` queda vacío en este flujo porque el proveedor llama `payment_complete()` sin ese argumento. Se distingue esta diferencia del caso #277: allí no apareció identificador nativo asociado al cierre observado; la asociación se comprobó por `external_reference` en el recurso autenticado. No se rellenó ningún campo para fabricar cumplimiento.

## Notificaciones y autenticidad

La versión genera `source_news=ipn` para Pro. Esto no identifica por sí solo la clase receptora: `NotificationFactory` elige IPN si recibe `topic=merchant_order` con esa fuente; Webhooks si recibe `type=payment` y `source_news=webhooks`; en otro caso elige Core y lee el identificador `P-`/`M-` del cuerpo. El pago del proveedor apunta a su puente `eplatforms-core-notification.melisystems.com`; el recurso Core consultado por SDK para #279 contiene como destino el receptor nativo HTTPS de esta sesión y la referencia correcta.

Core vuelve a obtener el recurso con la credencial del vendedor. La consulta de pago contrastó estado, ARS, monto, receptor ficticio, comprador distinto y referencia. El callback del pendiente llegó cuando el comprador seguía en Mercado Pago: no dependió de la redirección. No se añadió un receptor paralelo ni se cambió `source_news` para presentar IPN como Webhooks.

No se encontró HMAC `x-signature` en los manejadores instalados. Consulta autenticada del estado y firma de origen de la solicitud son controles diferentes. La [guía IPN específica de WooCommerce](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/additional-content/your-integrations/notifications/ipn) anuncia su retirada y explica que IPN no permite esa validación con secret. Antes de producción corresponde resolver el mecanismo soportado con Mercado Pago o una versión oficial que documente la migración; H7.1 no parchea el proveedor.

El diagnóstico también detectó un límite del lector nativo `OrderStatus::getNotificationId()`: deduce `M-` sólo si la URL del pago contiene el texto legacy `merchant-order-notification`. En esta sesión el puente actual no lo contiene: la búsqueda `P-` devolvió 404, pero el recurso `M-` derivado del merchant order real existía y devolvió estado, destino y referencia correctos mediante el mismo SDK. No se modificó el plugin; no se confundió esa búsqueda fallida con ausencia del pago ni con falta de autenticación. El mecanismo oficial de sincronización tiene fallback a Payments API; no fue usado para simular recepción automática.

## Reintento externo e idempotencia

Se interrumpió sólo el callback de #279 mediante una bandera local Apache, programando primero su retirada automática a los 90 segundos. No se alteró el receptor PHP. El pago se realizó con retorno automático deshabilitado y el comprador permaneció en Mercado Pago hasta comprobar el estado por CRUD.

| UTC | Registro mínimo | Alcance |
| --- | --- | --- |
| 15:29:55 | POST 200, sin marcador QA | Callback auténtico del pendiente #278 antes de volver |
| 15:35:27 | POST 503, `outage-probe` | Sonda interna de la falla controlada |
| 15:35:38 y 15:35:44 | POST 503, sin marcador QA | Dos intentos entrantes del proveedor durante la indisponibilidad |
| 15:36:05 | POST 400, `restored-probe` | Falla retirada; el receptor nativo vuelve a rechazar el cuerpo malformado |
| 15:55:41 | POST 200, sin marcador QA, `core` | Reintento externo; #279 pasó a `processing`, fecha/ID MP y reducción de dos unidades, sin retorno del comprador |
| 15:56:07 y 15:56:08 | POST 200, `reconstructed`, `core` | Dos solicitudes reconstruidas por QA para #276 con su recurso Core auténtico |

El registro usa `GlobalLog`; la configuración inicial con `CustomLog` global no cubría el virtual host y omitió los eventos tempranos. Se corrigió antes del pendiente y de la prueba de caída. No se deduce ausencia de entrega a partir del archivo inicial vacío. La clasificación sigue las condiciones de `NotificationFactory` y nunca captura cuerpos ni IDs de recursos.

Las dos reconstrucciones consultaron el recurso auténtico mediante el SDK antes de enviarse. Después, #276 conservó exactamente estado, fecha de pago, notas, metadatos reportados, productos e importe; el stock del simple no cambió y no apareció otro pedido. Esto verifica efectos idempotentes del receptor nativo sobre un pago real ficticio. **No acredita el replay byte por byte de la solicitud original**, cuyo cuerpo/historial no se conservó. No fue una simulación oficial del panel ni un mock de pago.

El retorno posterior de #279, la verificación de correo invitado y la recarga conservaron el pedido, stock y fecha. La lectura final encontró seis pedidos deliberados de esta sesión, sin pedidos extra ni segunda reducción. El backoff observado fue aproximadamente veinte minutos; no se convierte la referencia general de quince minutos de IPN en una garantía de esta cola Core.

## Incidencia de sesión pendiente

Mientras #279 esperaba el callback se abrió otro checkout en la misma sesión para probar BACS. Se observó que su método cambió a BACS antes de crear #280, aunque productos, variación, cantidades, envío, descuento y total originales de #279 se conservaron. Se restauró **sólo el método MP de ese pedido propio** mediante CRUD con guardas y nota explícita. No se tocó estado, fecha de pago ni stock: la aprobación posterior provino del callback externo. Los destinatarios permanecieron ficticios y capturados en Mailpit.

La [investigación posterior](h7-concurrency-audit.md) reprodujo la incidencia con un GET aislado (#296) y con la hidratación HTML de una segunda pestaña (#331). Determinó la cadena exacta de reutilización de sesión/Store API, asignación del método por defecto y persistencia al calcular totales. La extensión **Commerce Order Integrity** separa las referencias de sesión antes de REST/hidratación, sin guardar el pedido ni interceptar callbacks. Conserva los reintentos equivalentes y el pago existente autorizado. La repetición real #333 Pro pendiente → #334 BACS en otra pestaña preservó los datos de pago y pedido del primero. No se modificaron core, proveedor ni diseño.

## Retorno, stock y presentación

El recibo de #276 comunica pago confirmado con estado/fecha nativos. El de #278 comunica pago pendiente. Alterar `status` y `collection_status` a `approved` en el retorno de #278 mantuvo ese mensaje y no pagó ni redujo stock. Reload del retorno aprobado mantuvo el mismo pedido y stock. El abandono y el rechazo devolvieron al carrito conservando la selección.

Antes de resolver #279 se observaron cuatro unidades reservadas de la variación (dos del pendiente y dos de la operación aprobada aún sin callback), con stock físico todavía 6. Tras el callback, stock 4 y reserva 2 correspondiente a #278. El simple quedó en 6 por una unidad MP y otra BACS. Se distingue reserva de reducción; no se editó inventario manualmente.

Checkout real con ambos métodos: veinte combinaciones adicionales, anchos 320, 375, 390, 430, 768, 1024, 1366, 1920, 2560 y 3440, en claro y oscuro. Radios nativos presentes, total estable y sin desborde horizontal. El contenido y logos oficiales MP se conservaron; sin tarjetas o medios inventados. La suite local previa ya cubría BACS y recibos en cuarenta combinaciones.

## Cierre y regresión

Se forzó el vencimiento para ejercitar el watchdog. La primera ejecución de cierre descubrió que PowerShell 5.1 convertía stderr de progreso de Docker en excepción al redirigir streams. Se corrigió `Invoke-Compose` para decidir por el exit code nativo tanto al capturar como al transmitir salida. También se utilizó `NullString` explícito en la sustitución atómica del estado, compatible con los dos PowerShell. Se reejecutó el watchdog vencido: salida 0, túnel y watchdog detenidos, Pro deshabilitado y contenedores locales sanos. La portada y login volvieron a HTTP 200 en `localhost:8080`, sin hostname público en la portada. No quedó URL pública activa ni servicio instalado.

El registro protegido `record-linking` pasó: sólo actualizó la huella autorizada MP, sin escribir credenciales ni BACS. Se repitieron **33/33** comprobaciones funcionales H7.1, **7/7** guardas de preservación y **21/21** de envíos H6.2. El test de origen por loopback volvió a aprobar: HTTPS canónico, administrador/login/XML-RPC bloqueados, noindex, Store API ARS y restauración de localhost. Activar Pro desde localhost fue rechazado por el helper antes de habilitarlo. La revisión final del navegador mostró BACS disponible, ningún radio MP y el carrito local previo de tres artículos conservado.

Las suites internas no se presentan como pagos externos. Se preserva además la evidencia browser previa H5/H5.1/H6: catálogo, variaciones, lookbook, mini-cart, contador, cross-sells, cupones, Exit Intent, direcciones y envíos; no se borraron ni cancelaron pedidos BACS anteriores para repetirla. Mailpit conserva los correos de prueba, sin SMTP real ni cambios de comunicaciones H7.2.

## Evidencia reproducible y límites

Reportes/capturas privados bajo `.local/local/h7-1/`: `mp-responsive.json`, `before-pending-return.json`, `rejected-authenticated.json`, `approved-without-return.json`, `during-outage.json`, `stock-during-retry.json`, `external-final-before-return.json`, `external-final-after-return.json`, `reconstructed-result.json`, `return-result.json`, `callbacks-sanitized.log` y capturas de aprobado, rechazo, pendiente, BACS y mobile. Se mantienen privados y no se copian a Git. No compartir claves ni URLs completas de pedidos.

La guía enlazada arriba permite reproducir preparación, preflight, activación, lectura y cierre. No se resetea stock al repetir `prepare`, no se borran pedidos y los productos/cupón permanecen ocultos y restringidos a esta sesión. BACS se preserva mediante comparación interna de sus opciones/cuentas. Los pedidos BACS anteriores #250–#257 y el inventario del catálogo aprobado permanecen conservados.

Una llamada reconstruida por QA, aunque use un recurso auténtico y el receptor oficial, no equivale al reenvío idéntico del evento original por Mercado Pago. Tampoco un 400 de prueba ni un test PHP de estados equivalen a pago externo. La comparación más exhaustiva posterior encontró que el campo `[Date …]` de los metadatos MP cambia por un fallback a la hora de recepción cuando falta fecha en el recurso Core: las proyecciones anteriores verificaban los campos reportados, no esa cadena completa. Se conservan IDs, fecha de pago, estado, importe, productos y stock, pero **no se certifica idempotencia byte por byte de todos los metadatos**. La simulación aislada además reprodujo `cancelled` → `pending` ante un recurso obsoleto; no se certifica orden temporal universal. Evidencia, versiones, alternativa BACS y límites antes de producción en [el cierre auditado](h7-concurrency-audit.md).
