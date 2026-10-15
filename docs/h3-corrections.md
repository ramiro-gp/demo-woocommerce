# Correcciones post-H3 — 15/10/2026

Corrección de revisión manual sobre `a962061d7c8918c5c0cca2071288e4853eff3b02`, rama `codex/h3-commerce-base`, remoto `https://github.com/ramiro-gp/demo-woocommerce.git`. Se confirmó árbol limpio y ruta exclusiva `C:\Codigo\demo-woocommerce`. H4 no se inició.

## Instancias y URLs inequívocas

| Instancia | Servicio/puerto | Propósito | Theme |
| --- | --- | --- | --- |
| `local` | `demo-woocommerce-local-wordpress-1`, `127.0.0.1:8080 → 80` | Instalación original y demo principal para revisar correcciones H3 | Commerce Base **1.0.2**, activado en esta corrección |
| `h2-public` | `demo-woocommerce-h2-public-wordpress-1`, `127.0.0.1:18081 → 80` | Instalación aislada de H2, pruebas de pagos y sus pedidos/evidencia | Commerce Base **1.0.0**, activado durante H3 original, conservado |

La instancia `local` estaba detenida y tenía Twenty Twenty-Five activo. `h2-public` estaba ejecutándose y mostraba Hello world porque tenía la portada configurada para entradas y el contenido de instalación intacto. Commerce Base ya estaba activo allí: no era un fallo de activación. H3 se había revisado previamente en ese entorno técnico.

Ahora abrir:

- [Tienda H3 corregida](http://localhost:8080/?post_type=product).
- [Producto variable real](http://localhost:8080/?product=h2-variable).
- [Showcase H3 corregido](http://localhost:8080/?commerce-base-showcase=1).
- [Carrito](http://localhost:8080/?page_id=8), [checkout](http://localhost:8080/?page_id=9), administración `/wp-admin/`.
- `http://localhost:8080/` usa el índice base, sin entradas iniciales. No se creó la home definitiva.

Los IDs de las URLs de carrito/checkout describen esta instalación actual; el theme y las herramientas los resuelven con APIs de WooCommerce. Mailpit de la demo: `8025`; Mailpit H2: `18026`. `h1-final` sigue detenido como entorno de verificación histórico; no es una tercera tienda para revisión. El stack Supabase ajeno no se tocó.

No se copiaron credenciales ni opciones de pago entre instancias. La demo `local` conserva su configuración anterior **sin métodos de pago habilitados**; el mensaje nativo del checkout informa esa situación. Mercado Pago permanece configurado en `h2-public`. No se reconfiguró ni duplicó Checkout Pro. La sincronización opcional del theme nuevo a `h2-public` fue rechazada por revisión automática por considerar que modificaría H2; se continuó con la alternativa de corregir únicamente `local` y comprobar H2 mediante lectura. No hay correcciones actuales publicadas en 18081.

Para detener el entorno técnico sin perder datos: `powershell -NoProfile -File scripts/dev.ps1 stop -Instance h2-public`. No se ejecutó esta detención: la tarea deja disponible la demo y conserva el entorno técnico que ya estaba encendido.

## Componentes corregidos

**Underline.** `.cb-link` dibujaba una línea animada con background; la regla desktop `.cb-header-catalog:hover` añadía `text-decoration: underline`. Se retiró esa segunda decoración. El mismo componente conserva una línea de 100% a 35% en hover/focus-visible/active, con foco visible. `inline-size: fit-content` evita que links en grids/flex estiren su decoración. Se revisaron marca, catálogo e iconos del header.

**Volver arriba.** Link secundario con flecha, target de 44px, ancho del contenido, color secundario y movimiento de 2px. Usa un ancla real al comienzo del body, foco y scroll suave existentes; reduced motion salta directamente. No se coloca el destino debajo del header fijo.

**Footer.** Marca y descripción de WordPress; columna Tienda con tienda y carrito; columna Ayuda e información con cuenta/pedidos y páginas publicadas cuando existan. Se prepararon contactos, FAQ, envíos, cambios/devoluciones, términos, privacidad y arrepentimiento sin crear páginas ni links a borradores. La ubicación WordPress `footer-social` permite configurar Instagram/TikTok/Facebook u otras URLs del comercio; no se muestra vacía ni se inventan perfiles. El bloque nativo Navigation del Site Editor también permite editar su composición en el futuro. Se mantiene la nota de ARS únicamente cuando la moneda real es ARS.

Firma: `Hecho con [corazón] por Ramiro Garcia`. SVG local de corazón con nombre accesible “amor”; SVG decorativo interno; corazón y nombre en accent. Enlace exacto `https://ramita.dev`, `target="_blank"`, `rel="noopener noreferrer"`, anuncio de nueva pestaña, hover y focus-visible. Tipografía pequeña, fuera de la navegación principal.

**Variaciones.** `assets/js/variations.js` depende de `jquery` y `wc-add-to-cart-variation`, cargado sólo en producto variable. Genera botones para todos los atributos reales, sin nombres, términos, IDs ni colores hardcodeados. Conserva los selects y sus values; sólo los oculta tras construir la capa accesible. Cada botón escribe en su select y dispara el `change` oficial. Escucha `wc_variation_form`, `woocommerce_update_variation_values`, `woocommerce_variation_has_changed`, `found_variation` y `reset_data`.

No resuelve variaciones por su cuenta: usa `getChosenAttributes()`/`findMatchingVariations()` de la instancia oficial entregada por el evento. Conserva comodines de atributos y actualizaciones de precio, imagen, SKU, disponibilidad, límites de cantidad y add-to-cart oficiales. Stock y capacidad de compra provienen del mismo payload nativo. Combinaciones imposibles y agotadas muestran texto, borde discontinuo y estado disabled. Los selects sin JS conservan altura mínima y padding sin texto recortado.

Accesibilidad: radiogroup con label real, `role="radio"`, `aria-checked`, texto de estado, tabulación al seleccionado/primer disponible, flechas/Home/End, Space/Enter, foco y reset nativo. No depende del color. Para catálogos que exceden el umbral nativo y consultan variaciones por AJAX, la disponibilidad exacta sólo puede conocerse al recibir `found_variation`; no se inventan estados previos ni se eleva el umbral para descargar todo el catálogo. Ese modo conserva la consulta y bloqueo nativos y añade estado agotado al recibirlo; no se probó como caso real porque los fixtures tienen dos variantes.

**Relacionados.** El UL de Product Collection se había convertido a CSS Grid, mientras la regla nativa `.wc-block-product-template.is-flex-container.is-flex-container.columns-5 > li` seguía aplicando `width: calc(20% - 1em)` a partir de 600px. Eso reducía cada producto a una fracción de su propia celda. Se elimina ese ancho con `width: auto` scoped a items de Product Collection; el `!important` vence la especificidad de los distintos presets de columnas. No hay un porcentaje global arbitrario para li. Se normalizan margen/float/min-width y se usa auto-fill/minmax con ancho mínimo adaptable. Las listas clásicas `ul.products` también tienen reglas limitadas a related/up-sells/cross-sells. Fotografía 4:5, texto/precio de interfaz, alineación inicial, espaciado y hover siguen H3. No se duplicaron productos para llenar columnas.

**Checkbox.** La regla nativa con `[type=checkbox]:checked` conservaba fondo blanco con mayor especificidad, mientras H3 aplicaba una marca casi blanca. Se ajustó la especificidad del fondo, borde y estado; el SVG también recibe `color` además de `fill`, porque WooCommerce utiliza currentColor. Tokens compartidos `--cb-control-selected` y `--cb-control-mark`, derivados de accent/contrast en ambos modos. Contraste aproximado 7.19:1 claro / 9.85:1 oscuro. Hover, foco y disabled visibles.

**Spacing y geometría.** Checkout nativo establecía gap vertical cero mediante su layout flex y los márgenes aislados del país no resolvían la composición. Se adoptó gap 16px del sistema y márgenes verticales normalizados en los grupos de dirección. Campos a ancho completo en móvil; nombre y apellido comparten fila desde 640px. Radius compartido `--cb-radius-sm: 3px` para inputs, selects, textarea, controles equivalentes y opciones de variaciones; botones conservan su geometría editorial. Los selects se distinguen por el chevron y su interacción.

## Labels de Checkout Blocks

Se revisó el código **instalado de WooCommerce 11.2.1**, además de la [referencia oficial de países](https://woocommerce.github.io/code-reference/files/woocommerce-includes-class-wc-countries.html) y las [alternativas de hooks en Blocks](https://developer.woocommerce.com/docs/block-development/reference/hooks/hook-alternatives/).

`inc/checkout-labels.php` usa filtros PHP públicos:

- `woocommerce_get_country_locale_default`.
- `woocommerce_get_country_locale_base`.
- `woocommerce_get_country_locale` (AR; apellido/país también en otras entradas de locale).

`CartCheckoutUtils::get_country_data()` entrega esa metadata a Blocks; se verificó el resultado después de hidratar React, también en facturación independiente. Visibles: **Apellido, País, Provincia**. Se conserva required/hidden, orden, validación y valores. Los cambios se limitan a locales españoles; el nombre Provincia corresponde a Argentina y se conservan denominaciones regionales de otros países. No hay MutationObserver, reemplazo DOM ni filtro clásico `woocommerce_checkout_fields` esperando que afecte a Blocks. Se mantienen las etiquetas opcionales coherentes. Si una futura versión cambia la resolución de locales, repetir el test de labels; no editar bundles WooCommerce.

## Limpieza y preservación

Respaldo completo previo de `local`: `.local/local/backups/h3-before-corrections/`. Snapshot comercial anterior: `.local/local/h3/baseline.json`; no se reemplazó para ocultar diferencias. Identidad TRAMA guardada como nombre/descripción de WP, fuera del código del theme.

`docker/tools/h3-demo-content.php inspect|clean` identifica ejemplos por tipo, slug, título, contenido conocido y fecha de modificación intacta. Excluye referencias a páginas WooCommerce, portada, blog, privacidad y términos. `clean` exige confirmar la URL y se limita a `http://localhost:8080/`; envía a papelera, no borra permanentemente. Eliminados del frontend: Hello world!, Sample Page y el comentario inicial de WordPress asociado. Segunda ejecución: cero candidatos, idempotente. Se preservaron borradores de privacidad/devoluciones no publicados y páginas comerciales; no se redactaron legales. H2 conserva sus ejemplos porque es un entorno técnico independiente.

```powershell
.\scripts\dev.ps1 wp -Instance local -WpArgs @('eval-file','/opt/local-tools/h3-demo-content.php','inspect')
.\scripts\dev.ps1 wp -Instance local -WpArgs @('eval-file','/opt/local-tools/h3-demo-content.php','clean','http://localhost:8080/')
```

## Validación y límites

- 22 comprobaciones específicas: header/footer, variaciones reales, carrito, labels de envío y facturación, checkbox, radius, spacing, reduced motion, no-JS y limpieza. Matriz de producto/checkout/footer a 320, 390, 768, 1024, 1366, 1920, 2560 y 3840px, claro/oscuro.
- Regresión H3 ampliada: 43 comprobaciones, incluyendo otros anchos, header progresivo, menú fullscreen, trap/retorno de foco, panel, anchors, storage y reflow equivalente 200%/400%. No se afirma probar el zoom de la UI del navegador.
- Smoke Store API: simple y S al carrito, sesión invitada aislada, M agotado/exceso rechazados, tarifas/retiro nativos, preview repetido de pedido sin crearlo, HPOS y stock final sin cambios. Browser: selección nativa real, SKU S-NEGRO, máximo 3 unidades, reset y add-to-cart, carrito y Checkout Block hidratados. Los fixtures tienen el mismo precio y no tienen fotos por variación: no se afirma haber verificado cambios de foto/precio inexistentes; esos renderizadores siguen siendo los nativos.
- Sin nuevos pageerror JS; sintaxis PHP/JS válida; sin warnings PHP nuevos ni críticos WooCommerce. El log de `local` conserva una advertencia de conexión del 11/10, anterior a la tarea del 15/10; no se eliminó evidencia histórica.
- Hashes finales de ambas instancias conservan plugins/versiones, HPOS, gateways/credenciales, envíos, productos/stock, pedidos/refunds/reservas y páginas comerciales. No se enviaron pagos/refunds ni se repitió la matriz de Mercado Pago. No se creó un pedido cobrado; el smoke de pedido fue el preview oficial sin creación. Los carritos de QA se vaciaron.
- Capturas finales inspeccionadas manualmente: footer desktop/mobile, producto/relacionados desktop/mobile, checkout claro/oscuro y tilde. Evidencia privada: `.local/local/h3/corrections/`, `.local/local/h3/browser-qa.json`, `.local/local/h2-http.json`.

```powershell
.\scripts\h3.ps1 sync -Instance local
powershell -NoProfile -File tests/verify-h3-local.ps1 -Instance local
powershell -NoProfile -File tests/verify-h2-local.ps1 -Instance local
$env:NODE_PATH = 'C:\Users\ramiro\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
node tests/verify-h3-corrections.cjs
node tests/verify-h3-browser.cjs
```

## Pendientes, sin implementación

**H6 logística:** decidir Correo Argentino, Andreani, OCA u otra solución según el comercio; revisar zonas, tarifas, credenciales, tracking y operación. Se mantiene el envío básico/nativo actual. Sin plugins logísticos, APIs externas ni integración custom.

**H6/H7 pagos:** revisar la experiencia final de Checkout Pro, que ofrece tarjetas y otros medios dentro de Mercado Pago; evaluar posteriormente transferencia bancaria directa u otro gateway independiente sólo si el comercio lo necesita. Sin instalar/configurar pasarelas, duplicar Checkout Pro ni trasladar credenciales ahora.

## Revisión manual del cierre

Segunda revisión, Commerce Base 1.0.2: se eliminó el `margin-top: 12px` nativo del contenedor interno del select Provincia dentro de formularios de dirección WooCommerce. En desktop Ciudad y Provincia vuelven a compartir borde superior y altura de 50px; también se comprobó en mobile y ambos modos. La cabecera del diálogo de menú quedó separada del panel animado: la franja superior permanece fija/opaca y el contenido se desliza por debajo. Al finalizar el cierre, el header normal recupera su transparencia en 240ms. Se mantienen modalidad, foco, Escape, reapertura, scroll y reduced motion. El controlador escucha el `transitionend` del panel que efectivamente se mueve. QA final: 43/43 comprobaciones generales (incluye muestras de cada frame del cierre móvil en claro/oscuro) y 22/22 específicas; sin errores JS/PHP nuevos y baseline comercial intacto.

En 8080: alternar claro/oscuro; revisar hover/focus del catálogo; abrir/cerrar menú; recorrer footer y firma/nueva pestaña; volver arriba; probar S/Negro, M agotado, reset y teclado; mirar relacionados a distintos anchos; agregar al carrito y revisar tilde, labels, separación y radius de checkout. La raíz no sustituye el showcase y todavía no tiene home de H4. La disponibilidad de medios de pago en la demo queda expresamente fuera de este cierre visual.

## Avisos de WooCommerce en modo oscuro

Commerce Base 1.0.3 fija la tinta de texto en carbón dentro de los avisos de WooCommerce Blocks y de los avisos clásicos (`success`, `info` y `error`) cuando está activo el tema oscuro o el modo oscuro del sistema. El color se aplica también a los elementos internos y enlaces para que los mensajes sigan siendo legibles sobre el fondo claro que entrega WooCommerce.
