# H8.1 — Commerce Core

Alcance: cierre de H7.2 e implementación de H8.1, exclusivamente en `C:\Codigo\demo-woocommerce`. H8.2 y H9 no iniciados.

## Base y consolidación

H7.2 y la corrección visual aprobada de Mi Cuenta estaban commiteados y sincronizados: `9c86053` + **`7e09c7c0e4a8206df00acbb6a22a53d85a0fdfa8`**. Working tree limpio, origin `https://github.com/ramiro-gp/demo-woocommerce.git`. Fetch sin divergencias; fast-forward de main desde `64d57a4`, push normal y comprobación **main = origin/main = 7e09c7c0e4a8206df00acbb6a22a53d85a0fdfa8**. Rama nueva `codex/h8-1-commerce-core`; no mergear esta entrega.

Antes de consolidar: H7.2 configuración 14/14, navegación/cuenta 11/11, Chromium cuenta 25/25 y Commerce Order Integrity 31/31 (Store API, hidratación, sesiones, reintento autorizado, stock). La primera comprobación de bandeja coincidió con emails sintéticos de concurrencia: 24 checks UI pasaron, falló únicamente el total de Mailpit. Repetición aislada 25/25. No se ocultó un error de producto ni se cambió la aserción.

## Auditoría previa y responsabilidades

| Capacidad / ubicación actual | Responsable final | Dependencias / decisión |
| --- | --- | --- |
| Datos de productos, variaciones, precios, stock, carrito, clientes, direcciones y pedidos | WooCommerce | CRUD y validadores oficiales; no implementar otro modelo |
| Login, registro, recuperación, permisos, Mi Cuenta y guardado de direcciones | WooCommerce | WordPress usuarios/sesiones + formularios, nonce y CRUD nativos |
| Shoppable Lookbook / Product Hotspots: `commerce-lookbook/` | Extensión independiente | Contenido editorial persistente + productos Woo; Commerce Base aporta el aspecto. No mover ni duplicar bloque/API |
| Exit Intent: `commerce-base/inc/exit-intent.php`, JS/CSS homónimos | Commerce Base | Recordatorio visual opcional del carrito nativo, sin datos propios; filtro `commerce_base_exit_intent_config`. No migrar |
| Swatches: `commerce-base/inc/catalog.php`, `assets/js/variations.js` | Commerce Base | Presentación opcional de `pa_color` existente; selección/validación/stock nativos. La convención de este theme no se exporta a Commerce Core |
| Icono de acceso a cuenta, títulos, layout, contraseña, navegación activa y política en borrador: `commerce-base/inc/{components,account}.php`, `assets/css/account.css` | Commerce Base | Presentación mediante APIs Woo; sin credenciales ni escrituras comerciales. No migrar |
| Descargas: `commerce-account-navigation/commerce-account-navigation.php` | Extensión independiente | Preferencia explícita del comerciante en ajustes nativos; default visible. Mantener contrato y endpoint existentes |
| Integridad de pendientes: `commerce-order-integrity/` | Extensión independiente H7.1 | Hooks Store API/hidratación, sólo referencias de sesión; conservar sin cambios |
| Emails / BACS autorizado: `commerce-communications/` | Extensión independiente H7.2 | WC_Email, wp_mail, ajustes nativos; sin duplicar triggers/templates/remitentes |
| Catálogo, swatches concretos, guías, identidad, envío y emails demo: `demo/`, `docker/tools/h*-*.php` | Fixtures / configuración demo | Migradores explícitos locales, fuera del plugin reutilizable |
| Etiquetas checkout, recibo por estado, cards, mini-cart y cross-sells: `commerce-base/inc/` y assets | Commerce Base | Presentación sobre Blocks/estado/API nativos; no decide pagos, totales o stock |
| Copia explícita de facturación a envío en Mi Cuenta: `commerce-core/` | Commerce Core | Módulo opcional, lee sólo WC_Customer autenticado; prepara formulario nativo sin persistencia propia |
| Medios de pago e integraciones | Plugins oficiales / especializados | BACS Woo nativo; Mercado Pago oficial intacto y Pro deshabilitado |
| Funcionalidad legal persistente futura | Decisión H8.2 | Evaluar módulo acotado o extensión especializada; contenido/páginas en WordPress. No implementar ahora |

**Migraciones: ninguna.** No hay beneficio técnico en mover extensiones estables o presentación al nuevo plugin. Commerce Core no requiere Commerce Base ni las otras extensiones; tampoco reemplaza WooCommerce. V1 tiene un solo módulo útil y no añade un panel administrativo.

## Plugin y activación

Ruta física: **`C:\Codigo\demo-woocommerce\commerce-core`**; runtime **`/var/www/html/wp-content/plugins/commerce-core`**. Compose lo monta RO tanto en wordpress como wpcli. Distribuir únicamente esta carpeta, o un ZIP con `commerce-core/` en su raíz; no distribuir Docker, `.local`, DB o fixtures demo.

`commerce-core.php` declara el plugin, namespace `CommerceCore`, text domain `commerce-core`, versión 1.0.0 y carga modular. `includes/class-address-copy.php` contiene el módulo; `assets/js/address-copy.js` prepara el formulario. Sólo registra `woocommerce_before_edit_address_form_shipping`. No templates sobrescritos, panel nuevo, AJAX, REST, cron, tablas, dependencias frontend adicionales ni autoloader/framework. Reutiliza jQuery y los scripts de país/provincia ya requeridos por WooCommerce.

Paquete instalable generado localmente: `.local/local/h8-1/commerce-core-1.0.0.zip`; contiene exclusivamente la carpeta del plugin y sus cuatro archivos, sin fixtures o secretos.

En esta instalación ya inicializada:

```powershell
# Aplicar el nuevo mount utilizando el proyecto/env existentes, sin reset:
docker compose --project-name demo-woocommerce-local --env-file .local/local/.env up -d --wait wordpress
.\scripts\h3.ps1 sync
.\scripts\dev.ps1 wp -WpArgs @('plugin','activate','commerce-core')
```

En otro comercio: instalar WooCommerce, copiar/instalar Commerce Core y activarlo desde Plugins. No ejecutar los migradores demo. El plugin no requiere Commerce Base, Lookbook, Order Integrity, Communications ni Account Navigation. Requiere WP ≥6.5, PHP ≥7.4 y WC ≥11.2; **comprobado** aquí con WP 7.1.3, PHP 8.4.26 y WC 11.2.1. Esto no certifica combinaciones distintas: repetir QA al actualizar.

No hay escrituras de activación, desactivación, actualización o uninstall. Al desactivar desaparece la comodidad de copia y permanecen todas las direcciones nativas. Sin WooCommerce, el bootstrap se detiene sin cargar el módulo ni producir fatal. Declara compatibilidad HPOS y Cart/Checkout Blocks; no lee/escribe pedidos ni modifica Blocks. [Declaraciones oficiales](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks).

Para deshabilitar solamente el módulo, en un MU-plugin o plugin específico del sitio cargado antes de `plugins_loaded`:

```php
add_filter('commerce_core_address_copy_enabled', '__return_false');
```

## Experiencia de direcciones y seguridad

1. Iniciar sesión y abrir **Mi Cuenta → Direcciones → Editar dirección de envío**.
2. Pulsar **Usar mi dirección de facturación**. Si el formulario contiene datos, confirmar **Copiar y reemplazar los campos**; Cancelar/Escape conserva el formulario y devuelve el foco.
3. Revisar/editar los campos preparados; el feedback explica que aún no se guardaron.
4. Pulsar el botón nativo **Guardar dirección**. WooCommerce valida y persiste exclusivamente el usuario de la sesión.

Lista permitida: nombre, apellido, empresa, país, provincia, localidad, código postal, calle, complemento y teléfono, **sólo si ambos esquemas nativos exponen el campo**. En esta demo empresa está oculta y teléfono de envío es opcional. Campos de otras extensiones permanecen manuales y conservan validación nativa; no se copian email ni metadatos arbitrarios. La copia primero cambia país, deja a WooCommerce reconstruir provincia y después aplica el estado y los campos restantes.

Lee mediante `WC_Customer(get_current_user_id())`; no acepta IDs externos. Destino de envío permitido, campos requeridos compatibles, provincia y validadores nativos se comprueban antes de ofrecer datos. Con facturación incompleta/incompatible se muestra explicación y enlace **nativo localizado** a facturación. Los validadores y formatos por país siguen siendo los de WooCommerce, sin reglas postales nuevas propias.

Copiar sólo cambia el DOM. **Cero HTTP de copia, cero submit automático y cero escritura de servidor**. Recargar sin guardar conserva el envío anterior; cambiar facturación después no sincroniza envío. No hay vínculo persistido entre ambas direcciones. El POST final usa el nonce `woocommerce-edit_address`, selector de cliente, sanitización, reglas oficiales y guardado de `WC_Form_Handler::save_address()`. Query/POST de `user_id`/`customer_id` no seleccionan otro cliente. No se implementó endpoint ni nonce paralelo. [Formulario y handler nativos](https://woocommerce.github.io/code-reference/classes/WC-Form-Handler.html).

Escape contextual de URLs, atributos y texto; JSON inerte con `JSON_HEX_*`, valores limpiados y lista cerrada de campos. No SQL sobre clientes/pedidos, localStorage de direcciones, cookies propias, direcciones en logs, retención adicional, IPs, cuentas administrativas, tarjetas o secretos. La única preferencia localStorage utilizada en QA es el modo visual ya existente del theme. Visitantes sin sesión no reciben el bloque ni sus datos. Las páginas autenticadas conservan la política de caché privada de WooCommerce: no habilitar caché pública de Mi Cuenta.

## Presentación y adaptación

Commerce Base 1.6.2 agrega sólo estilos scoped a `.commerce-core-address-copy` en `assets/css/account.css`: tokens existentes, botones primario/secundarios, espaciado, feedback y foco. No se rediseña Mi Cuenta. El plugin entrega HTML semántico y funciona con otro theme sin CSS de Commerce Base. Grupo de confirmación accesible, botones reales `type=button`, descripción asociada, `aria-expanded`, región `role=status`, teclado y Escape; sin JavaScript queda disponible el formulario manual nativo.

Para otro cliente: configurar catálogo, atributos, países, monedas, shipping, medios, emails e identidad en WooCommerce/WordPress; elegir theme y extensiones necesarias. No hay marca, URL local, Color/Talle, transportista o gateway fijados en Commerce Core. La convención `pa_color` del theme actual sigue siendo una presentación opcional existente, no un contrato del nuevo plugin. Si se necesita otro tipo de atributo visual, adaptar el theme; no forzar esa convención sobre una tienda de tecnología/decoración. Traducir el text domain propio si corresponde. No transportar las preferencias o páginas demo sin revisión.

## QA y reproducibilidad

Backup íntegro previo **`h8-1-before-commerce-core-20261020`**, DB + WordPress + manifiesto con SHA-256, en `.local/local/backups/`. Línea base privada: **194 pedidos, 123 productos/variaciones/cupones y 3 cuentas previas**, además de ajustes de pago/email/shipping/theme y todas las páginas. Los reportes sólo exponen resultados y hashes, no PII/credenciales. Tres clientes nuevos `customer` identificados para direcciones y clientes sintéticos de registro; ninguna cuenta administradora nueva. Las suites de operación crean sus propios pedidos sintéticos y conservan históricos.

```powershell
# Sólo primera vez, después del backup y antes de preparar fixtures:
.\scripts\dev.ps1 wp -WpArgs @('eval-file','/opt/local-tools/h8-1-qa.php','snapshot','--quiet')
.\scripts\dev.ps1 wp -WpArgs @('eval-file','/opt/local-tools/h8-1-qa.php','prepare','--quiet')
.\tests\verify-h8-1.ps1
# Con Node/Playwright y NODE_PATH del runtime local:
node tests/verify-h8-1-browser.cjs
.\scripts\dev.ps1 wp -WpArgs @('--require=/opt/local-tools/h8-1-isolated.php','eval-file','/opt/local-tools/h8-1-isolated-country.php','--quiet')
node tests/verify-h8-1-isolated.cjs
node tests/verify-h8-1-registration.cjs
```

`snapshot` rechaza sobrescribir una línea base existente. `prepare`/`reset` sólo modifican sus tres clientes con marca y rol propios; no usar en otro entorno. Antes de repetir toda la suite browser, `reset` restaura exclusivamente sus direcciones sintéticas iniciales. No registrar emails de pruebas de pedidos al mismo tiempo que la aserción browser de Mailpit: correr suites mutantes secuencialmente.

Evidencia privada: `.local/local/h8-1/{browser,source,verify,theme,isolated}-report.json`, capturas del formulario; credenciales sólo en `.local/local/backups/h8-1-qa-fixtures.json` (no imprimir/versionar). `h8-1-qa-state.json` contiene la línea base; HTML aislado también privado. El aislamiento de theme/Woo/módulo usa filtros **sólo de un proceso WP-CLI**. No cambia las opciones de la instalación, ni activa/desactiva WooCommerce en principal.

| Comprobación | Resultado efectivo |
| --- | --- |
| Dirección / seguridad / ciclo de vida en Chromium | **17/17**: cliente completo, sin facturación, envío existente, otro cliente, copia/cancelación, editar antes de guardar, guardado/recarga, facturación posterior independiente, provincias, nonce inválido, IDs ajenos por GET/POST, invitado, login/registro visible, pedidos/detalles, no-JS y checkout invitado/autenticado |
| Responsive y teclado | **20 combinaciones**: 320/390/768/1366/1920 × light/dark × confirmación cerrada/abierta; sin overflow, targets ≥44px, foco visible, Enter/Tab/Space/Escape. Seis capturas revisadas |
| Independencia de theme y país/provincia | Twenty Twenty-Five en proceso aislado, formulario WC real con scripts nativos; **12 checks** browser, AR→CL→AR→CL, empresa/teléfono opcionales. Sin submit, CSP `form-action none`, usuario y ajustes intactos |
| Bootstrap opcional | Sin Woo: plugin cargado y módulo ausente sin fatal. Filtro de deshabilitación: WC presente y módulo ausente. Sin cambios de plugins en principal |
| Source / aislamiento / validación | **27/27**, usuario actual, invitado sin HTML/datos, email excluido, dirección incompleta/provincia/país inválidos, código postal inválido US mediante validador nativo, shipping no disponible, campos custom manuales, render sin escritura y hook exclusivo de envío |
| Formulario completo con otro theme | **29/29**, incluye lo anterior + theme alternativo cargado y formulario/nonce nativos presentes |
| Preservación | **15/15** finales, mismos 194 pedidos previos, catálogo y 3 cuentas originales; ajustes de pagos, emails, shipping, theme y páginas conservados. Se permite únicamente la diferencia de timestamp de dos productos ocultos QA, demostrada contra el backup sin modificar DB ni baseline; detalle debajo |
| Regresión de pedidos y comunicaciones | **133/133** operación nativa y stock, **615/615** Mailpit, 33 emails reales locales (BACS, processing, completed, cancelaciones, reembolsos manuales, notas y reenvíos); sólo pedidos nuevos sintéticos |
| Regresión core H7.1 | **33/33**; BACS/totales/stock/estados/HPOS, sin pagos externos |
| Envíos funcionales | **21/21**, umbral/cupón/retiro/provincia/país sin cobertura/recalculado nativos |
| Integridad posterior | **31/31** con Commerce Core activo: sesiones separadas, pendiente intacto, carrito/checkout/hidratación, reintento nativo autorizado y stock una vez |
| Registro y notificaciones de cuenta | Registro real con autenticación posterior y solicitud nativa de recuperación; **2 emails multipart** locales, sin contraseña en texto plano ni seguir el enlace de restablecimiento |
| Fuente y errores | PHP, Node y parser PowerShell correctos; sin errores PHP nuevos desde el inicio de H8.1; browser sin errores funcionales/assets, sin POST de compra ni emails propios de la copia |

**Advertencia de guarda histórica, sin reparación de datos:** el wrapper completo `verify-h6-2.ps1` se detiene por huellas H5/H6 divergentes en `TRM-REM-001`, `TRM-REM-002`, `TRM-PAN-001`, `TRM-ACC-001`, `TRM-ACC-004`, `TRM-ACC-005`, `TRM-ACC-006`. El catálogo actual coincide con la línea base previa a implementar H8.1: no se atribuyen estas diferencias históricas a Commerce Core ni se las oculta como una suite completamente verde. No se hizo seed, reset de stock o actualización de hashes. Se ejecutó directamente el QA funcional de envíos con su cupón efímero y cleanup propio: 21/21. No confundir una guarda del fixture original con la preservación comprobada de los datos al iniciar este hito. Esa reconciliación histórica queda fuera de esta entrega.

Los primeros intentos del runner detectaron supuestos incorrectos sobre empresa opcional, endpoint de facturación traducido y espera de navegación, binding del array PowerShell, países habilitados y código postal argentino (se respeta el validador nativo; inválido US se prueba aisladamente). Se corrigieron los tests; no se alteraron ajustes del comercio para satisfacerlos. El bloqueo deliberado de telemetría melidata de MP se registra por separado de los errores funcionales. La revisión visual encontró botones sin estilo completo; se integraron con los tokens antes de la corrida final.

La suite de concurrencia reutiliza sus productos ocultos **#294/#295**, no el catálogo demo. Su corrida posterior consumió 5+1 unidades. Se cancelaron **sólo** los seis pedidos nuevos BACS impagos **#501, #503, #505, #508, #509, #510** mediante `update_status('cancelled')`, con validación previa de propiedad, destinatario sintético, productos, método y ausencia de pago; WooCommerce repuso el stock a **14/35** sin editar cantidades. No se cancelaron pendientes MP ni históricos. El primer hash estricto siguió fallando por sus timestamps actualizados: se conservó ese diagnóstico. `h8-1-backup-product-origin.py` lee exclusivamente las dos filas de productos QA del backup original (no ejecuta SQL) y `verify` sustituye sus fechas **sólo en memoria al comparar**, siempre que SKU/marca/visibilidad/stock coincidan. El hash completo restante coincide, demostrando que no cambió otro campo. La DB conserva los timestamps reales y la línea base original permanece intacta. No aplicar esta excepción al catálogo comercial.

Para repetir esa regresión local adicional: `verify-h7-concurrency.ps1`, después `h8-1-qa.php cleanup-concurrency` (guardas de los seis BACS propios nuevos), ejecutar con Python `docker/tools/h8-1-backup-product-origin.py` y finalmente `verify-h8-1.ps1`. El lector del backup está acotado a esta evidencia y estos dos fixtures locales; no se distribuye con el plugin ni se usa para reconciliar datos de un cliente.

El primer runner de recuperación había creado correctamente una cuenta y capturado su bienvenida antes de fallar por un selector del propio test. Se conservó ese cliente sintético; la repetición final comprobó registro y recuperación. No se modificaron contraseñas de usuarios previos.

Revisión local, con sesión autenticada:

- [Mi Cuenta](http://localhost:8080/?page_id=10).
- [Direcciones](http://localhost:8080/?page_id=10&edit-address).
- [Editar envío](http://localhost:8080/?page_id=10&edit-address=shipping).
- [Editar facturación](http://localhost:8080/?page_id=10&edit-address=billing).
- [Pedidos](http://localhost:8080/?page_id=10&orders), [checkout](http://localhost:8080/?page_id=9) y [Mailpit](http://localhost:8025/).

WooCommerce también genera los equivalentes localizados `edit-address=envio` / `edit-address=facturacion`; el enlace de facturación del módulo utiliza esa API nativa. El cliente de revisión de H7.2 conserva sus datos; alternativamente usar los clientes privados de H8.1. No copiar credenciales a Git ni al informe público.

Capturas seleccionadas: [desktop claro](evidence/h8-1/shipping-light-desktop.png), [mobile oscuro](evidence/h8-1/shipping-dark-mobile.png). Son clientes sintéticos, sin emails, claves o contraseñas visibles. [Resumen sanitizado de QA](evidence/h8-1/qa-summary.json).

## Límites productivos y preparación H8.2

**Mercado Pago 8.9.6 continúa bloqueado para producción.** Checkout Pro deshabilitado, BACS demo disponible, sin cuentas bancarias ni transferencias reales. Siguen pendientes autenticidad/firma del receptor, replay externo original, eventos tardíos/orden temporal, timestamp diagnóstico, comprobaciones independientes de importe/moneda/asociación y compatibilidad productiva con WC 11.2.1. No se actualizó ni parcheó el plugin oficial ni se creó gateway/receptor propio. Mantener estos puntos como condición previa a cualquier despliegue; ver [auditoría H7.1](h7-mp-audit.md), [concurrencia/notificaciones](h7-concurrency-audit.md) y [email productivo](h7-email-production.md). SMTP productivo no contratado ni configurado; Mailpit continúa local.

Inventario **sólo de lectura** para H8.2: política de privacidad nativa **ID 3, `privacy-policy`, borrador**. No existen páginas con slugs `envios`, `cambios-y-devoluciones`, `terminos` ni `boton-de-arrepentimiento`. Contacto **ID 39** y Preguntas frecuentes **ID 40** están publicados como contenido demo; no equivalen a políticas aprobadas. El footer sólo enlaza estas páginas legales si están publicadas; hoy faltan esos enlaces. Mi Cuenta conserva el marcador de privacidad y la etiqueta sin enlace público al borrador. No se publicaron páginas, redactaron políticas finales, activaron botones/formularios legales ni inventaron datos.

Contacto sólo enlaza a Preguntas frecuentes; FAQ enlaza a Mi Cuenta y Contacto. Ambos conservan copy demo/provisional y no constituyen un canal legal operativo. Revisar contenido, contactos reales y enlaces de atención en H8.2.

Datos que deberá aportar cada comercio para H8.2: identidad/razón social y responsable, datos fiscales reales, domicilio y contactos comerciales, alcance territorial y logística/plazos/costos reales, condiciones comerciales de cambios/devoluciones, procedimiento y responsables de atención, tratamiento real de datos/proveedores/finalidades/conservación, y elementos fiscales/verificaciones aplicables. Deben validarse en ese hito según el comercio concreto. No se afirma cumplimiento legal aquí. Páginas y textos pertenecen al contenido/configuración de WordPress; una eventual acción legal persistente merece un módulo acotado o una extensión especializada independiente del theme, con permisos, trazabilidad y retención definidos en H8.2. Esa decisión **no inicia su implementación**.
