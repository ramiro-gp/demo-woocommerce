# H6.2 — Envíos y preparación logística

Evaluación: 16/10/2026. Ruta canónica `C:\Codigo\demo-woocommerce`. Rama `codex/h6-2-shipping-logistics`, para revisión manual del usuario; no se integra a main. WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.4.26 y Commerce Base 1.5.0. H7 no comenzó.

**Configuración DEMO — reemplazar al adaptar la tienda a un comercio real.** Importes, productos físicos y ubicación de retiro son ficticios. No representan tarifas ni cobertura operativa de un transportista.

## Consolidación previa

Árbol limpio en `codex/h6-1-cart-checkout`; remote `https://github.com/ramiro-gp/demo-woocommerce.git`. Fetch de origin sin divergencias. Último commit aprobado: `0db3289c3a8705140a755becc02672eda16a8f8b`, incluyendo `d11ab4c` (H6.1), `c5d73e6` (mini-cart/Exit Intent) y `0db3289` (cupón/drawer).

`main` avanzó por fast-forward desde `b3ed4af1180c8593468a95e2f4382fee0bfad7a7` al commit aprobado. Push normal exitoso; `main` y `origin/main` iguales antes de crear H6.2. No force push, rebase ni descarte de commits. H6.2 permanece en su propia rama.

## Arquitectura

WooCommerce conserva cálculo, paquetes, sesiones, Store API, selección, descuentos, validación, recálculo, loading y anuncios accesibles. Commerce Base sólo presenta sus componentes. No endpoint, cálculo manual, listener comercial, polling, SDK ni dependencia logística nueva. Commerce Lookbook y Mercado Pago conservan sus versiones existentes. No se creó Commerce Core.

`assets/css/shipping.css` estiliza selectores genéricos de WooCommerce, compartidos con futuros carriers; se carga sólo en carrito/checkout y en el editor. El theme desconoce tarifas, threshold, nombres de transportistas y credenciales.

## Zona y métodos

| Configuración | Valor demo |
| --- | --- |
| Zona | Argentina — DEMO, país AR, sin restricciones de CP/provincia |
| Envío estándar | Flat Rate nativo, $4.500 por pedido/paquete, sin clases ni fórmula |
| Envío gratis | Free Shipping nativo, mínimo $100.000, después del descuento de cupones |
| Ocultar tarifas pagas con envío gratis | Opción nativa `woocommerce_shipping_hide_rates_when_free=yes` |
| Retiro | Local Pickup moderno de Blocks, gratuito, separado de las zonas |
| Resto del mundo | Sin métodos de entrega; no se crea tarifa internacional |

Se evoluciona la zona técnica H2 existente mediante APIs WooCommerce, conservando su ID. Se reemplaza el retiro legacy de esa zona por el retiro moderno para evitar duplicados. El snapshot privado conserva la configuración anterior.

Los precios efectivos del catálogo van de $11.000 a $82.000 (promedio $43.062,50, con las tres ofertas vigentes). El mínimo de $100.000 requiere combinar productos, por ejemplo Buzo Refugio + Gorra Sombra = $102.000. Flat Rate $4.500 es un importe ilustrativo moderado; no se vincula a una tarifa de correo.

Con subtotal elegible menor a $100.000 aparece Envío estándar. Desde exactamente $100.000 aparece Envío gratis y Woo oculta Flat Rate; Retiro sigue disponible en su selector propio. Al bajar del mínimo Woo restablece el método elegible y recalcula el total. No hay barra «Te faltan $X»: no se agrega un cálculo paralelo al core.

Se mantiene venta/facturación Argentina. El selector de destinos de entrega permite consultar otros países mediante la opción oficial `woocommerce_ship_to_countries=all`; esto permite comprobar el estado sin cobertura. No incorpora métodos ni habilita cobertura mundial. Una tienda real debe adaptar países permitidos y zonas a su operación.

Fuentes: [Shipping Zones](https://woocommerce.com/document/setting-up-shipping-zones/), [Flat Rate](https://woocommerce.com/document/flat-rate-shipping/), [Free Shipping y descuentos](https://woocommerce.com/document/free-shipping/), [ocultar tarifas pagas](https://woocommerce.com/document/hide-other-shipping-methods-when-free-shipping-is-available/).

## Retiro con Checkout Blocks

Se configura el método `pickup_location`, `woocommerce_pickup_location_settings` y `pickup_location_pickup_locations`. El selector Delivery utiliza sus atributos públicos editables: «Enviar a domicilio» y «Retiro», icono y coste dinámicos. No se copia ni modifica React de WooCommerce.

Elegir Retiro oculta la dirección de entrega mediante WooCommerce y mantiene los datos de facturación que éste requiera. Total de envío cero, sin plazos prometidos. La ubicación es **TRAMA — punto DEMO**, `Dirección DEMO — reemplazar`, `Ciudad DEMO`, CP `0000`, AR/C: datos deliberadamente ficticios y reemplazables desde el fixture o WooCommerce, sin una dirección comercial real.

El retiro moderno es global por diseño de WooCommerce: incluso ante una dirección sin cobertura de entrega puede elegirse retiro. Esto no significa que haya envío a ese destino. [Documentación oficial de Local Pickup para Blocks](https://woocommerce.com/document/woocommerce-store-editing/customizing-cart-and-checkout/checkout-block/woocommerce-blocks-local-pickup/).

Al elegir **Enviar a domicilio** con dirección en Chile, el mensaje localizado nativo es «No hay opciones de envío disponibles para esta dirección. Verificá que sea correcta o probá con otra dirección.». No se añadió una traducción ni una región accesible paralela: el catálogo es_AR y los notices de WooCommerce ya resuelven este estado.

Matiz del carrito Core: si el comprador ya eligió retiro, puede conservarlo al navegar entre carrito y checkout, incluso para un destino extranjero. El resumen lo identifica como **Retiro (TRAMA — punto DEMO)**, a coste cero; no lo presenta como entrega internacional. Con **Enviar a domicilio** elegido y el recálculo terminado para Chile, no queda una tarifa seleccionada y el carrito muestra **«El envío se calculará al finalizar la compra»**. La matriz comprueba explícitamente el modo de entrega, su mensaje sin cobertura y el destino extranjero en Store API. No se añadió un filtro para alterar la selección nativa.

## Datos físicos y variaciones

Se migran deliberadamente las unidades iniciales sin uso (`lbs`/`in`) a `kg`/`cm`, con protección previa contra productos que ya tengan medidas manuales. Las dimensiones describen cada artículo doblado/preparado para envío; no sustituyen una estrategia de cajas para pedidos múltiples. Un carrier futuro deberá definir packaging, peso volumétrico y límites.

Los 10 padres variables conservan los datos físicos comunes. Las 72 variaciones guardan campos vacíos: `WC_Product_Variation` hereda peso/largo/ancho/alto del padre en contexto `view`; `edit` permite comprobar que no hay duplicación persistida. Un override futuro es posible por variación mediante WooCommerce, y los guards lo detectarán como edición manual. No se presupone que todos los carriers empaqueten igual.

Fuente exacta: `demo/shipping/h6-2-shipping.json`; verificación persistida en el reporte privado `verify.json`. Los seis simples también tienen datos propios:

| Producto | Peso kg | Largo × ancho × alto cm |
| --- | ---: | --- |
| Remera Base | 0,30 | 30 × 24 × 4 |
| Remera Trazo | 0,25 | 28 × 23 × 3 |
| Remera Aire | 0,20 | 28 × 22 × 3 |
| Remera Horizonte | 0,35 | 32 × 25 × 4 |
| Buzo Pulso | 0,70 | 35 × 28 × 9 |
| Buzo Refugio | 0,90 | 38 × 30 × 12 |
| Buzo Textura | 0,75 | 36 × 28 × 10 |
| Pantalón Cauce | 0,65 | 36 × 28 × 6 |
| Pantalón Línea | 0,70 | 38 × 28 × 7 |
| Pantalón Pausa | 0,60 | 34 × 27 × 7 |
| Bolsa Diario | 0,28 | 30 × 25 × 3 |
| Gorra Sombra | 0,18 | 25 × 22 × 14 |
| Gorro Punto | 0,12 | 22 × 18 × 4 |
| Medias Canal | 0,08 | 20 × 12 × 3 |
| Pañuelo Pliegue | 0,06 | 20 × 16 × 2 |
| Bolso Tránsito | 0,35 | 26 × 20 × 9 |

## Fixtures y protección

Backup preventivo íntegro: `.local/local/backups/h6-2-before-shipping-logistics`, base + WordPress + manifiesto SHA-256, fuera de Git. Flujo sobre la demo aprobada:

```powershell
.\scripts\h6-2.ps1 backup
.\scripts\h6-2.ps1 sync
.\scripts\h6-2.ps1 inspect
.\scripts\h6-2.ps1 seed
.\scripts\h6-2.ps1 verify
# Repetir seed debe conservar IDs, métodos, productos y configuración.
.\scripts\h6-2.ps1 seed
.\tests\verify-h6-2.ps1
# Prueba adicional que muta/restaura campos propios y demuestra los guards:
.\tests\verify-h6-2.ps1 -IncludeGuard
```

El fixture es una evolución protegida de H5/H6.1. Revisa huellas previas completas, source, medidas, relaciones cross-sell, páginas y configuración; guarda snapshots antes/después y actualiza únicamente los baselines de los campos migrados explícitamente. Precios, stock, medios, Home, Lookbook, pagos y datos de compradores no se reseedean. Las relaciones cross-sell siguen siendo las de H6.1, reproducibles desde su fixture.

Un cambio manual importante o una fuente modificada bloquea la importación y requiere una migración revisada. No se adopta automáticamente una edición como nuevo baseline. La protección de unidades revisa todos los productos/variaciones persistidos, incluso programados y en papelera; un tipo desconocido también bloquea la migración. No ejecutar seeds antiguos para revertir este hito; leer inspect/verify y preparar una migración explícita si se cambia la configuración comercial.

La primera migración verifica el resultado en un proceso WP-CLI nuevo después del commit de la transacción. Así se comprueba la herencia real sin forzar el `parent_data` que WooCommerce conserva en caché dentro de un request. Si una verificación posterior falla, se conservan snapshots/backup y se informa que la migración ya fue confirmada; no se anuncia un rollback que no ocurrió.

## UX y accesibilidad

Se conserva el layout H6.1. Métodos en filas nativas compactas, tipografía Manrope, superficies crudo/oliva u oscuras del sistema, radio visible, coste legible, borde de selección, foco de teclado y labels nativos. El selector domicilio/retiro utiliza los iconos oficiales y precios dinámicos. La tarjeta de dirección válida conserva «Editar» como una acción completa de al menos 44px; en móvil puede pasar a su propia fila, sin partir el texto letra por letra.

Carrito muestra opciones y costes cuando WooCommerce conoce el destino. El retiro moderno se elige en checkout; una selección hecha allí se refleja al volver al carrito cuando el core la expone. No se agrega una segunda UI de retiro ni se cambia la arquitectura de Mini-Cart/cross-sells.

La ficha ya comunica bajo «Envíos y cambios» que costes/opciones aparecen al ingresar la dirección en checkout. Se conserva esa información breve, sin cotizador, CP adicional, promesas de entrega ni importes dentro del template.

No se incorporan regiones aria-live paralelas. WooCommerce anuncia cambios/errores y administra estados ocupados. Las pruebas verifican labels, teclado, foco, coste, geometría y mensajes en español; no equivalen a una certificación con lectores de pantalla/dispositivos físicos.

## QA y evidencia

Evidencia privada en `.local/local/h6-2/`; no se versionan capturas, tokens, dumps, secretos ni datos runtime.

| Prueba | Resultado |
| --- | --- |
| Fixture físico/shipping | 94 comprobaciones aprobadas; 16 padres y 72 variaciones |
| Seed repetido | Mismos IDs, métodos, medidas, cross-sells y hash de configuración; sin duplicados |
| QA funcional PHP | 21 comprobaciones aprobadas, 15 escenarios, carrito aislado |
| Guard manual | 4 comprobaciones aprobadas: tarifa, producto, variación y baseline restaurado en finally |
| Guard catálogo H5 | Aprobado; 10 variables, 6 simples, 72 variaciones, stock/precios/medios preservados |
| H6.1 | 27 comprobaciones de datos/arquitectura aprobadas |
| H5.1 | 9 comprobaciones de contenido/Lookbook aprobadas |
| Smoke WooCommerce/HPOS/Mercado Pago | 14 comprobaciones aprobadas; mismos plugins y estado gateway previo |

Los límites aritméticos se prueban exactamente a **$99.999,99 / $100.000 / $100.000,01**, usando clones de artículos del carrito que no guardan precios en los productos. La prueba de navegador usa productos reales: $99.000, $100.000 y $108.000. Cupón temporal del 10% sobre $100.000: descuento $10.000, envío estándar $4.500, total $94.500; quitarlo restablece Free Shipping. También se verifica que un subtotal que sigue sobre el mínimo después del cupón conserva gratuidad.

La matriz funcional contempla selección Flat/Pickup y diferencia exacta de total, cantidad/subtotal y selección obsoleta, producto simple, variable heredado, varios productos ($102.000), provincia/CP, destino extranjero sin delivery, vuelta a Argentina y pickup sin dirección de entrega. En navegador se verifican los cambios de dirección/country/CP por la UI nativa y las respuestas reales de Store API.

El navegador registra los POST nativos con `__experimental_calc_totals=true` al alternar entrega/retiro: son recálculo/autoguardado Core. Se distinguen del POST final de Place Order, que no se ejecuta. En esta versión los recálculos observados devolvieron `order_id=0`, sin pago ni pedido persistido. El QA usa email `.test` propio y sólo limpia sus recursos verificados.

El script de navegador se ejecuta por separado, con Playwright bundled y sesiones nuevas; requiere el cupón temporal preparado:

```powershell
$env:NODE_PATH = 'C:\Users\ramiro\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
.\scripts\dev.ps1 wp -WpArgs @('eval-file', '/opt/local-tools/h6-2-qa.php', 'prepare', '--quiet')
try { node .\tests\verify-h6-2-browser.cjs }
finally { .\scripts\dev.ps1 wp -WpArgs @('eval-file', '/opt/local-tools/h6-2-qa.php', 'cleanup', '--quiet') }
# Sólo una vez cerrados todos los contextos de QA:
.\scripts\dev.ps1 wp -WpArgs @('eval-file', '/opt/local-tools/h6-2-qa.php', 'cleanup-sessions', '--quiet')
```

Resultado final de navegador: **261 casos únicos aprobados** según su última observación: 240 combinaciones responsive y 21 flujos funcionales/regresiones. Chromium recorre 320, 375, 390, 430, 768, 1024, 1366, 1440, 1920, 2560 y 3440 (ultrawide); Firefox/WebKit agregan 390 y 1440. Cada combinación comprueba carrito + checkout, light/dark y Flat Rate/Free Shipping/Pickup/sin cobertura. Las revalidaciones dirigidas no inflan el número de casos únicos.

Sin overflow horizontal, radios cortados, costes fuera del viewport ni errores de assets locales. Selección, labels, foco visible y teclado aprobados. «Editar» conserva 44×44px y texto entero. Se revisaron visualmente capturas mobile y desktop de los cuatro estados, incluyendo el mensaje sin cobertura en español, resúmenes con importes renderizados, retiro DEMO y superficies oscuras. No se rediseñó H6.1.

Las capturas esperan el importe real, además de los flags de cálculo: el skeleton nativo de WooCommerce mantiene brevemente su placeholder por diseño. Se conservaron los reportes iniciales, sus fallos del harness y sus revalidaciones. El consolidado privado `browser/final-qa.json` selecciona explícitamente la última observación válida por caso. El estado sin cobertura exige **Enviar a domicilio** seleccionado y el notice visible; no se certifica accidentalmente una captura de Retiro heredado.

No quedan errores JS críticos nuevos ni PHP frontend nuevos: el debug log de WordPress mantuvo sus cuatro líneas previas. Firefox registró una carga transitoria fallida del script Melidata del Mercado Pago preexistente; la revalidación completa terminó sin pageerrors. Se conservan aparte los abortos de telemetría Mercado Pago al navegar (`NS_BINDING_ABORTED`), sin silenciarlos ni presentarlos como cero requests fallidos. Los assets/gateway de Mercado Pago no se modificaron; no se repitieron pagos H2.

Regresión dirigida aprobada: carrito, quantity, Mini-Cart y restauración de foco, cupón, cross-sells, Exit Intent y exclusión de checkout, volver al carrito conservando sesión, catálogo, variante disponible, producto agotado y Lookbook con teclado. Los guards H5/H5.1/H6.1 y el smoke WooCommerce/HPOS/Mercado Pago complementan esa revisión. No se afirma certificación exhaustiva de lectores de pantalla ni dispositivos físicos no ensayados.

Limpieza final: cupón temporal eliminado, ningún borrador de pedido QA persistido y 13 sesiones guest de identidad QA exacta eliminadas mediante la API WooCommerce. El helper preserva usuarios registrados, identidades ajenas y sesiones asociadas a pedidos/pagos. Los contextos de navegador quedaron cerrados. Backups, reportes y capturas siguen privados y fuera de Git.

## URLs de revisión

| Vista | URL |
| --- | --- |
| Home/Lookbook | http://localhost:8080/ |
| Tienda | http://localhost:8080/?post_type=product |
| Producto variable | http://localhost:8080/?product=remera-base |
| Producto simple | http://localhost:8080/?product=bolsa-diario |
| Carrito | http://localhost:8080/?page_id=8 |
| Checkout | http://localhost:8080/?page_id=9 |
| Zonas (admin) | http://localhost:8080/wp-admin/admin.php?page=wc-settings&tab=shipping |
| Retiro (admin) | http://localhost:8080/wp-admin/admin.php?page=wc-settings&tab=shipping&section=pickup_location |

Para revisar: agregar Bolsa Diario ($19.000), probar entrega Argentina y Retiro; para alcanzar exactamente $100.000 combinar cuatro Bolsa Diario y una Gorra Sombra. Los artículos agregados con anterioridad por el usuario no se limpian ni modifican desde el QA; las pruebas utilizan sesiones propias.

## Pendientes y límites

[Informe de carriers y recomendación](h6-shipping-carriers.md). Ningún carrier instalado. Las credenciales, contrato, costes reales, packaging combinado y prueba de pedidos con carrier corresponden a cada cliente. Andreani/Correo/OCA no son dependencias de Commerce Base.

H7 conserva Mercado Pago final, transferencia/BACS, emails/SMTP, estados operativos y reembolsos. H8 conserva Commerce Core, arrepentimiento, legales, fiscales y ARCA. No se avanzó en esos hitos. La siguiente acción es revisión manual del usuario de H6.2.
