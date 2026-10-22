# H9.1 — Seguridad y hardening

Auditoría local iniciada el **21/10/2026, America/Buenos_Aires**; evidencias con timestamp UTC del 22/10. H8.2 `2b998dc55f0ece7757248848b9f90d89365b4131` consolidado por fast-forward y push, `main = origin/main`. H9 se trabaja únicamente en `codex/h9-security-performance-seo`. Sin despliegue, pagos, cambios de DNS ni H10/H11.

## Inventario verificado

[Inicial](evidence/h9-1/inventory-before.json) / [posterior](evidence/h9-1/inventory-after.json), obtenidos de WordPress y MariaDB reales mediante `h9-inventory.php`, sin valores personales ni secretos.

| Componente | Instalación / mantenimiento y decisión |
| --- | --- |
| WordPress | 7.1.3, última estable consultada; fixes oficiales de seguridad del 6/10 ya incluidos. Sin actualización de core. Checksums oficiales correctos; aviso esperado por `wp-config-docker.php`, archivo de la imagen Docker. |
| PHP Apache y CLI | **8.4.26 → 8.4.27**, reconstrucciones oficiales fijadas por digest; actualización necesaria por aviso crítico del 21/10. Misma serie 8.4. |
| Apache | **2.4.68 → 2.4.69**, paquete Debian `2.4.69-1~deb13u1` incluido en la reconstrucción oficial de WordPress, sin instalar paquetes manualmente. Revisión de [avisos Apache](https://httpd.apache.org/security/vulnerabilities_24.html): fixes de 2.4.69 incluidos; no se presume explotación de módulos no utilizados. |
| WooCommerce | 11.2.1, activo; HPOS y Cart/Checkout Blocks. Incluye fixes 11.2 de descargas, Cc/Bcc de recuperación y capacidades de shop managers. Sin actualización indiscriminada. |
| MariaDB | 10.11.19-MariaDB-ubu2204; rama LTS mantenida hasta febrero de 2028. Sin puerto publicado, sin migración. |
| Mercado Pago | 8.9.6 activo; todos sus gateways deshabilitados. Última release oficial encontrada; probado por fabricante hasta WC 11.0.1, inferior a WC instalado. Sin modificación ni actualización. |
| Propios activos | Core 1.1.1 → **1.1.2**; Order Integrity, Communications, Account Navigation y Lookbook 1.0.0. Mantenimiento en este repositorio; ninguno sustituye autenticación de WC. |
| Theme | Commerce Base 1.7.0, sin parent; fuentes locales Manrope/Newsreader y JS/CSS propios sobre bloques nativos. |
| Inactivos | Akismet 5.7.2, Hello Dolly 1.7.2, Twenty Twenty-Five 1.5, Twenty Twenty-Four 1.6 y Twenty Twenty-Three 1.7. No se borraron. Revisar necesidad antes de distribución. |
| Otros | MU Local Mail Capture; WP-CLI 2.12.0; Mailpit 1.31.4. Mailpit 1.31.5 disponible con fixes de parser/búsqueda; no se identificó una vulnerabilidad aplicable nueva a 1.31.4 que justifique mezclar esa actualización. |

**Servicios del proyecto:** WordPress Apache `127.0.0.1:8080`, Mailpit UI `127.0.0.1:8025`, SMTP 1025 sólo interno, MariaDB 3306 sólo interno y WP-CLI efímero. Volúmenes nombrados separados. No socket Docker dentro de WordPress. H2 y H8 de auditoría no se iniciaron. Otros proyectos Docker del equipo quedan fuera del alcance.

**Clon H9:** `demo-woocommerce-h9-audit`, WordPress `127.0.0.1:8199`, Mailpit `127.0.0.1:8299`, DB/HTML/correos propios. Copia de backup privado `h9-baseline`; URLs adaptadas mediante search-replace serializado sólo en el clon. No reutiliza el volumen de principal. Fixtures, claves de pedido, cookies, nonces, HTML administrativo y trazas crudas quedan en `.local/`, ignorado. MU de auditoría bloquea HTTP externo de WordPress y cron; el runner bloquea HTTPS externo en navegador. No equivale a un aislamiento de red de kernel ni se usa para ejecutar pagos.

## Superficie y revisión de código propio

| Módulo / entrada | Autorización, datos, persistencia y conclusión |
| --- | --- |
| Core / copia de dirección | Sólo `get_current_user_id()` y `WC_Customer`; no acepta ID ajeno. JSON con HEX escapes; campos permitidos explícitos. No endpoint ni escritura propia; validación/nonce/guardado nativos de WC. |
| Legal / POST público de página 514 | Nonce más token HMAC propio ligado a cookie HttpOnly/Lax, aleatorio de 192 bits y vencimiento. No es autenticación de identidad. Código aleatorio de 80 bits; ningún código otorga acceso a datos. SQL preparado, límites por email/IP con HMAC y ventanas de una hora; honeypot, límites de tamaño y tipos. Referencia declarada nunca consulta pedidos públicamente. |
| Legal / `admin-post.php` acciones `commerce_legal_manage` y `commerce_legal_settings` | Capacidad efectiva `manage_woocommerce` o `manage_options`, más nonce; nonce de gestión ligado al caso. Estados enumerados, notas sanitizadas y salida escapada. Vinculación ahora exige además `edit_shop_order` con ID efectivo y se valida bajo bloqueo de fila. Ajustes sólo aceptan páginas publicadas editables. |
| Legal / DB y privacidad | InnoDB, unicidad código/token, transacción solicitud+evento, CAS por destinatario para correo, trazabilidad. Exportador paginado por email en circuito autorizado de WordPress; supresión informa revisión de conservación, sin borrar automáticamente datos legales. Sin subida de archivos. |
| Order Integrity | Hooks REST/hidratación soportados, Nonce o Cart-Token soportado; separa punteros de la sesión elegida por Store API. No registra rutas, escribe pedidos ni altera stock. No concede acceso a pedidos; autorización de orden sigue en WC. Revisión completa y regresión representativa; [concurrencia H7](h7-concurrency-audit.md) conserva evidencia más amplia. |
| Communications | Presentación CSS/copy de emails; `view_order` vuelve a exigir capacidad con ID. No nuevos destinatarios, transporte, disparadores, SQL, rutas ni escrituras. |
| Account Navigation | Opción nativa de ajustes WC, sin endpoints ni cambios de permisos de descarga. Nonce/capacidades y persistencia a cargo de WC. |
| Lookbook | GET REST `commerce-lookbook/v1/products`: `edit_products` y `edit_posts`/`edit_pages`, con límite y tipos. Productos publicados, sin contraseña y no ocultos; precios HTML con KSES, texto/URLs/atributos escapados y posiciones acotadas. Guardado editorial nativo. Sin uploads propios. |
| Theme | Revisión de inputs, shortcodes, bloques y salida en `functions.php` y `inc/`; consultas acotadas y APIs WP/WC, sin receptor de pagos ni acceso paralelo a cuentas. Showcase limitado a local/admin y noindex. Menús y contenido editorial autorizados usan los permisos del CMS. |

Autenticación, registro, recuperación, cambio de contraseña, perfil, direcciones y `view-order` siguen en WC/WordPress, sin filtros que salteen autorización. REST de clientes/pedidos exige credenciales/capacidades; Store API pública entrega el carrito de su sesión, no listados privados. No se encontró SQL concatenado con entrada no confiable en plugins propios. No se promete ausencia universal de vulnerabilidades.

## Hallazgos y correcciones

| ID / severidad | Evidencia y decisión |
| --- | --- |
| SEC-01 / **crítica, tercero aplicable** | PHP anunció CVE-2026-109618 y CVE-2026-109619: afectan peticiones web antes de código de aplicación, especialmente mod_php. La instalación tenía 8.4.26. Se descargaron imágenes oficiales, se comprobó 8.4.27, se probaron en clon y luego se recreó sólo WordPress principal con el digest corregido. No se ejecutó exploit ni se aplicaron mitigaciones peligrosas de memoria ilimitada. |
| SEC-02 / **media, propio confirmado** | `wpdb::print_error()` escribe SQL completo aun con `WP_DEBUG_DISPLAY=false`. Inyección controlada de fallo de tabla en clon: un marcador sintético del nombre apareció en `/tmp/wordpress-debug.log`. [Antes](evidence/h9-1/storage-before.json): `private_marker_logged=true`; [después](evidence/h9-1/storage-after.json): `false`, conservando error recuperable. `private_query()` suprime temporalmente el log de consultas con PII, restaura el estado en `finally` y emite sólo diagnóstico genérico. Cubre inserción, eventos/notas y exportación/supresión por email. No oculta errores al solicitante. |
| SEC-03 / **baja, autorización en profundidad** | La vinculación legal confiaba sólo en `manage_woocommerce` y comprobaba cambio contra una fila anterior al lock. Se exige capacidad efectiva de editar el pedido y se comprueba la asociación sobre la fila bloqueada. Un rol QA con `manage_woocommerce`, nonce válido y sin permiso de pedidos recibe 403. No se demostró acceso de un cliente a esa ruta ni una explotación concurrente previa; no se presenta como escalada pública. |
| SEC-04 / **baja, configuración** | Archivos sensibles no tenían prohibición HTTP explícita: `wp-config.php` respondía 200 vacío. Ahora config, dotfiles y backups responden 403, se impide ejecución PHP en uploads y listados de directorio. No existían dumps públicos identificados. No se afirma una fuga anterior del contenido de wp-config. |
| SEC-05 / **baja, configuración** | Se agregan nosniff, SAMEORIGIN y CSP limitada a frame-ancestors/object-src/base-uri; Referrer-Policy por defecto preservando `no-referrer` legal. Sin allowlist arbitraria de scripts, sin HSTS en HTTP local. Se desactiva `expose_php`. Apache headers se carga explícitamente sólo si falta. |
| SEC-06 / **baja, robustez** | Cookie legal de tipo array ya no produce conversión/warning; ajustes legales rechazan `pages` mal formado y referencias que no sean páginas editables. Se retiran sólo métodos XML-RPC de pingback, manteniendo autenticación XML-RPC y REST/Store API. Local fuerza noindex aunque se altere `blog_public`; producción conserva control nativo. |

Permisos observados: archivos 0644 y directorios 0755, `wp-config.php` 0644 dentro del volumen, binds propios read-only. Salts generados válidos y únicos por instalación inicial, sin valores en evidencia. En hosting compartido, restringir wp-config según usuario/grupo real; no aplicar chmod que impida leer PHP. El código/volumen sigue escribible por PHP para operaciones nativas en DEV: H11 deberá limitar edición/instalación según despliegue real. Actualizaciones automáticas detenidas explícitamente en DEV reproducible; producción requiere política de parches propia.

## Cuentas, pedidos y sesiones: evidencia focalizada

[Navegador](evidence/h9-1/browser-security.json): **42 checks** con clientes A/B, subscriber, shop manager, administrador y rol de legales restringido. Propios accesibles; pedidos del otro cliente y REST de otro cliente denegados; copia de dirección usa sólo propietario aunque se inyecte `user_id`; clientes no ejecutan gestión/configuración; CSRF público/admin y cambio de ID con nonce ajeno rechazados; payload XSS no ejecuta; recuperación nativa no revela datos del pedido. Nonce válido no reemplaza capacidad efectiva del pedido.

[Sesiones](evidence/h9-1/session-check.json): cookie auténtica aceptada, token revocado y cookie vencida rechazados por APIs soportadas. HTTP local no marca Secure, deliberadamente; en HTTPS real WP/WC deben generar cookies Secure y el proxy debe informar TLS correctamente. No se fuerza Secure en localhost ni se confía universalmente en headers Forwarded. Formularios con nonce/token inválido devuelven 403 y guía para recargar.

[Checkout HTTP](evidence/h9-1/checkout.json) y [persistencia](evidence/h9-1/verify-checkout.json): carrito independiente, producto/cupón QA, envío nacional, ARS, BACS `on-hold`, HPOS, bandera nativa de reducción y cupón/envío persistidos. Solicitud con clave de orden falsa rechazada por autorización nativa. Correos capturados sólo en Mailpit; no pago real ni preferencia MP. La primera aserción del runner esperaba un código HTTP sin incluir todos los campos requeridos: se corrigió la prueba para alcanzar efectivamente la autorización; el resultado publicado corresponde a la ejecución válida.

Inventarios principal antes/después: mismos hashes y conteos en **11 tablas**, incluidos 235 pedidos, 10 usuarios, 444 posts, productos/metadatos, direcciones, ítems, 59 solicitudes y 210 eventos legales. Ningún histórico comercial se alteró por QA. No se repitió la matriz de 122 pruebas de H8.2; [su auditoría](h8-2-final-audit.md) conserva pruebas de deduplicación concurrente, fallo de correo, recuperación, instalación y restauración. Retención de notas y correos requiere plazo definido por el comercio, no limpieza indiscriminada.

## Mercado Pago: diagnóstico productivo

Se releen los manejadores reales, fábrica, scripts, metadata y estados; [hashes instalados](evidence/h9-1/mp-source.json). WP-CLI verificó checksums oficiales de WC y MP **2/2**. No se editaron archivos del proveedor. La [release oficial 8.9.6](https://github.com/mercadopago/cart-woocommerce/releases/tag/v8.9.6) sigue marcada Latest; sus cambios publicados no corrigen estos bloqueos. Que figure `update=none` en WP-CLI por sí solo no prueba esa conclusión: se contrastó la publicación.

| Riesgo | Estado y evidencia H9 |
| --- | --- |
| Origen / autenticidad | Confirmada ausencia de verificación `x-signature`/`x-request-id`/HMAC en los tres receptores instalados. Core consulta recurso con SDK autenticado; eso autentica el recurso consultado, no el origen HTTP. La [documentación WooCommerce](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-configuration/notifications) aún indica IPN, mientras [Webhooks generales](https://www.mercadopago.com.ar/developers/es/docs/checkout-pro-preferences/additional-content/notifications/webhooks) describe firma. No se encontró configuración que incorpore ese verificador a este plugin. |
| Replay | Sin ledger persistente/ventana de edad de evento observados. H7 ya acreditó reconsultas y reducción única, con límites. H9 no ejecutó un replay externo original ni lo da por resuelto. |
| Timestamp | `Helpers/PaymentMetadata.php:119` conserva fallback a hora de recepción cuando falta `date`. Persiste causa del cambio de metadata demostrado en H7; no equivale a idempotencia byte a byte. |
| Orden temporal / tardíos | `OrderStatus.php:453` sólo excluye processing/completed. Persisten límites demostrados por simulación H7 sobre cancelled→pending. No se afirma que el proveedor haya devuelto externamente un estado obsoleto. |
| Importe/moneda/asociación | `AbstractNotification.php:66–99` resuelve external_reference de recurso autenticado, sin comparación independiente contra importe/ARS/preferencia previamente vinculada antes de delegar estados. No se ensayaron pagos alterados ni se demuestra cobro fraudulento. Riesgo productivo sin garantía suficiente. |
| Compatibilidad | HPOS/Blocks declarados, con WC tested up to 11.0.1. Pruebas locales 11.2.1 no amplían soporte del proveedor. |
| Privacidad | Con Pro disabled siguen loaders SDK, seguridad, comportamiento, SuperToken y telemetría en carrito/checkout. Confirmado nuevamente, abajo. |

**Alternativas:** (1) BACS manual y plugin MP completamente inactivo cuando no se ofrece: retiro efectivo de sus scripts, gateways, onboarding, callbacks y cron; un negocio con operaciones MP pendientes necesitaría conciliación antes de hacerlo. (2) Mantener plugin activo/Pro disabled como esta demo: conserva integración histórica, pero no resuelve privacidad ni permite cobrar. (3) Versión oficial corregida/garantía de soporte y nueva QA integral, actualmente pendiente. (4) Elegir posteriormente otra integración oficial tras evaluación; fuera del alcance H9. No hay parche local, gateway propio, HMAC inventado ni receptor paralelo.

## Privacidad y recursos externos

[Plugin activo](evidence/h9-1/mp-active.json): 28/29 scripts locales MP en carrito/checkout y **13 rutas externas distintas intentadas** en la sesión, con todos los HTTPS externos interceptados. Incluye `sdk.mercadopago.com`, `www.mercadopago.com/v2/security.js`, `http2.mlstatic.com` y beacons `api.mercadopago.com`. Cookie `mp_st_variant` presente, storage `_mp_flow_id`/claves del proveedor. La fuente de beacons construye datos de versión, plataforma, tema, seller/site ID y ubicación; el manejador de error Melidata puede incluir query string. Scripts ejecutados en la página podrían leer su DOM; no se declara captura efectiva de datos de tarjetas o de esos campos.

[Desactivado sólo en clon](evidence/h9-1/mp-inactive.json): **0 scripts MP, 0 rutas externas, sin `mp_st_variant` en contexto nuevo**; BACS sigue renderizando. Luego se reactivó el plugin en el clon sin habilitar Pro. Principal mantuvo MP activo en todo momento. La desactivación oficial de WordPress es una alternativa operativa comprobada, no una opción granular de consentimiento manteniendo activo 8.9.6. No se halló tal opción granular soportada.

Las llamadas se bloquearon antes de salir: no representan bytes efectivamente enviados ni describen todos los tratamientos del código remoto. Algunos beacons/errores son provocados por ese bloqueo. IP/UA/referrer/cookies dependerán de las políticas reales si se habilitan esos servicios. [H8.2](h8-2-external-resources-audit.md) mantiene inventario anterior y limitaciones. No se consultaron credenciales ni historial de cuentas por MCP, no hay `payment_id` nuevo ni evaluación oficial de calidad.

Atribución WC y tracking general continúan `no`; sin cookies `sbjs_*` nuevas. Cookies reales inventariadas sin valores: sesión/carrito WC, cookie legal de sesión HttpOnly/Lax y variante MP. Apariencia/cooldown usan almacenamiento local; WP/Store API emplea claves técnicas. Gravatar/emojis remotos pueden aparecer según página/usuario, sin servicios nuevos. Los logs Apache ya omiten queries/referrer/cookies; conservan IP/ruta y requieren rotación/retención. Debug se guarda fuera de webroot. Logs WC/MP pueden contener payloads de recursos/pedidos si se habilita debug: no publicar dumps y revisar configuración/retención antes de producción. No se instala un banner de consentimiento para sustituir este diagnóstico.

## Avisos públicos y límites

| Fuente primaria consultada | Clasificación |
| --- | --- |
| [PHP aviso crítico](https://www.php.net/archive/2026.php), [8.4.27](https://www.php.net/releases/8_4_27.php) | Vulnerabilidades aplicables, corregidas con reconstrucción oficial. |
| [WordPress 7.1.3](https://wordpress.org/news/2026/10/wordpress-7-1-3-maintenance-and-security-release/), [WC 11.2](https://developer.woocommerce.com/2026/10/07/woocommerce-11-2-0-release-notes/) | Alertas anteriores corregidas en versiones instaladas; no tratadas como explotables aquí. |
| [PHP soporte](https://www.php.net/supported-versions.php), [MariaDB](https://mariadb.org/about/) | Series mantenidas; no certifica ausencia de avisos futuros. |
| [Mailpit GHSA-2vgv-6hcp-mf43](https://github.com/axllent/mailpit/security/advisories/GHSA-2vgv-6hcp-mf43) | Afecta hasta 1.31.1, corregida 1.31.2; no aplicable a 1.31.4. UI loopback/SMTP no publicado, sin envíos públicos. |
| [Akismet](https://wordpress.org/plugins/akismet/), [releases Mailpit](https://github.com/axllent/mailpit/releases) | Inventario de mantenimiento; no activados/actualizados por mera disponibilidad. |
| MP security advisories GitHub | Página no recuperable en consulta; no permite certificar que no existan avisos. Revisión de fuentes y releases sí realizada. Bloqueos observados permanecen. |

REST público de usuarios y archivos de autor siguen mostrando datos editoriales públicos soportados por WP; `context=edit`, emails/roles y clientes/pedidos protegidos fueron denegados. Publicación de nombres/nicenames es riesgo de privacidad/configuración, no prueba de acceso a cuentas. Definir autores públicos por comercio y rate limit de login/recuperación en hosting; no deshabilitar REST globalmente ni esconder wp-admin como sustituto de autorización.

## Reproducción y cierre de fase

Herramientas QA locales en `.local/h9-tools` (`lighthouse` 13.5.0 / `axe-core` 4.14.0) y Playwright del runtime. `NODE_PATH` debe apuntar al node_modules del runtime; navegadores en el cache Playwright del usuario. No dependencias productivas nuevas.

```powershell
.\scripts\h9.ps1 inventory
# Clone es one-shot y exige nombre explícito; no repetir sobre el clon existente:
.\scripts\h9.ps1 clone -Instance h9-audit
.\scripts\h9.ps1 wp -Instance h9-audit -WpArgs @('eval-file','/opt/local-tools/h9-security-qa.php','fixtures','--quiet')
.\scripts\h9.ps1 wp -Instance h9-audit -WpArgs @('eval-file','/opt/local-tools/h9-security-qa.php','restricted','--quiet')
node tests/verify-h9-security.cjs
node tests/verify-h9-checkout.cjs
node tests/verify-h9-secrets.cjs
```

H9.1 permite continuar H9.2: no queda defecto crítico propio demostrado sin corregir. Mercado Pago permanece **bloqueado para producción**, incluso con QA local aprobada. El parche PHP sí se aplicó tras validación aislada; no se desplegó un sitio. Git conserva fase separada y H9 sin merge a main.

## Mercado Pago Integration Review

**Scope**: security. **API detected**: Payments API / Preferences; recurso Core por SDK. **Products detected**: Checkout Pro oficial, deshabilitado. **Files analyzed**: receptores, fábrica, AbstractTransaction, PaymentMetadata, OrderStatus y Hooks/Scripts del plugin 8.9.6, identificados por hashes.

### CRITICAL
- Origen/replay, temporalidad, validación financiera independiente, compatibilidad declarada y recursos sin uso siguen como bloqueos productivos.

### WARNINGS
- Deshabilitar Pro no elimina los scripts; metadata de fecha puede cambiar en redelivery.

### PASS
- Secretos en configuración nativa, fuera de Git; estado consultado del lado servidor; external_reference nativo. Sin desarrollo de gateway/receptor alternativo.

### Quality Standards
Not requested for this scope. Sin homologación ni quality_evaluation: no payment_id nuevo, sin solicitud de análisis MCP de calidad ni autorización de producción.

### Security checklist (cross-cutting)

| # | Check | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Token fuera de código/Git | Pass | Opciones nativas del plugin; scanner local sin valores en salida |
| 2 | .env ignorado, ejemplo permitido | Pass | `.gitignore` |
| 3 | x-signature HMAC | Missing | Receptores instalados, hashes adjuntos |
| 4 | HTTPS de retornos/notificaciones | Partial | Producción bloqueada; local HTTP deliberado; helpers H7 exigen HTTPS de prueba |
| 5 | Estado servidor tras retorno | Pass | Reconsulta de recurso oficial; no se confía en query de redirect |
| 6 | Idempotency key al crear pago/order | N/A | H9 no crea pagos/orders MP; creación de preferencia Pro no equivale a cargo |
| 7 | external_reference | Pass | AbstractTransaction / AbstractNotification |
| 8 | Credenciales test fuera de despliegue | Partial | Fuera de Git; despliegue no realizado, exige nueva configuración comercial |
| 9 | No usar sandbox_init_point | Pass | Pro instalado usa init_point; no se cambió la integración |

**Summary**: Official quality: not requested. 5/9 checks pass; 2 partial, 1 missing, 1 N/A. No score comercial de MP inferido.

## Implementation Report

### Verified
- [x] Fuentes instaladas, versión, checksums y consulta autenticada de recursos.
- [x] Pro disabled, BACS funcional, sin pagos ni modificación del plugin oficial.
- [x] Desactivación completa en clon elimina recursos MP observados.

### Needs attention
- [ ] `Hooks/Scripts.php:265`: no hay opción granular hallada para eliminar todas las cargas con plugin activo.
- [ ] `PaymentMetadata.php:119`: fecha de recepción usada como fallback.

### Blockers (must fix before production)
- [ ] `Notification/*`: garantías oficiales de origen/replay y validaciones financieras independientes.
- [ ] `OrderStatus.php:453`: política temporal/conciliación; WC 11.2.1 fuera de tested up to 11.0.1.
- [ ] Resolver privacidad y configuración comercial/HTTPS antes de habilitar cobros.

### Next steps
1. Resolver los bloqueos con soporte/release oficial o decidir retirar MP cuando no se utilice.
2. Validar nuevamente compatibilidad, privacidad y notificaciones sobre versión/configuración aprobadas.
3. Re-run the `mp-review` skill after fixes to confirm the report.

### Resources used
- Local: codebase inspection + cross-cutting security floor.
- Web: documentación/release oficial enlazada y fecha de esta auditoría.
- Skill: `mp-review` v1.0.0; no herramientas de cuenta MCP utilizadas.

**Scores**: official quality: N/A, 5/9 security. **Verdict**: Blocked.
