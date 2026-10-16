# H5.1 — Shoppable Lookbook

Implementación local del 16/10/2026, preparada para revisión visual. H6 no comenzó. Sin merge a main ni deploy. Base aprobada: H5 `f398f3726d07e38c1781d5b60fc042719e6a8687`; rama de trabajo `codex/h5-1-shoppable-lookbook`.

## Arquitectura y distribución

**Commerce Lookbook**, plugin propio pequeño en `commerce-lookbook/`, registra el bloque Gutenberg **Shoppable Lookbook**, nombre técnico `commerce-lookbook/shoppable-lookbook`. No se instaló ningún plugin externo ni biblioteca adicional. Usa APIs y componentes incluidos en WordPress y objetos de WooCommerce.

La estructura, los atributos, el editor y el render pertenecen al plugin, porque deben seguir disponibles al cambiar el theme. Commerce Base **1.3.0** sólo incorpora el mapeo visual de tokens en `assets/css/lookbook.css`. El plugin aporta una presentación básica autónoma y legible cuando ese theme no está activo. No se creó Commerce Core ni un framework: esta carpeta puede integrarse después en Commerce Core conservando el nombre y esquema del bloque.

La imagen se importa a la Biblioteca multimedia: la instancia de Home no depende de la URL del theme. Cambiar theme conserva contenido y funcionalidad mientras Commerce Lookbook y WooCommerce continúen activos. Si se desactiva Commerce Lookbook, Gutenberg conserva los atributos en el comentario del bloque y el HTML guardado muestra la imagen; los hotspots necesitan que el plugin esté activo. Desinstalar el plugin no borra contenido ni media.

Registro mediante `block.json` API v3, editor JavaScript sin compilación y callback PHP dinámico. Referencias: [metadata de bloques de WordPress](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/) y [API WC_Product](https://woocommerce.github.io/code-reference/classes/WC-Product.html).

## Datos y fuente de verdad

El bloque guarda la referencia de media (`imageId`), URL de imagen para fallback, texto alternativo, dimensiones originales y un array:

```json
{
  "hotspots": [
    { "productId": 123, "x": 62.5, "y": 38 }
  ]
}
```

El ID ilustrativo se elige desde la búsqueda; no hay IDs permanentes requeridos. **Cada hotspot sólo guarda `productId`, `x`, `y`.** No guarda nombre, precio, moneda, stock ni permalink. La marca y los SKUs TRAMA están únicamente en las herramientas de demo y su contenido.

El render PHP resuelve `wc_get_product()`, `get_name()`, `get_price_html()`, `get_permalink()` e `is_in_stock()`. El HTML monetario de WooCommerce conserva moneda, decimales, impuestos configurados, rango variable y oferta. El nombre accesible anuncia el precio actual una vez; la píldora puede mostrar el precio tachado y el promocional nativos. No hay composición manual de moneda ni schema Product adicional.

Las consultas de posts, metadatos y términos se preparan en lote para los IDs únicos. Hay un límite defensivo de 12 hotspots por bloque y la recomendación editorial es 2–4. WooCommerce mantiene sus propias cachés de producto y variaciones. El frontend no hace fetch por hover, polling ni requests para abrir puntos. El JS de interacción pesa aproximadamente 3.8 KB sin comprimir; CSS base 3.8 KB. El editor se carga sólo en Gutenberg y los assets de interacción se registran como assets del bloque.

Un precio o slug nuevo se refleja en el siguiente render sin editar el lookbook. En una futura tienda con caché de página/CDN será necesario invalidarla con la política normal de WooCommerce; una página ya abierta no se actualiza en vivo. No se agregó una caché de producto propia.

## Crear y editar

1. Abrir una página en Gutenberg e insertar **Shoppable Lookbook** desde el inserter, categoría Media.
2. Usar **Elegir imagen**. Se abre la biblioteca nativa, con selección o carga de imagen. En la barra lateral del bloque se puede **Cambiar imagen** y editar **Texto alternativo**.
3. Pulsar **Agregar hotspot** y buscar el producto en **Producto WooCommerce**. Elegir el resultado por nombre; se guarda su ID, sin pedir un número manual.
4. Ajustar **Posición X (%)** y **Posición Y (%)**, mediante slider o campo numérico. La preview numerada refleja las posiciones. Seleccionar otro punto mediante su número o botón de producto.
5. Usar **Subir punto / Bajar punto** para cambiar el orden de Tab o **Eliminar hotspot** para quitarlo. Guardar y revisar la página, especialmente en mobile.

La versión inicial usa X/Y con preview, sin drag-and-drop ni editor de gráficos adicional. El editor muestra nombre/precio obtenidos mediante una ruta REST autenticada propia; requiere permiso para editar productos y entradas o páginas. La búsqueda devuelve hasta 20 productos publicados por vez. No expone claves de WooCommerce, usa el nonce nativo y sólo permite lectura. Los resultados excluyen productos ocultos y protegidos; no se ofrecen variaciones individuales.

## Estados e interacción

| Caso | Resultado |
| --- | --- |
| Mouse | Hover abre una píldora compacta. Salir la cierra, salvo que el enlace mantenga foco. Click navega a la ficha. |
| Touch / lápiz | Primer tap abre; segundo tap al punto o al contenido navega. Otro punto cierra el anterior. Tap fuera cierra. |
| Teclado | Tab recorre enlaces en el orden editable. Focus abre y muestra outline. Enter navega. Escape cierra y conserva foco. Space mantiene la semántica nativa de enlace. |
| Variable | Navega al padre. No selecciona talle, color ni otra variación. Una referencia manual a una variación también se normaliza al padre. |
| Agotado | Conserva enlace y precio, e indica **Agotado** visualmente y en el nombre accesible, aunque el catálogo general oculte agotados. |
| Borrado / papelera / sin publicar / oculto / protegido | Se omite el punto sin romper la imagen ni los demás. Al abrir el editor se advierte que la referencia no está disponible; puede reemplazarse o eliminarse. |
| JavaScript deshabilitado | Imagen, enlaces reales, hover/focus CSS y lista legible de productos en `noscript`. |
| Archivo JS bloqueado o fallido | Los enlaces nativos siguen navegando y focus/hover CSS muestran información. |

Sólo hay un punto activo por imagen. Una foto conserva su propio estado; al tocar fuera también se cierra el de otra imagen. Se usan enlaces semánticos, nombre accesible **Ver producto, precio, estado**, `aria-controls` e `aria-expanded` cuando JS controla la expansión. Los IDs de contenido son únicos por render. El `tabindex="0"` explícito mantiene los enlaces incluidos en la navegación nativa de WebKit. El círculo visual mide 18px y el área de interacción 44×44px.

## Responsive, capas y accesibilidad visual

Se usa la fotografía completa y su proporción original, con dimensiones y `aspect-ratio` explícitos: no hay crop distinto por breakpoint, zoom al hover ni filtros de color. Esto evita que el tamaño intrínseco automático de imágenes lazy de WordPress desplace los puntos. La foto usa `srcset`, `sizes`, lazy loading y dimensiones para reservar espacio.

X/Y representan porcentajes de esa composición completa. Cerca de los extremos, el centro del control se limita a 22px del borde para conservar el área touch. La píldora prefiere el costado con espacio y se limita a la intersección visible de imagen y viewport; también ajusta su altura. Un `ResizeObserver` y eventos pasivos de resize/scroll recalculan únicamente el punto abierto mediante `requestAnimationFrame`. Sin Popper ni Floating UI.

La fotografía y los puntos se mantienen en una capa local aislada, `z-index: 0`; sólo el punto activo sube dentro de ella. El header conserva su capa global 40. No hay lightbox ni modal de compra. No se cambia el scroll del visitante.

Commerce Base mapea fondos, texto, borde, accent y foco a los tokens claros/oscuros H3. El círculo tiene borde y halo para contrastar sobre zonas claras u oscuras. Las píldoras usan fondo sólido; el texto supera 4.5:1 en la verificación de ambas paletas. `prefers-reduced-motion` elimina la transición propia; se respeta además la reducción global del theme. No hay pulsos ni animaciones permanentes.

Limitaciones V1: las fotos extremadamente bajas o una cantidad excesiva de puntos próximos pueden volver incómoda la composición; separar los puntos y usar 2–4. Los nombres largos pueden ocupar más de una línea. Cambiar la foto requiere revisar X/Y. No hay coordenadas distintas por crop/breakpoint, preselección de variantes ni compra dentro de la imagen. El fallback sin script tiene posicionamiento CSS más sencillo.

## Home de demostración y assets

Revisión: **[http://localhost:8080/](http://localhost:8080/)**, sección **Notas de ciudad / 01 · El detalle cambia el ritmo.**

Edición: **[Páginas → Inicio → Shoppable Lookbook](http://localhost:8080/wp-admin/post.php?post=37&action=edit)**. Es la URL concreta de esta demo; otra instalación asignará sus propios IDs. Expandir la sección Lookbook desde la Vista de lista también permite seleccionar el bloque.

Se sustituyó únicamente el bloque de imagen dentro del Lookbook H4. Se conservaron su copy, botón, orden y grilla. No se agregó una segunda sección.

| Producto existente | Motivo / posición demo |
| --- | --- |
| [Remera Base](http://localhost:8080/?product=remera-base) | Remera cruda lisa de la fotografía; X 62%, Y 19%. Padre variable, precio WooCommerce actual. |
| [Pantalón Pausa](http://localhost:8080/?product=pantalon-pausa) | Pantalón oscuro amplio; X 64%, Y 64%. Padre variable, precio WooCommerce actual. |

No se creó ningún producto ni se vinculó la sobrecamisa: no corresponde fingir que es uno de los productos del catálogo H5. La fotografía es editorial demo y no representa una selección de variantes exactas.

Se reutilizó **`commerce-base/assets/images/editorial-detail.webp`**, generado en H4 mediante `imagegen`, 1536×1024, 176374 bytes. No se generó una nueva imagen. Prompt, herramienta y procedencia siguen documentados en [h4-assets.md](h4-assets.md). SHA-256: `636d953cf416325dc81c5b66c4b308f36e77a0ea56332d8517c6e94d979ca872`.

La herramienta de demo importa el mismo archivo a uploads como `editorial-lookbook.webp` y WordPress genera sus tamaños; fuente y bytes originales se conservan en Git, mientras uploads y variantes runtime permanecen privados. El hash de procedencia permite reusar la misma media en lugar de duplicarla. El contenido guarda la imagen de biblioteca y su alt, independientemente del theme.

## Operación y reproducción local

```powershell
.\scripts\dev.ps1 backup -Instance local -BackupId h5-1-before-lookbook
.\scripts\h5-1.ps1 sync
.\scripts\h5-1.ps1 seed
.\scripts\h5-1.ps1 verify
```

`sync` copia el theme y el plugin propio al volumen de la instancia local existente y activa el plugin; no configura pagos, stock ni checkout. `seed` exige el backup íntegro, productos H5 existentes y la imagen H4 esperada. Reemplaza sólo ese bloque y conserva un manifiesto con hashes previo/posterior en la base; una segunda ejecución verifica el resultado y no pisa ediciones. `verify` e `inspect` son lecturas sin cambios. Después de editar Home manualmente, una diferencia en el manifiesto indica un cambio editorial que debe revisarse, no un permiso para volver a sembrar.

El manifiesto histórico H4 no se reescribe para ocultar esta migración: su verificador puede señalar el cambio intencional de Home. No ejecutar el seed H4 para deshacerlo. H5.1 tiene su propio control de integridad; el verificador del catálogo H5 sigue pasando.

Respaldo preventivo y reportes quedan en `.local/local/`, ignorados por Git. Las pruebas de editor usan y eliminan un borrador temporal. Las de datos hacen cambios temporales de precio, slug, publicación y visibilidad sobre un producto simple existente, restaurados mediante `finally`; no editan Home, stock ni configuraciones comerciales.

```powershell
# Usar el Node y NODE_PATH del runtime local documentado por Codex.
node tests/verify-h5-1-editor.cjs
node tests/verify-h5-1-browser.cjs

# QA de datos, guardar su JSON privado para el fixture de bordes del browser:
.\scripts\dev.ps1 wp -Instance local -WpArgs @('eval-file', '/opt/local-tools/h5-1-functional-qa.php', '--quiet')
```

Antes de ejecutar el browser QA, guardar la salida JSON del último comando en `.local/local/h5-1/functional-qa.json`. El fixture de cuatro esquinas usa productos reales: variable, simple, oferta y agotado. Se inserta sólo en el DOM de prueba, sin publicar contenido de QA.

## Validación

Reportes privados: `verify.json`, `functional-qa.json`, `editor-qa.json`, `browser-qa.json` y capturas PNG en `.local/local/h5-1/`. Las capturas se inspeccionaron realmente en desktop, mobile, claro y oscuro, incluyendo 320px y ultrawide.

- Integridad Home: nueve comprobaciones, incluido reemplazo exclusivo de la imagen y dos enlaces WooCommerce reales.
- Datos: 19 comprobaciones con CRUD real y restauración; precio, permalink, agotado, oferta, padre variable, referencia inexistente, draft, trash, hidden, límite y validación de coordenadas.
- Gutenberg: 11 comprobaciones, búsqueda autenticada, imagen, X/Y, agregar/eliminar/reordenar, guardado/reapertura válida, ausencia de información de producto duplicada, aviso de referencia faltante y limpieza del borrador.
- Navegador: 87 comprobaciones. Matriz Chromium en 320, 375, 390, 430, 768, 1024, 1366, 1920, 2560, 3440 y 3840; Firefox/WebKit en 320, 390, 1024 y 1920. Ambas paletas, posición proporcional, cuatro esquinas, contraste, teclado, mouse, reduced motion y header. Cero errores JS.
- Touch Chromium: primer/segundo tap, cambio de punto, cierre fuera y navegación en ambas paletas, a DPR 2.
- Degradación: JS apagado, script bloqueado y presentación del plugin sin CSS de Commerce Base.
- Smoke: Home, catálogo, producto simple/variable, agregar al carrito nativo, carrito, campos del checkout y vaciado del carrito. Sin crear pedidos ni pagos.
- Catálogo H5 preservado: fixtures sin conflictos; 16 productos comerciales y 72 variaciones. Lint PHP, sintaxis JS, revisión de diff, secretos y archivos generados.

Estas pruebas no equivalen a certificación WCAG ni a pruebas en teléfonos físicos. La actualización de cachés externas deberá verificarse cuando exista un deploy; este hito es exclusivamente local.
