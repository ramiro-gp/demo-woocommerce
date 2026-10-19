# H7.1 — Pagos y pedidos

Implementación del 18/10/2026, Argentina, en `C:\Codigo\demo-woocommerce`.
H7.1 aprobado y cerrado como hito de **desarrollo** el 19/10/2026. No autoriza cobros reales. H8 no se inició.

## Cierre de desarrollo del 19/10/2026

Base aprobada `686006c736f283c9ea013815b43e382d6a8fc922`, idéntica a `origin/codex/h7-1-payments`, sin cambios locales inesperados. Consolidación prevista por fast-forward, preservando los tres commits H7.1 y este cierre documental, sin force push. El commit de cierre se identifica con `git log --oneline --grep='close H7.1 development'`; la documentación H7.2 registra el SHA final integrado y la coincidencia de main/origin/main.

Regresión nueva: 33/33 funcionales, 7/7 guardas, 21/21 envíos/cupones, 31/31 concurrencia HTTP/Blocks y 6/6 flujos Chromium (Home, catálogo, fichas, carrito, mini-cart, cross-sells, Exit Intent, checkout y envíos), sin errores JS/assets ni POST de compra. Reporte privado `h6-2/browser/h7-1-closure-browser.json`. Son pruebas locales, sin nuevos pagos ni callbacks externos. Backup nuevo íntegro `h7-2-before-communications-20261019`. Los pedidos históricos se conservan. Localhost:8080 y Mailpit:8025 restaurados; no hay túnel activo; BACS disponible y Checkout Pro deshabilitado.

**Bloqueos productivos sin resolver:** HMAC ausente en el receptor efectivo; replay externo idéntico original sin comprobar; garantías insuficientes de eventos tardíos/orden temporal; timestamp diagnóstico cambiante; `cancelled` → `pending` con recurso obsoleto simulado; comprobaciones independientes insuficientes de importe, moneda y asociación pago/pedido; compatibilidad productiva MP 8.9.6/WC 11.2.1 pendiente. Las pruebas ficticias aprobadas no resuelven estos bloqueos. No se modifica el proveedor ni se crea otro receptor.

Commerce Order Integrity 1.0.0 sigue activo, versionado y montado de sólo lectura en ambos servicios Compose. No depende de archivos temporales ni se mueve a Commerce Core. Para una instalación nueva: preparar H1/H2 y los hitos de contenido en orden, ejecutar `dev.ps1 start` con este Compose y `dev.ps1 wp -WpArgs @('plugin','activate','commerce-order-integrity')`; después ejecutar `verify-h7-concurrency.ps1`. La activación explícita es necesaria: `start` preserva la lista de plugins de una instalación existente. Mantener los flujos nativos de `order-pay` y reintento.

## Base Git y arquitectura

H6.2 se consolidó por fast-forward en `main`/`origin/main`, incluyendo la corrección aprobada que ocultó «Guardar $X» y ajustó los márgenes del resumen angosto: **`c736cdc50d57821eca27a56867a069914c962659`**. Se preservaron los cambios locales y se publicó también la rama H6.2. No hubo force push ni descarte de commits. Rama nueva: **`codex/h7-1-payments`**.

Se conservan WordPress, WooCommerce, Cart/Checkout Blocks, HPOS, Commerce Base, Docker Compose, MariaDB, WP-CLI, Mailpit, ARS, español argentino e invitado. No hay gateway propio, formulario de tarjetas, receptor paralelo, shortcode ni Commerce Core. El theme presenta información; WooCommerce y Mercado Pago administran pedidos y transacciones.

| Componente | Versión comprobada |
| --- | --- |
| WordPress | 7.1.3 |
| WooCommerce | 11.2.1 |
| Mercado Pago oficial | 8.9.6 |
| PHP | 8.4.26 |
| MariaDB | 10.11.19, imagen fijada |
| Commerce Base | 1.6.0 |

WooCommerce y Mercado Pago pasaron checksums oficiales estrictos. El plugin declara HPOS y Blocks y registra Pro nativo. Su `WC tested up to` es 11.0.1: la combinación con 11.2.1 tiene evidencia funcional histórica H2, no una declaración ampliada del fabricante. Ver [auditoría de código, seguridad y fuentes](h7-mp-audit.md).

## Mercado Pago Checkout Pro

El plugin ya estaba instalado y activo en principal; no se copió desde H2 ni se modificó. El método elegido sigue siendo `woo-mercado-pago-basic` (Checkout Pro, Redirect). Los demás checkouts MP deben permanecer deshabilitados.

Estado inicial: Pro deshabilitado y ninguna clave nativa `prod`/`test` ni vendedor vinculado. El migrador `scripts/h7-1.ps1` no importa credenciales ni habilita MP. La vinculación utiliza exclusivamente el onboarding oficial. El helper separado `h7-mp-session.php` permite activar el gateway nativo sólo con vendedor ficticio esperado verificado, HTTPS y BACS preservado; no implementa un pago. Una opción visual no se registra artificialmente si el plugin no puede ofrecerla.

La vinculación oficial se completó posteriormente con el vendedor ficticio autorizado. La consulta autenticada confirmó el nickname esperado, `test_user` y `MLA`; se utilizó el comprador ficticio distinto conservado privadamente por H2. No se copiaron credenciales entre tiendas ni se necesitaron credenciales nuevas por chat. **Checkout Pro funcionó externamente durante la sesión HTTPS; queda deshabilitado al restaurar localhost porque allí no puede ofrecer retornos/callbacks públicos. BACS continúa disponible.** La instancia H2 sigue detenida y sus volúmenes intactos. Ver [resultados externos y límites comprobados](h7-external-qa.md).

La prueba oficial WooCommerce requiere vendedor y comprador de prueba distintos y puede usar **Modo de ventas (producción)** con el vendedor de prueba. La etiqueta y el prefijo de token no prueban identidad ni autorizan dinero real. El inspector `docker/tools/h7-mp-seller-check.php` compara el vendedor esperado mediante el lector autenticado y devuelve booleanos. El onboarding actual no completa `_collector_id_v1`: su ausencia ya no produce un falso bloqueo ni se rellena artificialmente; si existe, se exige coincidencia. La presencia de claves tampoco se presenta como identidad comprobada. El lector oficial puede utilizar caché. [Procedimiento oficial](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-test).

Configuración utilizada: nombre **Mercado Pago**, Redirect, `init_point`, modo de ventas con cuenta ficticia verificada y `binary_mode=no`. Se conserva el contenido oficial del plugin, sin promesas propias de cuotas/medios. La primera aprobación probó retorno automático; luego se desactivó `auto_return` para observar callbacks con el comprador todavía en Mercado Pago. La rama `sandbox_init_point` del proveedor no se parcheó ni se utilizó como atajo.

### Confirmación y notificaciones

La URL la construye el plugin oficial con `WC_WooMercadoPago_Basic_Gateway`; se conserva el receptor y `source_news` que genera esa versión. Pro 8.9.6 mantiene la ruta IPN documentada para WooCommerce; no se reemplaza con una URL de webhook inventada. CoreNotification obtiene el recurso con SDK autenticado y asocia `external_reference` con el pedido mediante CRUD. Una redirección, texto visual o `status=approved` nunca confirma un pago.

No se encontró validación HMAC `x-signature` en los receptores instalados. La consulta autenticada acredita la autoridad del estado obtenido, pero no equivale a autenticar la firma de la solicitud entrante. Se verificó un reintento externo tras respuestas 503 y reconstrucciones Core sin duplicar efectos financieros. La revisión exhaustiva posterior detectó que el timestamp diagnóstico de metadatos puede cambiar en cada entrega y que una simulación de pendiente obsoleto puede reabrir un cancelado. Replay externo original, HMAC y orden temporal universal requieren resolución oficial antes de producción. La incidencia de método sí quedó reproducida y corregida mediante la extensión acotada **Commerce Order Integrity**, con hooks REST y de hidratación de Blocks; conserva pago existente autorizado y no intercepta notificaciones. Ver [cierre de concurrencia y notificaciones](h7-concurrency-audit.md), [auditoría](h7-mp-audit.md) e [informe externo](h7-external-qa.md).

## BACS nativo y configuración demo

Gateway **`WC_Gateway_BACS`**, ID `bacs`, título **Transferencia bancaria**.

Descripción: «Realizá una transferencia y verificaremos el pago antes de preparar tu pedido. TIENDA DE DEMOSTRACIÓN — No realizar transferencias reales.»

Instrucciones: «TIENDA DE DEMOSTRACIÓN — No realizar transferencias reales. Esta tienda no tiene una cuenta bancaria operativa.» El estado pendiente se muestra mediante el filtro del recibo: no se incrusta en instrucciones que WooCommerce puede seguir mostrando después de una confirmación manual.

La lista de cuentas queda vacía: ningún CBU, CVU, alias, titular ni número ficticio potencialmente operativo. WooCommerce admite BACS sin esos datos. Todo se edita después desde sus ajustes nativos; no se acopla al theme ni se instala otro plugin.

El checkout invitado crea un pedido HPOS con productos, variaciones, cupones, envío y total nativos. `process_payment()` de BACS lo deja **on-hold / En espera**, sin fecha de pago ni transacción. La confirmación manual de un pedido de prueba se realiza desde wp-admin cambiando a **Procesando**. No hay botón del comprador «Ya pagué» ni reconocimiento de comprobantes.

## Pedidos, estados y stock

| Situación | Estado/semántica de esta versión | Nivel de evidencia |
| --- | --- | --- |
| BACS enviado | `on-hold`, no pagado; reduce stock | Checkout real local y CRUD |
| BACS verificado manualmente | `processing`, fecha de pago; no segunda reducción | Admin nativo y lectura CRUD |
| BACS cancelado | `cancelled`; repone stock previamente reducido una vez | Core/CRUD |
| Pedido fallido | `failed`, no pagado; repone si antes redujo | Core/CRUD |
| MP aprobado | `processing`, pagado y fecha; reduce una vez | Pago externo ficticio #276; detalle de segunda aprobación en informe externo |
| MP `pending` / `in_process` | El recurso individual CONT quedó `in_process`; el pedido agregado #278 quedó `pending`, sin fecha ni reducción | Callback externo antes del retorno; no imponer `on-hold` al agregado nativo |
| MP rechazado | OTHE rechazado por MP; #277 permaneció impago y el retorno de fallo nativo lo canceló conservando carrito | Pago externo ficticio; no se fuerza `failed` manualmente |
| MP abandonado | #275 `cancelled`, impago, carrito conservado y sin reducción | Retorno nativo sin enviar pago |

WooCommerce distingue **reserva** y **reducción**. Reduce con `on-hold`, `processing` y `completed`; la bandera nativa de reducción y `_reduced_stock` por ítem evitan aplicar dos veces ese efecto. Al volver a `pending`, `failed` o `cancelled` restaura lo que había reducido. El timeout de pendientes no equivale a cancelar todos los BACS en espera; éstos requieren operación comercial posterior. No se ajusta stock manualmente para fabricar resultados de QA.

Los productos físicos no se marcan completados por cobrar. El theme sólo usa el filtro público `woocommerce_thankyou_order_received_text`: pendientes explican que esperan confirmación/verificación; `processing` con estado pagado y fecha de pago informa pago confirmado. Preserva mensajes nativos de fallo, cancelación, devolución y entrega. Nunca consulta parámetros del navegador para decidir el texto ni cambia estados.

## HTTPS reversible y privacidad

`scripts/h7-https.ps1` deriva del helper probado en H2, pero apunta exclusivamente a **local/8080**, Mailpit **8025**, métricas loopback **18182**, estado privado `.local/local/h7-1/https/` y mutex independiente. No inicia ni altera H2. Exige backup íntegro: por defecto `h7-1-before-payments`, o un `-BackupId` explícito. Esta sesión creó `h7-1-before-external-tests-20261018` después de la vinculación.

`compose.h7-https.yaml` cambia sólo `LOCAL_SITE_URL` usado por `WP_HOME`/`WP_SITEURL`, sin actualizar `home`/`siteurl` persistentes ni hacer reemplazos SQL. El helper valida TLS, redirecciones del mismo origen, portada/login y enlaces Store API HTTPS; al detener valida PID, ruta y fecha del proceso propio, apaga el túnel y restaura localhost. No deja hostname temporal en contenido. No ejecutar `dev.ps1 start` u otras configuraciones del origen mientras el túnel está activo.

Quick Tunnel sirve para una sesión supervisada; cambia hostname y no garantiza disponibilidad. Mantener el mismo proceso durante pago, retorno y callbacks. Si se corta, no continuar enlaces viejos: usar un túnel de desarrollo con hostname estable y repetir las verificaciones. El overlay bloquea `wp-login.php`, administrador y XML-RPC con HTTP 403; conserva `admin-ajax.php`, Store API y el receptor nativo. Envía `X-Robots-Tag: noindex, nofollow, noarchive`. Base y Mailpit permanecen privados. Un watchdog detiene la sesión a los 90 minutos y deshabilita Pro antes de restaurar el origen; el estado JSON se escribe atómicamente. No instala un servicio ni despliega producción. [Limitaciones Cloudflare](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/#limitations).

Se abrió un Quick Tunnel con TLS válido, portada HTTP 200, login HTTP 403 y 22 enlaces Store API del mismo origen HTTPS. Los resultados y el cierre se detallan en el informe externo. El primer arranque detectó que `mod_headers` no estaba cargado: se corrigió sólo el overlay; el helper restauró localhost al fallar. El healthcheck público usa una imagen estática porque el login está deliberadamente bloqueado. No se alteraron redirecciones canónicas ni el theme.

Apache ahora omite query strings, referrer y cookies del access log para no registrar claves de pedido ni códigos OAuth. Los reportes no imprimen credenciales ni URLs con claves; datos de sesión y evidencia privada quedan en `.local/` ignorado. No habilitar logs verbosos de proveedor que puedan revelar información sensible.

## Reproducibilidad y preservación

```powershell
.\scripts\h7-1.ps1 backup
.\scripts\h7-1.ps1 inspect
.\scripts\h7-1.ps1 configure
.\scripts\h7-1.ps1 verify
.\tests\verify-h7-1.ps1
```

Si el backup ya existe, no se sobrescribe: usarlo y ejecutar desde `inspect`. Configuración explícita sólo sobre la instancia principal local. Los guards H5/H6 se ejecutan antes de migrar. La migración cambia únicamente BACS demo y la huella de entorno de H6.1 correspondiente, conservando evidencia del cambio. No escribe cuentas bancarias ni opciones MP. Una edición posterior de BACS o datos bancarios existentes produce conflicto conservador, no un reset. Los hashes sensibles se calculan internamente y no exponen sus valores en los informes públicos.

`h5.ps1`, `h6-1.ps1` y `h6-2.ps1` mantienen sus protecciones; no sincronizan credenciales con fixtures. `h2.ps1 install` pertenece al entorno técnico H2 y no debe usarse para configurar principal: deshabilita gateways offline por su contrato histórico.

QA crea sólo productos ocultos, cupón y pedidos identificados como H7.1, con destinatarios `example.test`. Los tests internos bloquean llamadas externas; los tests browser permiten checkout final sólo BACS y bloquean HTTP externo. `cleanup` cancela pedidos propios por APIs nativas y repone stock por el core, sin pasarela, refunds o SQL a pedidos. Verifica destinatario, nombre ficticio, invitado, origen Store API, BACS sin transacción y propiedad de todos los productos antes de mutar. Conserva los pedidos como evidencia local. Los totales de la suite core utilizan productos/cupón efímeros independientes del stock que consume el browser. No ejecutar QA mientras HTTPS esté activo o haya compras manuales de prueba en curso.

## Resultados de QA y límites de cierre

No se considera una simulación local equivalente a una confirmación auténtica de Mercado Pago.

| Prueba | Resultado y alcance |
| --- | --- |
| Core WooCommerce / HPOS | 33 comprobaciones: BACS, pendientes/reservas, estados fallidos/cancelados, agotado, reducción única, notas/metadatos y mensajes según estado. Simulaciones internas; cero llamadas a pagos, refunds o HTTP externo |
| Preservación de configuración | 7/7 guardas: repetir H5/H6.1/H6.2/H7.1 conserva pagos/credenciales; se rechazan ediciones manuales BACS y datos de cuenta existentes sin sobrescribirlos; baseline restaurado |
| Totales nativos | Siete escenarios: simple/variación en promoción, estándar, retiro, umbral exacto, combinado y cupones que eliminan envío gratis |
| Regresión browser H5/H5.1/H6 | Catálogo/simple-variable/agotado/lookbook; mini-cart/foco/contador/cross-sells/volver al carrito/Exit Intent; umbral/cupón, retiro, direcciones/provincia/país sin cobertura y recálculo de cantidades. Siete checks de flujo aprobados, sin errores JS/assets ni checkout final |
| Compra BACS real local | Siete pedidos: doble click, gratis exacto, cupón→estándar, variación+retiro, desconexión antes de PHP, refresh antes de PHP con reintento y respuesta perdida después de PHP. Un pedido final por operación, impago/on-hold, productos/cupón/envío/total/stock coincidentes. El último caso confirma carrito vacío, reducción única y ausencia de reintento automático tras perder la respuesta ya procesada |
| Navegación/seguridad | Reload, atrás y parámetros `approved` falsificados no crean pedidos ni pagan ni descuentan otra vez. Clave inválida no expone detalles privados |
| Matriz visual | 40 combinaciones checkout/recibo × claro/oscuro × 320,375,390,430,768,1024,1366,1920,2560,3440. Sin overflow; controles, instrucciones, CTA/resumen y focos nativos utilizables |
| Confirmación manual | Cambio real desde wp-admin aislado de pedido BACS de prueba a processing; guardado repetido sin segunda reducción. Recibo confirmado sin instrucciones pendientes contradictorias |
| Totales de navegador | 48/48 checks en suite completa + 1/1 post-admin + 1/1 pérdida de respuesta post-PHP = **50/50 principales**. Cuatro revisiones/capturas adicionales de recibo refinado aprobadas |
| Regresión funcional H6.2 | 21/21 checks aprobados. Se adaptó su assertion histórica de cero gateways para admitir exclusivamente BACS/Pro después de la migración H7.1; antes de H7 mantiene el contrato de cero gateways. Cupón temporal retirado al finalizar |
| Mailpit | API local disponible y mensajes de destinatarios controlados capturados; MU-plugin sigue enrutando correo a Mailpit, sin SMTP real ni rediseño |
| Integridad fuente/runtime | Checksums oficiales 2/2; parsers PS 5.1/7 y Node correctos; cero errores PHP críticos en log revisado; archivos runtime excluidos |
| Mercado Pago H7.1 externo | Ejecutado con cuentas ficticias distintas: aprobado, rechazado, CONT pendiente, abandono, retorno, importes, HPOS y stock. [Evidencia y alcance de reintentos/reconstrucción](h7-external-qa.md) |
| Simulación oficial de notificación MP | **No ejecutada**. Las pruebas core y del recibo no la sustituyen |

El primer pase de la regresión H6.2 omitió preparar su cupón temporal: seis checks pasaron y el de cupón falló por fixture ausente. Se preparó el cupón y se repitió únicamente ese caso: pasó. Los primeros intentos del test browser H7 detectaron un selector QA legacy frente al recibo Blocks; se corrigió el test y se cancelaron nativamente sus dos pedidos propios antes de la corrida final. Una excepción inicial del helper al crear fixtures se corrigió; no era un fallo del storefront ni una transacción. No se omiten estos diagnósticos para presentar los intentos como todos exitosos.

La suite browser local bloquea intencionalmente HTTP externo, incluida telemetría melidata del proveedor: sus errores esperados se registran separados. El QA externo posterior utilizó el navegador autorizado y verificó el selector real de BACS/MP, el checkout administrado por MP y veinte combinaciones adicionales del checkout MP: diez anchos en claro/oscuro, sin desborde. Se conservan capturas privadas.

Los pedidos browser actuales se conservan para revisión: **#250, #251, #252, #254, #255, #256 y #257**; #250 fue confirmado manualmente en wp-admin, los otros seis permanecen en espera sin pago. Son productos propios ocultos: stock final simple 0 y variación 3. El catálogo H5 conserva su inventario. `cleanup` permite cancelar estas pruebas de forma nativa antes de repetirlas. Los pedidos #236/#237 de los diagnósticos iniciales quedaron cancelados sin pasarela. Los pedidos core llevan otra identificación y destinatario; no representan pagos MP.

Evidencia privada: `.local/local/h7-1/functional-verify.json`, `functional-guard.json`, `https-origin.json`, `browser/qa.json`, `browser/post-admin.json`, `browser/response-loss.json`, `browser/receipts-refined.json` y capturas. Las claves sólo se conservan en el archivo privado de rutas de pedido; no se versionan. Regresión browser bajo `.local/local/h6-2/browser/h7-regression-*.json`.

La integración externa Checkout Pro quedó ejercitada con dinero ficticio, además de BACS y QA interno. El [informe externo](h7-external-qa.md) distingue eventos auténticos, solicitudes de QA reconstruidas y escenarios todavía no certificados. No hay habilitación productiva. [Reproducción](h7-payment-testing.md#checkout-pro-de-prueba).

## Adaptar a un comercio real

H7.1 no habilita producción. Crear una instalación/entorno limpio, retirar la vinculación de prueba mediante el plugin oficial, revocar accesos que correspondan y excluir `.local/`, backups, pedidos QA, secretos y volúmenes demo. El comerciante vincula su propia cuenta sólo en el hito autorizado, con HTTPS estable y revisiones de autenticidad/idempotencia resueltas. Configura BACS con datos propios verificados desde WooCommerce. No importar opciones sensibles en un fixture ni transportar claves demo.

H7.2 queda reservado para comunicaciones y operación: emails/branding/remitentes, circuito de verificación bancaria y notificaciones definitivas. Aquí sólo se verifica Mailpit y se corrige copy de recibo engañoso; no se rediseñan emails, SMTP ni operación.

## Revisión manual

- [Tienda](http://localhost:8080/?post_type=product), [carrito](http://localhost:8080/?page_id=8), [checkout](http://localhost:8080/?page_id=9).
- [Pedidos HPOS](http://localhost:8080/wp-admin/admin.php?page=wc-orders).
- [BACS](http://localhost:8080/wp-admin/admin.php?page=wc-settings&tab=checkout&section=bacs).
- [Mercado Pago](http://localhost:8080/wp-admin/admin.php?page=mercadopago-settings).
- [Mailpit](http://localhost:8025/).
- [Guía de prueba](h7-payment-testing.md) y [auditoría MP](h7-mp-audit.md).

No publicar URLs de Order Received con la clave de acceso. Abrirlas desde el propio checkout o pedido autorizado.
