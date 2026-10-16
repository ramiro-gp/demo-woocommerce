# Correcciones post-H5

H5 recibió aprobación visual general. Esta pasada sigue en `codex/h5-catalog-product`, desde `dce5566197a6dc51b7ffd2a4b2b52957564afd2c`, y espera una nueva revisión visual antes del merge. Main permanece en `42250c5363f73d632b3c6def14e63f41e689d123`. H6 no comenzó.

## Cambios y causas

1. **Galería:** el trigger nativo usa z-index 99 y escapaba al header 40. La galería ahora forma un contexto aislado de capa 0; sus controles permanecen dentro. Tokens de capa: header 40, overlays fullscreen 100, skip link 200. PhotoSwipe y el drawer nativo usan overlay 100; el menú conserva la top layer de `dialog`. No se aumenta arbitrariamente el header ni se transforma su contenedor.
2. **Guía:** se retiró el `span` con ↗. El único indicador es el triángulo nativo de `summary`, que gira al abrir y conserva teclado/semántica de `details`. No había un segundo chevron custom que debiera recrearse.
3. **Orden:** select y option heredan `color-scheme` y los tokens de texto/fondo. Se conserva el select nativo. El popup pertenece parcialmente al navegador/Windows: el motor puede ignorar colores de option o usar selección del sistema. Se verifica lectura/contraste en los motores disponibles; no se afirma igualdad visual entre plataformas ni cobertura de Safari en macOS/iOS.
4. **Precio:** el selector general de campos alcanzaba `input[type=range]` y le imponía un mínimo de 48px aunque Woo declara altura 0. Se excluyó range del campo general. El filtro oficial tiene un área de 44px, track de 4px centrado y círculo visual de 16px dentro del thumb de 44px. Los offsets derivan de esas dimensiones; WebKit/Blink y Gecko tienen sus pseudoelementos nativos. No hay un top −16px ni un slider nuevo.
5. **Stock:** `product-filter-status` de Woo 11.2.1 admite showCounts/displayStyle/isPreview/hideEmpty; no admite selección inicial. Su selección real proviene de filterParams. Se eligió **`woocommerce_hide_out_of_stock_items=yes`**, configuración de inventario nativa autorizada. El catálogo muestra 15 productos; los 16 productos y 72 variaciones siguen intactos. El checkbox “Hay stock” no se marca ficticiamente; limpiar filtros conserva la política nativa de inventario. La ficha de Pantalón Línea sigue accesible. Revertir la política corresponde al ajuste de inventario, no a desmarcar ese filtro.
6. **Swatches:** sólo variables con atributo global pa_color. Se recorren `get_available_variations('objects')`, `is_purchasable()` e `is_in_stock()`, se deduplican los slugs reales de `get_variation_attributes()` y se resuelven nombres de términos. Una variación “cualquier color” representa las opciones reales del padre. Cero/un color no produce swatches. El mapping reutilizable pasa por `commerce_base_color_swatches`; Natural/Crudo tienen borde. La lista incluye texto accesible “Disponible en …”; no son selectores ni cambian fotografías.
7. **Compra rápida:** cada card reutiliza el bloque oficial Product Button con contexto postId. Simples comprables agregan dinámicamente; variables usan el enlace nativo “Seleccionar opciones”; agotados/no comprables muestran “No disponible”. Las solicitudes, éxito y errores son de Woo. El indicador de loading/aria-busy y el bloqueo temporal leen `getProductsPendingAdd()` del store público; el foco permanece en el botón mientras se espera. No se implementa otro add-to-cart. El bloque editable de productos de Home mantiene los cuatro recientes y su layout; internamente renderiza Product Collection para obtener también su contexto y región oficial de errores accesibles, sin copiar APIs privadas.
8. **Header:** Mini-Cart oficial, icono bag, sin precio y `productCountVisibility=greater_than_zero`. La cantidad nativa cuenta unidades. En Cart/Checkout Woo oculta expresamente el Mini-Cart; allí un componente de presentación suscrito con useSelect lee `getCartData().itemsCount` del store público y enlaza al carrito. No hay polling, suma manual, localStorage de cantidades ni peticiones propias. Cero no renderiza badge visible; el nombre accesible conserva el estado.
9. **Cuenta:** únicamente los h2 de `.woocommerce-Addresses .woocommerce-Address-title` usan Manrope y escala 18–24px. El fieldset de contraseña y sus acciones quedan separados por `--cb-space-6` (2rem). No se cambia el h2 global.
10. **Checkout i18n:** la llamada `_n` procede de `wc-cart-checkout-base-frontend.js`, handle `wc-cart-checkout-base`, dominio woocommerce. El pack es_AR instalado (también comprobado mediante actualización oficial) contiene el resto del catálogo pero carece de ese plural. El hook público `load_script_translations` incorpora una entrada Jed suplementaria para es_AR, con singular/plural. Conserva todas las traducciones oficiales y deja de suplir la entrada cuando el pack la incluya. No se cambia el plugin ni el DOM. Se usa el msgid original como corresponde a i18n, no como búsqueda de texto renderizado.

## Reproducir configuración y QA

Backup privado íntegro: `.local/local/backups/h5-before-corrections`, base de datos y WordPress con manifiesto SHA-256. No se versiona.

```powershell
.\scripts\h5.ps1 sync
.\scripts\h5.ps1 configure -BackupId h5-before-corrections
.\scripts\dev.ps1 wp -Instance local -WpArgs @('eval-file','/opt/local-tools/h5-corrections-verify.php','--quiet')
$env:NODE_PATH = 'C:\Users\ramiro\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
.\tests\verify-h5-corrections.ps1
node .\tests\verify-h5-browser.cjs
node .\tests\verify-h5-accessibility.cjs
node .\tests\verify-h5-mercadopago.cjs
.\tests\verify-h2-local.ps1 -Instance local
.\scripts\h5.ps1 verify
```

`configure` exige backup válido y verifica fingerprints antes de modificar exclusivamente el ajuste nativo de stock. No reseedea ni restaura catálogo/stock. El verificador adicional prueba exclusión de colores agotados/no comprables mediante filtros temporales en memoria, retirados en finally: no escribe productos.

Evidencia privada en `.local/local/h5/`: `corrections-qa.json`, capturas `corrections-*`, regresión H5 y contraste. Las sesiones de prueba son independientes y sus carritos se vacían. La prueba de validación usa un comprador ficticio local, sin gateway ni datos de pago. Woo crea un checkout-draft antes de rechazar la falta de pago: el wrapper PowerShell limpia en finally únicamente borradores store-api de ese email exclusivo, nombres ficticios, sin método/transacción/reducción de stock; preserva cualquier estado inesperado. No toca otros pedidos.

## Validación final

- **85 casos post-H5 aprobados:** 72 combinaciones de galería/guía/controles en Chromium, Firefox y WebKit, light/dark y 320/390/1024/1366/1920/3840px; mouse en ambos thumbs, touch real emulado a DPR 2, zoom CSS al 200%, compra/cantidades/eliminación/vaciado, errores reales de stock en catálogo y Home, validación española y Mi Cuenta. `corrections-final-qa.json` conserva referencia a los 84 aprobados de la pasada completa y la revalidación dirigida de Mi Cuenta; los reportes originales se conservan. Cero errores JS pendientes.
- Mi Cuenta se verifica iniciando sesión por su formulario nativo y esperando sus controles y estilos; las capturas no dependen de recursos externos que pueden retrasar DOMContentLoaded. El QA espera las solicitudes reales de sincronización nativa antes de forzar una navegación: el badge optimista por sí solo no confirma que terminó la escritura.
- **8 verificaciones PHP adicionales**, fingerprints de 16 productos/72 variaciones intactos, HPOS activo y cero borradores de la prueba después de cleanup. Las capas calculadas se verifican explícitamente como 0/40/100.
- **304 comprobaciones de regresión H5 y 42 mediciones de contraste aprobadas**, además de 10 comprobaciones HTTP H2, fingerprints H4 y smoke de Mercado Pago en 18081. Su huella comercial sigue siendo `de3c4462a4ff9ef130416dd9d15e2385e34f6dc6270cf60e02e5aa2f81d627d5`. No se repitieron pagos.
- Revisión visual de catálogo, Home, ficha/lightbox, popup nativo light/dark en Chromium/Windows, header con cantidades, direcciones, edición de cuenta y checkout. Incluye 320px, 1920×1080 y ultrawide. Firefox/WebKit verifican controles nativos y sus colores; WebKit en Windows no acredita Safari real en macOS/iOS. No hay claim de popup pixel-perfect ni de certificación WCAG exhaustiva.
- Diff y secretos revisados. No se agregaron fotografías ni se modificaron las 32 WebP existentes. Capturas, reportes, backups y credenciales permanecen ignorados. No hay advertencias PHP nuevas del sitio; los logs anteriores se conservaron.

## Pendientes registrados, sin implementación

- **H6/H8, direcciones:** Checkout ya ofrece nativamente “Usar la misma dirección para la facturación”, activada inicialmente. No está mal configurada ni necesita duplicarse. Evaluar en H6 el flujo/etiqueta conceptual inversa solicitada. Mi Cuenta maneja edición separada de facturación/envío; una copia persistente futura debe evaluarse en Commerce Core/H8, con decisión explícita sobre sincronización. No se implementó copia ni sincronización bidireccional.
- **H6, volver:** link secundario “← Volver al carrito” cerca/encima del resumen. Pendiente del diseño final de checkout.
- **H6, recomendaciones:** “¿Agregar algo más?” debajo del carrito mediante Cross-Sells oficial de Product Collection del Cart Block, evaluando su layout Carousel y configurando relaciones reales en los productos demo. No se agregó sección, relaciones ni Swiper/Slick.
- **H6, logística:** investigar opciones mantenidas de Correo Argentino, Andreani, OCA y alternativas según el comercio. No se integró ninguna API.
- **H6/H7, pagos:** evaluar BACS nativo y otro gateway sólo si un comercio lo requiere. Checkout Pro ya ofrece tarjetas; no se duplicaron capacidades, habilitó transferencia ni cambió Mercado Pago.

## Revisión manual

- [Tienda](http://localhost:8080/?post_type=product), [Pantalones](http://localhost:8080/?product_cat=pantalones), [Home](http://localhost:8080/).
- [Variable/guía/galería](http://localhost:8080/?product=remera-base), [simple](http://localhost:8080/?product=bolsa-diario), [agotado directo](http://localhost:8080/?product=pantalon-linea).
- [Carrito](http://localhost:8080/?page_id=8), [checkout](http://localhost:8080/?page_id=9).
- [Direcciones](http://localhost:8080/?page_id=10&edit-address), [editar cuenta](http://localhost:8080/?page_id=10&edit-account), con sesión iniciada.
- [Checkout de la instancia preservada con Mercado Pago](http://localhost:18081/?page_id=7), después de agregar un producto técnico de esa instancia.

Referencias contrastadas con el código instalado: [bloques WooCommerce](https://developer.woocommerce.com/docs/block-development/reference/block-references/), [Mini-Cart y su restricción en Cart/Checkout](https://woocommerce.com/document/woocommerce-store-editing/customizing-cart-and-checkout/mini-cart/), [store público del carrito](https://developer.woocommerce.com/docs/block-development/reference/data-store/cart/), [hook WordPress de traducciones JS](https://developer.wordpress.org/reference/hooks/load_script_translations/).
