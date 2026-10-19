# H7.1 — Integridad de pedidos y auditoría de notificaciones

Investigación del 18/10/2026 (los reportes nocturnos usan UTC del 19/10). Rama `codex/h7-1-payments`, base `9dfdb49edccf7f90ad5e335dc0361c433bddc8fc`. Alcance exclusivo H7.1; sin merge a main, Commerce Core ni H7.2.

## Causa comprobada

WooCommerce **11.2.1**, Mercado Pago oficial **8.9.6**, WordPress **7.1.3**, PHP **8.4.26**, HPOS y Checkout Blocks. Se conservaron los checksums oficiales de ambos plugins. Los seis archivos centrales usados para explicar sesión, controlador, hidratación, Core y timestamp coincidieron por SHA-256 con los archivos realmente instalados.

No fue necesario enviar un segundo pago para reproducir el cambio. Con un pedido sintético propio pendiente, el mismo carrito y `chosen_payment_method=bacs`, un **GET `/wc/store/v1/checkout`** devolvió el pedido existente y persistió BACS en #296. Se conservaron su total, productos y referencia sintética; también cambiaron IP/agente del comprador. La reproducción no creó preferencias ni pagos MP.

La cadena exacta del core instalado es:

1. `StoreApi/Utilities/DraftOrderTrait::is_valid_draft_order()` admite un pendiente/fallido que necesita pago cuando coincide `cart_hash`; obtiene su ID de `store_api_draft_order`.
2. `StoreApi/Routes/V1/Checkout::get_route_response()` reutiliza ese pedido incluso en GET, mediante `create_or_update_draft_order()`.
3. `StoreApi/Utilities/OrderController::update_order_from_cart()` copia datos del carrito/cliente, llama `set_payment_method(PaymentUtils::get_default_payment_method())` y después `calculate_totals()`, que persiste el pedido. El método por defecto proviene de la selección de sesión; en la reproducción fue BACS.
4. El render HTML del Checkout Block tiene otra entrada: `Blocks/BlockTypes/Checkout::enqueue_data()` hidrata cart/checkout. `Blocks/Domain/Services/Hydration::get_response_from_controller()` llama directamente al controlador GET, sin pasar por los filtros REST habituales. Esto reprodujo el cambio en #331 al abrir la segunda pestaña real, incluso después de una primera corrección limitada a REST.

Por tanto, es la reutilización/sincronización nativa de un pendiente en **Store API, sesión e hidratación de Blocks**, comprobada con el plugin oficial instalado. La llamada mutante está en WooCommerce; no se atribuye al procesador de notificaciones de Mercado Pago. No fue un cambio autorizado por el flujo `order-pay`: bastó abrir otra página. No se afirma que otras versiones estén afectadas sin reproducirlas.

## Corrección soportada

Plugin acotado `commerce-order-integrity/commerce-order-integrity.php`, independiente del theme y de Commerce Core. Se monta en ambos servicios de Compose y está activo en la principal. Declara compatibilidad HPOS/Blocks; no registra gateways, endpoints ni receptores de pagos.

Usa `rest_request_before_callbacks`, `woocommerce_load_cart_from_session`, `woocommerce_hydration_dispatch_request` y sus filtros de salida. La hidratación interna tiene su hook propio; no se deshabilita autenticación externa. En REST exige Nonce válido o Cart-Token válido antes de tocar referencias de sesión.

Sólo separa `store_api_draft_order` y, si apunta al mismo ID, `order_awaiting_payment`, cuando el pedido es `store-api`, `pending` y Checkout Pro. Un GET/PATCH o interacción de carrito pasa a un contexto nuevo. No guarda, cancela ni repara el pedido, no borra metadatos, no vacía el carrito y no libera su reserva. De esta forma los callbacks siguen apuntando al pedido original mientras otra compra crea su propio pedido.

Se conserva el POST nativo de reintento con el mismo método y los mismos datos del comprador/nota. Datos diferentes desde una pestaña antigua representan otra compra y separan el contexto. El core conserva además su validación de `cart_hash`. Los fallidos y borradores conservan su comportamiento nativo. `/wc/store/v1/checkout/{id}` y `order-pay` no se interceptan: siguen exigiendo sus permisos nativos (propietario o clave/correo del invitado) y admiten cambiar legítimamente de método.

Esto no añade serialización transaccional ni promete resolver carreras arbitrarias entre POST simultáneos distintos contra un único carrito compartido. El carrito sigue siendo de la sesión; dos pestañas no tienen carritos independientes. Se verifican las compras/reintentos descritos abajo y se conserva el comportamiento nativo de envío del formulario.

## QA y evidencia

Backup íntegro `h7-1-before-concurrency-audit-20261018`. Productos ocultos nuevos #294/#295, SKUs `H71-CONCURRENCY-A/B`, propietarios explícitos, sin reset de stock. Sesiones aleatorias propias y correos `example.test`. Las referencias sintéticas se rotulan `H71-INTERNAL-NOT-A-PAYMENT`; no son transacciones MP. Ningún pedido histórico se borró ni se reparó para ocultar la reproducción. #296 conserva la evidencia inicial; #331 se abandonó por el enlace nativo después de reproducir la entrada HTML.

| Escenario | Evidencia y resultado |
| --- | --- |
| Pendiente → GET/refresh/seleccionar BACS/otra compra | HTTP Store API real, Cart-Token propio. La compra nueva recibe otro ID y el pendiente conserva datos de pedido y pago |
| POST BACS directo desde pestaña antigua | Pedido nuevo; no cambia el pendiente anterior |
| Dos pestañas | GET simultáneos por HTTP, cambio de productos y compra BACS; además dos pestañas reales del mismo IAB bajo HTTPS |
| Dos sesiones independientes | Tokens invitados diferentes; la compra de una conserva ambos pendientes anteriores |
| Abandono y regreso | GET repetidos no materializan pedidos; abandono nativo en navegador cancela sólo el pedido propio impago |
| Pago existente autorizado | Clave incorrecta rechazada; clave/correo correctos permiten BACS en el mismo ID mediante `/checkout/{id}` |
| Reintento fallido | El fallido mantiene su ID al volver a checkout y pagar por BACS |
| Reintento Pro | Preferencias reales oficiales, vendedor ficticio verificado: POST equivalente mantiene ID; comprador distinto crea otro pedido |
| Stock/refresh | Reducciones físicas exactas por los BACS deliberados; Pro sin pago no reduce; recargas no generan pedidos extra ni segunda reducción |
| Callback de un pedido anterior | Reconstrucciones de #276 con recurso auténtico: no cambia los otros cinco pedidos externos ni las compras posteriores; detalle de timestamp abajo |

La prueba real en navegador dejó #333 pendiente en Checkout Pro mientras la segunda pestaña creó #334 BACS `on-hold`, ambos por $54.500. Abrir/recargar el segundo checkout y realizar BACS conservó método MP, transacción, importes, ítems, datos de comprador y metadatos de pago de #333. Se recargó el recibo BACS. Después se abandonó #333 desde “Volver a la tienda”, sin enviar pago. El recibo #334 continúa diciendo pago pendiente de verificación, con instrucciones demo. El carrito original de localhost (tres artículos) queda separado del origen público.

Se distingue la limpieza asíncrona de WooCommerce: `_debug_log_source_pending_deletion` puede cambiar/desaparecer por `Internal/Logging/OrderLogsDeletionProcessor`. La aserción informa esa diferencia cruda, pero no la confunde con un cambio de metadatos de pago. No se ignoran diferencias en IDs, fecha de pago, estado, totales, comprador, ítems o metadatos MP.

Prueba HTTPS adicional con preferencias reales: #335 y #336 Pro, #337 BACS. **14/14** comprobaciones, exactamente tres compras deliberadas y sólo una reducción física por BACS. No se envió ningún pago nuevo; los APRO/OTHE/CONT externos acreditados siguen siendo los del [informe anterior](h7-external-qa.md).

Artefactos privados: `.local/local/h7-1/concurrency/report.json`, `pro-report.json`, `notification-simulation.json`, `authenticated-reconstruction-report.json`, snapshots privados y `browser-bacs-receipt.png`. Tokens, claves de pedido y respuestas completas permanecen ignorados en Git.

Regresión final: **31/31** checks de concurrencia (incluida hidratación y reducción física exacta: cinco A y una B por seis BACS deliberados), **14/14** de preferencias Pro reales, **11/11** aserciones de simulación del receptor con sus limitaciones expuestas, **33/33** funcionales H7.1, **7/7** guardas de preservación y **21/21** de envíos/cupones H6.2. El conteo de la matriz local dio exactamente once pedidos deliberados, sin extras; la hidratación técnica previa se cuenta por separado. La prueba de origen por loopback volvió a aprobar HTTPS canónico, rutas sensibles bloqueadas, noindex, Store API ARS y restauración de localhost. Lint PHP y parsers PowerShell sin errores; checksums oficiales **2/2**. Las suites internas y las preferencias sin pago no se presentan como nuevos APRO/OTHE/CONT externos.

Túnel y watchdog cerrados, Pro deshabilitado al restaurar `http://localhost:8080`, BACS demo disponible y vendedor ficticio vinculado sin imprimir ni copiar claves. La revisión final del checkout local conservó tres artículos y Claro. No hubo pagos reales, reembolsos, borrado de históricos ni cambios visuales. El alcance de concurrencia queda resuelto para los escenarios comprobados; las limitaciones oficiales de notificaciones siguientes siguen bloqueando producción.

## Receptor efectivo 8.9.6

`Transactions/AbstractTransaction::getNotificationUrl()` y `Helpers/NotificationType` siguen construyendo Pro con `source_news=ipn`. El parámetro no basta para determinar el manejador. `NotificationFactory` elige:

| Solicitud | Clase | Consulta del estado |
| --- | --- | --- |
| `topic=merchant_order`, `source_news=ipn` | `IpnNotification` | `GET /merchant_orders/{id}` y `/v1/payments/{id}`, Bearer del vendedor |
| `type=payment`, `source_news=webhooks` | `WebhookNotification` | `GET /v1/payments/{data_id}`, Bearer del vendedor |
| Otras combinaciones del receptor nativo | `CoreNotification` | Cuerpo escalar u objeto `notification_id`, formato `P-`/`M-`; SDK `GET /v1/asgard/notification/{id}` con Bearer del vendedor |

Las entregas externas observadas en H7.1 utilizaron el puente `eplatforms-core-notification.melisystems.com` y Core, aunque el destino nativo conserva `source_news=ipn`. No se cambia esa fuente manualmente ni se lo presenta como Webhooks firmado. `AbstractNotification::handleSuccessfulRequest()` obtiene `external_reference` del recurso consultado, elimina el prefijo configurado `WC-`, resuelve `wc_get_order()` y delega CRUD/estados. Core guarda los IDs MP nativos; la aprobación llama `payment_complete()` sin poblar el transaction ID genérico.

La consulta autenticada evita confiar en un estado declarado por el llamador. **No autentica criptográficamente el origen HTTP.** Los tres manejadores no implementan validación HMAC de `x-signature`/`x-request-id`, ventana temporal ni deduplicación persistente del evento. El manejador tampoco aplica una comprobación independiente de método actual, monto/moneda contra el pedido ni vinculación previa de una preferencia antes de resolver `external_reference`; estas garantías no deben inventarse. En las pruebas se contrastaron receptor ficticio, referencia, importe y ARS mediante lecturas autenticadas adicionales.

De esa ausencia se infiere otro riesgo que requiere conciliación: cambiar legítimamente un pedido a BACS no cancela automáticamente un pago MP que siga en curso; una aprobación posterior puede seguir resolviendo la referencia original. No se ejecutó ese cobro posterior ni se presenta como observado. La corrección de sesión preserva el cambio autorizado; no cancela transacciones ni intenta resolver esa política comercial mediante un bloqueo general.

## Repeticiones y eventos tardíos

Se consultó `notifications_history` para el ID público de la aplicación realmente vinculada. Rechazó por **OAuth ownership validation failed**: la aplicación no pertenece al mismo propietario de la conexión. El panel Developers también requirió verificar cuenta; la sesión de navegador era la del comprador ficticio, no una autorización para administrar la aplicación vinculada a la tienda. No se cambió la aplicación ni se registró otra URL. No hay una herramienta MCP de reenvío/simulación entre las herramientas disponibles. **Replay externo idéntico del evento original: no ejecutado.**

Sí se enviaron pares HTTP con **el mismo cuerpo reconstruido**, marcador `reconstructed` y recurso Core ficticio auténtico de #276; el receptor oficial consultó MP con sus credenciales. HTTP **200/200**. Una comparación completa detectó un defecto que la proyección más acotada anterior no mostraba:

- Método, ID MP, fecha de pago, estado, total, productos, comprador, notas y stock físico permanecen iguales. Los otros cinco pedidos externos permanecen iguales.
- El único cambio de #276 fue `[Date …]` dentro de `Mercado Pago - Payment <id>`. El recurso autenticado no trae `date` ni `date_created` en `payments_details`; `Helpers/PaymentMetadata::formatPaymentMetadata()` usa `Date::getNowDate()` cuando falta `date`. Por eso reescribe la hora de recepción en cada entrega. **No hay idempotencia byte por byte de todos los metadatos.** No se normalizó el proveedor para esconderlo.

La suite aislada llama al parser y manejador Core originales con transporte SDK reemplazado explícitamente por un recurso fijo, sin red ni pagos. Separa entregas idénticas por más de un segundo para no esconder ese fallback. Comprueba aprobación tardía del primer pedido sin tocar el segundo, asociación de ID sintético, aprobación duplicada sin otra reducción, rechazo de ID malformado antes de leer SDK y consulta del recurso en cada entrega. **No es prueba de autenticidad ni replay externo.**

También reproduce límites de orden temporal: un pendiente posterior a `processing` conserva estado/stock, pero puede añadir una nota nativa; un pendiente posterior a `cancelled` cambia el estado a `pending`. La guarda de `OrderStatus::canUpdateOrderStatus()` sólo excluye `processing` y `completed`. La simulación fuerza un recurso obsoleto para medir la guarda; Core normalmente vuelve a consultar el recurso actual. No demuestra que MP haya entregado externamente ese recurso obsoleto.

No se parchea el plugin ni se filtran genéricamente sus escrituras para congelar estados: eso impediría conciliaciones legítimas o cambios basados en recursos auténticos. No hay un hook semántico del receptor para distinguir universalmente una corrección válida de un recurso obsoleto o de una fecha ausente. Se necesita corrección/garantía oficial y nueva prueba antes de producción. Como alternativa operativa, mantener Pro deshabilitado y usar BACS con verificación manual hasta resolverlo; no crear otro receptor de pagos.

## Webhooks con firma y límites productivos

La [guía WooCommerce](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-configuration/notifications) documenta IPN. La [guía de IPN](https://www.mercadopago.com.ar/developers/es/docs/prestashop/additional-content/your-integrations/notifications/ipn) anuncia retirada y que su firma no se valida con el secret; la [guía general](https://www.mercadopago.com.ar/developers/es/docs/links-and-debts/additional-content/your-integrations/notifications) recomienda Webhooks. El [SDK/documentación de Webhooks](https://www.mercadopago.com.ar/developers/es/docs/prestashop/additional-content/your-integrations/notifications/webhooks) describe firma y prioridad de la URL por operación; no prueba que WooCommerce 8.9.6 la implemente.

Se revisaron fábrica, manejadores, generación de URL y configuración instalada. **No se encontró un selector/secret ni una vía oficial compatible que habilite HMAC para este Pro 8.9.6.** Configurar otro webhook en el panel o sustituir `source_news` no añade un verificador al receptor y puede seleccionar un parser incompatible. Se conserva la integración aprobada; la alternativa productiva es una versión oficial que documente Pro firmado y resuelva estos defectos, validada nuevamente, o resolución con soporte de Mercado Pago. No se afirma que hoy exista esa actualización ni se instaló otra versión.

Antes de producción permanecen: firma/origen y protección contra replay; acceso al historial original y reenvío oficial; corrección/garantía de timestamps y orden temporal del proveedor; compatibilidad de MP con WC 11.2.1 (declara probado hasta 11.0.1); HTTPS estable y configuración comercial autorizada, sin transportar base, secretos, fixtures o usuarios demo. Las URLs persistentes H6 deben adaptarse al dominio definitivo: durante la prueba el CTA del carrito vacío aún apuntó al catálogo localhost, sin afectar los pedidos ni insertar una URL temporal en contenido.

## Reproducción

PowerShell 7, principal local, backup nuevo antes de intervenir. `dev.ps1 start` aplica el montaje de la extensión; activación: `dev.ps1 wp -WpArgs @('plugin','activate','commerce-order-integrity')`. Para retirar la corrección, desactivar ese plugin; no cambia datos comerciales ni requiere migración.

```powershell
.\tests\verify-h7-concurrency.ps1
.\scripts\dev.ps1 wp -WpArgs @('eval-file','/opt/local-tools/h7-notification-qa.php','--quiet')
# Bajo HTTPS, Pro habilitado y vendedor ficticio verificado según la guía H7:
.\tests\verify-h7-pro-session.ps1 -ExpectedTestSeller $sellerNickname
.\tests\verify-h7-notifications.ps1 -ExpectedTestSeller $sellerNickname -OrderId $ownOrderId -PaymentId $ownMpPaymentId
```

La última suite puede aprobar **con limitaciones nativas**: comprueba integridad financiera y expone `full_metadata_idempotent=false`; no convierte esa diferencia en éxito total. No ejecutar con IDs ajenos. Las suites conservan pedidos; la simulación aislada cancela sólo los pedidos que acaba de crear, mediante WC nativo, para restaurar su propio stock. Nunca borra históricos.
