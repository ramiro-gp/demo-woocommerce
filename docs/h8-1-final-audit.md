# H8.1 — Auditoría final del 21/10/2026

Rama `codex/h8-1-commerce-core`, base `609f826141b8715c652651fc33e3012bd1cf8595`. Principal: `C:\Codigo\demo-woocommerce`. WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.4.26. Sin merge a main, H8.2 o nueva implementación comercial.

## Cinco escenarios solicitados

| Escenario | Resultado y evidencia |
| --- | --- |
| 1. Copiar dirección | **PASS.** Login con cliente marcado `h81_complete_*`. Se comprobaron los nueve campos expuestos y compatibles: nombre, apellido, país, provincia, localidad, código postal, calle, complemento y teléfono. Empresa está deshabilitada por la configuración de la demo; email excluido. Copiar no produjo solicitudes HTTP |
| 2. Persistencia | **PASS.** Copiar y recargar sin guardar restituyó todos los valores originales. Repetir, guardar por el formulario nativo y recargar conservó todos los valores copiados |
| 3. Confirmación | **PASS.** Se guardó una calle distinta en el cliente QA; abrir la confirmación no cambió campos. Cancelar conservó todos los valores, incluso tras recarga. Confirmar, guardar y recargar produjo la copia esperada |
| 4. Sin sincronización | **PASS.** Tras guardar envío, se modificó y guardó facturación nativamente. La recarga de envío conservó todos sus campos; sólo cambió el origen ofrecido para una futura copia explícita |
| 5. Interfaz | **PASS.** Chromium real, 320/390/1366px × claro/oscuro × confirmación abierta/cerrada: **12 combinaciones**. Bordes del control alineados con el formulario con error ≤1px, sin overflow, targets ≥44px. Enter/Tab/Shift+Tab/Space/Escape y focus-visible ≥2px. Cero errores JS o assets; revisión visual de capturas desktop/mobile |

Se usaron contextos de navegador aislados, nunca la sesión personal. Se prepararon únicamente los tres clientes H8.1 previamente marcados y se operó sobre uno de ellos. No hubo checkout, pagos, operaciones de pedidos, cambios de catálogo, publicación legal o correos nuevos. No se encontró defecto funcional/visual: **Commerce Core y Commerce Base quedan sin modificaciones**.

Nueva línea base estricta: **235 pedidos/reembolsos/borradores**, **127 objetos de catálogo** y **5 cuentas protegidas**, excluyendo sólo los tres clientes QA identificados para direcciones. Hashes de datos, ítems, notas, direcciones de otras cuentas, ajustes, páginas y las opciones de guardas H5/H6 se conservaron. **10/10 checks finales**, sin normalizaciones de fechas ni excepciones de catálogo. Order Integrity y Communications activos, BACS disponible y Checkout Pro deshabilitado.

## Causa exacta de las guardas H5/H6

La huella de H5/H6 incluye `stock_quantity` de productos y variaciones. La operación de pedidos posterior a esos hitos redujo stock legítimamente; la huella de instalación inicial se mantuvo deliberadamente intacta. La comparación actual es **9/16 padres iguales y 7/16 divergentes**, porque incluye recursivamente sus variaciones.

| Objeto afectado | ID | Stock del fixture → actual | Pedidos que explican la reducción |
| --- | --- | --- | --- |
| `TRM-REM-001-NAT-M` | 54 | 4 → 2 | #454 y #456, una unidad cada uno |
| `TRM-REM-002-GRI-L` | 70 | 7 → 6 | #455, una unidad |
| `TRM-PAN-001-VER-L` | 132 | 4 → 3 | #456, una unidad |
| `TRM-ACC-001` | 154 | 22 → 21 | #454, una unidad |
| `TRM-ACC-004` | 163 | 25 → 23 | #455 y #457, una unidad cada uno |
| `TRM-ACC-005` | 166 | 26 → 25 | #457, una unidad |
| `TRM-ACC-006` | 169 | 27 → 26 | #457, una unidad |

Los pedidos **#454–456** se crearon como `h72-manual-account-review`, con marca `_commerce_h72_manual_review`, para revisar Mi Cuenta. El artefacto privado de preparación registra exactamente sus 6 unidades y los stocks antes/después. **#457** es un BACS Store API en espera con destinatario sintético `.test`; sus tres líneas tienen `_reduced_stock=1`. En total, las **9 unidades** explican exactamente las siete diferencias. Todos esos pedidos se leyeron, nunca se modificaron ni cancelaron durante esta auditoría.

Además cambiaron `post_modified`/`post_modified_gmt` y `total_sales`. Las guardas ya excluyen esas propiedades: **no causan el conflicto**. En las variaciones #54/#70/#132 apareció `_crosssell_ids` persistido, igual al del padre. El código instalado `WC_Product_Variation_Data_Store_CPT::read_product_data()` carga esa propiedad desde `parent_data` y `wc_update_product_stock()` guarda el producto al actualizar stock. La semántica de los cross-sells se conservó; tampoco causa la divergencia: normalizar sólo cross-sells deja **9/16**, sin recuperar ninguna de las siete huellas.

Prueba causal, exclusivamente en memoria: reemplazar **sólo** las cantidades por las del backup original reproduce **16/16 huellas H5**, sin tocar cross-sells, precios o stock real. También se reconstruyeron **16/16 huellas H6.2** y **16/16 huellas previas a cross-sells de H6.1**. Las fuentes H5/H6.2 coinciden por SHA-256 con las registradas en sus opciones. No se cambiaron hashes originales ni se debilitó ninguna guarda.

La cronología de backups confirma que el catálogo no tenía estas diferencias en `h7-2-before-communications-20261019`; ya las tenía en `h7-2-before-account-corrections-20261020` y en `h8-1-before-commerce-core-20261020`. Son **anteriores a Commerce Core**. La preparación #454–456 ocurrió el 19/10 a las 22:42 y #457 a las 22:46, hora argentina.

## Reproducibilidad y conclusión

No se detectó defecto del importador ni pérdida de reproducibilidad de las fuentes de una instalación limpia. La evidencia muestra un **estado operativo posterior al fixture inicial**, no una instalación inicial incoherente: con el stock inicial se recuperan exactamente las 16 huellas. No se repitió una instalación completa H0–H7 desde cero; la conclusión se apoya en backups, fuentes inalteradas, API instalada y reconstrucción exacta, sin resembrar ningún catálogo.

Los comandos antiguos de seed/verify seguirán rechazando correctamente esta demo con pedidos, ya que su contrato es preservar ediciones/datos operativos. No ejecutar seed para borrar esta advertencia. Para regresiones posteriores usar una línea base del estado actual y QA aislado; la guarda de instalación inicial no sustituye esa comprobación. Los pedidos históricos #454–457 y su stock deben conservarse.

**H8.1 está listo técnicamente para consolidarse en main**, pendiente únicamente de esa acción autorizada por el usuario. No se efectuó el merge. La advertencia queda explicada; no queda un defecto de Commerce Core pendiente. Los bloqueos productivos de Mercado Pago siguen vigentes y esta conclusión no autoriza producción ni pagos reales.

## Evidencia y repetición

- [Resumen sanitizado](evidence/h8-1/final-audit-summary.json).
- [Desktop claro](evidence/h8-1/final-desktop-light.png).
- [Mobile oscuro](evidence/h8-1/final-mobile-dark.png).
- Privado: `.local/local/h8-1-final-20261021/` contiene reporte por escenario, mediciones, nueve capturas, preservación estricta, comparación de backups y atribución por pedido. Sin credenciales en Git o en los reportes públicos.

```powershell
# Sólo una vez: rechaza sobrescribir la línea base de esta auditoría.
.\scripts\dev.ps1 wp -WpArgs @('eval-file','/opt/local-tools/h8-1-final-audit.php','snapshot','--quiet')
# Sólo clientes H8.1 con marca y rol comprobados; no modifica catálogo.
.\scripts\dev.ps1 wp -WpArgs @('eval-file','/opt/local-tools/h8-1-qa.php','reset','--quiet')
# Con Node/Playwright y NODE_PATH locales:
node tests/verify-h8-1-final-audit.cjs
.\scripts\dev.ps1 wp -WpArgs @('eval-file','/opt/local-tools/h8-1-final-audit.php','verify','--quiet')
# Python lee exclusivamente posts/metadatos de productos de los backups:
python docker/tools/h8-1-fixture-origin.py
.\scripts\dev.ps1 wp -WpArgs @('eval-file','/opt/local-tools/h8-1-fixture-audit.php','--quiet')
```

La auditoría necesitó recuperar Docker Desktop, detenido por sockets AF_UNIX obsoletos. Se preservaron por renombre sólo directorios comprobados que contenían archivos de socket vacíos, fuera del repositorio, y se recrearon sus rutas temporales. No hubo factory reset, prune, borrado de imágenes/volúmenes ni restauración de la tienda. Se iniciaron los servicios existentes sin ejecutar instaladores.
