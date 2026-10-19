# H7.1 — Auditoría acotada de Mercado Pago

Revisión del 18/10/2026, Argentina. Separa H2, auditoría estática y [prueba externa nueva H7.1](h7-external-qa.md). No certifica preparación para producción. Complementa [H7.1](h7-1-payments.md) y la [guía de pruebas](h7-payment-testing.md).

**Cierre 19/10/2026:** aprobado como desarrollo. Todos los bloqueos productivos siguientes permanecen abiertos, incluidos monto/moneda/asociación independientes y compatibilidad oficial WC 11.2.1. Pro deshabilitado; no se parchea el plugin, no se improvisa HMAC ni se añade receptor paralelo. La consolidación Git no altera este veredicto.

Ampliación de cierre: [integridad de sesión y auditoría dinámica](h7-concurrency-audit.md). La incidencia de método se reprodujo y corrigió con una extensión soportada; se comprobaron además límites concretos de timestamp diagnóstico y orden temporal del receptor. Se intentó `notifications_history` para la aplicación vinculada: rechazo por propiedad OAuth diferente. El replay externo original continúa sin ejecutarse; las reconstrucciones HTTP y las simulaciones aisladas se identifican por separado.

## Alcance y método

Se leyeron completos el prompt H7.1, [H2](h2-validation.md), [HTTPS H2](h2-temporary-https.md), y las skills `mp-integrate`, `mp-webhooks` y `mp-review` v1.0.0 con sus referencias aplicables. Se contrastó documentación oficial pública vigente. El alcance de `mp-review` es `security`: no se conectó MCP ni se consultaron cuentas reales. La prueba externa posterior utilizó onboarding y plugin oficiales con cuentas ficticias; sí ejecutó pagos simulados y el receptor nativo. No hubo importación de secretos entre instancias ni webhook paralelo.

Las instrucciones genéricas de las skills sobre scaffolding, SDK, CTA y receptor propio no se aplican: H7.1 exige conservar Checkout Blocks y el plugin oficial sin desarrollar otra integración. No se crearon variables MP de entorno porque este plugin utiliza su configuración nativa. La vinculación de una cuenta debe realizarse por su onboarding soportado.

Se leyeron fuentes instaladas directamente del contenedor principal. Las rutas de proveedor siguientes son relativas a `/var/www/html/wp-content/plugins/woocommerce-mercadopago/`; pertenecen al volumen runtime, no se versionan ni se modificaron. La consulta de credenciales devolvió únicamente booleanos de presencia. El barrido de patrones privados en fuentes versionadas imprimió sólo nombres de archivos coincidentes; no encontró tokens completos ni claves privadas.

## Estado inicial observado en H7.1

| Elemento | Resultado nuevo del 18/10 |
| --- | --- |
| WordPress / PHP | 7.1.3 / 8.4.26 |
| WooCommerce | 11.2.1 |
| Mercado Pago | 8.9.6, activo en principal |
| HPOS | Activo |
| Credenciales públicas/de acceso nativas de MP | Ausentes tanto en las opciones `prod` como `test` de principal; no se imprimieron valores |
| Checkout Pro antes de los fixtures H7.1 | `enabled=no` |
| H2 público | Tres contenedores detenidos; tres volúmenes propios conservados |

La ausencia de credenciales se comprobó en `_mp_public_key_prod`, `_mp_public_key_test`, `_mp_access_token_prod` y `_mp_access_token_test`, nombres definidos por `src/Configs/Seller.php:26`. El sufijo `prod` describe el campo del plugin: por sí solo no determina si su titular es una cuenta real o de prueba. Los cambios posteriores de configuración y el resultado final de los fixtures se informan en el documento principal H7.1.

No se inició H2 ni se cambió su configuración para obtener estos datos. H2 conserva históricamente una vinculación de vendedor de prueba; no se afirma que esa sesión o sus tokens sigan vigentes hoy. No se copiaron opciones, credenciales, pedidos ni bases entre instancias.

## Compatibilidad

El paquete instalado declara WordPress mínimo 6.3, probado hasta 7.1, PHP mínimo 7.4 (`readme.txt:4`); WooCommerce mínimo 5.5.2 y probado hasta 11.0.1 (`woocommerce-mercadopago.php:12`). Declara compatibilidad HPOS y Cart/Checkout Blocks en la misma cabecera, y registra Pro mediante `src/Blocks/BasicBlock.php:11` con ID `woo-mercado-pago-basic`. [Cabecera oficial 8.9.6](https://raw.githubusercontent.com/mercadopago/cart-woocommerce/v8.9.6/woocommerce-mercadopago.php).

WooCommerce 11.2.1 supera el techo declarado de pruebas de MP 11.0.1. H2 documenta pruebas funcionales de esa combinación; eso no amplía la declaración del fabricante. La release oficial consultada continúa identificando 8.9.6 como la última publicada. No se justificó reemplazar ni parchear el proveedor. [Release oficial](https://github.com/mercadopago/cart-woocommerce/releases/tag/v8.9.6).

## Evidencia histórica H2 y repetición en principal

H2 acreditó operaciones externas con cuentas ficticias distintas: aprobado, rechazado, pendiente con navegador cerrado, confirmación posterior al primer retorno y dos devoluciones efectivas de saldo ficticio. HPOS, stock, correo y retorno se verificaron entonces. No se deben repetir devoluciones como parte de H7.1: este hito prohíbe reembolsos monetarios.

H2 no reprodujo la notificación idéntica. Faltaba el cuerpo original y el historial del vendedor no era accesible con el OAuth de la cuenta Developers principal. Una devolución seguida de callback no equivale a repetir una notificación. Ningún resultado H2 se presenta como prueba externa realizada en H7.1.

La sesión externa H7.1 ya repitió aprobado, OTHE rechazado, CONT pendiente, abandono, retornos, importes y recepción de callback antes del retorno. El [informe nuevo](h7-external-qa.md) conserva el resultado real de cada pedido, la indisponibilidad controlada y la distinción entre reintento del proveedor y solicitudes reconstruidas por QA.

## Cuenta de prueba y modo correcto

La guía específica de WooCommerce exige dos cuentas de prueba distintas, iniciar sesión con el vendedor de prueba, crear/vincular la aplicación y seleccionar `Modo de ventas (producción)` para probar Pro. El comprador debe usar otra sesión y su cuenta de prueba. Esa etiqueta no autoriza una cuenta comercial real. [Prueba oficial WooCommerce](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-test).

El procedimiento seguro para reutilizar las cuentas conocidas de H2 es volver a vincular el vendedor desde el onboarding oficial de la instancia principal y contrastar su identidad de prueba antes de habilitar pagos. No equivale a copiar los tokens del volumen H2. Sin identidad comprobada, no se ejecuta una transacción.

El prefijo de una credencial y `live_mode` aislados no acreditan que el receptor sea de prueba. No se solicitó ningún secreto por chat. Sin vinculación segura, Pro debe permanecer técnicamente indisponible; no corresponde registrar un método visual ficticio.

El inspector `docker/tools/h7-mp-seller-check.php` admite `inspect` sin consultar identidad y `verify-linked-test-seller <vendedor-esperado>` mediante `getSellerInfo`. Se ejecutó tras el onboarding y pasó `test_user`, MLA, vendedor esperado y HTTPS. El onboarding actual no rellena el collector legacy: vacío se informa `null`, sin inventarlo; si existe, se contrasta. Nunca devuelve identidad ni credenciales. No activa métodos ni genera preferencias. El lector puede usar su cache; TLS, comprador distinto y callback fueron comprobados por separado en la prueba externa.

## HTTPS

H2 utilizó un overlay reversible de `LOCAL_SITE_URL`/WP_HOME/WP_SITEURL y un Quick Tunnel supervisado, sin reemplazo SQL ni URLs temporales en contenido. Ese helper está limitado expresamente a `h2-public`; no se debe ejecutar apuntándolo a principal mediante cambios improvisados.

Un Quick Tunnel puede servir para una sesión supervisada de QA si su hostname permanece constante durante todo el flujo y el callback puede entrar sin autenticación interactiva. Cloudflare no garantiza uptime y cambia el hostname al recrearlo; una sesión nueva invalida la continuidad de los callbacks anteriores. Si no permite completar la prueba, usar un túnel de desarrollo con hostname estable y acceso acotado, sin desplegar producción. [Límites oficiales](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/#limitations).

Antes de publicar principal: backup, overlay reversible, prueba TLS/enlaces/Store API/retorno, restricción del tiempo de exposición administrativa y restauración comprobada de localhost. No publicar Mailpit ni MariaDB. No agregar autenticación interactiva global delante del callback. No registrar URLs de retorno que incluyan claves de pedido.

## Receptor oficial y autenticidad

La configuración instalada construye la URL mediante `src/Transactions/AbstractTransaction.php:119`, usando `WC_WooMercadoPago_Basic_Gateway` y `source_news` del proveedor. En localhost, sin dominio personalizado, no genera una URL externa utilizable. El dominio personalizado sólo se usa conforme a las opciones nativas del plugin.

`src/Helpers/NotificationType.php:20` mapea Basic/Pro a `ipn`. `src/Notification/NotificationFactory.php:25` elige Webhook con `type=payment`/`source_news=webhooks`, IPN con `topic=merchant_order`/`source_news=ipn` y Core como ruta restante. No se debe cambiar `source_news` manualmente para presentar el flujo Pro como webhook moderno. La guía específica WooCommerce todavía documenta IPN; la guía general recomienda webhooks firmados y anuncia la retirada de IPN. Esta diferencia del proveedor queda explícita. [Notificaciones WooCommerce](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-configuration/notifications), [notificaciones generales](https://www.mercadopago.com.ar/developers/es/docs/links-and-debts/additional-content/your-integrations/notifications).

Core acepta únicamente identificadores de formato `P-`/`M-` y consulta el recurso por SDK autenticado antes de procesar estados (`CoreNotification.php:56`, `:63`, `:79`). Webhook vuelve a consultar `/v1/payments/{id}` con la credencial del vendedor (`WebhookNotification.php:78`); IPN consulta recursos del proveedor (`IpnNotification.php:204`). `AbstractNotification.php:66` obtiene `external_reference` del recurso consultado, resuelve el pedido con `wc_get_order` y usa CRUD para sus metadatos. [Core oficial](https://raw.githubusercontent.com/mercadopago/cart-woocommerce/v8.9.6/src/Notification/CoreNotification.php).

La búsqueda en los receptores instalados no encontró `x-signature`, `x-request-id` ni HMAC. La consulta autenticada del recurso protege la autoridad del estado; no acredita firma de origen del request entrante. No se certifica validación HMAC ni se añade un receptor paralelo. Antes de una liberación productiva debe resolverse con el mecanismo oficial soportado o con una versión del proveedor que documente esas garantías. La firma y el historial del panel son controles diferentes de una respuesta HTTP 200. [Webhooks oficiales](https://www.mercadopago.com.ar/developers/es/docs/links-and-debts/additional-content/your-integrations/notifications/webhooks).

## Estados, idempotencia y stock: evidencia estática

| Estado MP | Mapeo del proveedor | Consecuencia nativa de stock |
| --- | --- | --- |
| `approved` | `processing` para físicos mediante `payment_complete()` | Reduce una vez y libera la reserva |
| `pending` | `pending` | Mantiene la semántica de reserva del checkout, sin afirmar cobro |
| `in_process` | `on-hold` | Puede reducir stock aunque el pago aún no esté acreditado |
| `rejected` | `failed` | No acredita pago; puede restaurar stock previamente reducido |
| `cancelled` | `cancelled` | Puede restaurar stock previamente reducido |

El mapeo se leyó en `src/Order/OrderStatus.php:428`; `approvedFlow` sólo completa estados `pending`, `on-hold` o `failed` (`:142`). `pendingFlow`, `inProcessFlow`, `rejectedFlow` y `cancelledFlow` consultan una guarda que no modifica `processing`/`completed` (`:453`). No hay en esa guarda una regla temporal general para todos los finales: no se afirma que un callback retrasado nunca pueda afectar un pedido cancelado o devuelto. Core vuelve a consultar el recurso actual, pero la lectura estática no sustituye la prueba de reintento/replay.

WooCommerce 11.2.1 usa la bandera de stock reducido en el data store y `_reduced_stock` por ítem (`woocommerce/includes/wc-stock-functions.php:104`, `:190`). Reduce al pasar a `processing`, `completed` y `on-hold`; restaura al pasar a `cancelled`, `pending` o `failed` cuando había reducción. BACS `on-hold` por ello puede reducir antes de que exista dinero. Los cambios repetidos se deben probar mediante APIs nativas, sin ajustar inventario artificialmente. La matriz runtime H7.1 se informa por separado.

No existe un POST de pagos propio al cual agregar una `X-Idempotency-Key`. Checkout Pro crea preferencias y delega el cobro al proveedor. No se inventa soporte de esa cabecera en Preferences. Doble click, retorno, refresh, caída de conexión y notificación repetida requieren QA del flujo completo; el código estático sólo demuestra las guardas descritas.

## Totales y retorno

El plugin toma productos y variaciones del pedido, agrega sus importes luego de descuentos/impuestos y el envío como un ítem de importe único (`src/Transactions/AbstractTransaction.php:279`, `:320`). La preferencia recibe `external_reference` y `notification_url` en `:105`. Debe verificarse externamente que el total coincida, además del QA interno de cupones/envíos de H7.1.

`src/Gateways/BasicGateway.php:240` contiene una bifurcación a `sandbox_init_point` cuando el plugin está en su modo de pruebas. La guía específica para Pro usa Modo Ventas con vendedor de prueba; esa rama usa `init_point`. No se modifica el proveedor ni se habilita la rama problemática. Nunca se considera que parámetros como `status=approved` prueben un pago.

## Mercado Pago Integration Review

**Scope**: security, H7.1 local; no certificación productiva.
**API detected**: Payments API para consultas; Preferences API para Checkout Pro. No Orders API propia.
**Products detected**: Checkout Pro oficial WooCommerce.
**Files analyzed**: `.gitignore`, documentación H2, `compose.yaml`, `compose.h2-https.yaml`, `scripts/h2-https.ps1` y fuentes runtime 8.9.6 referenciadas arriba.

### CRITICAL

- Autenticidad HMAC y replay externo no acreditados. No hay autorización de producción.

### WARNINGS

- WooCommerce 11.2.1 está por encima del `WC tested up to` declarado de MP.
- Pro todavía contiene ruta IPN y rama `sandbox_init_point` en el proveedor; no se parcheó.
- La guarda de estados no equivale a orden temporal para cualquier estado final.
- `OrderStatus::getNotificationId()` reconoce merchant orders por un texto legacy de la URL. El puente actual puede devolver 404 para la búsqueda P- aunque el recurso M- exista; detalle y alternativa diagnóstica soportada mediante SDK en el informe externo. No se modificó el proveedor.
- La reutilización del pendiente y su hidratación HTML alteraban el método sin enviar otro pago. Corregida mediante **Commerce Order Integrity**, con pruebas HTTP y dos pestañas reales; detalle del alcance y reintentos autorizados en el cierre auditado.
- Core sin fecha de pago en el recurso reescribe `[Date …]` diagnóstico en cada notificación. Reconstrucciones autenticadas de #276 preservaron efectos financieros y stock, pero no la cadena completa de metadatos.
- La simulación de un recurso pendiente obsoleto después de `cancelled` volvió a `pending`; la guarda no proporciona orden temporal universal. No se presentó como una entrega obsoleta externa real.

### PASS

- Plugin oficial instalado/activo; declaraciones HPOS/Blocks y adaptador Pro nativo.
- Credenciales consultadas sólo como booleanos y ausentes en principal al inicio.
- Vendedor y comprador ficticios distintos comprobados; pagos externos exclusivamente simulados, sin reembolsos ni copia de secretos H2 a principal.
- El receptor consulta recursos autenticados; referencia y persistencia usan APIs WooCommerce.

### Quality Standards

Not requested for this scope. `quality_evaluation`: omitida, sin un pago externo nuevo ni alcance de calidad MCP solicitado. Homologación no solicitada ni presentada como aprobada.

#### Required fields

No evaluados mediante `quality_checklist`; no se inventan filas ni puntuaciones.

#### Best practices

No evaluadas mediante `quality_checklist`.

### Security checklist (cross-cutting)

| # | Check | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Token fuera del código | Pass | Barrido versionado sin tokens completos; opciones nativas privadas |
| 2 | `.env` ignorado; `.env.example` permitido | Pass | `.gitignore:3`; `git check-ignore` |
| 3 | HMAC `x-signature` | Not verified | Búsqueda receptores sin implementación; consulta autenticada no equivale a HMAC |
| 4 | HTTPS para retorno/callback | Pass en QA | TLS, origen y callback externo comprobados en H7.1; restauración local al cierre |
| 5 | Estado contrastado en servidor | Pass en QA | Código oficial y lecturas autenticadas: monto ARS, estado, receptor, comprador y referencia |
| 6 | Clave de idempotencia al crear pago | N/A a llamada propia | No creador custom de pagos; Preferences no se presenta como Payments API |
| 7 | `external_reference` | Pass en QA | `AbstractTransaction.php:108`, `AbstractNotification.php:71`; coincide con los pedidos probados |
| 8 | Credenciales fuera de entrega | Pass | `.local/` ignorado, volúmenes privados, sin copia automática |
| 9 | Ausencia de `sandbox_init_point` | Partial | Ausente de integración propia; rama del proveedor `BasicGateway.php:240` |

### Recommendations

1. Mantener Pro deshabilitado en localhost; reactivarlo sólo en otra sesión HTTPS con identidad ficticia verificada.
2. Resolver garantías de autenticidad y migración del mecanismo IPN/Core con soporte/versión oficial antes de producción.
3. Certificar el replay idéntico del evento original y la ordenación de todos los estados finales; no sustituirlos por una reconstrucción.

**Summary**: Official quality: not requested. 6/9 controles con evidencia local/estática o externa; uno no verificado, uno no aplicable y uno parcial. No es una certificación productiva.

---

## Implementation Report

### Verified

- [x] Versiones runtime, MP oficial activo, HPOS y declaraciones Blocks.
- [x] Ausencia inicial de credenciales en principal, comprobada sin revelar valores.
- [x] Volúmenes H2 conservados, instancias separadas y sin migración de secretos.
- [x] Mecanismo nativo de consulta de estado/referencia/CRUD y guardas de stock documentados.
- [x] Vinculación ficticia verificada, pagos externos simulados, HTTPS y recepción antes del retorno comprobados en H7.1.

### Needs attention

- [ ] `BasicGateway.php:240`: conservar el flujo oficial Pro con `init_point`; no activar la rama de modo de pruebas por su nombre.
- [ ] `OrderStatus.php:453`: límite `cancelled` → `pending` reproducido en simulación aislada; resolver con el proveedor y volver a probar externamente.
- [ ] `Helpers/PaymentMetadata.php::formatPaymentMetadata`: fallback a hora actual, cambio diagnóstico reproducido con el recurso Core auténtico; resolver con el proveedor si se exige idempotencia total de metadatos.
- [ ] Repetición idéntica de notificación aún sin acreditar externamente.
- [ ] Calidad/homologación oficiales no ejecutadas; fuera del alcance de habilitación productiva de H7.1.

### Blockers (must fix before production)

- [ ] Resolver/documentar garantías de autenticidad del receptor oficial de la versión elegida; HMAC no certificado aquí.
- [x] Resolver con una solución soportada la reutilización de la sesión pendiente: extensión REST/hidratación, comparación de pedidos y reintentos nativos completados; sin modificar core/proveedor.
- [ ] Obtener garantía/corrección oficial para timestamps e intercalación de estados finales; no congelar genéricamente las conciliaciones del plugin.
- [ ] Completar entorno comercial real en un hito autorizado, sin arrastrar secretos ni cuentas ficticias.

### Next steps

1. Revisar los [resultados externos](h7-external-qa.md) y sus límites antes de adaptar la plantilla a un cliente.
2. Repetir notificaciones con el evento original accesible y resolver las garantías pendientes con el proveedor.
3. Reejecutar `mp-review` después de resolver estos puntos, antes de producción.

### Resources used

- Local: fuentes oficiales instaladas, documentos H2, comprobaciones runtime y sesión ficticia nueva detallada en el informe externo.
- Web: documentación pública oficial enlazada arriba, consultada el 18/10/2026.
- Skills: `mp-integrate`, `mp-webhooks`, `mp-review` v1.0.0; adaptación a restricciones explícitas H7.1.
- MCP: la auditoría inicial no usó MCP. La ampliación intentó sólo `notifications_history` de la aplicación vinculada y recibió rechazo por propiedad OAuth; sin cambios de cuentas, webhooks, autenticación ni pagos.

**Scores**: official quality: N/A; 6/9 controles locales/estáticos o externos. **Verdict**: Needs fixes before production — pruebas ficticias H7.1 realizadas; HMAC, replay original y orden temporal universal no certificados.
