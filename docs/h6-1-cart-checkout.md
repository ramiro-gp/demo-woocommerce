# H6.1 — Carrito y checkout TRAMA

Entrega en `codex/h6-1-cart-checkout`, pendiente de revisión manual. H6.2 y H7 no comenzaron. Ruta única: `C:\Codigo\demo-woocommerce`. Commerce Base 1.4.0 sobre WooCommerce 11.2.1 y WordPress 7.1.3 existentes.

## Consolidación aprobada

Se verificaron working tree limpio, remote `https://github.com/ramiro-gp/demo-woocommerce.git`, ramas, historial y referencias mediante fetch. La cadena aprobada era lineal:

| Hito | Commit completo |
| --- | --- |
| H5 | `dce5566197a6dc51b7ffd2a4b2b52957564afd2c` |
| Correcciones post-H5 | `f398f3726d07e38c1781d5b60fc042719e6a8687` |
| H5.1 | `b3ed4af1180c8593468a95e2f4382fee0bfad7a7` |

`main` avanzó por fast-forward desde `42250c5363f73d632b3c6def14e63f41e689d123` a `b3ed4af1180c8593468a95e2f4382fee0bfad7a7`. Push normal aprobado; main/origin/main iguales antes de crear la rama H6.1. No force push, rebase ni descarte de commits. H6.1 no se mergea a main.

## Dirección visual y arquitectura

Se aplicó el skill local `.local/skills/frontend-design/SKILL.md`. La dirección mantiene TRAMA: Newsreader para títulos, Manrope para contenido/controles, fondo crudo y acento oliva del sistema H3, superficies oscuras correspondientes, controles con radio común de 3px, reglas discretas y espacio útil. El protagonismo está en productos y datos; el resumen funciona como un recibo legible. No se rediseñaron Home, catálogo, fichas ni Lookbook.

- `templates/page-cart.html` y `page-checkout.html` componen la página y mantienen `post-content` editable.
- `assets/css/cart.css`, `checkout.css`, `mini-cart.css` presentan los bloques oficiales.
- `demo/cart-checkout/cart.html` contiene Cart/Empty Cart/Product Collection oficiales y la card compartida H5.
- `demo/cart-checkout/cross-sells.json` es configuración de datos demo por SKU; el theme nunca lo consulta.
- `docker/tools/h6-1-cart-checkout.php` migra datos mediante APIs WordPress/WooCommerce, con precondiciones y huellas.
- `assets/js/cart-editor.js` registra exclusivamente el filtro público `additionalCartCheckoutInnerBlockTypes`, limitado a `woocommerce/filled-cart-block`, para permitir Product Collection después de las dos columnas nativas. El bloque de navegación conserva su markup guardado válido.

WooCommerce conserva sesión, carrito, cantidades, cupones, direcciones, validaciones y pedidos. No se añadió estado comercial paralelo, endpoint propio, polling, MutationObserver ni manipulación del React/DOM interno del checkout.

## Carrito y controles

Desktop: productos predominantes a la izquierda y resumen a la derecha; la colección ocupa la fila inferior completa. Móvil: productos, resumen/acciones/cupón y CTA, luego cross-sells, en ese orden real del documento. No hay reordenamiento visual que contradiga el teclado. El resumen y CTA permanecen en flujo; no hay sticky que tape teclado, mensajes o footer.

Cada línea conserva imagen, nombre enlazado, precio unitario, variación humana nativa (`Color: Natural / Talle: L`), cantidad, subtotal y papelera. No se muestran slugs técnicos. El subtotal de la línea se mantiene visible en móvil. En carrito, mini-cart y resumen de checkout se oculta únicamente la descripción editorial redundante del producto, no atributos ni importes.

El selector nativo `− 1 +` conserva min/max, stock, múltiplos, entrada numérica, teclado, validación al perder foco y agrupación de cambios de WooCommerce. Botones y entrada tienen 44px; la papelera nativa también, con nombre accesible contextual en español. El store nativo decide los cambios, el rechazo de stock y la eliminación. Un status de presentación lee `getItemsPendingQuantityUpdate()`/`getItemsPendingDelete()` para anunciar «Actualizando tu carrito…»; no emite solicitudes ni guarda cantidades.

El contador post-H5 conserva su implementación y suscripciones públicas. Sus reglas visuales se trasladaron intactas de `catalog.css` a `system.css` para que checkout pueda omitir estilos del catálogo. Mini-Cart sigue siendo el bloque oficial, con variaciones, cantidades, subtotal, papelera, cierre y foco nativos; drawer integrado light/dark.

Vacío: «Tu carrito está esperando.», una frase breve y «Explorar la tienda» con URL resuelta mediante WooCommerce. Sin grilla de productos aleatorios ni exceso de contenido.

## Resumen y cupón

Subtotal, descuentos cuando existen, envío conocido, impuestos aplicables y total permanecen visibles. El total tiene mayor escala y peso. El envío técnico H2 existente no se ocultó ni reemplazó: H6.1 no define tarifas comerciales.

El cupón usa el disclosure oficial «Agregar cupones», secundario frente a «Pasar al pago». Se conservan aplicar, errores, descuento y quitar, teclado y estados de espera de Woo. El cupón temporal de QA `h6-1-qa-10` se crea sólo durante la prueba y se elimina en `finally`; no queda una promoción comercial publicada.

## Cross-Sells reales

Copy final: **«¿Sumamos algo más?»** y «Pequeños complementos para lo que elegiste.»

Product Collection usa `woocommerce/product-collection/cross-sells`, consulta WooCommerce y excluye productos del carrito y agotados; acepta stock disponible y pedidos pendientes permitidos por Woo. Máximo seis recomendaciones, sin selección aleatoria. Relaciones reales configuradas mediante `WC_Product::set_cross_sell_ids()`:

| Producto/grupo | Complementos |
| --- | --- |
| Las cuatro remeras | Gorra Sombra, Bolsa Diario, Pañuelo Pliegue |
| Los tres pantalones | Medias Canal, Gorra Sombra, Bolso Tránsito |
| Los tres buzos | Bolso Tránsito, Gorro Punto, Medias Canal |
| Bolsa Diario | Gorra Sombra, Medias Canal |
| Gorra Sombra | Medias Canal, Bolsa Diario |
| Gorro Punto | Pañuelo Pliegue, Bolso Tránsito |
| Medias Canal | Gorra Sombra, Bolsa Diario |
| Pañuelo Pliegue | Bolsa Diario, Gorro Punto |
| Bolso Tránsito | Gorro Punto, Medias Canal |

Presentación Carousel oficial, navegación `woocommerce/product-gallery-large-image-next-previous`, botones «Productos anteriores/siguientes», desplazamiento táctil y scrollbar. Cards H5, con tipografía de 16px en este contexto. Sin librerías, autoplay ni temporizadores. El usuario controla la navegación.

En desktop, cuando Woo oculta las flechas porque caben todas las cards, CSS conserva su espacio con `visibility: hidden`. Así el cambio del atributo nativo `hidden` no altera la altura durante la entrega de ResizeObserver. Se verificó el observer conectado a la colección real, sin errores ni callbacks repetidos; las flechas ocultas siguen fuera del árbol accesible y del foco. No se filtran ni silencian advertencias del navegador.

Woo 11.2 solicita explícitamente scroll suave al accionar las flechas. Para `prefers-reduced-motion: reduce`, CSS presenta la misma colección como grilla estática y oculta esas flechas: todos los productos quedan accesibles sin desplazamiento animado. No se intercepta la acción nativa ni se sustituye su JS.

## Checkout, invitado y direcciones

Desktop: datos/dirección/envío/pago a la izquierda y resumen a la derecha, dentro del layout amplio del sistema. El formulario tiene un ancho de lectura máximo de 56rem. Resumen estático, thumbnails moderados, cantidades/variaciones y desglose nativo. En móvil se mantiene el resumen superior colapsable de Checkout Blocks y la secuencia nativa de contacto, dirección, envío, pago, revisión y acciones; no se mueve el React interno para imponer otro orden.

`checkout-actions-block` activa los atributos oficiales `showReturnToCart: true` y `cartPageId: 8`. «← Volver al carrito» es el enlace secundario nativo junto a las acciones; conserva el carrito, sin JS custom.

Compra como invitado habilitada. «Crear una cuenta con TRAMA» permanece como checkbox opcional, inicialmente desmarcado; no se requiere contraseña/cuenta para comprar. No se cambiaron ajustes de cuenta existentes.

«Usar la misma dirección para la facturación» está marcado inicialmente por WooCommerce. Desmarcar revela los campos de facturación; volver a marcar los oculta y Woo conserva la sincronización. No se duplican checkbox ni direcciones en código del theme.

Campos finales nativos para Argentina: email, nombre, apellido, país, dirección de la calle, departamento/unidad opcional bajo disclosure, localidad/ciudad, provincia, código postal y teléfono opcional. Empresa sigue oculta; nota del pedido opcional. Requisitos y validación postal son del core; no se agregó validación externa. Autocomplete usa email/given-name/family-name/country/address-line1/address-line2/address-level1/address-level2/postal-code/tel y secciones shipping/billing nativas.

Mi Cuenta conserva la navegación y las dos tarjetas de dirección ya corregidas post-H5. La revisión de `WC_Form_Handler::save_address()` y `templates/myaccount/my-address.php` confirma edición independiente; no hay sincronización persistente nativa que activar. Una futura copia/sincronización debe definirse en Commerce Core/H8, con decisión explícita sobre su persistencia. No se implementó en el theme.

## i18n, feedback y accesibilidad

Se mantienen `Apellido`, `País`, `Provincia` mediante los filtros públicos de locale, y el suplemento Jed existente de `load_script_translations` para el plural faltante de es_AR: «Corregí el siguiente error antes de continuar» / «Corregí los siguientes errores antes de continuar». El patrón oficial del Mini-Cart vacío tenía otro msgid sin traducción PHP: un suplemento acotado `gettext_woocommerce` traduce «Tu carrito está vacío» sólo en es_AR y sólo si falta traducción oficial, que tiene precedencia futura. No se reescriben textos renderizados ni archivos de WooCommerce. Los títulos de página ahora son «Carrito» y «Finalizar compra» como contenido WordPress; el nuevo status usa `wp.i18n`.

Inputs/select/textarea usan geometría común; dark/light contempla campos, floating labels, autofill, checkbox/radio, notices, cupones, disabled y loading. Los campos conservan sus labels flotantes nativos. Lectura y acciones de H6.1 usan 16px como mínimo; el contador compacto aprobado del header mantiene su tamaño anterior. Focus-visible usa el token H3 y targets nativos ampliados. Notices no bloquean el sitio; errores se mantienen cerca de campos y en la región accesible nativa. Checkout utiliza su bloqueo de envío/espera nativo.

Objetivo WCAG 2.2 AA, con pruebas de teclado, foco, nombre accesible, orden DOM, contraste y reduced motion. Esto no constituye una certificación exhaustiva ni acredita lectores de pantalla/dispositivos físicos no probados.

## Performance y configuración protegida

Carrito omite CSS editorial/lookbook, pero conserva CSS de las cards compartidas. Checkout omite CSS de páginas, catálogo y lookbook; no carga `catalog.js`, `variations.js` ni assets de Shoppable Lookbook. Mini-Cart comparte sólo su presentación. No se desregistran scripts WooCommerce. Cart/checkout/account siguen con cabeceras nativas sin caché; no se incorporó caché.

Backup íntegro privado: `.local/local/backups/h6-1-before-cart-checkout`, base+WordPress+manifiesto SHA-256. `configure` exige ese respaldo y hashes exactos del carrito/checkout H5; preserva edición manual y aborta ante cambios de productos. El manifiesto `trama_h6_1` conserva páginas originales y huellas/relaciones before/after. Se actualizan deliberadamente sólo los fingerprints H5 de los padres afectados. Ni el source H5, ni stock, precios, atributos, fotos, guías, Home, shipping ni gateways se reseedean.

Las variaciones heredan cross-sells del padre y Woo retiene instancias dentro del proceso. La comparación normaliza únicamente ese campo en el árbol para reconstruir la huella anterior, y una nueva invocación verifica todas las huellas persistidas sin esa normalización. La protección detectó ese detalle durante desarrollo; la recuperación acotada y H5 pasaron antes de reaplicar. `recover` sólo revierte relaciones de una migración interrumpida antes de escribir páginas; si páginas/productos cambiaron de otro modo, preserva todo para revisión. `refine` permite actualizar sólo contenido del carrito desde fuente bajo revisión, exige backup y huellas actuales, y conserva la revisión anterior en el manifiesto. Ninguno se necesita para navegar.

```powershell
.\scripts\h6-1.ps1 inspect
.\scripts\h6-1.ps1 verify
# Reproducir sobre baseline H5.1 existente, sólo tras respaldo:
.\scripts\h6-1.ps1 backup
.\scripts\h6-1.ps1 sync
.\scripts\h6-1.ps1 configure
# QA: usa Node y paquetes bundled (NODE_PATH) del entorno.
.\tests\verify-h6-1.ps1
node .\tests\verify-h6-1-editor.cjs
```

`compose.yaml` añade únicamente el fixture `demo/cart-checkout` como lectura para WP-CLI. No se añadió un bind mount del theme/WordPress, servicios ni puertos.

## QA y evidencia

Evidencia privada en `.local/local/h6-1/`; capturas, reportes, dumps y credenciales quedan fuera de Git. Resultados ya completados:

| Verificación | Resultado |
| --- | --- |
| Navegador H6.1, consolidado final | 148 casos únicos aprobados según su última observación; matriz inicial más revalidaciones dirigidas |
| Cierre dirigido final | 27 comprobaciones aprobadas y el caso restante de teclado aprobado en ejecución aislada; cero errores JS/ResizeObserver |
| H6.1 PHP / datos | 27 comprobaciones, aprobadas |
| Stock funcional aislado / HPOS / invitado | 13 comprobaciones, aprobadas |
| Editor Gutenberg | 4 comprobaciones, cero errores JS; borrador propio eliminado |
| Regresión H5 | 304 comprobaciones, cero fallos / errores JS |
| Regresión H5.1 | 87 comprobaciones, cero fallos / errores JS |
| Smoke H2 local | 10 comprobaciones, HPOS/stock intactos; ningún pedido persistido |
| Mercado Pago H2 | Visible, script oficial cargado, sin enviar pedido ni pago |
| Contraste y foco H6.1 | 16 estados, 412 textos: mínimo 5,12:1; bordes mínimo 3,34:1; 16 focos de 2px, mínimo 5,54:1; cero errores JS |

`final-audit.json` reúne 148 casos únicos con su última observación aprobada; es un consolidado, no una nueva ejecución monolítica. Los reportes originales y sus revalidaciones se conservan separados: `browser-qa.json` registra la matriz inicial (147 aprobados, un fallo y notificaciones ResizeObserver). `browser-qa-closure-final.json` registra 27 aprobados y un problema de sincronización del foco de la prueba, resuelto por `browser-qa-webkit-390-keyboard-enabled-final.json` (1/1). Ambas ejecuciones de cierre terminaron sin errores JS/ResizeObserver. Se conservan seis capturas finales actuales.

- Verificación PHP funcional: disponible simple/variable, última unidad, sobrestock, agotado tras agregar y mensaje español. La simulación altera sólo un clon del item en un carrito CLI aislado; no guarda productos ni cambia stock.
- QA navegador: Chromium, Firefox y WebKit; light/dark; 320, 375, 390, 430, 1024, 1366, 1440, 1920, 2560 y 3440px. Geometría, overflow, teclado, cupones, cantidades, eliminación, vacío, contador, mini-cart, cross-sells, retorno, invitado, autocomplete, misma dirección, validaciones y error sin pago.
- Editor: bloques válidos y filtro oficial; copia temporal guardada/reabierta; Cart publicado preservado; borrador eliminado.
- Regresión H5/H5.1, HPOS y smoke de Mercado Pago en 18081 sin enviar pedido ni pago.
- Revisión visual directa de carrito/checkout/recomendaciones en navegador a 320/390px, claro/oscuro, altura reducida 430px, QHD 2560×1440 y ultrawide 3440×1440 (layout 1760px, formulario 896px), además de capturas de las suites. Se corrigieron texto de resumen móvil de 14px, mensaje inglés del mini-cart vacío, contador sin estilo en checkout, panel lateral estirado y espacio reservado por filas vacías de totales.
- La revalidación de geometría espera las líneas reales después de la hidratación: 60 estados de carrito en tres motores, dos temas y diez anchos. El diagnóstico final `resize-observer-native-ax-final-diagnostic.json` verifica el observer nativo activo, cero errores y flechas ocultas fuera del foco. La causa se aisló en la geometría de su navegación; la atribución inicial exclusiva a grid/flex quedó corregida en `resize-observer-conclusion.json`.
- El error sin pago se revalidó a 1440px y con click real a 390px. La prueba espera que termine el desplazamiento del navegador y el recálculo nativo antes de accionar el botón; no duplica envíos ni reemplaza el submit de WooCommerce.

Las pruebas de envío de formulario usan comprador ficticio `h6-1-qa@example.test`, sin gateway ni datos de pago. `finally` limpia sólo borradores checkout-draft/store-api de ese email y nombre exactos, sin pago/transacción/reducción de stock; preserva cualquier estado inesperado. Limpia también el cupón marcado como propio. Cierre: cero borradores de QA y cero cupones temporales restantes. Los carritos de las suites son sesiones aisladas y quedaron vacíos.

WebKit emulado en Windows no acredita Safari físico/iOS. Viewports táctiles y altura reducida no reproducen un teclado virtual de iOS/Android: esa comprobación física queda explícitamente como revisión manual. No hay sticky CTA que tape el área inferior.

## Correcciones post-H6.1 y Exit Intent (Commerce Base 1.4.1)

Baseline manualmente aprobado: `d11ab4ca5f4d3c522003934d6bced48d7b0957b6`. Se trabaja sobre la misma rama, sin merge a main, H6.2 ni H7.

### Mini-Cart: diagnóstico y presentación

El importe de `.wc-block-cart-item__prices` es el precio unitario nativo (original/actual en oferta). `.wc-block-cart-item__total` es el importe de la línea por cantidad. Las reglas responsivas oficiales `.is-small ... .wc-block-cart-items__row .wc-block-cart-item__total` tenían más especificidad que la regla H6.1 y imponían `grid-row-start: 1`. El total se superponía al título y al área de controles: el hit-test llegaba a esa celda en lugar de `−`/papelera. No era un fallo del store ni de sus listeners.

Presentación elegida: nombre, variación, precio unitario y selector de cantidad; subtotal general al pie. Se omite deliberadamente el subtotal por línea en el drawer para evitar redundancia. Ofertas conservan precio original tachado y actual; Woo calcula todos los importes. La página Carrito conserva subtotales por línea. El layout final usa flex-wrap: imagen de 64px y producto con base de lectura de 11,5rem. Evita las columnas implícitas del grid responsivo del core y apila automáticamente cuando el espacio es estrecho, incluso bajo zoom; no se reducen los targets de 44px.

El drawer nativo posiciona/desplaza usando `--drawer-width` y `--neg-drawer-width`; H6.1 había cambiado `width` sin armonizar esas variables. Ahora ancho y desplazamiento comparten una medida de hasta 32rem y 100% del espacio disponible. `box-sizing`, `min-inline-size` y `max-inline-size` mantienen el contenido dentro del panel. `scrollbar-gutter: stable` reserva espacio en drawer y lista desplazable; en motores sin soporte queda el overflow nativo con padding. No se oculta la scrollbar ni se agrega una personalizada.

Vacío: wrapper flex en columna con alineación inicial, gap por token, mensaje y grupo de botón sin márgenes automáticos/centrado. Comparten referencia horizontal. Para las cabeceras Producto/Total, los bordes superiores de celdas con distintos modos de display (`table-cell` y `flex`) y border-collapse daban referencias separadas. Se usa un único borde en la fila del encabezado y se retira el borde superior de la primera fila de datos. El cupón pasa a flex centrado, con icono estático al final: se elimina el translateY que correspondía al posicionamiento absoluto nativo; al abrir rota 180°. El enlace oficial Volver al carrito usa inline-flex centrado, gap común y SVG sin margen; hover desplaza la flecha horizontalmente y reduced motion lo desactiva.

### Exit Intent reutilizable

Archivos: `inc/exit-intent.php`, `assets/js/exit-intent.js`, `assets/css/exit-intent.css`. El filtro `commerce_base_exit_intent_config` permite desactivar y cambiar copy/tiempos (mínimos 15s/24h). No hay nombres técnicos acoplados a TRAMA, librerías, servicios, tracking ni backend.

Trigger: `mouseout` del documento con `relatedTarget === null`, `clientY <= 0` y `0 < clientX < innerWidth`. No responde a cruces internos ni salidas laterales. Exige viewport mínimo 960px, `(hover: hover)` y `(pointer: fine)`, página visible, interacción real previa (pointerdown/teclado/scroll), 15 segundos desde inicio del script, store listo con al menos una unidad, cooldown vencido y ausencia de otro panel/modal. No intenta adivinar el cierre de pestaña ni distinguir con certeza DevTools.

El Mini-Cart carga sus datos de forma diferida. En escritorio elegible se usa una resolución inicial pública `wp.data.resolveSelect(cartStore).getCartData()`; luego el trigger lee `wp.data.select(cartStore).getCartData().itemsCount`. No hay petición en el evento de salida, endpoint manual, polling, lectura del contador ni estado paralelo de cantidades. Si el store no está listo, no muestra el recordatorio.

Se guarda timestamp al mostrar en **`commerce_exit_intent_v1`**: localStorage, fallback sessionStorage y finalmente memoria. Se consulta almacenamiento de nuevo en cada intento para respetar otras pestañas. Con localStorage bloqueado, la garantía se limita a la sesión; con ambos bloqueados, al documento actual. Un timestamp vigente impide volver a mostrar tras cerrar, cambiar de pestaña o recargar. La prueba de 24h modifica sólo esa key de un contexto aislado.

Exclusiones de servidor: wp-admin, checkout (incluye pago/order received), endpoints order-pay/order-received, Mi Cuenta y vista de personalización. No se sirve en páginas externas de Mercado Pago. En cliente se excluyen menú fullscreen, mini-cart, cualquier dialog modal visible y galería PhotoSwipe. En móvil/touch no se registran listeners ni se resuelve el store para esta feature. Si se carga inicialmente en móvil y luego se conecta un mouse, se habilita en una navegación posterior elegible.

Copy: **¿Te vas sin tus cosas?** / **Tu carrito sigue acá.** Acciones: **Ver carrito** (enlace Woo) y **Seguir comprando** (cierra). En Carrito: sólo **Seguir con mi compra** (cierra en el mismo lugar). Cierre adicional con botón accesible y Escape. No ofrece promociones ni urgencia.

`<dialog>` nativo con aria-modal, título/descripción asociados, foco inicial en título, trap de Tab/Shift+Tab y restauración de foco al cerrar. Apertura y cierre de 220ms con opacidad/desplazamiento leve; reduced motion elimina animación y espera. Light/dark usa tokens existentes. No modifica title/favicon, no usa visibilitychange como trigger, beforeunload/unload, alert/confirm, sonidos ni notificaciones. Sin soporte de showModal se omite la feature.

QA y capturas post-H6.1 se guardan privados en `.local/local/h6-1/post/`. Suites reproducibles: `node tests/verify-h6-1-post.cjs` y `node tests/verify-h6-1-post-extra.cjs`, con NODE_PATH bundled. Usan sesiones/carritos aislados y los limpian en finally. Se conservan los diagnósticos iniciales y revalidaciones; el reloj simulado alteraba APIs performance que Woo consume, por eso el QA definitivo espera el intervalo real.

Resultado post-H6.1: **98 casos únicos aprobados por su última observación**, según `post/final-audit.json`. Incluye 72 combinaciones de mini-cart (Chromium/Firefox/WebKit, 320/390/1366/1920, claro/oscuro y ampliación CSS 100/125/150%), secuencias nativas de simple/variable/oferta, cantidad y eliminar; 81 comprobaciones finales de layout/interacción y seis revalidaciones del tema vivo sin errores JS. La celda flex evita además el recorte extremo Firefox 320/150%. Ocho escenarios extra verifican salida real del puntero, ausencia de interacción, menú/galería/cruces internos/laterales, storage local/session/memoria, cero solicitudes en la salida, title/favicon intactos, acciones específicas de Carrito, reduced motion, bordes/chevron a 1366/1920 y ampliación, retorno alineado, touch y scroll largo. Se conserva el historial de fallos y correcciones de los tests (favicon ausente, sesión iniciada por formulario, espera del cierre nativo), sin filtrar errores.

Regresión: H5 **304/304**, H5.1 **87/87**, integridad H6.1 **27/27**, stock/HPOS/invitado **13/13**, Mercado Pago H2 visible con script oficial y sin enviar pedido/pago. La matriz nativa carrito/checkout dio 147/148 sin errores JS; el único caso de submit Chromium se revalidó mediante Enter real (`post-checkout-revalidation.json`, 2/2 contando alta de productos), con los mensajes españoles exactos. Cierre: cero cupones y borradores ficticios restantes, carritos aislados vacíos.

La revisión visual directa incluyó el drawer con scrollbar nativa Windows, móvil oscuro y cambio de tema; detectó el fondo inline de Woo que retenía el color al montar el drawer. Se anula exclusivamente esa propiedad inline con el token dinámico (prioridad necesaria), y se verificó claro/oscuro después de montar. Se miraron las capturas de Exit Intent, carrito, checkout y scroll largo. La ampliación CSS automatizada no acredita zoom real de la interfaz del navegador ni scrollbar overlay de macOS/iOS; esas variantes quedan para revisión manual en dispositivo. El fallback de almacenamiento se prueba tras resolver Woo, aislándolo de plugins que asumen acceso al storage durante su inicialización; si Woo no puede resolver el carrito, Exit Intent no se muestra.

## Pendientes fuera del alcance

- **H6.2:** decisión comercial de zonas/métodos/costos, cobertura, preparación logística, pesos/dimensiones y evaluación/integración de transportistas cuando corresponda. Permanecen únicamente los métodos técnicos preexistentes H2; no se configuraron tarifas definitivas.
- **H7:** decisión y convivencia de Mercado Pago/BACS u otros métodos autorizados, estados de pago, comunicaciones, SMTP y emails finales. Mercado Pago H2 preservado; local8080 sigue sin gateway habilitado. No se invocaron pagos.
- **H8 / Commerce Core:** diseño de copia/sincronización persistente de direcciones de Mi Cuenta si se confirma esa necesidad.
- Sin deploy, legales, ARCA, Botón de Arrepentimiento ni Commerce Core nuevo.

## URLs de revisión

- [Carrito](http://localhost:8080/?page_id=8) y [checkout](http://localhost:8080/?page_id=9), después de agregar productos.
- [Remera Base variable](http://localhost:8080/?product=remera-base), [Bolsa Diario simple](http://localhost:8080/?product=bolsa-diario), [Pantalón Línea agotado](http://localhost:8080/?product=pantalon-linea).
- [Tienda](http://localhost:8080/?post_type=product), [Home/Lookbook](http://localhost:8080/).
- [Mi Cuenta → Direcciones](http://localhost:8080/?page_id=10&edit-address), con sesión iniciada.
- [Editor del carrito](http://localhost:8080/wp-admin/post.php?post=8&action=edit), [editor del checkout](http://localhost:8080/wp-admin/post.php?post=9&action=edit).
- [Checkout Mercado Pago H2 preservado](http://localhost:18081/?page_id=7), después de agregar un producto técnico de esa instancia. No usarlo para compras reales.

Referencias contrastadas con el código instalado: [bloques WooCommerce](https://developer.woocommerce.com/docs/block-development/reference/block-references/), [extensión pública de hijos de Cart/Checkout](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/filters-in-cart-and-checkout/additional-cart-checkout-inner-block-types/), [store público del carrito](https://developer.woocommerce.com/docs/block-development/reference/data-store/cart/), [direcciones de WooCommerce](https://woocommerce.com/document/configuring-woocommerce-settings/accounts-and-privacy/), [hook i18n WordPress](https://developer.wordpress.org/reference/hooks/load_script_translations/).
