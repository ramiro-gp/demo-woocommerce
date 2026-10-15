# H2 — Compatibilidad WooCommerce y Mercado Pago

**Cierre funcional: 14/10/2026, hora de Argentina.** La combinación elegida completó catálogo, carrito, checkout invitado, pagos aprobado/rechazado/pendiente, actualización sin retorno del comprador, actualización posterior al primer retorno, inventario y devolución efectiva de saldo ficticio. HPOS y Cart/Checkout Blocks siguen aprobados para continuar el proyecto. No se inició H3.

El caso condicional de repetición exacta de una notificación **no se reprodujo** de forma segura. No se presenta como aprobado ni se declara preparación para producción. Toda implementación y Git se mantienen en `C:\Codigo\demo-woocommerce`, branch `main`.

## H1 consolidado

H1 estaba publicado como `1174a8da7d8ed612fafb727dc7c1ca0fa97d8bba`, pero la carpeta canónica estaba vacía. Se clonó el remoto autoritativo `https://github.com/ramiro-gp/demo-woocommerce.git`, se conservaron los volúmenes existentes y se recrearon los contenedores desde la ruta correcta con credenciales locales nuevas. No se copiaron secretos, bases ni runtime desde otra carpeta. La reconciliación y sus pruebas quedaron publicadas con autorización explícita en `17e129b84cb6ca5947593eaf0482e0c9a9e90c4d`. El árbol estaba limpio antes de H2. Ver [validación H1](h1-validation.md).

## Versiones probadas

Selección inicial: 10/10/2026. Pruebas locales: 10/10; sesiones HTTPS y pagos: 13–14/10.

| Componente | Versión | Fuente |
| --- | --- | --- |
| WordPress | 7.1.3 | [Archivo oficial](https://wordpress.org/download/releases/) |
| WooCommerce | 11.2.1 | [Plugin oficial](https://wordpress.org/plugins/woocommerce/) |
| Mercado Pago oficial para WooCommerce | 8.9.6 | [Release oficial](https://github.com/mercadopago/cart-woocommerce/releases/tag/v8.9.6) |
| PHP | 8.4.26 | [Descargas](https://www.php.net/downloads.php), [soporte](https://www.php.net/supported-versions.php) |
| MariaDB | 10.11.19 | Serie 10.11 LTS de [MariaDB](https://mariadb.org/) |
| WP-CLI / Mailpit | 2.12.0 / 1.31.4 | Infraestructura fijada de H1 |

Tags y digests en `compose.yaml` y `config/dependencies.json`; versiones y URLs explícitas de plugins en `config/h2-versions.json`. Ambos plugins pasaron la verificación estricta de checksums de WordPress.org, también al cierre. No se alteró su código ni se usó `latest`.

PHP y MariaDB son provisionales hasta conocer la cuenta Hostinger. Se conserva la infraestructura moderna de H1 y se eleva el límite PHP a 512 MB. No se afirma paridad de Apache local con LiteSpeed del hosting. [Requisitos WordPress](https://wordpress.org/about/requirements/), [requisitos WooCommerce](https://woocommerce.com/document/server-requirements/).

MP 8.9.6 declara HPOS y Blocks; su cabecera cita WooCommerce probado hasta 11.0.1. Se probó 11.2.1 con evidencia real, sin bajar preventivamente por una matriz atrasada. [Declaraciones del paquete](https://raw.githubusercontent.com/mercadopago/cart-woocommerce/v8.9.6/woocommerce-mercadopago.php), [HPOS](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/).

## Configuración y reproducción

- Argentina, `es_AR`, ARS, símbolo nativo `$`, zona horaria `America/Argentina/Buenos_Aires`.
- Invitado habilitado, registro opcional, HPOS con `OrdersTableDataStore`.
- Páginas nativas Cart/Checkout Blocks; Twenty Twenty-Five sin diseño propio.
- Stock gestionado, sin backorders, reserva nativa de 60 minutos.
- Envíos nativos: tarifa fija ARS 500 y retiro local ARS 0.
- Solo WooCommerce y Mercado Pago como plugins funcionales; correo local de H1 a Mailpit.
- Checkout Pro `woo-mercado-pago-basic`, Redirect, una cuota, retorno automático y modo binario desactivado. Otros gateways deshabilitados.
- Sin datos legales, bancarios o credenciales de un comercio real.

| Fixture | SKU | Precio ARS | Stock inicial y final |
| --- | --- | ---: | ---: |
| Simple físico | H2-SIMPLE | 1000 | 5 |
| Variable S / Negro | H2-VARIABLE-S-NEGRO | 1500 | 3 |
| Variable M / Negro agotada | H2-VARIABLE-M-NEGRO | 1500 | 0 |

Atributos globales Talle y Color; IDs consultados mediante APIs nativas. No se creó el catálogo definitivo.

Desde la carpeta canónica, con Docker Desktop abierto y H1 inicializado:

```powershell
.\scripts\h2.ps1 install
.\scripts\h2.ps1 inspect
.\scripts\h2.ps1 inventory
powershell -NoProfile -File .\tests\verify-h2-local.ps1
```

`install` exige versiones exactas, comprueba integridad y crea el fixture mediante WP-CLI en entorno `local`. Conserva stock y credenciales existentes, pero reaplica configuración técnica y deshabilita gateways offline: no ejecutarlo sobre una tienda real. `inventory` crea pedidos técnicos identificados y un producto aislado; nunca contacta la pasarela ni devuelve fondos.

`local` conserva puertos 8080/8025. Las pruebas públicas usaron `h2-public`, proyecto Compose `demo-woocommerce-h2-public`, WordPress 18081 y Mailpit 18026, con base, volúmenes y accesos propios. Es otra instancia del mismo código, no otro clon. Ver [operación HTTPS temporal](h2-temporary-https.md).

## Resultados locales

| Comprobación | Resultado |
| --- | --- |
| Inspector final | 14/14: versiones, plugins, HPOS, configuración, Blocks y fixtures |
| Inventario del core | 66 comprobaciones: reserva, agotado, exceso, cancelación, fallido, reducción única, refund sin pasarela y consultas HPOS |
| Store API final | 10/10: simple y variante, carrito persistente/aislado, agotado y exceso HTTP 400, ambos envíos y preview invitado |
| Preview GET de checkout | `order_id=0`, `checkout-draft`, cliente 0; GET repetido no crea pedido ni modifica stock |
| Pasarela preseleccionada | Tras vincular, Woo puede seleccionar Pro en el preview; el verificador acepta solo Pro disponible o método vacío y conserva las guardas de pedido/stock |
| Persistencia | Recreación de WordPress al iniciar/apagar HTTPS conserva catálogo, vinculación, HPOS, pedidos y refunds |
| Navegador/admin | Carrito y Checkout Block operativos; listado HPOS final con tres pedidos y estados esperados |
| Correo | Mailpit capturó nuevo pedido, procesamiento, cancelación y devoluciones |
| Errores críticos | Sin fatal, parse error, excepción sin capturar ni agotamiento de memoria en el log revisado |

Las pruebas sintéticas del core no acreditan pagos ni replay de notificaciones Mercado Pago. Sus pedidos técnicos permanecen separados de los tres pedidos de checkout reales de prueba.

## Cuentas y autoridad del pago

Se creó DemoWooH2 en la cuenta Developers autorizada. La automatización informó fallo al crear la aplicación del vendedor, pero el panel confirmó que el vendedor de prueba sí existía. Se creó y verificó un comprador distinto, ambos Argentina; no se duplicaron cuentas. El onboarding nativo de WooCommerce generó su propia aplicación para el vendedor. El usuario completó términos, reCAPTCHA y vinculación.

Se contrastaron las identidades de vendedor y pagador en cada operación con el lector oficial. Se siguió la [guía de pruebas WooCommerce](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-test): Modo Ventas con la **cuenta de prueba** vinculada. Algunos recursos informan `live_mode=true`; esa bandera aislada no determina dinero real. Las identidades de prueba verificadas y el saldo ficticio delimitan estas operaciones.

La contraseña del comprador entregada por MCP difería de la del panel autenticado. Se usó la del panel, guardada privadamente. La verificación adicional rechazó el código de prueba en algunos intentos. En el último CONT, la alternativa documentada de los últimos seis dígitos del User ID coincidía con el código ya rechazado; se omitió repetir el mismo valor. No se cambiaron accesos ni se eludió la validación.

El receptor y todos los cambios de estado/stock pertenecen al plugin oficial y WooCommerce. Los lectores de evidencia solo consultan APIs del core y del plugin; no envían pagos, refunds ni notificaciones. El retorno del navegador nunca se tomó como prueba suficiente de aprobación.

## Matriz de pagos ficticios

Importe por pedido: ARS 1000, una unidad H2-SIMPLE y retiro gratuito. Los tres son de invitado y `created_via=store-api`.

| Caso | Resultado observado | Stock / estado |
| --- | --- | --- |
| Aprobado #16 | `approved/accredited` con saldo ficticio; callback HTTP 200, correo, retorno y administración HPOS | `processing`, 5→4 una vez |
| Rechazado #17 | OTHE: `rejected/cc_rejected_other_reason`; UI «Tu tarjeta rechazó el pago» y «Pagar con otro medio». Recuperado el mismo pedido por `order-pay` | Sin pagar; cancelado al volver, stock 5 y reserva 0 |
| Pendiente #19 | CONT quedó en validación de cuenta; se usó Rapipago nativo en el mismo pedido. `pending/pending_waiting_payment`, callback HTTP 200 con Checkout Pro cerrado | Sin pagar, stock físico 5, reserva 1 |
| Confirmación posterior al primer retorno #19 | Desde la actividad del comprador se eligió «Usar otro medio de pago» y saldo ficticio; nuevo pago aprobado para el mismo pedido. Cupón Rapipago `cancelled/by_payer` | `processing`, reserva liberada, 5→4 una vez |
| Reembolso total #16 | Un refund #18, `refunded_payment=true`; pago `refunded/by_admin`, ARS 1000 efectivamente devueltos, callback y correo | `refunded`, 4→5 |
| Reembolso total #19 | Un refund #21 por el plugin oficial, mismo resultado de pago/importe y correo; callback HTTP 200 | `refunded`, 4→5; reserva 0 |
| Notificación repetida exacta, condicional | No reproducida: no había payload original utilizable y el historial MCP rechazó acceso por propietario OAuth distinto | No se afirma idempotencia de replay real |

En #17 el ID rechazado se consultó desde la pantalla del proveedor y se contrastaron referencia e identidades, porque no quedó en el metadato del pedido. No se observó callback de rechazo; la cancelación ocurrió en el retorno nativo. No hubo falso pago aprobado.

### Independencia del navegador y llegada posterior

Horas de Argentina, 14/10 salvo #16 del 13/10:

| Evento | Hora | Evidencia |
| --- | --- | --- |
| Callback aprobado #16 | 23:03:27 | POST oficial HTTP 200 |
| Pago/stock/correos #16 | 23:03:28 | Core y Mailpit |
| Primer retorno #16 | 23:03:32 | GET comprador; estado ya actualizado |
| Callback pendiente #19 | 20:52:16 | Checkout Pro cerrado antes de cualquier retorno; servidor pendiente y reserva 1 |
| Primer retorno #19 | 20:54:43 | «Pedido recibido» nativo; servidor todavía pendiente y sin fecha de pago |
| Cambio nativo de medio y aprobación #19 | 20:59:21 | Callback HTTP 200 y segundo retorno registrados en el mismo segundo |
| Pago/stock/correos #19 | 20:59:22 | Pago acreditado, procesamiento y reducción única |
| Devolución #19 / callback | 21:07:08 / 21:07:09 | Refund oficial, correo y POST HTTP 200 |

El caso tardío es una confirmación válida del mismo pedido después de su primer retorno pendiente, obtenida mediante cambio nativo de medio. No es un reintento de transporte con latencia artificial. El log no permite ordenar dentro del segundo el callback y el segundo retorno; no se afirma esa precisión. La recepción del pendiente con checkout cerrado y la aprobación de #16 antes del retorno prueban por separado actualización independiente del navegador.

La página nativa «Pedido recibido» del pendiente no mostró un aviso explícito de pendiente ni el cupón. El estado servidor siguió sin pagar. Esa limitación de información debe revisarse en el futuro trabajo de experiencia; no se alteró H2 con lógica de pagos o diseño propio.

### Reembolso y límites

Se verificaron por separado el refund registrado, `refunded_payment=true`, el importe devuelto por Mercado Pago, el estado WooCommerce, la reposición y el correo. Cambiar estado o reponer stock por sí solos no acredita devolución de fondos. [Procedimiento oficial](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/manage-orders/refunds).

La confirmación UI de #16 dejó de responder. Antes de ejecutar la alternativa API autorizada, la prevalidación encontró un refund ya completado desde administración y evitó duplicarlo. Para #19, la primera llamada WP-CLI sin usuario falló en `current_user_can('manage_woocommerce')`, antes de enviar el refund externo. Se ejecutó con el administrador local existente mediante `--user`, preservando esa protección, y pasó. No se usó reembolso manual ni se modificó código del proveedor.

Saldo ficticio del comprador restaurado a ARS 10000; tres pedidos finales #16 devuelto, #17 cancelado, #19 devuelto; dos refunds únicos #18/#21. Se consumió uno de los tres pedidos adicionales autorizados. No quedaron cupones Rapipago pendientes ni reservas de stock. El parcial no se probó, por ser opcional.

El receptor oficial consulta la notificación por SDK autenticado antes de actualizar el pedido. No se capturó el cuerpo original ni se habilitó logging sensible. `notifications_history` de la aplicación del vendedor respondió «OAuth ownership validation failed», porque el OAuth de Codex pertenece a la cuenta Developers principal. No se cambió esa autorización solo para el caso opcional ni se inventó un ID/payload. La devolución seguida de callback no repuso stock otra vez; eso no equivale a repetir idéntica notificación.

## Evidencia privada y apagado

Reportes y capturas permanecen bajo `.local/`, ignorados por Git. Entre ellos: `h2-http.json`, `h2-inspect-after-final-stop.json`, `h2-pending-after-browser-close.json`, `h2-pending-after-return.json`, `h2-pending-updated-to-paid.json`, `h2-pending-update-timing.json`, `h2-final-payment-matrix.json`, `h2-final-mail.json`, `h2-final-callbacks.json`, `h2-order-19-final-notes.json` y `h2-final-orders-hpos.jpg`. Los identificadores completos de proveedor, accesos, códigos y URLs de retorno quedan privados.

Al cierre, el helper apagó el proceso propio y restauró `http://localhost:18081`: HTTP 200, ningún cloudflared apuntando al puerto 18081. El inspector posterior pasó 14/14, el Store API pasó 10/10 y se conservaron estados, refunds y stock 5/3/0. No usar enlaces de checkout de la sesión HTTPS anterior.

Se revisaron fuentes/diff, parsers de PowerShell 5.1 y 7, integridad de plugins, exclusiones y ausencia de secretos antes de consolidar H2. El historial Git identifica el commit final; los datos runtime no forman parte de esa entrega.

## Decisiones y pasos previos a producción

Se mantienen HPOS y Blocks, Checkout Pro oficial, métodos de envío nativos y Mailpit. No fue necesario checkout clásico, gateway propio, receptor propio, motor alternativo de inventario ni downgrade. Se corrigieron únicamente herramientas locales y un supuesto del verificador sobre preselección del gateway. La consola mostró una advertencia de dependencia `wc.wcBlocksData`, sin fallo de checkout; no se parcheó el proveedor.

Antes de producción: comprobar PHP/MariaDB y entorno real Hostinger; usar HTTPS estable y repetir la matriz, incluida repetición segura de notificación; revisar las garantías del receptor oficial y la información mostrada al comprador pendiente; configurar identidad comercial y envíos con el usuario, cuenta vendedora correcta y correo real; separar accesos, logs y datos de prueba. No se desplegó ni se modificó producción. H3 requiere una siguiente instrucción del usuario.

## Mercado Pago Integration Review

**Scope**: security — revisión local acotada a H2; no certificación de producción.
**API detected**: Payments API para consultas del plugin; Checkout Pro crea preferencias.
**Products detected**: Checkout Pro oficial WooCommerce.
**Files analyzed**: `.gitignore`, `compose.h2-https.yaml`, `scripts/h2-https.ps1`, `docker/tools/h2-verify.php` y paquete oficial 8.9.6 en volumen (rutas siguientes relativas a `woocommerce-mercadopago/`).

### CRITICAL

- Ningún secreto encontrado en fuentes. La seguridad completa de producción no está certificada por H2.

### WARNINGS

- No se acreditó HMAC `x-signature` en el receptor CoreNotification revisado; usa consulta autenticada de recurso. Conservar el receptor oficial y verificar las garantías vigentes antes de producción.
- `src/Gateways/BasicGateway.php:240` contiene una rama `sandbox_init_point`; no se ejecutó en Modo Ventas probado, que usó `init_point`. No habilitar esa rama basándose en el nombre «prueba».
- Replay real y calidad/homologación oficiales no evaluados.

### PASS

- Credenciales separadas, origen público HTTPS comprobado, estado contrastado en servidor y referencia de pedido nativa.
- Integridad del plugin intacta; dos devoluciones efectivas sin duplicar stock.

### Quality Standards

Not requested for this scope.

### Security checklist (cross-cutting)

| # | Check | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Token fuera del código | Pass | Credenciales nativas en base/volumen privado; barrido de fuentes sin valores privados |
| 2 | `.env` excluido, ejemplo permitido | Pass | `.gitignore:2`, `.gitignore:3`, `.gitignore:5` |
| 3 | HMAC `x-signature` | Not verified | `src/Notification/CoreNotification.php:85–104` consulta SDK; no se agregó receptor propio |
| 4 | Retornos/callback públicos HTTPS | Pass en sesión H2 | Overlay y verificación TLS/origen; localhost HTTP solo tras apagar |
| 5 | Estado contrastado en servidor | Pass | Lector oficial y matriz; `src/Notification/IpnNotification.php:204` |
| 6 | Idempotencia al crear pago | N/A a llamada propia | Checkout Pro gestiona el pago; no existe POST propio de pagos. Refund usa clave en `src/Refund/RefundHandler.php:153` y `:274`; replay no certificado |
| 7 | `external_reference` | Pass | `src/Transactions/AbstractTransaction.php:108`, referencias de los tres pedidos verificadas |
| 8 | Accesos de prueba fuera de entrega | Pass | `.local/` ignorado; plugin/base permanecen en volúmenes locales |
| 9 | Sin `sandbox_init_point` | Partial | Ausente de código propio; rama inactiva del proveedor en `src/Gateways/BasicGateway.php:240` |

### Recommendations

- Repetir validación en hosting equivalente y revisar los puntos no acreditados con la versión oficial entonces elegida.

**Summary**: Official quality: not requested. 6/9 security checks pass; uno no verificado, uno no aplicable a llamada propia y uno parcial.

## Implementation Report

### Verified

- [x] HPOS, Cart/Checkout Blocks, invitado, catálogo, stock y persistencia.
- [x] Aprobado, rechazado, pendiente con navegador cerrado, confirmación posterior al primer retorno y dos devoluciones oficiales.
- [x] Integridad del proveedor, fuentes sin secretos, túnel apagado y datos conservados.

### Needs attention

- [ ] Repetición idéntica de notificación: sin payload/historial accesible durante H2.
- [ ] Información nativa del pendiente, rama sandbox del proveedor y garantías del receptor: referencias anteriores.
- [ ] Calidad/homologación oficiales no solicitadas ni presentadas como cumplidas.

### Blockers (must fix before production)

- [ ] Completar configuración y validación del entorno comercial real; H2 solo acredita viabilidad en prueba.

### Next steps

1. Conservar este cierre H2 como base técnica para la siguiente etapa autorizada.
2. Antes de producción, repetir matriz y resolver los puntos no verificados en HTTPS estable.
3. Re-run the `mp-review` skill after fixes to confirm the report.

### Resources used

- Local: codebase inspection + cross-cutting security floor; pruebas de la matriz H2.
- Skill: `mp-review` v1.0.0, scope security.
- No se llamaron herramientas MCP de calidad ni homologación.

**Scores**: official quality: N/A, 6/9 security. **Verdict**: Needs fixes antes de producción; H2 funcional cerrado.
