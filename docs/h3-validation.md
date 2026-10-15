# H3 — Cierre técnico y revisión visual

**Nota posterior:** este documento registra el cierre original en `h2-public`. Para las correcciones actuales, la demo principal es `http://localhost:8080`, Commerce Base 1.0.1. Ver [informe post-H3](h3-corrections.md) con separación de instancias, URLs y resultados actuales.

Implementado y validado el 15/10/2026, hora de Argentina. Commerce Base está listo para la revisión visual del usuario. H4 no se inició.

## Punto de partida y preservación

Ruta canónica: `C:\Codigo\demo-woocommerce`. Se verificaron H0, H1 y H2 antes de editar. `main` y el remoto real coincidían en `4e7259094e8c415e4a4c7e26d74acc6524cd392a`; árbol limpio, sin pendientes H2 para reconciliar. Trabajo H3 en `codex/h3-commerce-base`.

Instancia existente `h2-public`, sitio `http://localhost:18081`, Mailpit `http://localhost:18026`. Respaldo preventivo privado `.local/h2-public/backups/h3-before-theme/`. No se inicializó otra tienda, reseteó base ni eliminó volumen. No se tocó el stack Supabase ajeno. El túnel HTTPS sigue apagado.

Se activó Commerce Base y se configuraron nombre/descripcion TRAMA como datos de WordPress. No se modificó la configuración comercial. `h3-verify.php` compara hashes de plugins/versiones, HPOS, opciones comerciales y credenciales MP, envíos, productos/variantes/precios/stock, pedidos/devoluciones, reservas, URLs y contenido de páginas. Los valores secretos nunca se imprimen. Baseline antes de activación y comparación final iguales.

## Evidencia ejecutada

| Área | Resultado |
| --- | --- |
| Block Theme | Reconocido y activo; theme.json válido; templates/parts parseados; fuentes WOFF2 válidas |
| PHP/JS/PowerShell | Sintaxis válida; diff sin whitespace errors |
| HTTP | Frontend, login, showcase, tienda, carrito, checkout y cuenta: 200; showcase con X-Robots-Tag noindex |
| Browser H3 | 42/42 comprobaciones, Chromium, sin pageerror ni fallos de assets propios |
| Responsive | 320, 375, 390, 430, 768, 1024, 1366, 1440, 1920, 2560, 3440 y 3840px en claro/oscuro; sin overflow ni controles fuera del viewport |
| Tipografía/controles | Fuentes locales cargadas, body >=16px, labels y targets de botones >=44px |
| Apariencia | Sistema inicial, cambio del SO, selección manual persistente tras reload, sincronización entre selects/pestañas, storage bloqueado |
| Header | Transparencia/progreso 0, 0.5, 1; página sin hero con offset correcto |
| Menú/drawer | Entrada/cierre inverso, diálogo abierto durante transición, Tab/Shift+Tab, Escape, foco inicial/retorno, scroll restaurado, reapertura durante cierre, búsqueda |
| Anchors | Scroll suave, hash, foco, margen de cabecera; anchor desde menú espera al cierre |
| Reduced motion/sin JS | Movimiento reducido, anchors inmediatos, contenido esencial visible y navegación alternativa |
| Reflow/zoom | Equivalente de 200%/400% desde 1280px mediante viewports CSS 640/320 y escala de píxel 2/4; no es una prueba del control de zoom de la UI del navegador |
| Firefox | Smoke adicional a390px en oscuro: menú abre, Escape/cierre funciona, sin overflow |
| WooCommerce Store API | Carrito invitado aislado, simple+S, rechazo M agotado/exceso de stock, envíos y selección de tarifa/retiro, preview de checkout repetido sin crear pedido |
| WooCommerce navegador | Add-to-cart nativo desde producto; Cart Block poblado; Checkout Block hidratado a1440/390px en oscuro; carrito de prueba vaciado |
| Administración | Login real con credenciales privadas, Commerce Base activo en Apariencia y Site Editor cargado sin errores JS |
| Persistencia Docker | Reinicio de WordPress, theme/configuración/datos conservados; smoke y baseline posteriores aprobados |
| Logs | No se generó wordpress-debug.log; sin errores PHP críticos en logs del contenedor durante H3 |
| Reutilización | Header renderizado con otra identidad mediante filtro temporal de opción, sin guardar datos ni editar templates |

Las capturas se inspeccionaron visualmente, incluidos hero claro/oscuro, menú mobile, tipografía/paleta, controles, estados, productos y checkout hidratado. Los checks automatizados de reflow complementan esa revisión. WCAG 2.2 AA es el objetivo del sistema; esta entrega no pretende una certificación exhaustiva de todos los bloques de terceros ni de páginas futuras.

La revisión manual adicional cubrió 320, 375, 430, 768, 1024, 1366, 1920 y 2560px. El ajuste final del hero de notebook mide 548px de 768px (71.38svh); en 1920/2560px se mantiene aproximadamente en 71.8/70.8svh. Las capturas `visual-*-light-top.png` y los reportes `visual-review.json`/`visual-notebook-final.json` documentan esta inspección. CSS `zoom` se exploró como diagnóstico, pero no se usa como sustituto ni evidencia del zoom real del navegador.

## Problemas encontrados y resueltos

- Los layouts nativos de WooCommerce no usaban la clase de margen del theme. Se añadió clase/skip target mediante el filtro público de grupos; se comprobó que la cabecera ya no cubre contenido.
- Checkout usaba el header mínimo de WooCommerce. Se agregó una sola composición `page-checkout.html` que reutiliza partes del theme y conserva wrapper/notices/post-content nativos. No se alteró contenido de carrito/checkout ni la pasarela.
- Country/province selects conservaban superficie blanca en oscuro. Se adaptaron las clases públicas de esos controles y se revisó la captura tras hidratación completa. Se añadió espacio entre grupos de dirección y tipografía de interfaz para títulos funcionales.
- El preview de bloques necesitaba `useBlockProps` y una cabecera estática dentro del editor. Se corrigieron ambos.
- Los primeros checks de reflow midieron el panel todavía animándose. Se corrigió la espera del test hasta transform final; no se ocultó contenido ni se deshabilitó la animación para aprobarlo.

## Límites conservados

No se enviaron nuevos pagos ni devoluciones. La validación de Mercado Pago es la evidencia funcional aprobada de H2 más preservación de plugin, configuración/credenciales y disponibilidad local; no se afirma haber repetido pagos de extremo a extremo en H3. La repetición exacta de notificación sigue con el límite documentado en H2.

Los productos técnicos siguen sin fotografías comerciales. El showcase usa una imagen de muestra durante render; algunos bloques nativos WooCommerce mantienen su placeholder propio. El catálogo/producto y los filtros definitivos son H5, y la composición final de checkout es H6. El hero es un patrón, la home no está construida.

## Repetir comprobaciones

```powershell
.\scripts\h3.ps1 sync -Instance h2-public
powershell -NoProfile -File .\tests\verify-h3-local.ps1 -Instance h2-public
powershell -NoProfile -File .\tests\verify-h2-local.ps1 -Instance h2-public
$env:NODE_PATH = 'C:\Users\ramiro\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
node .\tests\verify-h3-browser.cjs
```

Playwright se usa del runtime ya disponible; no se agregó al theme ni se creó un package.json con dependencias de producción. `PLAYWRIGHT_BROWSERS_PATH` puede configurarse para otra instalación. No repetir `snapshot` después de modificar datos para esconder diferencias: el baseline representa H2 anterior a la activación.

## Revisar visualmente

Abrir [showcase H3](http://localhost:18081/?commerce-base-showcase=1), alternar Claro/Oscuro/Sistema, recorrer el índice y probar menú, búsqueda, foco, estados y panel de filtros. Esta vista no crea una página publicada ni indexable.

Capturas privadas exactas en `.local/h2-public/h3/`:

- `showcase-1440-light-top.png`, `showcase-1440-dark-top.png`.
- `showcase-390-light-top.png`, `showcase-390-dark-top.png`.
- Los mismos cuatro nombres terminados en `-full.png` muestran todo el sistema.
- `menu-1440.png`, `menu-390.png`.
- `cart-light.png`, `checkout-dark.png`, `checkout-mobile-dark.png`.

Reportes: `baseline.json`, `after.json`, `http.json`, `browser-qa.json`, `commerce-browser.json`; todos excluidos de Git. Ver [dirección, estructura, tokens y funcionamiento](h3-design-system.md).
