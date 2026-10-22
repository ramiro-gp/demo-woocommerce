# H9.2 — Rendimiento, accesibilidad y SEO

Auditoría del 21–22/10/2026 (Argentina), después del cierre de seguridad `5c50cc8`. WordPress 7.1.3, WooCommerce 11.2.1, PHP **8.4.27 en ambos lados** de la comparación, MariaDB 10.11.19. No mide el efecto del parche PHP de H9.1. Sin publicación ni datos de tráfico real.

## Método y condiciones

Lighthouse **13.5.0**, Chromium/Playwright del runtime, Windows 11 10.0.26200, AMD Ryzen 7 9800X3D, 16 procesadores lógicos, 31 GB de RAM visibles. Docker Desktop local; clon `http://localhost:8199`, mismo contenido/DB y mismos fixtures antes/después. No es infraestructura Hostinger ni benchmark de capacidad/concurrencia productiva.

**22 resultados por fase**: una pasada por página/perfil, excepto catálogo/categoría/carrito/checkout, donde se toma el run de **mediana LCP de tres** (se publican las tres muestras). Mobile estándar LH: 412×823 CSS px, DPR 1.75, red simulada y CPU 4×; desktop 1366×900, DPR 1, RTT simulado 40 ms, 10 Mbps y CPU 1×. Los `configSettings` exactos están en cada fila JSON. Caché de disco vaciada y memoria del renderer expulsada mediante el protocolo usado por LH, cachés de servidor/OPcache calientes, sin caché HTML ni object cache persistente. Cookie/carrito QA se conserva para medir estado lleno. La preparación de carrito/checkout se hace por HTTP sin descargar assets; después se vacía la caché. La comparación final se rehízo completa con este protocolo y con código/metadata iniciales restaurados en clon. Se amplió la muestra de esas cuatro páginas ante variabilidad y el hallazgo de prioridad LCP, sin seleccionar el mejor score.

HTTPS externo bloqueado antes de cargarlo en ambas fases; MP permanece activo/Pro disabled, con los mismos loaders. Requests cuenta entradas del registro de LH, incluidos intentos externos bloqueados; bytes suman transferSize de recursos efectivamente recibidos, con cabeceras/compresión. `resourceBytes` ofrece tamaño decodificado. Los resultados no describen el coste completo de ejecutar scripts remotos ni tráfico hacia el proveedor. Trazas/LHR crudos quedan en `.local/h9-audit/lighthouse/`; evidencia publicada sólo incluye métricas y nombres de auditorías.

La vista **Lookbook es una sección de Home**, no otra URL/documento: comparte sus métricas, con interacciones evaluadas por separado. Se midieron Privacidad y Términos como páginas legales representativas, además del formulario de arrepentimiento. No se duplicó una medición por cada ancla editorial.

No hay INP de campo, CrUX ni p75. TBT de laboratorio no se llama INP. Referencias futuras de experiencia real: LCP ≤2,5 s, INP ≤200 ms y CLS ≤0,1 en p75. Una pasada local no acredita esos objetivos ni significancia estadística: variaciones pequeñas de tiempos/puntuación pueden ser ruido. Peso y recursos retirados sí permiten comprobar cambios concretos.

## Comparación por página

Ver [métricas iniciales](evidence/h9-2/lighthouse-before.json) y [finales](evidence/h9-2/lighthouse-after.json). Las tablas generadas que siguen expresan **P/A/BP/SEO** (Performance/Accessibility/Best Practices/SEO), KiB transferidos, requests, LCP de laboratorio en segundos y CLS. SEO conserva penalizaciones intencionales por noindex y limitaciones de contenido DEMO. Un 100 de accesibilidad no certifica WCAG.

<!-- H9_TABLES_START -->

### Mobile

| Página | LH inicial → final (P/A/BP/SEO) | KiB inicial → final | Requests inicial → final | LCP inicial → final (s) | CLS inicial → final | Peso reducido | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Home | 74/97/96/58 → 85/100/96/69 | 1363.8 → 1134.1 | 81 → 81 | 5.910 → 3.780 | 0.001 → 0.001 | 16.8% | LCP de una pasada |
| Catálogo | 77/97/100/54 → 83/100/100/69 | 1723.7 → 1087.4 | 101 → 100 | 4.256 → 3.760 | 0.000 → 0.000 | 36.9% | Mediana LCP (3); caché fría |
| Categoría Remeras | 73/97/100/54 → 84/100/100/61 | 977.4 → 844.7 | 90 → 89 | 4.759 → 3.684 | 0.000 → 0.000 | 13.6% | Mediana LCP (3); descripción pendiente |
| Producto simple | 69/97/100/58 → 70/100/100/69 | 1589.4 → 1383.9 | 93 → 93 | 9.718 → 8.751 | 0.078 → 0.078 | 12.9% | Galería; límite mobile |
| Producto variable | 70/94/100/58 → 70/100/100/69 | 1327.8 → 1156.9 | 98 → 98 | 8.911 → 8.142 | 0.078 → 0.078 | 12.9% | Galería; límite mobile |
| Carrito lleno | 73/100/100/54 → 73/100/100/58 | 982.8 → 982.8 | 168 → 168 | 4.504 → 4.504 | 0.017 → 0.017 | 0.0% | Mediana LCP (3); caché fría |
| Checkout lleno | 64/100/96/58 → 64/100/96/61 | 983.8 → 980.7 | 169 → 168 | 8.406 → 8.406 | 0.000 → 0.000 | 0.3% | Mediana LCP (3); caché fría |
| Mi Cuenta (login) | 80/97/100/54 → 93/100/100/58 | 766.4 → 235.0 | 73 → 34 | 3.911 → 2.705 | 0.001 → 0.001 | 69.3% | Assets por contexto |
| Privacidad | 80/96/100/54 → 95/100/100/66 | 742.9 → 206.5 | 70 → 29 | 3.758 → 2.404 | 0.042 → 0.042 | 72.2% | Assets por contexto |
| Términos | 81/96/100/54 → 95/100/100/66 | 741.5 → 205.1 | 70 → 29 | 3.760 → 2.404 | 0.000 → 0.000 | 72.3% | Assets por contexto |
| Arrepentimiento | 80/97/100/54 → 95/100/100/66 | 742.8 → 206.4 | 70 → 29 | 3.759 → 2.404 | 0.000 → 0.000 | 72.2% | Assets por contexto |
| Lookbook | Ver Home | Ver Home | Ver Home | Ver Home | Ver Home | Ver Home | Misma URL/documento; teclado probado |

### Desktop

| Página | LH inicial → final (P/A/BP/SEO) | KiB inicial → final | Requests inicial → final | LCP inicial → final (s) | CLS inicial → final | Peso reducido | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Home | 97/97/100/58 → 97/100/100/69 | 1317.6 → 1088.4 | 83 → 83 | 1.207 → 1.166 | 0.001 → 0.001 | 17.4% | LCP de una pasada |
| Catálogo | 95/100/100/54 → 98/100/100/69 | 1730.1 → 1093.8 | 104 → 103 | 1.399 → 1.057 | 0.000 → 0.000 | 36.8% | Mediana LCP (3); caché fría |
| Categoría Remeras | 97/100/100/54 → 99/100/100/61 | 983.9 → 851.2 | 93 → 92 | 1.040 → 0.968 | 0.000 → 0.000 | 13.5% | Mediana LCP (3); descripción pendiente |
| Producto simple | 97/97/100/58 → 98/100/100/69 | 1595.9 → 1453.3 | 96 → 96 | 1.206 → 1.030 | 0.000 → 0.000 | 8.9% | Galería; límite mobile |
| Producto variable | 99/94/100/58 → 99/100/100/69 | 1334.3 → 1223.6 | 101 → 101 | 0.986 → 0.909 | 0.000 → 0.000 | 8.3% | Galería; límite mobile |
| Carrito lleno | 98/100/100/54 → 98/100/100/58 | 982.8 → 982.8 | 168 → 168 | 0.926 → 0.926 | 0.000 → 0.011 | -0.0% | Mediana LCP (3); caché fría |
| Checkout lleno | 99/100/96/58 → 98/100/96/61 | 983.8 → 980.8 | 169 → 168 | 0.723 → 0.923 | 0.002 → 0.002 | 0.3% | Mediana LCP (3); caché fría |
| Mi Cuenta (login) | 99/97/100/54 → 100/100/100/58 | 766.4 → 235.0 | 73 → 34 | 0.849 → 0.605 | 0.004 → 0.004 | 69.3% | Assets por contexto |
| Privacidad | 99/96/100/54 → 100/100/100/66 | 742.9 → 206.5 | 70 → 29 | 0.807 → 0.525 | 0.004 → 0.004 | 72.2% | Assets por contexto |
| Términos | 99/96/100/54 → 100/100/100/66 | 741.5 → 205.1 | 70 → 29 | 0.775 → 0.524 | 0.000 → 0.000 | 72.3% | Assets por contexto |
| Arrepentimiento | 99/97/100/54 → 100/100/100/66 | 742.9 → 206.4 | 70 → 29 | 0.808 → 0.524 | 0.000 → 0.000 | 72.2% | Assets por contexto |
| Lookbook | Ver Home | Ver Home | Ver Home | Ver Home | Ver Home | Ver Home | Misma URL/documento; teclado probado |

<!-- H9_TABLES_END -->

## Cambios comprobables

- **Assets por contexto:** catálogo/CSS sólo donde hay cards, catálogo, carrito o cuenta; Lookbook sólo si está su bloque; skin Mini-Cart excluido también del checkout, que no lo renderiza. El adaptador legacy de presentación del carrito se retira de páginas sin cards ni contador/feedback propios: el Mini-Cart conserva su controlador oficial de Interactivity. No se retiran librerías necesarias de WC ni se reescribe carrito/checkout.
- **Imágenes:** nuevo tamaño WP `commerce-card` 360×450, metadatos/srcset nativos y 32 derivados aditivos, calidad WebP 86. Total de esos derivados: 633.776 bytes. Se conservan tamaños mayores para DPR/resoluciones que los necesiten, imágenes originales, calidad editorial y posiciones de hotspots. No se convierten formatos por moda ni se modifica stock.
- **Hero:** derivado 480 px WebP, **44.382 vs 122.150 bytes** del original (63,7% menor para ese recurso cuando el navegador lo elige). Srcset/sizes selecciona según viewport/DPR; no se afirma que todos los móviles reciban el pequeño. Eager/high sólo para imagen principal; galería secundaria lazy/low, dimensiones y tamaños responsivos preservados. [Evidencia](evidence/h9-2/hero-derivative.json) / [cards](evidence/h9-2/card-derivatives.json).
- **Accesibilidad:** Mini-Cart cerrado se oculta también mediante visibility, conservando dimensiones/controlador/animación nativa de apertura y evitando Tab hacia contenido offscreen; nombres accesibles de “Seleccionar opciones” incluyen ese texto visible y producto; estado `.disabled` de variaciones se expone como `aria-disabled`, sin impedir el flujo de aviso nativo. El skip link autogenerado de WP usa el mismo foco explícito al main que los enlaces propios.
- **SEO:** fallback pequeño de descriptions/OG/Twitter basado en datos WordPress/WC, desactivable por filtro y omitido al detectar Yoast, SEOPress, Rank Math, AIOSEO o The SEO Framework. Títulos/canonical singular/JSON-LD siguen a cargo de core/WC; sólo se completa canonical de archivos. Endpoint nativo robots.txt recuperado en permalinks simples. Sin plugin SEO nuevo ni identidad TRAMA codificada en el módulo.

No se minificaron/agruparon arbitrariamente librerías, no se precargaron todas las imágenes, no se eliminó funcionalidad para subir la puntuación. Fuentes WOFF2 locales con `font-display:swap`, sin Google Fonts ni nuevas dependencias de frontend. Las animaciones existentes respetan reduced motion.

## Límites de rendimiento pendientes

Las fichas siguen mostrando **LCP mobile de laboratorio alto**, aun con menor peso; catálogo/Home también superan 2,5 s en la simulación. La galería clásica y los assets compartidos de WC/React/Blocks siguen representando trabajo de red/CPU. La captura automática incluye imágenes que el navegador decide anticipar por su umbral de lazy loading, y la inicialización de galería/thumbs puede producir un candidato LCP tardío. No se toca el plugin oficial ni se sustituye su galería para ocultarlo.

Lighthouse estima CSS/JS no usado en esa navegación; no demuestra que pueda quitarse sin romper modales, cambios de estado o variantes. Los JSON incluyen `unusedCssBytes`, `unusedJsBytes`, `jsExecutionMs`, TTFB y TBT. Las estimaciones de ahorro de LH no se suman como mejora real. Home registra LCP menor en esta pasada, sin certificar una mejora sostenida. Una muestra intermedia de categoría mobile había empeorado: la traza identificó la primera foto visible como LCP, todavía lazy y sin prioridad. Se corrigió **sólo la primera card de listados** a eager/high, con sizes apropiado; Home/relacionados siguen lazy. En las tres muestras finales la mediana de categoría baja **4,759 → 3,684 s**, y catálogo **4,256 → 3,760 s**. [Detalle de prioridad](evidence/h9-2/lcp-priority.json). No se sustituye el producto nativo ni se ocultan imágenes. Carrito y checkout mobile permanecen prácticamente iguales en las medianas; checkout desktop varía de 0,723 a 0,923 s. No hay medición de INP real ni certificación de rendimiento productivo.

## DB, consultas y tareas

[Perfil focalizado](evidence/h9-2/query-profile.json), instrumentación sólo en clon/MU con `SAVEQUERIES` después de carga MU; excluye consultas previas al MU. Home 129 consultas/25,85 ms SQL, catálogo 235/59,34 ms, producto variable 154/33,24 ms y formulario legal 82/16,92 ms. Catálogo repite cuatro SQL exactos; los otros tres casos no. No se exporta SQL ni valores de pedidos/clientes. Son muestras locales, no p95 ni prueba de ausencia de consultas lentas en datos masivos.

33 consultas de Home corresponden al grid de cuatro productos y 36 del catálogo a las cards/colores/variaciones. No se encontró una consulta propia lenta reproducible que justifique una caché paralela con invalidación de stock compleja. Los productos y variaciones usan CRUD/cache nativos; Lookbook prima productos/meta/términos. Legal no escanea su tabla de solicitudes en cada página: sus opciones se cachean por petición y las consultas de casos se limitan al formulario/gestión/exportación.

Opciones autoload iniciales: **336 / 55.875 bytes**. Tablas legales InnoDB con índices de ID, código/token únicos, email, request_id y expiración de límites; lista por ID y exportación paginadas. No se modifican índices/estructuras nativas WC. No se limpian sesiones, revisiones, transients, pedidos, notas ni metadatos comerciales por rutina. Cron y Action Scheduler conservan sus mecanismos soportados; H11 requiere ejecución fiable y observación de cola/fallos, sin jobs duplicados.

Ampliación de privacidad de cierre: [barrido agregado de logs principal](evidence/h9-2/log-audit.json), 66 logs WC / 888.037 bytes, sin coincidencias de email, tokens de credenciales ni claves de orden en los patrones utilizados; sí 100 marcadores de campos de dirección. Esto no demuestra anonimización ni ausencia de datos personales en otros formatos. Debug PHP no existe en el contenedor principal recreado (almacenamiento temporal), sin exportar contenido ni borrar los logs WC históricos. Mantener acceso restringido y definir retención; no trasladarlos al cliente como material público. Las cabeceras se normalizan para evitar nosniff duplicado en REST y se bloquea también el template `wp-config-docker.php`.

## Estrategia de caché WooCommerce

**No se instala un cache de páginas, Redis ni LiteSpeed Cache en H9.** [Prueba de origen](evidence/h9-2/cache.json): 17 casos, anónimo y clientes A/B. Carrito, checkout y cuenta devuelven `private`/no-cache; formulario legal añade no-store y Store API cart devuelve no-store. Cada cliente permanece sin datos de pedidos del otro. Cambios de cupón/envío, checkout y stock se probaron con fixture en clon, sin tocar históricos.

| Recurso | Regla requerida al configurar hosting/CDN |
| --- | --- |
| Home, catálogo, categorías, editorial público | Potencial caché sólo de GET/HEAD anónimos sin sesión/carrito/personalización. Invalidar al cambiar producto/precio/stock/taxonomías/contenido; TTL y variación por query aprobados. |
| Carrito/checkout/cuenta y endpoints | Bypass completo en URI bonita **y** permalinks/query strings simples (IDs actuales 8/9/10). Incluir order-pay, order-received, view-order, edit-address, lost-password y rutas futuras traducidas. |
| Formulario/constancias legales | Bypass de página 514 y toda su futura URL: contiene cookie/token/nonce y respuesta privada. No cachear POST ni mensajes de error/constancias. |
| Sesiones / autenticación | Bypass con wordpress_logged_in_*, wp_woocommerce_session_*, woocommerce_items_in_cart, woocommerce_cart_hash; respetar Authorization, Nonce, Cart-Token, cookies y Set-Cookie. No cachear datos por ID de sesión para servirlos a otro visitante. |
| APIs/AJAX/admin | No cachear wc/store/* (cart, checkout, order, batch), WC REST privado, ?rest_route=, wc-ajax, admin-ajax, admin-post, login/admin ni notificaciones de pago. Nada de cache de POST/PATCH/PUT/DELETE. |
| HTML traducido/moneda/filtros | Definir keys/variantes sólo cuando existan esas funciones; no asumir moneda o ubicación global. Preferir bypass ante personalización desconocida. |

La prueba de origen **no verifica un CDN inexistente**. El cache que responde antes de PHP debe excluir cookies/rutas aunque WordPress emita no-store; H11 deberá validar MISS/BYPASS/HIT con dos clientes, stock, cupones y envío contra el proveedor real. [Hostinger documenta bypass de cookies WP/WC](https://www.hostinger.com/support/how-hostinger-cdn-caching-works/), pero eso no comprueba que el plan actual incluya ese CDN ni sus ajustes concretos.

## Accesibilidad WCAG 2.2 AA

[Navegador y axe 4.14.0](evidence/h9-2/browser.json): **22 casos** (11 páginas × 390 dark / 1366 light), sin violaciones automáticas de los tags WCAG A/AA/2.1/2.2 evaluados. **89 comprobaciones** de estructura, reflow e interacción. En la línea inicial LH detectó `aria-hidden-focus`, `label-content-name-mismatch` y contraste del botón con clase disabled; los cambios anteriores corrigen exposición de foco, nombre visible y semántica del estado. No se alteraron tokens de color aprobados para maquillar el scanner.

Interacciones reales: primer skip link y foco al main; menú con foco/trap, Escape y retorno al opener; búsqueda de escritorio con foco al input; Lookbook focus/Escape; apertura/cierre Mini-Cart y foco; radios de swatches con flechas; selects nativos preservados, galería con teclado; formulario y selects disponibles sin JS. Login/cuenta, carrito, checkout, labels, headings, contraste light/dark y legal incluidos en axe. Las capturas de Home/variable a 390 dark y 1366 light fueron inspeccionadas; no cambió la composición aprobada.

Reflow probado a **320 y 640 CSS px**, como equivalentes de viewport reducido al 400%/200% desde 1280 px, sin overflow horizontal en Home/producto/cuenta/legal. **No se certifica zoom nativo del navegador**, personalización de fuentes, zoom de texto independiente, contraste forzado ni todos los tamaños intermedios. Axe tiene reglas incompletas que requieren juicio (registradas en JSON). Sin motor de lector de pantalla disponible: pendientes NVDA/JAWS/VoiceOver, pronunciación, secuencia y calidad de anuncios bajo interacción extensa. Los tamaños de controles principales y hotspots, focus-visible y reduced motion se revisaron en componentes; no es una declaración integral de conformidad AA.

## SEO y datos estructurados

[QA técnico](evidence/h9-2/seo.json): canonical singular único de core, archivos con canonical local, filtros noindex, 404 real y `robots.txt` manejado por core en permalinks simples. La DEMO mantiene `blog_public=0`, meta robots y X-Robots-Tag de local forzado por Core. El robots nativo permite leer el noindex y bloquea rutas operativas; **no se presenta como un control de acceso ni como garantía de desindexación por sí solo**. Sitemap local no disponible (404), coherente con visibilidad privada. No se publica ni envía a buscadores.

Títulos/H1 nativos, breadcrumbs WC, alt de fotografías reales y URLs existentes preservados. Las variantes siguen en producto padre, con estado de stock/precio nativo. No se crean URLs indexables por cada selección ni marcado paralelo. Productos ocultos y rutas privadas reciben noindex. Un filtro de catálogo canonicaliza a su archivo público. El catálogo actual cabe en una página: `paged=2` devuelve 404 correctamente; no se simuló contenido de una segunda página. No hubo cambios de slug ni redirecciones permanentes necesarias.

Descriptions se derivan de excerpt/descripción del producto, página, taxonomía o descripción del sitio; se omiten si no hay datos. En Remeras no hay descripción persistida utilizable: queda pendiente de contenido real del comercio, sin inventar copy. OG usa identidad/título/URL/imagen destacada efectivos; si falta imagen destacada se omite og:image, con Twitter summary. Una futura capa SEO especializada debe desactivar este fallback mediante detección o `commerce_base_metadata_enabled=false`. Nada identifica permanentemente a TRAMA en el código funcional.

WooCommerce mantiene un único nodo **Product** por producto simple/variable inspeccionado, **Offer en ARS**, precio visible 36.000/32.000 respectivamente, InStock coincidente y URLs/imágenes reales del sitio. Sin reseñas/ratings, marca inventada, fiscalidad, shippingDetails ni returnPolicy nuevos. BreadcrumbList sigue nativo. Los límites del marcado de variantes pertenecen a la versión de WC: H9 no promete schema por variante si WC entrega la oferta del padre. No se duplicó JSON-LD.

Validación local sintáctica/semántica y contraste con el DOM; Google Rich Results y recepción de sitemap sólo podrán probarse con URL pública futura autorizada. No se conectó Search Console ni servicios sociales/analytics. Activación futura de SEO: entorno production correcto, datos reales/legales revisados, setting nativo de visibilidad, canonical/HTTPS/rewrites definitivos y proveedor SEO elegido, sin reutilizar URLs ficticias. Es requisito de configuración para H11, no una tarea iniciada aquí.

## Integridad y reproducción

El generador de imágenes sólo añade `commerce-card` a metadata de 32 adjuntos; no cambia productos, stock, cuentas, pedidos ni originales. [Preservación final](evidence/h9-2/preservation.json) compara tablas comerciales, posts y metadata no adjunta por hashes y valida los archivos originales. Los fixtures y pedidos nuevos pertenecen sólo al clon. Regresiones focalizadas BACS/HPOS, correo, permisos, módulos propios, cart/shipping/coupon y legal se enlazan desde [H9.1](h9-1-security-hardening.md); la corrida final tras sync confirma que no se perdió funcionalidad.

El cierre incluye [dos correos BACS multipart, admin/cliente, capturados en Mailpit aislado](evidence/h9-2/emails.json), todos los destinatarios `.test`, sin entrega externa. [Inventario final principal](evidence/h9-2/inventory-final.json): Base 1.8.0, Core 1.1.2, MP activo/Pro disabled, atribución no, HPOS sí. [SAPI web real del clon](evidence/h9-2/web-runtime.json): PHP 8.4.27, apache2handler; la principal usa la misma imagen oficial. El clon se detiene al terminar QA, conservando volúmenes/backup privados. La principal permanece disponible en localhost:8080.

```powershell
# Herramientas QA ignoradas; NODE_PATH y navegadores según H9.1:
node tests/measure-h9.mjs before
# Aplicar sólo en clon primero, conservar originales:
.\scripts\h9.ps1 sync -Instance h9-audit
.\scripts\h9.ps1 wp -Instance h9-audit -WpArgs @('eval-file','/opt/local-tools/h9-media.php','cards','--quiet')
node tests/verify-h9-browser.cjs
node tests/verify-h9-cache.cjs
node tests/verify-h9-seo.cjs
node tests/measure-h9.mjs after
```

No repetir medición inicial sobre el código final y llamarla baseline; los JSON versionados fijan el estado de cada fase. [Matriz de preparación](h9-production-readiness.md) separa cierre técnico de autorización comercial y requisitos del hosting.
