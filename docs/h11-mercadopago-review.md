# H11 — Mercado Pago: revisión documental y bloqueo productivo

Consulta: **2026-10-23, America/Buenos_Aires**. **Mercado Pago permanece bloqueado para producción.** H11 no actualiza el plugin histórico 8.9.6, no activa Checkout Pro, no consulta cuentas por API/MCP, no realiza pagos ni reembolsos y no crea receptores alternativos. Se conservan las evidencias H7–H9 y su distinción entre pruebas externas ficticias, reconstrucciones y simulaciones locales.

La distribución limpia H11 **no incluye Mercado Pago**: [build-release.ps1](../scripts/build-release.ps1) empaqueta una lista explícita de theme y cinco plugins propios. [initialize.php](../deployment/initialize.php) rechaza MP activo y deja todos los gateways deshabilitados; [h11-verify.php](../tests/h11-verify.php) comprueba su inactividad. Una instalación nueva que no use MP debe conservarlo ausente o completamente desactivado. Deshabilitar únicamente Pro no evita todos los recursos externos del plugin activo, como ya comprobó H9. No se cambia la instalación histórica TRAMA para lograrlo.

## 1. Publicación oficial nueva y alcance verificable

El [directorio oficial de WordPress.org](https://wordpress.org/plugins/woocommerce-mercadopago/) muestra **versión 8.9.7**, con changelog fechado **22/10/2026**. Los cambios relevantes se identifican aquí una sola vez:

- **U1:** conservar intentos de pago de Checkout Pro para recuperar pedidos pagados antes de su cancelación automática.
- **U2:** consultar el estado antes de autocancelar pedidos sin pago, preservando los pagados y aquellos cuyo estado no se puede confirmar.
- **U3:** evitar credenciales de suscripciones en metadatos/logs y retirar credenciales de diagnósticos de reembolso.

La misma ficha declara WordPress mínimo 6.3, probado hasta 7.1.3 y PHP mínimo 7.4. Ese mínimo no es una recomendación de seguridad ni una declaración de compatibilidad con WooCommerce 11.2.1. **La ficha no anuncia expresamente una solución de firma de origen, replay, orden temporal universal, timestamp diagnóstico o validación independiente de importe/moneda/asociación.** Esta última observación se limita al texto publicado; no prueba ausencia de otros cambios en código. [Ficha y changelog oficial](https://wordpress.org/plugins/woocommerce-mercadopago/).

En paralelo, la [lista oficial de releases de GitHub](https://github.com/mercadopago/cart-woocommerce/releases) sigue marcando **8.9.6 como Latest** y el [CHANGELOG de main consultado](https://raw.githubusercontent.com/mercadopago/cart-woocommerce/main/CHANGELOG.md) comienza por 8.9.6 del 07/10/2026. No se interpreta esta diferencia entre canales como que 8.9.7 sea ficticia ni como que el código de 8.9.6 contenga sus cambios. Se registra **8.9.7 anunciada/publicada en el directorio; paquete, diff y runtime no auditados por H11**. Los accesos públicos al tag/readme 8.9.7 por las rutas SVN/GitHub intentadas no devolvieron contenido utilizable; no se completa ese vacío con suposiciones.

La [cabecera oficial 8.9.6](https://raw.githubusercontent.com/mercadopago/cart-woocommerce/v8.9.6/woocommerce-mercadopago.php) sigue declarando `WC tested up to: 11.0.1` y compatibilidad HPOS/Cart-Checkout Blocks. Corresponde a la versión histórica auditada, no a 8.9.7. Actualizar por número de versión sin auditar y repetir las pruebas pertinentes no cierra el bloqueo.

## 2. Evidencia histórica que se conserva

| Referencia | Qué acredita y qué limita |
| --- | --- |
| [Auditoría H7](h7-mp-audit.md) | Receptor/SDK, preferencias, metadatos y declaraciones del proveedor. Scope security; no certificación productiva |
| [QA externo H7](h7-external-qa.md) | Operaciones externas con cuentas ficticias; retornos, callbacks, stock e importes de casos concretos. No replay idéntico original universal |
| [Concurrencia y notificaciones H7](h7-concurrency-audit.md) | Corrección acotada de sesión con Commerce Order Integrity; límites de timestamp y temporalidad reproducidos y explicados |
| [Seguridad H9](h9-1-security-hardening.md#mercado-pago-diagnóstico-productivo) | Relectura de 8.9.6 instalado, hashes/checksums; riesgos de origen, replay, estados, conciliación, compatibilidad y privacidad persistentes |
| [Hashes H9](evidence/h9-1/mp-source.json) | Identidad de archivos 8.9.6 analizados; no corresponde a 8.9.7 |
| [MP activo H9](evidence/h9-1/mp-active.json) / [inactivo en clon H9](evidence/h9-1/mp-inactive.json) | Carga de recursos con Pro apagado y retirada al desactivar completamente en clon; no mide todos los tratamientos de scripts remotos bloqueados |
| [Preparación H9](h9-production-readiness.md) | Bloqueos previos independientes del cierre técnico local |

Las rutas de código del proveedor citadas abajo son relativas a su plugin runtime, registradas por H7/H9. No se copian archivos del proveedor al repositorio ni se afirman líneas de 8.9.7. H11 no reejecuta aquellas transacciones ni presenta esa evidencia como prueba nueva.

## 3. Matriz de riesgo y aceptación

La severidad describe la decisión de lanzamiento para este proyecto. **Alta/bloqueante no significa explotación o fraude demostrados.** Responsables: **MP** = fabricante/soporte oficial; **DEV** = administrador técnico que valida la versión y el despliegue; **COM** = titular del comercio que aprueba condiciones y operación. «Sin anuncio específico» se refiere al changelog consultado, no a una auditoría negativa del paquete nuevo.

| Problema | Evidencia H7–H9 | Severidad | Responsable | Actualización oficial disponible | Prueba pendiente | Criterio de aceptación |
| --- | --- | --- | --- | --- | --- | --- |
| Autenticidad/origen de notificaciones | H9, `Notification/*`: sin verificación `x-signature`/`x-request-id`/HMAC en los tres receptores 8.9.6. SDK autentica el recurso consultado, no el request entrante | **Alta / bloqueante** | MP mecanismo soportado; DEV verifica | Sin anuncio específico | Examinar receptor efectivo de la versión candidata y respuesta de soporte; eventos válidos y solicitudes con firma ausente/alterada mediante procedimiento autorizado | Garantía oficial verificable, implementada en el flujo usado, y rechazo sin efectos de entradas inválidas; no sólo HTTP 200 ni consulta posterior |
| Replay y deduplicación | H7: reintento auténtico/reconstrucciones sin doble reducción financiera; evento externo original idéntico no reenviado. H9: sin ledger/ventana de evento observados | **Alta / bloqueante** | MP y DEV | Sin anuncio específico; U1 no acredita deduplicación | Obtener evento original mediante acceso legítimo; reenvío oficial controlado y concurrencia; comparar estado, stock, transacción, notas, emails y metadatos | Un solo efecto financiero/stock/correo ante duplicados; no reapertura indebida ni dependencia del orden de llegada; diagnóstico repetible |
| Timestamp de metadatos | `Helpers/PaymentMetadata.php:119`: cuando falta fecha del recurso, usa recepción actual; H7 observó cambio `[Date …]` | **Media; pendiente de resolver/documentar para MP** | MP; DEV valida semántica | Sin anuncio específico | Dos entregas separadas más de un segundo del mismo recurso sin fecha; comparar metadatos completos | Fecha de evento estable o campo de recepción separado documentado; no declarar idempotencia byte a byte si cambia el diagnóstico |
| Orden temporal y eventos tardíos | `OrderStatus.php:453`: protección processing/completed; simulación H7 `cancelled → pending` con recurso obsoleto. No fue una entrega externa obsoleta demostrada | **Alta / bloqueante** | MP y DEV | U1/U2 tratan recuperación/autocancelación; **no** acreditan orden temporal general | Matriz de estados finales, pendiente/aprobado retrasados, consulta fallida, reintentos y pedidos distintos | Corrección/garantía oficial para transiciones válidas; no regresión injustificada de finales y preservación de conciliaciones legítimas |
| Importe independiente | `AbstractNotification.php:66–99`: referencia del recurso consultado sin contraste independiente contra total esperado antes de estados | **Alta / bloqueante** | MP; DEV verifica; COM concilia | Sin anuncio específico | Casos controlados de total, descuentos, envío, impuestos, redondeo y pago que no corresponde al total | No marcar pagado ni reducir nuevamente ante discrepancia; conciliación explícita de importes del pedido/recurso |
| Moneda independiente | Mismos manejadores; lecturas adicionales de QA verificaron ARS para casos concretos, no una garantía del receptor | **Alta / bloqueante** | MP y DEV | Sin anuncio específico | Recursos con moneda discrepante y conversiones sólo si el comercio las usa | Coincidencia de moneda esperada o conversión oficial explícita y conciliable; rechazo de discrepancia no autorizada |
| Asociación pago/preferencia/pedido | Resolución por `external_reference`; H9 no acredita vinculación previa independiente, método actual y destinatario en todos los caminos | **Alta / bloqueante** | MP y DEV | U1 conserva intentos; no acredita todos los vínculos | Cruces controlados entre dos pedidos, dos intentos, otra referencia/destinatario y método | Sólo el pago legítimamente asociado afecta el pedido correcto; sin afectar otra compra ni otro comercio |
| Autocancelación con pago existente/incierto | Riesgo operativo de eventos tardíos de H7; no se inventa una nueva reproducción del caso concreto que motiva 8.9.7 | **Alta / pendiente** | MP corrige; DEV prueba; COM define conciliación | **U1/U2 directamente relevantes**, aún sin validación local | Pedido pagado antes del job; intento previo pagado; recurso inaccesible; respuesta incierta; nuevo intento | No cancelar un pagado ni inferir impago por error de consulta; recuperación y cola conciliables, sin duplicar efectos |
| Compatibilidad WP/WC/PHP/HPOS/Blocks | 8.9.6 probado por fabricante hasta WC 11.0.1; proyecto usa WC 11.2.1. QA anterior no amplía soporte | **Alta / condicional al gateway** | MP y DEV | Ficha 8.9.7 actualiza WP probado; WC del paquete nuevo **no verificado** | Leer cabeceras/changelog/diff; QA del conjunto exacto, HPOS, Blocks, Order Pay, sesión, reintentos y jobs | Compatibilidad explícita suficiente y flujo completo aprobado en versión concreta; sin sobrescribir datos comerciales |
| Recursos externos/cookies con gateway apagado | H9: loaders SDK/seguridad/comportamiento/telemetría con Pro disabled. Desactivación completa en clon retiró recursos observados | **Alta / decisión de privacidad y operación pendiente** | MP; DEV inventaría; COM aprueba tratamientos | Sin anuncio específico; U3 no significa ausencia de telemetría | Navegador limpio, red/cookies/almacenamiento por página con plugin activo/inactivo y nueva versión | MP ausente/inactivo cuando no se ofrece; si se ofrece, inventario efectivo, minimización y tratamiento aprobado; sin afirmar cero tratamiento por bloquear requests |
| Logs, diagnósticos y secretos | H9 advierte payloads/queries/metadata en logs y telemetría; no demuestra exfiltración de tarjetas o credenciales en este proyecto | **Alta si expone secretos; revisión pendiente** | MP; DEV configura; COM fija retención | **U3 relevante** para rutas anunciadas; alcance restante pendiente | Revisar diff y salida sintética de logs en rutas de error/refund/suscripción, sin cobros nuevos en H11 | Sin tokens/cookies/order keys/datos privados innecesarios; acceso y retención restringidos; no publicar dumps |
| Sesión pendiente alteraba método | H7 corrigió con Commerce Order Integrity, REST/hidratación y pruebas de dos pestañas; no era un parche del receptor MP | **Controlado en versión probada; regresión requerida** | DEV; WC/MP según causa futura | No sustituir la extensión sólo por U1 | Repetir regresión acotada al cambiar WC/MP; conservar Order Pay/reintento autorizado | Pedido MP original intacto mientras otra compra usa BACS; sin bloquear el reintento legítimo |

La guía específica [WooCommerce/IPN](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-configuration/notifications) sigue presentando configuración IPN en la consulta actual. La guía general de [Webhooks](https://www.mercadopago.com.ar/developers/es/docs/your-integrations/notifications/webhooks) describe validación HMAC y sus SDK. Esta diferencia documental **no instala un verificador en 8.9.6**. No se cambia `source_news`, URL o parser para simular soporte firmado ni se implementa otro receptor durante H11.

## 4. Decisión operativa y alternativas informativas

1. **En H11:** MP ausente de la nueva distribución, gateways inicialmente apagados, cero credenciales y cero pagos reales. La DEMO histórica conserva su instalación y pruebas. H11 no constituye un permiso de activar Pro en ninguna cuenta.
2. **BACS futuro:** alternativa nativa ya validada localmente, con cuenta bancaria auténtica del comercio, instrucciones aprobadas y responsable de conciliación. El pedido en espera no acredita fondos; verificar acreditación, importe y referencia en el banco antes de procesar/entregar. Una captura del comprador no sustituye la comprobación. También requiere resolver correo, legales y aprobación de lanzamiento. [BACS oficial](https://woocommerce.com/document/bacs/).
3. **Cobro al entregar/retirar:** considerar sólo si operación y logística lo permiten. COD nativo marca `processing` aun antes de cobrar; no usar ese estado como comprobante bancario. Requiere evaluación y QA futura; H11 no lo habilita. [COD oficial](https://woocommerce.com/document/cash-on-delivery/).
4. **Otra pasarela oficial:** evaluación posterior de disponibilidad para la entidad/país, moneda, costes, liquidaciones, devoluciones, autenticidad, callbacks, compatibilidad y privacidad. No se instala ni se recomienda una marca sin investigación específica y contrato del comercio. Un link de pago externo requeriría conciliación y asociación manual explícitas; no es una confirmación automática de WooCommerce.

Si un comercio existente tuviera operaciones MP pendientes, desactivar su plugin podría impedir callbacks/conciliación; haría falta inventario y cierre de operaciones antes de cambiarlo. Esta distribución nueva no tiene ese historial. No restaurar bases históricas, tokens o pedidos QA para iniciar un cliente.

## Mercado Pago Integration Review

**Scope**: security/documental H11, revisión de publicaciones y evidencias conservadas; sin nueva certificación runtime.
**API detected**: Preferences para Checkout Pro y Payments/merchant-order mediante SDK, según H7/H9; sin Orders API propia.
**Products detected**: Checkout Pro del plugin oficial, bloqueado; BACS es gateway nativo independiente.
**Files analyzed**: informes y evidencias enlazados, `.gitignore`, `config/h2-versions.json`, `scripts/build-release.ps1`, `deployment/initialize.php`, `tests/h11-verify.php`; fuentes oficiales públicas indicadas. El código runtime 8.9.6 fue examinado en H7/H9, no reauditoría de 8.9.7.

### CRITICAL

- Bloqueos de origen/replay, temporalidad, validación financiera independiente y compatibilidad siguen sin aceptación suficiente para habilitar MP.

### WARNINGS

- 8.9.7 publicada en WordPress.org con mejoras pertinentes, mientras el canal GitHub consultado sigue en 8.9.6; falta paquete/diff/runtime verificados.
- Deshabilitar sólo el método Pro no evita todas las cargas del plugin activo.

### PASS

- Distribución con lista explícita de paquetes propios que excluye MP; inicialización rechaza MP activo.
- Evidencias y proveedor históricos conservados; revisión sin pago ni cuentas por API/MCP.

### Quality Standards

Not requested for this scope. `quality_checklist`, `quality_evaluation` y `form_homologation` no ejecutados; no hay ID nuevo ni puntuación o certificación oficial.

#### Required fields

No evaluados por MCP en H11; no se inventan filas de cumplimiento.

#### Best practices

No evaluadas por MCP en H11.

### Security checklist (cross-cutting)

| # | Check | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Tokens fuera de código entregable | Pass del diseño de distribución; credenciales runtime no consultadas | Lista explícita de paquetes propios; H7/H9 documentan opciones nativas privadas, no variables/token hardcodeados propios |
| 2 | `.env` ignorado, `.env.example` permitido | Pass | `.gitignore` leído; `.local`, secretos, DB y logs excluidos |
| 3 | HMAC `x-signature` | Missing en 8.9.6 histórico; 8.9.7 no verificado | Matriz de origen y hashes H9 |
| 4 | HTTPS retorno/callback | Evidencia QA histórica; producción pendiente | H7 externo; no hosting público validado en H11 |
| 5 | Estado validado por servidor | Partial histórico | SDK consulta recurso; controles financieros independientes pendientes |
| 6 | Idempotency key al crear pago | N/A a implementación propia | Se usa Preferences del proveedor; no creador propio de pagos/orders |
| 7 | `external_reference` | Partial histórico | Se envía/resuelve, pero asociación independiente pendiente |
| 8 | Credenciales QA fuera de entrega | Pass del empaquetado; no exportación de DB | Release de código sin `.local` ni opciones/runtime |
| 9 | No usar `sandbox_init_point` | Partial histórico, no usado por el flujo seleccionado | H7 documenta rama del proveedor y selección oficial `init_point`; no parcheada |

### Recommendations

1. Mantener bloqueo y entregar MP ausente/inactivo.
2. Obtener paquete/diff oficial identificable y resolución del fabricante por cada fila pendiente; no interpretar U1/U2/U3 como cierre general.
3. Autorizar posteriormente QA aislada pertinente con cuentas ficticias verificadas, conservando producción separada; sólo después evaluar habilitación comercial explícita.

**Summary**: Official quality: not requested. Sin puntuación nueva S/9: los controles runtime se apoyan en evidencia histórica y no se reevaluó el paquete 8.9.7.

---

## Implementation Report

### Verified

- [x] Consulta pública de WordPress.org 8.9.7 y de GitHub 8.9.6, con diferencia entre canales documentada.
- [x] Actualización anunciada U1/U2/U3 diferenciada de corrección comprobada.
- [x] Evidencia H7–H9 y bloqueo productivo conservados; sin modificación del proveedor histórico.
- [x] MP excluido de la lista de distribución limpia; guardas de inicialización/inactividad presentes.

### Needs attention

- [ ] Auditar paquete/diff de la versión candidata e identificar su compatibilidad WC/HPOS/Blocks y su procedencia.
- [ ] Validar recuperación/autocancelación y redacción de logs donde el proveedor anuncia cambios.
- [ ] Resolver semántica del timestamp diagnóstico y privacidad de los recursos externos efectivos.

### Blockers (must fix before production)

- [ ] Garantías soportadas y pruebas de autenticidad/origen, replay y estados tardíos.
- [ ] Validación independiente de importe, moneda y asociación del pago/pedido.
- [ ] Compatibilidad del conjunto exacto y aceptación del titular; infraestructura/correo/legal/lanzamiento todavía requieren sus propios cierres H11.

### Next steps

1. Revisar con soporte oficial las filas bloqueantes y obtener versión/mecanismo verificables.
2. Auditar y probar esa versión en entorno aislado autorizado; conservar el bloqueo hasta aceptación documentada.
3. Repetir `mp-review` del alcance que se autorice antes de decidir producción; una puntuación MCP tampoco sustituiría estos controles.

### Resources used

- Local: documentación/evidencias H7–H9 y preparación de distribución H11; sin nueva prueba de pago.
- Web: fuentes oficiales enlazadas, consultadas 2026-10-23; fechas editoriales distintas declaradas donde están disponibles.
- Skill: `mp-review` v1.0.0, aplicado al alcance security/documental, sin operaciones MCP.

**Scores**: official quality: N/A; seguridad runtime nueva: N/A, sin extrapolar resultados históricos. **Verdict**: **Blocked para Mercado Pago en producción**. H11 no autoriza cobros reales.
