# H3 — Commerce Base: dirección y sistema visual

Commerce Base es el Block Theme reutilizable. TRAMA es únicamente la identidad local de WordPress (`blogname`/`blogdescription`); no aparece en templates, PHP, JS ni tokens. H3 no crea la home, páginas institucionales, productos definitivos ni Commerce Core.

## Dirección elegida

Editorial textil y urbana: fondo crema, tinta verdosa, oliva, líneas finas, fotografía dominante y titulares Newsreader. Las superficies están abiertas, sin cards encerradas, sombras decorativas, pills ni gradientes. En oscuro se diseñaron superficies verdes profundas y un acento lima apagado, conservando jerarquía y contraste.

Se conservó la dirección de H0 y se refinó el acento para cada modo. El hero de muestra usa un titular en tres líneas y una fotografía recortada; su escala se ajusta también a la altura disponible en desktop. El objetivo aproximado es 65–72svh, con crecimiento permitido cuando el texto necesita espacio. Mobile apila el contenido sin altura fija. El showcase incluye textos de prueba; no es una propuesta de home terminada.

Se aplicó el skill obligatorio [frontend-design de vercel-labs/open-agents](https://github.com/vercel-labs/open-agents/blob/cf865e94de7729751c747171785b6ce57e7b178c/.agents/skills/frontend-design/SKILL.md), instalado y leído antes del trabajo visual. Copia privada: `.local/skills/frontend-design/SKILL.md`. Fuente fijada al commit `cf865e94de7729751c747171785b6ce57e7b178c`; no se versiona una copia de terceros.

## Estructura

```text
commerce-base/
├── style.css                 # identificación del theme
├── theme.json                # presets, tipografía, layout y estilos del editor
├── functions.php             # assets, bloques y adaptación de presentación
├── templates/                # index, page, single, search, 404, page-checkout
├── parts/                    # header y footer
├── patterns/                 # editorial-hero y product-grid
├── inc/
│   ├── components.php        # header, footer, íconos y productos reales
│   └── showcase.php          # vista de desarrollo protegida
└── assets/
    ├── css/system.css        # tokens y componentes, frontend/editor
    ├── js/                   # theme-init, interaction y editor
    ├── fonts/                # tres WOFF2 y dos licencias OFL
    └── images/               # fotografía de muestra y placeholder propio
```

Los bloques dinámicos `commerce-base/header`, `footer` y `product-grid` usan APIs públicas, renderizado servidor y preview mediante `ServerSideRender`/`useBlockProps`. El patrón hero usa bloques nativos editables. La navegación principal se obtiene de la ubicación `primary`; admite enlaces de primer nivel. Sin menú asignado, utiliza Catálogo, Mi cuenta y Carrito con URLs nativas. No se inventan páginas ni enlaces institucionales de H4.

No hay overrides PHP de WooCommerce. `page-checkout.html` es una composición de layout necesaria para que el checkout use la misma cabecera, apariencia y márgenes. Conserva `woocommerce/page-content-wrapper`, `store-notices` y el contenido original de la página. No copia ni sustituye los bloques internos del checkout. Un filtro público de render de grupos añade el destino del skip link y clases de layout a los `main` nativos.

## Tokens semánticos

CSS es la fuente de los valores por modo. `theme.json` los expone como presets semánticos de WordPress.

| Token `--cb-…` | Claro | Oscuro |
| --- | --- | --- |
| background | `#f3f0e8` | `#171f1b` |
| surface | `#e9e5db` | `#222c25` |
| surface-elevated | `#e0dfd3` | `#2d382f` |
| text | `#202a24` | `#f0eee4` |
| text-muted | `#586157` | `#b2bbae` |
| border | `#777e70` | `#788574` |
| accent | `#405b35` | `#c5d6a2` |
| accent-hover | `#30472a` | `#d9e6bd` |
| accent-contrast | `#faf9f2` | `#1c281e` |
| focus | `#285f7b` | `#93c3e0` |
| success | `#256449` | `#86cdb0` |
| warning | `#86531c` | `#e5bc72` |
| danger | `#a0372b` | `#f0a295` |

Contrastes calculados sobre fondo: texto 13.00/14.46, secundario 5.65/8.50 y acento/contraste 7.19/9.85. Los bordes superan 3:1 incluso sobre la superficie elevada. Los estados tienen texto y símbolos además de color.

Espacio: `space-1…8` = 4, 8, 12, 16, 24, 32, 48 y 64px expresados en rem. Sección: `clamp(4rem, 7vw, 8rem)`. Gutters: `clamp(1rem, 3.3vw, 5rem)`. Layout comercial máximo: `110rem` (1760px a tamaño de fuente raíz habitual). Lectura: `68ch`. `.cb-container`, `.cb-reading` y `.cb-full-bleed` separan las responsabilidades; WordPress conserva sus alineaciones nativas.

## Tipografía

Newsreader 400 normal e italic para editorial. Manrope variable 400–700 para interfaz, productos, precios y controles. Tres archivos WOFF2 Latin, aproximadamente 70KiB en total, incluyen ñ y acentos castellanos. Se sirven desde el theme, con `font-display: swap`; no hay requests de Google Fonts en el sitio. Body fluido 16–18px, introducciones 18–24px, titulares con `clamp()`. Labels menores se reservan a metadatos.

Archivos obtenidos de Google Fonts oficiales, con licencias SIL OFL adjuntas. [Manrope](https://github.com/google/fonts/tree/main/ofl/manrope), [Newsreader](https://github.com/google/fonts/tree/main/ofl/newsreader). Los URLs exactos de los WOFF2 están registrados en `commerce-base/assets/fonts/SOURCES.md`.

## Apariencia, cabecera y menú

La preferencia `light`/`dark`/`system` vive en `localStorage`, clave `commerce-base-color-mode`. Un script inline pequeño, antes de los estilos, aplica `data-theme` y `color-scheme`. Sistema sigue `prefers-color-scheme` y sus cambios; las elecciones manuales persisten y se sincronizan entre pestañas. Si el almacenamiento está bloqueado, la selección funciona durante la sesión. Sin JS, CSS sigue la preferencia del sistema.

La cabecera es fija, sin saltos de layout. En presencia del hero parte transparente y el fondo/borde progresa con `scrollY / 140`, actualizado en un único frame mediante listener pasivo. Las demás páginas parten sólidas. `ResizeObserver` mantiene la altura usada por los anchors. El buscador abre el menú con foco en búsqueda; en mobile se mantiene dentro del menú.

El menú es un `dialog` nativo fullscreen. Entra desde arriba con `transform`, y vuelve por el mismo camino al cerrar. Permanece modal durante el cierre; se desmonta al finalizar la transición, con fallback acotado. Tiene foco inicial, Tab/Shift+Tab, Escape, cierre accesible, bloqueo y restauración de scroll/foco. Sin JS queda una navegación alternativa visible. El drawer de filtros entra desde la derecha y comparte el controlador accesible, con estados independientes.

## Movimiento y controles

Duraciones 140ms (respuesta), 240ms (microtransición), 360ms (paneles); easing `cubic-bezier(0.22, 0.61, 0.36, 1)`, desplazamiento habitual 2px. CSS controla fill, borde, foco, press, íconos, imagen, links, badges y feedback. JS se limita al estado del theme, paneles, anchors y muestras de controles. No hay librería de animación ni dependencia de producción agregada.

Reduced motion reduce transiciones, detiene el spinner y elimina scroll animado. Los anchors propios usan scroll nativo suave, actualizan hash y focalizan destino sin scroll duplicado; respetan la altura de cabecera. No se intercepta la rueda, el trackpad ni touch. Scrollbar nativa con thumb de acento: propiedades Firefox y pseudo-elementos Chromium/WebKit.

Botones primary, secondary, ghost, icon, disabled y loading; estados hover, active y focus-visible. Controles input, textarea, select, checkbox, radio, quantity y search. Errores con etiqueta, `aria-invalid`, descripción y texto visible; muestras success/disabled. Los estados de muestra nunca simulan una compra.

## Producto y responsive

`product-grid` consulta productos publicados mediante `wc_get_products`. La card toma imagen, categoría, nombre, precio regular/promocional, oferta y disponibilidad de WooCommerce. No contiene productos TRAMA ni IDs técnicos. El CTA lleva al producto, donde WooCommerce conserva variantes y add-to-cart. Imagen predominante 4:5 y presentación sin caja/sombra. Si no hay fotografía, usa un placeholder propio donde la API nativa lo admite; algunos bloques Woo usan su placeholder interno.

Grilla: una columna por debajo de 360px, dos desde 360px, tres desde 640px y cuatro donde el ancho permite. En layout lateral, las columnas se ajustan al espacio restante. El showcase contiene únicamente los dos productos técnicos existentes; no llena la grilla con duplicados inventados. La fotografía se inyecta solo como fallback de muestra durante ese render, sin guardarla en productos.

Los breakpoints responden al contenido. Desktop introduce composición lateral y asimetría; mobile apila hero/controles y usa drawer para filtros. El panel de filtros es únicamente un patrón visual, sin lógica ni opciones ficticias. H5 configurará los filtros nativos. QHD/ultrawide/4K mantienen el máximo de layout y textos de lectura contenidos.

## Fotografía de muestra

`assets/images/editorial-specimen.webp`, 960×1200, ~119KiB. Generada con el tool integrado `imagegen`; optimizada a WebP para el sitio. No se modifican imágenes de productos ni se agregan medios a WordPress.

Prompt final: “Editorial photo specimen for a refined urban apparel visual-system showcase. Portrait 4:5, anonymous adult cropped from chin to upper thighs, dark olive textured overshirt over ecru cotton t-shirt, hand in trouser pocket, warm limestone architecture and directional late afternoon light, quiet contemporary Buenos Aires atmosphere, tactile fabric, muted olive/cream/charcoal. Single realistic photograph. No text, logos, watermarks, frames or UI.”

## Operación y revisión

```powershell
.\scripts\h3.ps1 sync -Instance local
.\scripts\h3.ps1 verify -Instance local
powershell -NoProfile -File .\tests\verify-h3-local.ps1 -Instance local
```

`sync` empaqueta exclusivamente `commerce-base`, valida rutas y copia al volumen actual. No borra datos, themes ajenos ni archivos obsoletos; avisa si existen estos últimos. No se agregó un bind mount que interfiera con restore de H1. La copia del volumen es runtime; la fuente canónica sigue siendo este repositorio. Los comandos `start`/`stop` conservan el theme sincronizado. Un cambio de fuente requiere `sync`.

Abrir [showcase local](http://localhost:8080/?commerce-base-showcase=1). Accesible en entorno WordPress `local`, o para un administrador autenticado; en otro entorno el visitante recibe 404. Envía `noindex, nofollow, noarchive` y cabeceras sin caché. No crea una página en la base, sitemap ni contenido indexable. Las capturas y reportes privados viven en `.local/local/h3/`.

Commerce Base 1.0.1 añade correcciones de links/footer, opciones de variación y controles de checkout: tokens `--cb-radius-sm: 3px`, `--cb-control-selected` y `--cb-control-mark`. Ver [correcciones, APIs e instancias](h3-corrections.md). El cierre original en 18081 se conserva como evidencia histórica.

Ver [validación y límites de H3](h3-validation.md). H4 requiere revisión visual del usuario.
