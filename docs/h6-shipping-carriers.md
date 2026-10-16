# H6.2 — Transportistas para WooCommerce en Argentina

Fecha de evaluación: **16 de octubre de 2026**, America/Buenos_Aires. Investigación documental y lectura estática de distribuciones públicas; **ningún transportista fue instalado, activado ni conectado**. No se solicitaron credenciales ni se generaron envíos.

## Decisión para Commerce Base

Mantener WooCommerce Core: Flat Rate, Free Shipping y Local Pickup de Blocks. El theme presenta métodos genéricos y estados; la cuenta, tarifas, sucursales, etiquetas y operación pertenecen al plugin/configuración del futuro cliente. No incluir SDK, nombres de transportistas, reglas tarifarias ni clases CSS de carrier en la base.

Para un pequeño comercio argentino con Checkout Blocks y HPOS, **Andreani oficial es el primer candidato para una prueba de integración** por la evidencia de integración nativa y mantenimiento. Es una recomendación técnica derivada de las fuentes, no una certificación de producción ni una comparación de precios/cobertura. Su disparador de alta de envíos requiere atención. Si el cliente ya opera con OCA, CRPlugins merece una prueba equivalente: documenta Blocks, HPOS y operación completa, con suscripción y servicio externo. Correo Argentino oficial no se recomienda hoy como integración predeterminada de este stack: no se pudo confirmar un fix vigente para Blocks.

## Criterio y límites de evidencia

- **Documentado:** afirmación del mantenedor, documentación, changelog o metadatos oficiales. Una declaración de compatibilidad no demuestra todo el flujo en nuestra versión.
- **Inspección estática:** lectura del código publicado de la versión indicada, sin ejecutar el plugin. Permite corroborar declaraciones y disparadores; no sustituye una prueba con cuenta real.
- **Reporte de usuario:** testimonio primario en soporte/reviews. Se conserva su versión/entorno cuando los informa; no se generaliza a todas las instalaciones.
- **NV:** no verificado en las fuentes consultadas. No significa que la función no exista.

Se consultaron las fichas, soporte y repositorios de WordPress.org, sitios de los carriers y documentación de cada proveedor. Las fechas exactas proceden de `last_updated` de la API pública WordPress.org; no se convirtieron los textos relativos de buscadores en fechas. Los importes son el precio publicado del software/servicio en esta evaluación, **no tarifas de transporte**. Las versiones probadas declaradas pueden quedar detrás de WordPress 7.1.3 / WooCommerce 11.2.1 de la demo.

## Comparación de las opciones principales

| Aspecto | Correo Argentino oficial | Andreani oficial | OCA mediante CRPlugins |
| --- | --- | --- | --- |
| Solución / mantenedor | [Correo Argentino for WooCommerce][ca-plugin], `gdicorreo`, Correo Argentino | [Andreani WooCommerce][an-plugin], `integracionandreani`, Andreani | [OCA para WooCommerce][cr-plugin], CRPlugins |
| Oficial vs tercero | Publicado por el carrier | Publicado como plugin oficial del carrier | Tercero; no es OCA |
| Versión evaluada | 3.0.8 | 1.8.5 | 4.1.6 |
| Actualización exacta, GMT | [2026-09-02 15:09][ca-api] | [2026-10-16 14:17][an-api] | [2026-06-29 19:11][cr-api] |
| WordPress mínimo / probado | 6.7 / 7.1.3 en API | 5.8 / 6.9.10 en API | 4.8 / 7.0.7 en API |
| WooCommerce mínimo / probado | [8.9 / 9.0][ca-code] | [7.1 / 10.7][an-code] | [4.2 / 10.9.1][cr-code] |
| PHP mínimo declarado | 7.4 | 7.4 | 7.1 |
| HPOS | `custom_order_tables=true` en código; 3.0.8 cambia manejo de pedidos | `custom_order_tables=true` y `orders_cache=true` | `custom_order_tables=true`; changelog lo incorpora en 2.6.0 |
| Checkout Blocks | **NV; reporte de incompatibilidad y sin declaración en 3.0.8** | `cart_checkout_blocks=true`; soporte nativo desde 1.6.4 | `cart_checkout_blocks=true`; soporte desde 3.0.0, fix JS en 3.2.8 |
| Cuenta / credenciales | MiCorreo: usuario y contraseña. PAQ.AR 2.0: Agreement + Apikey otorgados comercialmente | Credential ID PyME/Corporativo; detecta tipo de cliente | Licencia API Key CRPlugins + usuario/contraseña OCA, cuenta, CUIT y operativas |
| Coste software / servicio | Plugin descargable sin licencia paga publicada; transporte según cuenta | Plugin descargable sin licencia paga publicada; transporte y pago en Andreani según cuenta | [ARS 9.000 por tienda / 31 días][cr-product], renovación automática; transporte aparte |
| Dependencia externa | API MiCorreo / PAQ.AR | APIs y backend WooCommerce de Andreani | OCA **y** servicio externo CRPlugins |
| Cotización real | MiCorreo en tiempo real; no extrapolar idéntico comportamiento a PAQ.AR 2.0 | Tiempo real según contratos, productos y destino | Tiempo real; admite precio estático/modificador |
| Domicilio | Clásico / Exprés | Paquetería / Bigger según producto y contrato | Operativas puerta o sucursal de origen a domicilio |
| Sucursal / punto | Clásico / Exprés con selección de sucursal; Blocks pendiente | Selector nativo en Blocks; Bigger sólo domicilio | Selector de sucursal; operativas puerta/sucursal; e-lockers configurables |
| Generación de envío | MiCorreo: importar órdenes. PAQ.AR: preimposición | Alta automática + gestión/reintentos | Manual, masiva o automática por estado |
| Etiquetas | PAQ.AR: rótulo PDF. MiCorreo: changelog 3.0.8 menciona rótulos; confirmar flujo de pago/impresión vigente | PDF A4/térmica Zebra y descarga masiva para PyME, Middle Market y Corporativo | PDF A4, PDF 10×15 y ZPL, también masivas |
| Tracking | Gestión/seguimiento; PAQ.AR número con enlace al carrier | Número, estado y línea de tiempo; sincronización automática | Rastreo y correo configurable al comprador |
| Cancelación logística | PAQ.AR documentada; MiCorreo NV como operación equivalente dentro del plugin | NV; no confundir cerrar diálogo o volver a pendiente con anular envío en carrier | Documentada: cancela envío OCA sin cambiar estado WooCommerce ni avisar al comprador |
| Envío gratis | Umbral; opciones separadas por servicio en 3.0.8 | Umbral por modalidad | Umbral por método y cupón de envío gratis |
| Peso / dimensiones | Manual recomienda kg/cm y advierte límites; completar todos los productos | Completar peso/medidas; paquetes guardados, apilado y multibulto | Obligatorios; admite herencia de variables y bultos configurables |

Fuentes técnicas de la tabla: [cabecera Correo 3.0.8][ca-code], [cabecera Andreani 1.8.5][an-code], [cabecera CRPlugins 4.1.6][cr-code], [manual Correo][ca-manual], [readme Andreani 1.8.5][an-readme], [configuración CRPlugins][cr-config], [operación de órdenes CRPlugins][cr-orders] y [FAQ CRPlugins][cr-faq]. Las filas con NV no se completaron mediante supuestos comerciales.

## Correo Argentino: MiCorreo, PAQ.AR 2.0 y Blocks

Son dos servicios de acceso distintos. La [ficha oficial][ca-plugin] diferencia login MiCorreo de Agreement/Apikey PAQ.AR 2.0 y publica domicilio/sucursal, Clásico/Exprés, ajustes y gratuidad. El [manual alojado por Correo][ca-manual] todavía corresponde a **3.0.3 rc-241028-4**: sus requisitos ideales WP 6.5.2 / WC 8.7 / PHP 8.2 no reemplazan las cabeceras mínimas actuales de 3.0.8. Explica importación a MiCorreo, preimposición PAQ.AR, rótulo, tracking y cancelación PAQ.AR. La diferencia de antigüedad obliga a confirmar el circuito operativo actual antes de conectar una cuenta.

### Evidencia específica de Blocks

| Evidencia | Qué permite concluir |
| --- | --- |
| [Review “Incompatible con Bloques y React”, 19/06/2026][ca-blocks-report] | El usuario reporta error al cargar CP y afectación de otros métodos; afirma que volvió a 3.0.3. **No identifica el número exacto de la versión nueva afectada.** Su explicación de React/JSON es hipótesis del usuario, no diagnóstico oficial. |
| [Readme 3.0.8][ca-readme], posterior al reporte | Corrige recarga infinita cuando sucursal queda seleccionada primero, checkout/importación y manejo HPOS; **no anuncia soporte nativo Blocks ni dice resolver el reporte de React/JSON**. No se puede presentar ese fix de loop como prueba del fix de Blocks. |
| [Código principal 3.0.8][ca-code], inspección | Declara HPOS, pero no `cart_checkout_blocks`. Esto por sí solo no demuestra incompatibilidad universal; tampoco permite afirmar soporte. |
| Hilo de la review al consultar | No se encontró respuesta técnica oficial ni confirmación pública de resolución. El número de participantes mostrado por el foro no equivale a una respuesta visible con fix. |

**Estado actual para Commerce Base: compatibilidad Blocks no confirmada y riesgo relevante abierto.** No afirmar que la review prueba un fallo reproducido en 3.0.8; tampoco afirmar que 3.0.8 lo arregló. No degradar el checkout aprobado a shortcode para adoptar este plugin. Una futura evaluación debe comprobar domicilio y selector de sucursal en Blocks, CP/dirección, convivencia con Core y pedido HPOS en staging con la versión y credenciales del cliente.

### Mantenimiento e incidencias posteriores

La publicación 3.0.8 del 02/09/2026 incluye correcciones de direcciones/rótulos, ajustes independientes por servicio, backup/restauración de opciones y HPOS. Hay actividad; los límites `WC tested up to: 9.0` siguen muy por debajo del stack de la demo. [Readme versionado][ca-readme] y [cabecera][ca-code].

Un [reporte del 19/09/2026][ca-api-report] identifica **3.0.8**, MiCorreo, WP 7.1, WC 11.0.1, PHP 8.4 y **shortcode**: informa cortes cURL 52, tarifas que desaparecen y un método de error seleccionable a coste cero, entre otros problemas. El 05/10/2026 Correo respondió derivando consultas al ejecutivo comercial, sin publicar diagnóstico/parche en ese hilo. Es evidencia de usuario sobre disponibilidad/errores; **no es evidencia adicional de Blocks**.

Otro [reporte de 3.0.8][ca-address-report] describe campos de dirección desplegados indebidamente al seleccionar otros métodos en checkout **clásico**. Permanece sin resolución visible. Confirma la necesidad de probar convivencia; no se reutilizó su workaround en Commerce Base.

## Andreani: integración oficial vigente

El [readme versionado 1.8.5][an-readme] documenta domicilio/sucursal, DNI y selector nativos en Blocks vía Store API desde 1.6.4, HPOS, Credential ID, etiquetas y seguimiento. Las rutas de credenciales son [PyME][an-pyme] y [Corporativo][an-corp]. No confundir este plugin gratuito del repositorio con desarrollos comerciales de terceros también llamados “Andreani”.

La lectura de la [cabecera 1.8.5][an-code] corroboró las declaraciones Blocks/HPOS. El changelog registra fixes de unidades de medida (1.6.2), duplicación de altas (1.6.8), decimales/valor declarado (1.6.12), herencia de packaging de variaciones y filtros HPOS (1.8.0), cotización de variación (1.8.1), paquetes (1.8.3–1.8.4) y cantidad de etiquetas (1.8.5). Esto ofrece evidencia de mantenimiento concreto, pero una versión recién publicada merece validación antes de producción. [Historial publicado][an-plugin].

### Productos, cajas y medidas

Paquetería admite hasta 50 kg, lado máximo 165 cm y suma de lados hasta 300 cm; Bigger y productos configurados en varias cajas viajan sólo a domicilio, con etiqueta por caja. El panel ofrece paquetes guardados, unidades por caja, apilado y simulador. La caja sugerida es orientativa: no convierte medidas ficticias de catálogo en embalaje real. [Readme 1.8.5][an-readme].

La documentación recomienda datos completos y alerta productos sin medidas. Hay un matiz: el changelog 1.6.11 y el [constructor de paquetes 1.8.5][an-packages] conservan un piso de **1 gramo**, y existen mínimos permisivos para dimensiones en partes del empaquetador. No debe interpretarse como permiso para vender sin medir: no se verificó que todos los caminos de cotización acepten faltantes y esos defaults pueden producir importes/declaraciones incorrectos. H6.2 prepara peso y tres dimensiones en todos los productos; el cliente debe reemplazarlos por los del paquete real.

### Flujo de pedidos e incidencias

- **Documentado/inspección:** el [callback de alta 1.8.5][an-orders] usa `woocommerce_thankyou`, verifica alta existente y toma un lock. En ese callback no hay verificación de `is_paid()` ni selección de estados. Antes de adoptar, comprobar el flujo exacto de la pasarela, pagos pendientes, pedidos por encargo/preventa y reintentos; generar una orden logística no debe confundirse con despacharla.
- **Reportado por usuarios:** [21/08/2026][an-status-report] y [02/10/2026][an-preorder-report] solicitan control por estado/generación manual porque ven altas antes de estar listos para despachar. No informan versión exacta y no muestran respuesta/resolución visible. La observación del callback aporta evidencia independiente limitada, no una reproducción con cuenta real.
- **Reporte y fix relacionados:** el [hilo de WC 10.9.4+][an-tracking-report] informa warnings en sincronización con almacenamiento clásico. El changelog 1.6.1 declara una corrección de consultas para clásico/HPOS; 1.8.0 corrige filtros HPOS. El hilo sigue sin resolución visible: hay fix documentado de esa clase de problema, pero no confirmación del reportante para la versión vigente.

La valoración pública tiene una muestra pequeña y no prueba calidad operativa. La recomendación se basa en API/Blocks declarados, código y cambios concretos; **no en estrellas**. Cancelación del envío en Andreani sigue NV en esta investigación.

## OCA: servicio oficial, terceros y SaaS

OCA publica [ecommerce con logo WooCommerce][oca-ecommerce], [ePak][oca-epak] y [portal Developers][oca-developers]. Esto acredita la oferta de integración/API, pero **no permitió identificar una distribución actual, versionada y mantenida por OCA de un plugin WooCommerce propio**. No se afirma que no exista: queda pendiente obtener del carrier el enlace/versionado/mantenedor concreto.

Una API oficial de OCA no convierte automáticamente en oficial a cada plugin que la utiliza. Tampoco una leyenda de “developer oficial” en una agencia sustituye confirmación del carrier.

| Solución | Naturaleza, versión y mantenimiento | Compatibilidad y funciones verificadas | Cuenta / coste / dependencia |
| --- | --- | --- | --- |
| [CRPlugins OCA][cr-plugin] | Tercero. 4.1.6, 29/06/2026; changelog detallado. Principal alternativa OCA de esta comparación | HPOS y Blocks declarados en código/documentación. Domicilio/sucursal, tarifas, etiquetas, tracking, cancelación, gratis, automatización por estado | OCA + licencia CRPlugins; ARS 9.000/31 días; servicio externo CRPlugins |
| [Wanderlust OCA gratuito][wl-free] | Tercero. 1.1.995, [09/05/2025 13:39 GMT][wl-api]; WP ≥5.0, probado 6.8.11; PHP mínimo NV | Tarifas y selector sucursal. HPOS anunciado desde 1.1.994. Blocks NV. Etiquetas/tracking se reservan a Premium; cancelación NV | Cuenta OCA, CUIT/operativas y productos medidos; software gratuito, transporte aparte |
| [Wanderlust OCA Premium][wl-paid] | Tercero comercial, separado del gratuito. Versión exacta NV. Fechas inconsistentes: producto 05/08/2025; [categoría][wl-category] 20/09/2026 | Cotización, sucursal/Smartlocker, etiquetas y tracking publicados. Requisitos WP/WC/PHP, HPOS y Blocks de **Premium** NV; no trasladar los del gratuito. Cancelación/gratis NV | Precio mostrado $65.000, pago único, 1 sitio, 30 días de soporte. Confirmar moneda/impuestos/licencia y build antes de comprar; API OCA |
| [WebWoo / Open24 OCA e-Pak][webwoo] | Agencia independiente; se presenta como “oficial”. **Vínculo oficial no corroborado en fuente OCA**. Versión/fecha/changelog NV | Declara Blocks, WP ≥5.8, WC ≥6.0 probado 8.x, PHP ≥7.4, domicilio/sucursal, tarifas, guías, tracking y gratis. HPOS/cancelación NV | USD 89, 1 producción, actualizaciones de por vida y soporte 12 meses; cuenta con webservice OCA. Falta distribución/código para corroborar |
| [Envíopack Argentina][ep-plugin] | SaaS multicarrier, tercero respecto de OCA. 1.0.19, [30/06/2026 18:53 GMT][ep-api]. WP ≥5.4 probado 6.6.10, PHP ≥7.0; WC mínimo NV | Tutorial documenta domicilio/sucursal, multibulto, estados, etiquetas y webhooks. HPOS/Blocks NV en fuentes consultadas; tracking del servicio, no se certificó todo el flujo Woo | Cuenta Envíopack + API Key/Secret; Google Maps en tutorial. [Servicio básico][ep-services] sin coste de plataforma con OCA/Urbano, transporte aparte; colecta/otros servicios a cotizar. Dependencia SaaS y carriers |

El aviso de WordPress.org para Wanderlust gratuito y Envíopack señala falta de prueba con tres versiones mayores recientes. Es una señal de validación pendiente, no prueba de que estén abandonados. En el [foro Wanderlust][wl-support] hay reportes de opciones ausentes y sucursal no persistida; no se extrapolan a Premium. CRPlugins mantiene [documentación operativa][cr-orders] y su [foro público][cr-support] tiene poca actividad: su soporte se canaliza también por el servicio privado, por lo que la ausencia de hilos recientes no demuestra ausencia de incidencias.

### OCA mediante CRPlugins: puntos operativos concretos

La [configuración][cr-config] exige cuenta, CUIT, operativas, remitente y peso/largo/ancho/alto. Documenta cuatro operativas entre domicilio/sucursal, herencia del padre para variables y sobreescritura por variación. El [FAQ][cr-faq] explica que normalmente usa un bulto por producto, o un paquete con tamaño predeterminado; **no divide automáticamente por tamaño/peso**. Ese modelo debe ajustarse al embalaje real de una compra múltiple de TRAMA.

La [operación de órdenes][cr-orders] permite procesar, descargar PDF/ZPL y cancelar el envío sin cambiar el estado comercial. El sitio anuncia automatización por estado, evitando asumir equivalencia entre “pedido creado”, “pagado” y “listo para despachar”. El changelog 4.1.3 corrige procesamiento duplicado, 4.1.0 cambio de sucursal y 3.2.8 JS de Blocks. [Ficha/versionado][cr-plugin]. Son fixes declarados; no fueron reproducidos en la demo.

## Recomendación y adopción por cliente

1. **Commerce Base:** conservar exclusivamente Core y los pesos/dimensiones demo. La gratuidad y el retiro demostrativos siguen siendo configuración WooCommerce. No instalar ninguno de los plugins investigados.
2. **Pequeño comercio sin operador elegido:** evaluar primero Andreani oficial vigente con su Credential ID y contratos. Es el candidato técnicamente mejor respaldado para Blocks/HPOS de los plugins oficiales de carrier investigados. Validar el alta temprana de envíos y la falta de cancelación documentada antes de decidir.
3. **Cliente con contrato OCA:** probar CRPlugins 4.1.6 o versión vigente, si acepta suscripción/dependencia externa; exigir cotización y packaging correctos con varias prendas. Pedir versión, changelog y demostración Blocks+HPOS a otros proveedores antes de contratar.
4. **Cliente que exige MiCorreo/PAQ.AR:** solicitar evidencia de resolución Blocks a Correo y probar la versión entregada. Mantener la arquitectura aprobada mientras no exista evidencia suficiente; considerar un conector distinto sólo tras verificar domicilio **y** sucursal, operación y costes.

La futura prueba deberá recorrer dirección/CP, ambas modalidades, cupón/gratuidad, variables con herencia, varias cantidades, timeout/API sin tarifas, pedido HPOS, pago pendiente/aprobado, alta repetida, rótulo, tracking y anulación logística según contrato. Se ejecutará en staging del cliente; no forma parte de una instalación en H6.2. No se adelantaron pagos ni estados operativos finales de H7.

## Fuentes primarias

Todos los enlaces se consultaron el 16/10/2026. Los repositorios se apuntan a tags exactos para que la evidencia pueda revisarse aunque avance la versión estable.

[ca-plugin]: https://wordpress.org/plugins/correo-argentino-for-woocommerce/
[ca-api]: https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=correo-argentino-for-woocommerce
[ca-code]: https://plugins.svn.wordpress.org/correo-argentino-for-woocommerce/tags/3.0.8/correo-argentino-for-woocommerce.php
[ca-readme]: https://plugins.svn.wordpress.org/correo-argentino-for-woocommerce/tags/3.0.8/readme.txt
[ca-manual]: https://www.correoargentino.com.ar/MiCorreo/public/files/manual-usuario-plugin-para-woocommerce-v5.pdf
[ca-blocks-report]: https://wordpress.org/support/topic/incompatible-con-bloques-y-react/
[ca-api-report]: https://wordpress.org/support/topic/api-de-micorreo-corta-conexiones-curl-52-checkout-sin-envio-a-domicilio/
[ca-address-report]: https://wordpress.org/support/topic/checkout-el-bloque-de-envio-se-muestra-siempre-aunque-no-se-tilde-la-casilla/
[an-plugin]: https://wordpress.org/plugins/andreani-shipping/
[an-api]: https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=andreani-shipping
[an-code]: https://plugins.svn.wordpress.org/andreani-shipping/tags/1.8.5/andreani.php
[an-readme]: https://plugins.svn.wordpress.org/andreani-shipping/tags/1.8.5/readme.txt
[an-pyme]: https://pymes.andreani.com/integraciones/
[an-corp]: https://corporativo.andreani.com/woocommerce
[an-packages]: https://plugins.svn.wordpress.org/andreani-shipping/tags/1.8.5/includes/api/common/class-andreani-package-builder.php
[an-orders]: https://plugins.svn.wordpress.org/andreani-shipping/tags/1.8.5/includes/order/andreani-order.php
[an-status-report]: https://wordpress.org/support/topic/discriminar-por-estado-del-pedido/
[an-preorder-report]: https://wordpress.org/support/topic/sugerencia-de-mejora-3/
[an-tracking-report]: https://wordpress.org/support/topic/incompatibilidad-con-woocommerce-9-2-0-wc_order_data_store_cptquery-called-i/
[oca-ecommerce]: https://www.oca.com.ar/productos-ecommerce/index.html
[oca-epak]: https://www5.oca.com.ar/ocaepak/
[oca-developers]: https://developers.oca.com.ar/
[cr-plugin]: https://wordpress.org/plugins/oca-for-woocommerce/
[cr-api]: https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=oca-for-woocommerce
[cr-code]: https://plugins.svn.wordpress.org/oca-for-woocommerce/tags/4.1.6/woocommerce-oca.php
[cr-product]: https://crplugins.com.ar/oca-woocommerce
[cr-config]: https://documentacion.crplugins.com.ar/oca/configuracion/
[cr-orders]: https://documentacion.crplugins.com.ar/oca/ordenes/
[cr-faq]: https://documentacion.crplugins.com.ar/oca/preguntas-frecuentes/
[cr-support]: https://wordpress.org/support/plugin/oca-for-woocommerce/
[wl-free]: https://wordpress.org/plugins/wanderlust-oca-e-pak-shipping-rates/
[wl-api]: https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=wanderlust-oca-e-pak-shipping-rates
[wl-paid]: https://shop.wanderlust-webdesign.com/shop/woocommerce-oca-premium-shipping/
[wl-category]: https://shop.wanderlust-webdesign.com/product-category/plugins/
[wl-support]: https://wordpress.org/support/plugin/wanderlust-oca-e-pak-shipping-rates/
[webwoo]: https://webwoocommerce.com/plugins/oca-woocommerce/
[ep-plugin]: https://wordpress.org/plugins/enviopack-argentina/
[ep-api]: https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=enviopack-argentina
[ep-services]: https://ayuda.enviopack.com/es/articles/10581735-nuestros-servicios

Documentación adicional de Envíopack consultada: [integración WooCommerce](https://ayuda.enviopack.com/es/articles/10581826-integra-woocommerce-con-enviopack).
