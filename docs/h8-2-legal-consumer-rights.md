# H8.2 — Legales, privacidad y consumidor argentino

Entrega para revisión visual y funcional, **sin merge H8.2 a main, H9 ni producción**. Revisión normativa al 21/10/2026 (Argentina). Ver [matriz con fuentes oficiales y pendientes](h8-legal-compliance-matrix.md) y [adaptación por cliente](h8-client-legal-setup.md). La preparación técnica no acredita cumplimiento jurídico integral.

## Base consolidada

Repositorio `C:\Codigo\demo-woocommerce`, remote `https://github.com/ramiro-gp/demo-woocommerce.git`. Se verificó árbol limpio, rama e historial, fetch de origin y coincidencia con el commit aprobado. Se hizo fast-forward de `main` desde `7e09c7c` hasta **`18da5be06f12fae286f7770b92ed5938a3ae61fa`**, que incluye la auditoría final H8.1, y push sin force. `main = origin/main`. Después se creó `codex/h8-2-legal-consumer-rights`.

## Auditoría inicial y decisiones de contenido

WordPress 7.1.3, WooCommerce 11.2.1, PHP8.4. Commerce Core, Order Integrity, Communications, Account Navigation y Lookbook estaban activos. BACS DEMO disponible, Checkout Pro deshabilitado. No se instalaron plugins ni servicios nuevos.

- Privacidad: único borrador ID3 `privacy-policy`, título/contenido genérico en inglés de WordPress. Se leyó y preservó en snapshot y backup antes de reutilizarlo; no se eliminó ni duplicó.
- No había páginas de envíos, cambios, términos o arrepentimiento. Footer sólo enlazaba páginas publicadas, por lo que no tenía falsos links a ellas.
- Registro tenía un aviso español pero remitía a privacidad no publicada. Checkout tenía aviso de privacidad en inglés, sin página de términos configurada y un bloque de términos nativo disponible.
- Contacto ID39 publicado con canales explícitamente pendientes y FAQ ID40 con orientación DEMO. Se conservó contacto y se agregó identidad/navegación compartida; no se borraron borradores.
- Dirección del comercio y datos fiscales vacíos. No se tomó el email técnico del administrador como canal público ni se inventó CUIT, domicilio o teléfono.
- Retención Woo: anonimización automática no configurada; supresión de datos de pedidos a solicitud deshabilitada. Se respetaron esos ajustes y se documentó la necesidad de política por cliente.
- Exportadores/erasers nativos WordPress/Woo ya disponibles para cuentas, pedidos, descargas y tokens. Se amplió ese mecanismo, sin un sistema paralelo de derechos de datos.

Backup completo: `.local/local/backups/h8-2-before-legal-20261021/`. Snapshot privado de páginas/opciones y huellas: `.local/local/backups/h8-2-before.json`. Contienen datos de la instalación; están excluidos de Git. Los reportes públicos sólo incluyen resultados y códigos sintéticos.

## Arquitectura

**Commerce Core 1.1** incorpora `includes/class-legal.php`, módulo independiente de Address Copy. Se puede desactivar por filtro `commerce_core_legal_enabled`; no borra datos. Registra shortcodes para formulario, identidad y navegación. No añade API REST pública ni búsqueda de pedidos. Las páginas y textos legales son Gutenberg editables. La configuración compartida reside en `commerce_core_legal`; la identidad no está copiada en cada texto.

**Commerce Base 1.7** sólo aporta presentación: `assets/css/legal.css`, acceso visible en Home, footer y exclusión de Exit Intent. Tipografía/tokens del sistema existente, índice lateral en escritorio, índice arriba en móvil, lectura de 68ch y controles con foco. La [nota de diseño](h8-2-design-notes.md) identifica la skill utilizada con autorización del usuario.

**Commerce Communications** conserva su responsabilidad de presentación de emails Woo. El módulo Legal dispara únicamente las constancias de su propio trámite y reutiliza `WC_Email`/mailer mediante `wrap_message`. No crea triggers de pedidos, SMTP ni lógica de pagos. Se agrega información de revocación a los emails de pedidos del consumidor mediante el hook nativo, en HTML y texto.

La inicialización administrativa/CLI crea tablas propias mediante dbDelta, con versión de esquema. No hay seed automático de páginas. `h8-2-setup.php` es una operación explícita y local: exige URL exacta, backup, páginas esperadas y rechazo de una segunda ejecución para no sobrescribir ediciones.

## Páginas y navegación

| Página | ID / URL local | Origen |
| --- | --- | --- |
| Privacidad | [3](http://localhost:8080/?page_id=3) | Reutilizada; inglés preservado en backup |
| Términos y condiciones | [511](http://localhost:8080/?page_id=511) | Nueva |
| Envíos | [512](http://localhost:8080/?page_id=512) | Nueva |
| Cambios y devoluciones | [513](http://localhost:8080/?page_id=513) | Nueva |
| Derecho de arrepentimiento y formulario | [514](http://localhost:8080/?page_id=514) | Nueva |
| Contacto | [39](http://localhost:8080/?page_id=39) | Se conserva y amplía con identidad compartida |

Todas las legales están publicadas **sólo como muestras DEMO para QA local**, con advertencia visible y metadato `_commerce_legal_demo`. El módulo bloquea su acceso fuera de ambiente `local` y su exposición REST no local; la marca no es un flujo de aprobación legal. No trasladar esta base a producción.

Home muestra exactamente **BOTÓN DE ARREPENTIMIENTO** en una franja debajo del header, en el primer viewport, sin abrir menú ni modal. Footer repite el acceso y enlaza las cinco páginas publicadas. La navegación legal usa los IDs configurados, por lo que no necesita slugs fijos. Sin Core, el tema conserva su fallback de enlaces publicados.

Registro usa la opción nativa `woocommerce_registration_privacy_policy_text`, en español y con `[privacy_policy]` válido. Checkout conserva el bloque oficial `woocommerce/checkout-terms-block`, con texto editable español, enlaces publicados y explicación destacada de revocación. Su opción `checkbox=true` usa el único control de aceptación nativo de WooCommerce; no hay checkbox personalizado ni marketing obligatorio. Se configuraron `wp_page_for_privacy_policy`, `woocommerce_terms_page_id` y aviso nativo de privacidad de pedidos. Los enlaces en el texto editorial del bloque deben revisarse al reemplazar páginas; no se afirma que un texto personalizado siga automáticamente un nuevo ID.

## Comprador y recepción

Invitados y clientes con/sin cuenta usan el mismo formulario. Nombre/email son obligatorios; referencia de pedido y mensaje son opcionales. No se exige motivo, DNI, foto, banco, registro o navegación previa por Mi Cuenta. Funciona sin JavaScript. No expone ninguna consulta de pedidos aunque se agreguen query params de email o número.

Flujo: validación → transacción de registro y primer evento → commit → intento de correo al consumidor y al administrador → constancia visible. El identificador `ARR-AAAAMMDD-<20 hex>` combina fecha UTC y 80 bits aleatorios; no es un número de pedido ni un token de acceso. La fecha de recepción se rotula UTC. Un reenvío del mismo formulario con igual contenido devuelve el mismo código, sin otro caso ni correos. Con contenido diferente exige un formulario nuevo.

La constancia dice **recibida, pendiente de gestión**; no expresa aprobación, pedido cancelado, refund o devolución completada. El código se emite inmediatamente, sin esperar el plazo reglamentario máximo de 24 horas. Incluye impresión/guardado desde el navegador. Si falla el correo conserva registro y muestra el código con aviso de fallo. No tiene consulta pública por código: se conserva como comprobante y se usa en atención tras verificar identidad.

## Datos, seguridad y permisos

Tablas con prefijo WordPress:

| Tabla | Datos y finalidad |
| --- | --- |
| `commerce_legal_requests` | ID interno, código único, hashes de token/contenido, fecha UTC, nombre/email, referencia y mensaje declarados, estado de gestión, pedido vinculado manualmente, estados de correo y último intento |
| `commerce_legal_events` | Recepción, intentos de correo y cambios de gestión; fecha UTC, usuario actor, tipo y nota. Sin endpoint público |
| `commerce_legal_limits` | Bucket HMAC por ventana horaria/IP o email, contador y vencimiento. Sin IP en claro; registros vencidos se purgan al enviar |

Validación íntegra en PHP: tipos escalares, longitudes, email sin “corregirlo” silenciosamente, sanitización de texto y escape al mostrar. Límite de cuerpo POST de 16 KiB. Consultas parametrizadas. No se guardan passwords/secretos ni documentos. En local sólo se admite email `.test`; destinatario interno también ficticio. Mailpit captura localmente, sin SMTP real.

El formulario utiliza nonce WordPress y token firmado con HMAC ligado a una cookie aleatoria de sesión HttpOnly, SameSite Lax y Secure en HTTPS. Vence en 24 horas y no se admite en otro navegador. Página no cacheable, sin token en URL y Referrer-Policy no-referrer. No depende sólo del nonce genérico de invitado de WordPress. La clave de idempotencia tiene índice único, evitando duplicados concurrentes; la operación de conflicto no registra un error SQL esperado con los datos del formulario.

Antispam sin CAPTCHA: honeypot, 30 solicitudes/IP/hora y 6/email/hora, con mensaje de recuperación/canal alternativo. No se rechaza por estado de pedido, fecha o excepción automática. Los límites exigen revisión del contexto de proxy y del canal real del cliente para no bloquear injustificadamente compradores. No se usa `X-Forwarded-For` aportado por el visitante.

Lectura/gestión: `manage_woocommerce`, administradores y shop managers nativos. Configuración de identidad: `manage_options`. Cada escritura administrativa exige capacidad y nonce. No se publica un post type de solicitudes. La lista está paginada y el detalle escapa todos los campos.

No hay modificación automática de pedido, stock, BACS, reembolsos ni Mercado Pago. Asociar una solicitud a un pedido exige confirmar verificación razonable y registrar una nota. La referencia inicial nunca es prueba suficiente. Un usuario con capacidad administrativa sigue teniendo que operar reembolsos mediante WooCommerce y el medio correspondiente; H8.2 no los ejecuta.

## Administración y correo

[WooCommerce → Arrepentimiento](http://localhost:8080/wp-admin/admin.php?page=commerce-legal): lista por código, fecha, estado y resultados de transporte; detalle con campos declarados, notas y trazabilidad. Estados: recibida/en gestión/esperando información/gestión cerrada. Guardar gestión agrega un evento con actor y transición; un cierre no tiene significado financiero. El enlace al pedido verificado utiliza `get_edit_order_url`, compatible con HPOS.

Cada destinatario tiene estado de correo `pending/sending/sent/failed`. Un claim atómico evita que dos reintentos envíen simultáneamente. El botón manual reintenta sólo `pending/failed`; los enviados no se repiten. No hay cron comercial ni reenvío automático continuo. Si un proceso muere después del claim, `sending` requiere comprobación del transporte y recuperación asistida, para no duplicar envíos inciertos. `sent` indica aceptación por el transporte y no lectura; debe existir supervisión real antes de producción.

Constancia al consumidor y aviso interno contienen código, fecha y estado en español; no incluyen productos, direcciones, importes, notas, datos de terceros ni secretos. Mensajes HTML con texto alternativo (multipart). [Mailpit](http://localhost:8025) es el único transporte del entorno. No se configuró SMTP real ni se mandaron reclamos a un comercio real.

## Privacidad, envíos e identidad

La política refleja cuentas, pedidos, direcciones, email/teléfono, atención y nuevas solicitudes. No afirma ausencia de almacenamiento ni borrado inmediato de todo. Distingue el hosting Docker/correo Mailpit local de proveedores aún no elegidos. Declara Gravatar observado en administración y los intentos del plugin MP de cargar recursos de `sdk.mercadopago.com`, `www.mercadopago.com`, `api.mercadopago.com` y `http2.mlstatic.com` en checkout aun con Pro deshabilitado. El navegador QA intercepta esas conexiones antes de continuarlas; no se concluye que no existan comunicaciones externas por deshabilitar un gateway. Queda pendiente revisar sus datos/cookies y legitimación antes de producción, sin parches de pagos en H8.2.

Se describen cookies funcionales de WordPress/Woo, cookie de protección del formulario y almacenamiento de apariencia/Exit Intent. El último no aparece en páginas legales. No se añadieron trackers, analítica, listas de marketing ni banner ornamental.

Exporter/eraser se registran en las herramientas nativas. Exporta campos de solicitudes tras el circuito de confirmación WordPress; no entrega tokens técnicos. Informa retención de casos para revisión de obligaciones, sin borrar automáticamente. Notas internas y datos de terceros requieren revisión manual para responder íntegramente un acceso. La política concreta de plazos y purga/disociación sigue pendiente por cliente; conservar por defecto no acredita cumplimiento.

Envíos informa zona Argentina, estándar DEMO $4.500, gratis desde $100.000 después de descuentos y retiro nativo. WooCommerce calcula el precio real; no se modifican montos ni umbral. No hay operador, cobertura ni plazos inventados. Cambios diferencia arrepentimiento, política voluntaria aún pendiente, defectos, garantía legal, cancelación previa y reembolso manual.

[Ajustes → Identidad y legales](http://localhost:8080/wp-admin/options-general.php?page=commerce-legal-settings) centraliza nombre comercial, titular, CUIT, domicilio, sitio, contacto, área y horarios. Vacíos se muestran pendientes; TRAMA se rotula demostración. DataFiscal acepta únicamente dos URL oficiales HTTPS mediante allowlist de host/ruta, sin scripts. Sin configuración muestra ausencia explícita en local, sin QR falso. El titular debe generar y verificar su propio recurso.

## QA y preservación

Se ejecuta QA proporcional del módulo y superficies modificadas; no se resembró catálogo ni se normalizaron hashes H5/H6. Snapshot estricto de pedidos HPOS, direcciones, datos operativos, metadatos/ítems/notas, catálogo, direcciones de usuarios, zonas/métodos y opciones de pagos/envíos. Se crean exclusivamente solicitudes y usuarios sintéticos marcados de H8.2; el pedido QA histórico#454 se lee/vincula en la solicitud sin modificarlo. Los contactos QA y sus claves quedan sólo en `.local`.

| Validación | Resultado |
| --- | --- |
| PHP integración | **33/33**: persistencia, fallo/reintento de email, idempotencia, formato, minimización, export/erase, límites, Data Fiscal y configuración |
| Navegador | **81 checks**: Home/footer/registro/checkout, invitado sin JS, cliente autenticado, sin pedido, referencia desconocida, doble POST concurrente, CSRF, permisos, gestión, notas y vínculo HPOS |
| Responsive | **50 casos**: cinco páginas × 320/390/768/1366/1920 × light/dark, sin overflow; teclado, orden lógico y foco visible verificados en formulario |
| Correos | **10 multipart** de cinco solicitudes browser; además prueba HTTP de fallo visible y recuperación real de sus dos correos desde administración; sólo destinatarios ficticios y Mailpit |
| Suplemento final | **19 checks**: fallo visible, recuperación, permiso de configuración, copia H8.1 disponible, enlaces/checkbox nativos y checkout sin overflow en cinco anchos |
| Preservación | **13/13 huellas idénticas**, incluyendo pedidos HPOS, ítems/notas, stock/catálogo, direcciones, zonas/métodos y opciones protegidas |
| Errores browser | Cero errores JS o assets locales. Orígenes externos existentes auditados y bloqueados por el runner |

Evidencia en `docs/evidence/h8-2/`; archivos de administración y credenciales, sólo privados. El runner encontró una escritura incorrecta de JSON Gutenberg al configurar términos: se corrigió con `wp_slash` y se validaron tres enlaces reales más el control nativo. Se preservaron las 50 comprobaciones visuales ya aprobadas y se continuó de forma focalizada por checkout/correo; el reporte indica esa continuación. No se omite una prueba funcional fallida: se corrigió y repitió el área afectada.

La prueba HTTP de correo utiliza un MU-plugin temporal, restringido a local, header de QA y destinatarios `.test`; se retiró en `finally` y se confirmó su ausencia. Las pruebas no realizan checkout submit, pagos, refunds ni cambios de stock. Hubo un error PHP transitorio mientras se agregaba el archivo del módulo durante edición local; no se reprodujo una vez completados los archivos. No se presenta ese log transitorio como una falla pendiente del producto.

Reproducción local (requiere permisos Docker y runtime Node/Playwright disponible):

```powershell
# En una demo existente ya respaldada y configurada:
.\scripts\dev.ps1 wp -WpArgs @('eval-file','/opt/local-tools/h8-2-qa.php','verify','--quiet')
$env:NODE_PATH='C:\Users\ramiro\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
node tests/verify-h8-2-browser.cjs
.\scripts\dev.ps1 wp -WpArgs @('eval-file','/opt/local-tools/h8-2-audit.php','verify','--quiet')
```

El verificador PHP crea casos ficticios nuevos en cada ejecución. El runner requiere fixture privado preparado una vez con modo `prepare`. No ejecutar `setup` otra vez sobre contenido editado; `refine` documenta Gravatar/MP en la página DEMO marcada y repara la serialización del bloque nativo de términos. Para sincronizar cambios de presentación usar el mecanismo existente `scripts/h3.ps1 sync`; el tema no está bind-mounted. El suplemento requiere copiar temporalmente `h8-2-mail-failure.php` a MU-plugins y eliminar esa copia en `finally` como se hizo en QA; no instalarlo como funcionalidad de la tienda.

## Limitaciones y cierre

Identidad, canales reales y horarios, DataFiscal, proveedores/transferencias, retención, textos contractuales conservables y revisión jurídica siguen pendientes por cliente. Un recibo técnico no resuelve ni efectiviza por sí solo una revocación. Se debe preparar operación y entregabilidad reales. No hay CRM, consulta pública de casos, baja de servicios inexistentes, facturación ARCA ni resolución automática de excepciones.

MercadoPago sigue deshabilitado con bloqueos productivos H7.1 intactos. No se inició H9 ni se desplegó. H8.2 se entrega en su rama para que el usuario revise la interfaz y el funcionamiento antes de decidir continuidad.
