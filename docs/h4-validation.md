# H4 — Home y estructura institucional

**Estado: implementado y validado; listo para revisión visual del usuario.** Trabajo del 15/10/2026, hora de Argentina, exclusivamente en `C:\Codigo\demo-woocommerce`, rama `codex/h4-institutional-home`. H0–H3 y las correcciones post-H3 son la base aprobada. Se confirmó remoto `https://github.com/ramiro-gp/demo-woocommerce.git`, árbol limpio y cierre H3 en `a212c310e67e6cef91e8044f8b501f59aa8d42c0`, también presente en GitHub, antes de editar. H5 no comenzó.

## Instancia y accesos

H4 utiliza únicamente la instalación existente `local`, `http://localhost:8080`, con Mailpit en `http://localhost:8025`. `h2-public`, `http://localhost:18081`, conserva el entorno técnico y la evidencia de pagos de H2. No se trasladaron sus credenciales ni se habilitaron gateways en la demo principal.

URLs leídas del reporte privado `.local/local/h4/content.json` después de sembrar las páginas:

| Vista | ID de esta instalación | URL actual |
| --- | ---: | --- |
| Inicio | 37 | <http://localhost:8080/> |
| Conocenos | 38 | <http://localhost:8080/?page_id=38> |
| Contacto | 39 | <http://localhost:8080/?page_id=39> |
| Preguntas frecuentes | 40 | <http://localhost:8080/?page_id=40> |
| Tienda | 7 | <http://localhost:8080/?page_id=7> |
| Búsqueda de productos | — | <http://localhost:8080/?s=h2&post_type=product> |
| Ruta inexistente de QA | — | <http://localhost:8080/pagina-inexistente/> |

Los slugs editoriales son `inicio`, `conocenos`, `contacto` y `preguntas-frecuentes`. Se preservó la estructura de enlaces permanentes existente: los links se obtienen mediante `get_permalink()`, `wc_get_page_permalink()` y `get_term_link()`. Los IDs anteriores son evidencia local, no constantes del theme. No se cambiaron las URLs de carrito, checkout, cuenta ni productos.

## Home y contenido

La home contiene seis secciones y el footer existente, sin carrusel, newsletter ni grillas repetidas:

1. Hero editorial: eyebrow **TRAMA / Ropa para todos los días**, H1 **Vestir lo cotidiano.**, descripción breve y dos accesos: **Ver catálogo** y **Conocé TRAMA**. Copy, imagen y acciones son hermanos dentro de `cb-home-hero-grid`; CSS dispone texto/imagen en desktop y mensaje/imagen/CTA en mobile. La altura desktop apunta a aproximadamente 65–72svh cuando el contenido y el viewport lo permiten. Mobile crece según el contenido.
2. Selección inmediata de productos: **A mano, todos los días.** y `commerce-base/product-grid` con `limit: 4`. Consulta los publicados más recientes visibles en catálogo mediante `wc_get_products`, respetando la opción nativa de ocultar agotados. Con los fixtures existentes muestra dos productos; no inventa ni repite productos para llenar columnas.
3. Categorías: navegación editorial numerada con términos reales y enlaces nativos. La selección de términos se guarda en atributos del bloque, no en una lista paralela del theme.
4. Lookbook: **El detalle cambia el ritmo.**, una imagen amplia, texto corto y acceso al catálogo.
5. Resumen de marca: **Menos vueltas. Más uso.**, con enlace a Conocenos.
6. Tres beneficios prudentes: elegir con información, revisar antes de confirmar y encontrar respuestas. No prometen plazos, envío gratuito, garantías comerciales ni políticas aún no definidas.

Conocenos presenta una historia ficticia breve, la mirada urbana y tres ideas de diseño: forma, textura y movimiento. No afirma certificaciones, origen de fabricación ni características ambientales no comprobadas. Contacto muestra posiciones editables para WhatsApp, Instagram, email y horario/ubicación, con su estado demo explícito. No contiene números, direcciones, perfiles de terceros, formulario ni envío de mensajes.

FAQ usa `core/details` con `details`/`summary` nativos. Contiene las siete preguntas solicitadas: talle, medios de pago, envíos, demora, cambios, cuenta y consulta del pedido. Informa que la demo no recibe pagos; las respuestas de envíos/cambios son orientativas y no reemplazan las futuras políticas. Apertura, cierre, foco y teclado dependen del control nativo, con una transición CSS breve que respeta reduced motion.

## CMS, templates y patrones

Los títulos, textos, imágenes y CTAs se guardan como bloques nativos en las páginas de WordPress. La identidad TRAMA se incorpora en el seed y en `blogname`/`blogdescription`; no queda fijada en templates o componentes reutilizables.

`front-page.html` contiene header, `main` y `post-content` sin un contenedor que limite toda la portada. `page-institutional.html`, registrado para páginas en `theme.json`, tampoco añade `post-title`: cada página institucional contiene su único H1 editable. Las composiciones usan los gutters, fuentes, tokens, botones y modos claro/oscuro de H3; `assets/css/pages.css` añade la composición contextual y también se carga en el editor.

Patrones reutilizables, independientes de la identidad:

| Slug | Composición |
| --- | --- |
| `commerce-base/page-introduction` | Introducción editorial de página, H1 y texto de lectura |
| `commerce-base/editorial-story` | Imagen y relato breve con CTA |
| `commerce-base/category-navigation` | Encabezado y navegación dinámica de categorías |
| `commerce-base/home-selection` | Selección de cuatro productos reales con acceso al catálogo |

Se conserva el patrón H3 `commerce-base/editorial-hero`. El seed H4 guarda bloques expandidos y editables; no obliga a editar un patrón sincronizado para cambiar el contenido de una página.

Componentes dinámicos nuevos, registrados con APIs públicas y preview del editor:

- `commerce-base/category-links`: `limit` y `termIds`. Consulta `product_cat`; una selección explícita permite términos vacíos y respeta el orden elegido. Sin selección, consulta categorías con productos. Usa thumbnails de categoría cuando existan, nombres/destinos nativos y estado **Colección en preparación** para las vacías.
- `commerce-base/search-tools`: formulario nativo de búsqueda de productos y acceso al catálogo; `recovery: true` añade volver al inicio. No agrega un motor de búsqueda.

`search.html` usa la consulta general heredada de WordPress. `product-search-results.html` usa Product Collection y bloques nativos WooCommerce, conservando término, resultados, paginación y estado vacío. `404.html` ofrece inicio, catálogo y búsqueda, sin redirigir automáticamente. El HTTP 404 real debe acreditarse en la tabla de evidencia.

## Categorías estructurales de H4

Se crearon únicamente cuatro términos vacíos de navegación: **Remeras, Buzos, Pantalones y Accesorios**. Sus IDs locales son 19, 20, 21 y 22, respectivamente; el seed y los bloques los resuelven/guardan como referencias de taxonomy. Si un slug ya existe, se utiliza sin modificarlo. Si existe un ID registrado, se conserva esa referencia aunque cambie el nombre o slug del término.

No se asignaron los fixtures a esas categorías, ni se modificaron productos, atributos, SKU, fotos comerciales o stock. **Colección en preparación** distingue esos archivos vacíos de la selección real disponible. Las categorías constituyen estructura navegable H4; su catálogo, fotografía y clasificación final corresponden a H5.

## Imágenes y movimiento

El hero reutiliza la fotografía propia de H3 `assets/images/editorial-specimen.webp`, 960×1200. El lookbook incorpora la imagen generada para esta demo `editorial-detail.webp`, 1536×1024, y su variante `editorial-detail-small.webp`, 768×512: una persona con remera cruda y pantalón oscuro sentada sobre piedra, con una sobrecamisa oliva al lado. [Procedencia, prompt y optimización](h4-assets.md). Se aplicó el skill obligatorio `frontend-design` de Vercel desde la copia local fijada en H3 y el skill `imagegen` para el nuevo asset.

Los bloques `core/image` guardan atributos y markup nativos, con relación de aspecto y object-fit. Un filtro de render añade dimensiones y metadatos de carga a las imágenes locales del theme: hero eager/high; imágenes posteriores lazy; lookbook con `srcset`. Esto mantiene las imágenes editables y evita guardar atributos ajenos al serializer de Gutenberg.

El motion contextual usa CSS: entrada tipográfica breve del hero, recorte inicial de fotografía, flecha de categorías, escala mínima del lookbook y respuesta del FAQ. El contenido permanece visible sin JS. Se conservan el controlador de menú, foco, Escape, scroll lock, anchors y reduced motion de H3. No se añadió librería de animación, parallax ni interceptación del scroll.

## Operación conservadora

`scripts/h4.ps1` admite `inspect`, `sync`, `backup`, `seed` y `verify`, únicamente para `local` en puerto 8080. `sync` reutiliza `scripts/h3.ps1 sync`; no elimina runtime ni añade mounts. `seed` exige un backup completo de la misma instancia y valida SHA-256 antes de escribir. El respaldo preventivo de esta entrega es `.local/local/backups/h4-before-content/`; la acción `backup` permite crear otro deliberadamente, sin reemplazar uno existente.

El seed guarda un manifiesto privado en la opción WordPress `commerce_base_h4_demo_content`, marcadores de propiedad en las páginas y hashes de páginas, menú, portada e identidad. Antes de escribir detecta slugs ajenos, páginas protegidas, páginas eliminadas o ediciones manuales. Una diferencia bloquea el seed; no sobrescribe ediciones del CMS. Las categorías no se sobrescriben. Un menú propio parcialmente creado puede completarse por objeto/ID/título sin duplicar ítems; elementos inesperados exigen revisión.

`verify` comprueba los hashes y exige las cuatro páginas publicadas, template esperado, un H1 nativo por página, portada correcta y menú primary con sus cuatro enlaces nativos ordenados. El reporte `.local/local/h4/content.json` contiene URLs, IDs, categorías y pendientes; no contiene credenciales. Estas guardas no reemplazan la revisión de Gutenberg, la prueba de navegación ni la comparación comercial de H3.

```powershell
.\scripts\h4.ps1 inspect
.\scripts\h4.ps1 seed -BackupId h4-before-content
.\scripts\h4.ps1 verify
powershell -NoProfile -File .\tests\verify-h3-local.ps1 -Instance local
powershell -NoProfile -File .\tests\verify-h2-local.ps1 -Instance local
$env:NODE_PATH = 'C:\Users\ramiro\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
node .\tests\verify-h4-browser.cjs
```

No repetir el snapshot H3 para ocultar diferencias. No ejecutar fixtures, resets, pagos, refunds, túneles ni sincronizar H4 a `h2-public` para comprobar este hito.

## Evidencia y criterios de cierre

Chromium: **223/223 comprobaciones** en la pasada completa final. Tras corregir el gutter de búsqueda detectado visualmente se repitió únicamente ese scope: **64/64**, con capturas actualizadas y comprobación de alineación añadida al arnés. La matriz principal comprende **168 combinaciones** (7 vistas × 12 anchos × 2 modos). Reportes privados `browser-qa.json`, `browser-qa-search.json`, `editor-qa.json`, `performance.json` y capturas correspondientes.

| Área | Estado y evidencia |
| --- | --- |
| Sintaxis PHP/JS/PowerShell, theme.json y templates | Aprobada; PHP lint del theme/seed, node --check, parser PowerShell, verificador H3 |
| Seed idempotente y guardas | Segunda ejecución y verify aprobados; páginas, menú y portada conservados. Guardas de ediciones y recuperación revisadas en código; no se simularon fallos destructivos sobre la demo |
| Editor y previews | Login real; Home 58 bloques, Conocenos 30, Contacto 24, FAQ 26: cero inválidos o no registrados; no se guardaron cambios en QA |
| HTTP | Home/institucionales/búsqueda: 200. Ruta inexistente: 404, sin redirect a inicio |
| Búsqueda | Producto real y término visibles, nueva búsqueda y vacío con recuperación; paginación nativa. General core/query: TRAMA devuelve cuatro páginas, término inexistente devuelve estado vacío; ambos HTTP 200 |
| Hero | 70svh: 1366×768 → 537.59px; 1440×900 → 630px; 1920×1080 → 756px; 2560/3440×1440 → 1008px; 3840×2160 → 1512px. Mobile: orden mensaje/imagen/CTAs, altura por contenido |
| Responsive | 320, 375, 390, 430, 768, 1024, 1366, 1440, 1920, 2560, 3440 y 3840px; sin overflow horizontal |
| Sistema/claro/oscuro | Matriz y contraste calculado de texto sobre superficies sólidas; selección manual/persistencia y cambios de SO; fotografías sin filtros destructivos |
| Teclado | Trap Tab/Shift+Tab, Escape, foco inicial/retorno, lock/restauración de scroll por página; FAQ Enter/Space, búsqueda y firma accesibles |
| Reduced motion / sin JS | Todas las vistas esenciales visibles; navegación alternativa, búsqueda servidor y details nativos. Reflow equivalente 200/400%, sin afirmar zoom de la UI del navegador |
| Regresión H3 | 22/22 correcciones: header/menú, underline, footer, variaciones nativas, checkbox, labels y spacing de checkout, volver arriba y selects sin JS |
| WooCommerce | Tienda/producto, S real/add-to-cart, M agotado rechazado, carrito/Checkout Blocks hidratados, Store API y HPOS aprobados; sin nuevos pedidos ni cambios de stock |
| Mercado Pago | Checkout de H2 muestra Mercado Pago y carga script oficial, sin pageerror, pedido o pago enviado; carrito invitado vaciado. Demo 8080 conserva cero gateways habilitados |
| Preservación comercial | Hash local `5c12769ee2ff2e0b89a2c4fe4285ba7397143b5a049fd2abe8b3ad5004f12a8f` y H2 `de3c4462a4ff9ef130416dd9d15e2385e34f6dc6270cf60e02e5aa2f81d627d5`, iguales antes/después |
| Performance local | CLS 0.00060 mobile / 0.00125 desktop; LCP observado 460/484ms en Chromium local sin throttling. No equivale a métricas de producción. Hero eager/high; lookbook responsive y lazy; sin librerías nuevas |
| Logs y limpieza | Cero pageerror/assets fallidos reales; sin PHP críticos nuevos. Debug log conserva sólo una entrada del 11/10 anterior a H4. Hello world/Sample Page siguen en papelera, cero candidatos de limpieza |
| Inspección visual | Capturas desktop/mobile claro/oscuro: hero, Home completa, institucionales, FAQ, búsqueda/vacío, 404 y footer; corregidos los problemas detallados abajo |

## Navegación, footer y revisión final

Primary: **Inicio, Tienda, Conocenos, Contacto**. El menú conserva el controlador fullscreen de H3. FAQ queda en footer, junto con tienda, carrito y cuenta/pedidos. No hay enlaces a legales sin publicar ni redes inventadas. La ubicación `footer-social` queda preparada para URLs del comercio. Menú, fallback sin JS y futuras redes externas controladas por el theme aplican `_blank` y `noopener noreferrer`, incluso para URLs protocol-relative; los internos permanecen en la misma pestaña. No se modifican redirecciones de Mercado Pago.

El footer conserva marca/descripción editables de WordPress, **Precios expresados en pesos argentinos (ARS).**, volver arriba y **Hecho con [corazón] por Ramiro Garcia**. Corazón/nombre usan accent; enlace exacto `https://ramita.dev`, target/rel correctos. Colores, superficies, controles y foco siguen los tokens claro/oscuro de H3. El layout mantiene el máximo 110rem en ultrawide; los textos tienen medidas de lectura y el footer tiene navegación real.

Problemas detectados y resueltos:

- La proporción intrínseca de la fotografía estiraba el hero a más de un viewport; la imagen desktop ocupa el área del grid sin dictar su altura. Se midió nuevamente 70svh.
- Los márgenes entre hijos de grupos WP desalineaban columnas; se normalizaron sólo en los grids H4.
- WooCommerce añadía `cb-native-commerce` a búsqueda y anulaba el gutter; se restauró el contenedor H4 y se agregó verificación de alineación con header.
- La grilla heredada no filtraba visibilidad comercial: ahora respeta catálogo y la decisión de ocultar agotados antes de limitar resultados.
- Los enlaces externos de menús configurables dependían del checkbox del editor; ahora se normalizan en las superficies controladas por el theme, sin tocar gateways.
- El placeholder estándar de WooCommerce todavía dominaba los productos sin foto; se conectó el SVG propio H3 para ese fallback, conservando placeholders propios del comercio.
- El primer arnés sin JS esperaba una promesa de fuentes/rAF de una página con scripts deshabilitados; se corrigió la espera y se repitió el caso. La evidencia inicial permanece conservada; no fue un error del sitio.

Las imágenes y textos institucionales, botones y links se editan en Páginas; el nombre/descripción en ajustes de WP; categorías/nombres/thumbnails en Productos → Categorías; selección/cantidad mediante atributos del bloque; navegación mediante la ubicación primary. No se agregaron formularios, CRM, plugins ni un catálogo paralelo. Fuentes técnicas de búsqueda: [theming de Woo blocks](https://developer.woocommerce.com/docs/theming/block-theme-development/theming-woo-blocks) y [Product Search Results](https://woocommerce.com/document/woocommerce-store-editing/templates/customizing-product-search-results/), contrastadas con el código instalado WooCommerce 11.2.1.

Capturas y reportes de QA deben permanecer bajo `.local/local/h4/`, excluidos de Git. No se certifica exhaustivamente WCAG 2.2 AA ni se afirma preparación para producción. H4 no incorpora Commerce Core, legales definitivos, integraciones logísticas, medios de pago nuevos, SEO final ni deploy. La validación comercial y los límites de H2 continúan en su [informe de cierre](h2-validation.md).

## Corrección de rutas 404 tras revisión del usuario

La comprobación inicial de 404 usaba `?p=2147483647`. Esa consulta sí devolvía la pantalla correcta, pero una ruta como `/no-existe` mostraba Home con HTTP 200. Con enlaces permanentes simples y el front controller de Apache, WordPress no tiene reglas de rewrite para distinguir ese path de la raíz. Se corrigió mediante el hook público [parse_request](https://developer.wordpress.org/reference/classes/wp/parse_request/): únicamente en modo de permalinks simples, los paths desconocidos activan el error 404 nativo. Se conservan raíz/index.php, reglas reconocidas y handlers API. No se cambiaron permalinks ni datos comerciales.

La pantalla mantiene el diseño H4 y muestra **Volver al inicio** antes del buscador, junto a Ver catálogo. Para revisarla: <http://localhost:8080/404> o cualquier ruta inexistente. No se crea una página CMS con HTTP 200 ni un redirect automático.

`tests/verify-h4-404.cjs`: **23 comprobaciones aprobadas**, cinco rutas 404 sin redirect, doce rutas nativas/API con HTTP 200, seis combinaciones mobile/desktop claro/oscuro con botón de Home probado mediante navegación real. Capturas finales inspeccionadas; evidencia privada `404-routing.json` y `404-path-*.png`.
