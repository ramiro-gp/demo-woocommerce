# H5 — Catálogo y producto TRAMA

Estado: **implementado y validado; pendiente de revisión visual del usuario**. H6 no comenzó. Ruta única: `C:\Codigo\demo-woocommerce`. Rama: `codex/h5-catalog-product`.

## Consolidación H4

Se actualizó origin y se verificó que `ae5116290df2c3c731c206cb93edb47e3e425d0e` era ancestro de `origin/codex/h4-institutional-home`. Main no divergía de origin/main. Se consolidó por fast-forward toda la rama H4, incluida su corrección 404, hasta `42250c5363f73d632b3c6def14e63f41e689d123`. Push de main confirmado; main y origin/main quedaron iguales antes de crear H5. No hubo force push, rebase, descarte de commits ni resolución destructiva.

## Catálogo real

16 productos publicados: **10 variables y 6 simples; 72 variaciones**. Categorías pobladas: Remeras 4, Buzos 3, Pantalones 3 y Accesorios 6. Los atributos globales son `pa_talle` (S, M, L, XL) y `pa_color` (Natural, Negro, Gris, Verde, Bordó, Crudo). Ocho prendas ofrecen dos colores; Buzo Textura y Pantalón Línea, uno. Cada producto tiene dos imágenes. Los atributos de accesorios son informativos, sin variaciones.

| SKU | Producto | Tipo | Categoría | Colores | Precio demo |
| --- | --- | --- | --- | --- | --- |
| TRM-REM-001 | Remera Base | Variable | Remeras | Natural, Negro | $ 32.000 |
| TRM-REM-002 | Remera Trazo | Variable | Remeras | Negro, Gris | $ 34.000 → $ 29.000 |
| TRM-REM-003 | Remera Aire | Variable | Remeras | Verde, Natural | $ 30.000 |
| TRM-REM-004 | Remera Horizonte | Variable | Remeras | Gris, Negro | $ 39.000 |
| TRM-BUZ-001 | Buzo Pulso | Variable | Buzos | Gris, Negro | $ 69.000 → $ 59.000 |
| TRM-BUZ-002 | Buzo Refugio | Variable | Buzos | Verde, Negro | $ 78.000 |
| TRM-BUZ-003 | Buzo Textura | Variable | Buzos | Bordó | $ 82.000 |
| TRM-PAN-001 | Pantalón Cauce | Variable | Pantalones | Negro, Verde | $ 74.000 |
| TRM-PAN-002 | Pantalón Línea | Variable | Pantalones | Natural | $ 79.000 |
| TRM-PAN-003 | Pantalón Pausa | Variable | Pantalones | Gris, Negro | $ 62.000 |
| TRM-ACC-001 | Bolsa Diario | Simple | Accesorios | Natural | $ 19.000 |
| TRM-ACC-002 | Gorra Sombra | Simple | Accesorios | Negro | $ 24.000 |
| TRM-ACC-003 | Gorro Punto | Simple | Accesorios | Verde | $ 21.000 |
| TRM-ACC-004 | Medias Canal | Simple | Accesorios | Crudo | $ 11.000 |
| TRM-ACC-005 | Pañuelo Pliegue | Simple | Accesorios | Bordó | $ 17.000 → $ 14.000 |
| TRM-ACC-006 | Bolso Tránsito | Simple | Accesorios | Negro | $ 36.000 |

Los precios son ficticios en ARS; expresan una escala coherente entre accesorios, remeras, buzos y pantalones, **sin representar precios reales de mercado**. La presentación usa $ y conserva los decimales/configuración comercial de H2. El footer aprobado contiene la aclaración ARS.

Ofertas: Remera Trazo, Buzo Pulso y Pañuelo Pliegue. Destacados nativos: Remera Base, Remera Aire, Buzo Pulso y Pantalón Cauce. La colección editorial **Ritmo diario** es un `product_tag` real de seis productos: Base, Aire, Pulso, Cauce, Bolsa Diario y Bolso Tránsito. Tiene archivo propio y enlaces desde las fichas.

Pantalón Línea está totalmente agotado (sus cuatro talles). También están agotadas Base/Natural/XL, Pulso/Negro/M y Cauce/Verde/S. Cada variación tiene precio, imagen de su color, SKU y stock propios, sin backorders. El stock disponible inicial varía entre 3 y 11 unidades; los accesorios, entre 22 y 27. La interfaz comunica **En stock** o **Agotado**, sin urgencia artificial.

SKU: `TRM-{REM|BUZ|PAN|ACC}-{001…}`. Variaciones: `TRM-REM-001-NAT-S`; colores NAT, NEG, GRI, VER, BOR, CRU. El SKU identifica establemente cada producto, sin depender de los IDs locales.

## WooCommerce y presentación

WooCommerce es la única fuente de datos en ejecución. Los JSON bajo `demo/catalog/` se usan exclusivamente por WP-CLI para preparar la demo; el theme no los lee. Las cards obtienen nombres, fotografías, precios, ofertas, enlaces y disponibilidad de objetos WC_Product.

Commerce Base 1.2.0 conserva el sistema editorial H3/H4, Manrope/Newsreader, paleta, modos y tokens de motion. `inc/catalog.php` añade presentación; `catalog.css` y `catalog.js` completan layout, foco y feedback. La card única se reutiliza en Home, Product Collection, categorías, búsqueda y relacionados: foto 4:5, nombre, precio y badge cuando corresponde; sin cajas ni sombras.

El catálogo no tiene hero. Cabecera corta, breadcrumb secundario, conteo y select nativo; filtros de 180–200 px a la izquierda desde 783 px. La grilla usa espacio disponible: una columna a 320, dos desde 360, y auto-fill con tamaños mínimos en desktop. En pantallas amplias puede llegar a seis columnas con fotos de tamaño legible. El catálogo admite hasta 140rem; la ficha y Home mantienen el ancho aprobado de H4.

Los filtros son **Product Filters de WooCommerce**, dentro de un patrón nativo: categoría, precio, talle, color y disponibilidad. Los IDs de atributos se resuelven por el slug global; no están fijados al entorno. El orden de talles usa el orden de términos WC. La consulta, las facetas, los parámetros, los conteos y las selecciones pertenecen a WooCommerce.

En móvil el overlay oficial permite seleccionar, aplicar, limpiar y cerrar. WooCommerce actualiza los resultados mientras se selecciona; Aplicar vuelve a la grilla. Se añade contador de activos, aria-expanded/controls/modal, foco de apertura y retorno, aislamiento del overlay cerrado y ciclo Tab/Shift+Tab. Escape, cierre y scroll lock siguen el mecanismo nativo. Las cards declaran compatibilidad con la navegación de bloques, por lo que filtrar no recarga toda la página.

El select conserva orden de colección, recientes, precio ascendente y descendente; se usan los algoritmos nativos. Las cuatro categorías y la colección comparten estructura. Los vacíos muestran explicación, limpiar filtros, catálogo y búsqueda.

## Ficha y galería

Desktop: imagen dominante a la izquierda y compra a la derecha. Móvil: imagen, nombre/precio, variantes y compra. No se usa sticky. La galería oficial de WooCommerce conserva sus miniaturas, selección manual, swipe y PhotoSwipe; no se añadió una librería externa ni autoplay. Las miniaturas reciben nombre, rol, estado y activación Enter/Space. WooCommerce controla el cambio de fotografía al seleccionar una variación.

Color y talle usan el adaptador H3 sobre los selects oficiales y su motor de variaciones. Los botones tienen radiogroup/radio, selección, foco, flechas/Home/End y estados agotados/no disponibles. La selección también usa peso tipográfico y grosor de borde, sin depender sólo del color. El agotado bloquea compra en la UI y en Store API. Fondo y texto seleccionados cambian juntos de inmediato para mantener contraste; el borde usa el motion breve existente.

La guía de talles es un `details` accesible entre variantes y compra. Los datos están en `demo/catalog/size-guides.json` y se importan a la opción `commerce_base_size_guides`; cada producto referencia el grupo en `_cb_size_guide`. Se pueden reemplazar mediante opciones de WordPress o el filtro `commerce_base_size_guides`, sin editar templates. Las medidas se identifican como demo y reemplazables.

Cantidad y add-to-cart usan el formulario oficial, validación de stock y avisos WooCommerce. La presentación indica Agregando durante el envío nativo. Se conservó ese mecanismo estable, incluido su reload en la ficha; no se creó una API de compra paralela. Descripción, composición/cuidados y envíos/cambios se presentan en tres desplegables. Estos últimos contienen copy demo prudente, sin políticas legales definitivas.

Relacionados usa la colección nativa `woocommerce/product-collection/related`, por categorías/tags reales. Reutiliza card y cuatro columnas desktop/dos mobile (una a 320), con width nativo neutralizado. Home conserva páginas, composición y selección de cuatro recientes; únicamente reutiliza la card final. Sus categorías reales dejan de mostrar Colección en preparación al tener productos. No se cambiaron textos ni layout de H4.

## Fixtures y reproducción

Recursos fuente versionados: `demo/catalog/h5-catalog.json`, `size-guides.json`, `images/*.webp`, `image-prompts.json` e `image-manifest.json`. No se versionan uploads, tamaños regenerables, respaldos, cachés, credenciales o capturas.

Sobre la instancia local H0–H4 existente en 8080, con WooCommerce y ARS ya configurados:

```powershell
.\scripts\h5.ps1 backup -BackupId h5-before-catalog
.\scripts\h5.ps1 sync
.\scripts\h5.ps1 inspect
.\scripts\h5.ps1 seed -BackupId h5-before-catalog
.\scripts\h5.ps1 verify
```

No reemplazar un backup existente; elegir otro ID para un respaldo nuevo. El seed exige manifiesto del entorno correcto y SHA-256 válido de base/WordPress. Importa medios mediante APIs WordPress y productos/variaciones mediante CRUD WooCommerce. El volumen de fixtures se monta read-only sólo en el contenedor WP-CLI.

La opción privada `trama_h5_catalog` registra IDs, fingerprints de productos/variaciones/stock, medios, términos, orden de talles, guías y fuente. Repetir conserva los 16 productos y 32 medios, sin duplicarlos. Un SKU ajeno, cambio manual, stock distinto por venta, imagen alterada o cambio de fuente bloquea la importación antes de escribir. La recuperación de una importación parcial exige revisar el conflicto; no se sobreescribe silenciosamente. No borrar manifiestos ni actualizar hashes para ocultar diferencias: preparar una migración deliberada.

El índice oficial de atributos estaba vacío y desactivado. H5 lo regeneró mediante `wp wc palt regenerate --force --batch-size=50` y habilitó `woocommerce_attribute_lookup_enabled` **sólo en local**, para sostener las facetas combinadas. El seed realiza esta preparación una vez y después preserva la decisión registrada. Los ejemplos técnicos H2 quedan intactos, con visibilidad comercial hidden; siguen accesibles por SKU/URL. No se borraron sus pedidos, stock ni configuración.

## Validación y evidencia

Evidencia privada en `.local/local/h5/`:

- `browser-qa.json`: **304 comprobaciones aprobadas**, 288 combinaciones de 12 vistas × 12 anchos × 2 modos, más 16 flujos. Anchos: 320, 375, 390, 430, 768, 1024, 1366, 1440, 1920, 2560, 3440 y 3840. Sin overflow horizontal ni errores JS/assets propios. Capturas completas con imágenes lazy cargadas.
- Flujos: filtros combinados y limpieza, precio, stock, cuatro ordenamientos, vacío/recuperación, panel móvil en ambos modos, ciclo de foco/Escape, variaciones y fotografía, guía por teclado, miniaturas/lightbox/Escape, cantidad/carrito/checkout invitado, rechazo de variación agotada, reduced motion, selects sin JS y Home/categorías.
- `fixture-guard.json`: edición manual simulada detectada; seed bloqueado, contenido preservado y campo demo restaurado en finally. Segunda ejecución e integridad aprobadas.
- `order-smoke.json`: pedido invitado sintético con dos líneas, total $51.000 y HPOS; descuento de stock una sola vez, reposición exacta y eliminación del pedido sintético. Sin gateway, pedido real ni pago enviado.
- Regresión `verify-h2-local.ps1`: carrito Store API, aislamiento de invitados, límites de stock, envío nativo, checkout invitado sin pedido persistido y HPOS.
- `mercadopago-smoke.json`: Mercado Pago disponible y script oficial visible en checkout H2 de 18081, sin enviar pedido/pago. Huella H2 conservada: `de3c4462a4ff9ef130416dd9d15e2385e34f6dc6270cf60e02e5aa2f81d627d5`.
- **44 mediciones de contraste aprobadas**, incluidas superficies sólidas, estados comerciales y bordes de checkbox en ambos modos: `contrast-qa.json`. La revisión apunta a WCAG 2.2 AA; no constituye certificación exhaustiva con tecnologías de asistencia.

La demo 8080 sigue sin gateways habilitados, igual que H3/H4. La instancia H2 de 18081 conserva Mercado Pago. No se trasladaron credenciales ni se repitieron pagos H2.

Repetir QA con Node/Playwright del runtime incluido:

```powershell
$env:NODE_PATH = 'C:\Users\ramiro\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
node .\tests\verify-h5-browser.cjs
node .\tests\verify-h5-accessibility.cjs
node .\tests\verify-h5-mercadopago.cjs
.\tests\verify-h2-local.ps1 -Instance local
.\scripts\dev.ps1 wp -Instance local -WpArgs @('eval-file','/opt/local-tools/h5-smoke.php','--quiet')
.\scripts\h5.ps1 verify
```

El test Mercado Pago requiere su instancia H2 conservada y el mapa redacted `mp-routes.json` (URLs, ID de producto técnico e IDs de gateways; sin credenciales). Las capturas de checkout contienen sólo sesiones demo creadas por QA.

Para recrear ese mapa desde las APIs WooCommerce de H2:

```powershell
.\scripts\dev.ps1 wp -Instance h2-public -WpArgs @('eval', 'echo wp_json_encode(["cart"=>wc_get_cart_url(),"checkout"=>wc_get_checkout_url(),"simple"=>wc_get_product_id_by_sku("H2-SIMPLE"),"gateways"=>array_keys(WC()->payment_gateways()->get_available_payment_gateways())]);', '--quiet') | Set-Content .local/local/h5/mp-routes.json -Encoding utf8
```

Problemas resueltos: JSON/alcance del seed bajo WP-CLI, markup vacío serializado como array, límite nativo de galería a 512px, especificidad heredada de relacionados, orden de talles, índice nativo de atributos deshabilitado, declaración de compatibilidad de navegación y enlace de limpiar filtros que omitía el puerto local. La URL de archivo nativa evita el redirect de shop page_id que pierde parámetros en permalinks simples. No se editaron archivos del plugin. Advertencias iniciales del bloque de chips se resolvieron con su markup requerido; se conservaron los logs para trazabilidad.

## URLs para revisión

- [Home](http://localhost:8080/)
- [Tienda](http://localhost:8080/?post_type=product)
- [Remeras](http://localhost:8080/?product_cat=remeras), [Buzos](http://localhost:8080/?product_cat=buzos), [Pantalones](http://localhost:8080/?product_cat=pantalones), [Accesorios](http://localhost:8080/?product_cat=accesorios)
- [Ritmo diario](http://localhost:8080/?product_tag=ritmo-diario)
- [Variable con combinación agotada](http://localhost:8080/?product=remera-base)
- [Prenda en oferta](http://localhost:8080/?product=buzo-pulso)
- [Simple con galería de detalle](http://localhost:8080/?product=bolsa-diario)
- [Agotado](http://localhost:8080/?product=pantalon-linea)
- [Sin coincidencias](http://localhost:8080/?post_type=product&min_price=999999)
- [Búsqueda vacía](http://localhost:8080/?s=sin-producto-h5-9428&post_type=product)
- [Carrito](http://localhost:8080/?page_id=8), [checkout](http://localhost:8080/?page_id=9)

Fuentes técnicas, contrastadas con WooCommerce instalado 11.2.1: [Theming for Woo blocks](https://developer.woocommerce.com/docs/theming/block-theme-development/theming-woo-blocks), [Product Collection](https://developer.woocommerce.com/docs/block-development/extensible-blocks/product-collection-block/register-product-collection). Frontend-design se reutilizó desde la copia privada fijada en H3 al [commit cf865e94 de Vercel](https://github.com/vercel-labs/open-agents/blob/cf865e94de7729751c747171785b6ce57e7b178c/.agents/skills/frontend-design/SKILL.md).

H5 no incorpora logística externa, nuevas pasarelas, transferencia, legales finales, Commerce Core, ARCA, SEO final, SMTP real, caché de producción, analytics, Pixel ni deploy. El siguiente hito espera revisión visual y autorización del usuario.
