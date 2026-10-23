# H10 — Guía práctica para una tienda nueva

Esta guía transforma una solicitud comercial en una instalación independiente de Commerce Base y Commerce Core. H10 valida el proceso en DEMO local: no incluye publicación, contratación, DNS, correo real ni cobros. El registro de pruebas y sus resultados está en [H10](h10-rebranding-portability.md); la lista de entrega está en [h10-delivery-checklist.md](h10-delivery-checklist.md).

## 1. Convertir la consulta en un alcance acordado

Pedir al titular una descripción de qué vende, a quién, en qué regiones y con qué operación diaria. Acordar categorías, cantidad inicial de productos y variantes, idiomas, moneda, métodos de pago, cobertura de envíos/retiro y responsables de catálogo, atención y pedidos. Identificar productos regulados o servicios que requieran un análisis adicional. Registrar qué está incluido, qué material falta, quién lo aporta y cómo se aprueba el trabajo. No prometer integraciones o tiempos operativos que todavía no se probaron.

Solicitar este paquete, con un responsable que pueda aprobarlo:

| Material | Información necesaria | Responsable de validación |
| --- | --- | --- |
| Marca | Nombre comercial, logo vectorial y versión para fondos claros/oscuros, favicon, colores, tipografías y sus licencias; ejemplos de tono y mensajes | Titular de la marca |
| Catálogo | Planilla por SKU: nombre, tipo simple/variable, categoría, descripción, precio/moneda/impuestos, oferta y vigencia, stock, peso/medidas de despacho, atributos, variantes y SKU/stock/precio de cada variante | Comercio y asesor contable cuando corresponda |
| Imágenes | Foto principal y detalles por producto/variante, imágenes editoriales, recortes permitidos, texto alternativo y comprobación de derechos de uso | Comercio |
| Operación | Zonas/códigos postales, métodos y costos, umbral de envío gratis, preparación, plazos, retiro, cambios, garantía, devoluciones y atención de contingencias | Comercio y logística |
| Identidad legal | Titular/razón social auténtica, CUIT, domicilio, contactos, horarios, responsables de privacidad y reclamos; Data Fiscal oficial si corresponde | Titular, asesor contable y jurídico |
| Contenido | Home, beneficios verificables, historia, preguntas frecuentes, páginas y navegación, redes/WhatsApp auténticos cuando se utilicen | Comercio |
| Comunicaciones | Remitente y respuesta, destinatarios internos, firma/logo/colores, textos del pedido y responsables de fallos | Comercio |
| Pagos | Medios contratados, titularidad, condiciones y circuito de conciliación; BACS con datos auténticos sólo en la etapa autorizada | Titular y responsable financiero |

No pedir claves fiscales, contraseñas personales por chat ni bases históricas como plantilla. Para productos variables no usar una lista universal de talles: cada rubro define Material, Acabado, Capacidad, Medidas u otros atributos reales. La moneda es un ajuste nativo de WooCommerce, no un texto que se reemplaza visualmente.

## 2. Accesos y condiciones de trabajo

Solicitar accesos nominados con los permisos mínimos a los recursos que efectivamente se usarán. El cliente conserva la titularidad de dominio, hosting, correo y cuenta de pagos. Pedir invitaciones o un mecanismo seguro de entrega/rotación de secretos, con revocación al terminar. Si hay un proveedor previo, acordar qué se migra, de quién son los datos y cómo se conserva un respaldo.

En H10 sólo se utilizan Docker y archivos privados locales. Para H11 habrá que verificar el plan real, acceso al panel/SSH si existe, dominio/DNS, certificados, transporte de correo, backups y restauración. No dar por incluidas prestaciones de hosting ni inventar planes o precios. La documentación de referencia de [preparación H9](h9-production-readiness.md) es un inventario de comprobaciones, no una acreditación del plan contratado.

## 3. Separar código, configuración y contenido

| Capa | Ubicación/mecanismo | Ejemplos |
| --- | --- | --- |
| Código compartido | `commerce-base/`, `commerce-core/` y plugins propios del repositorio | Layouts, accesibilidad, variaciones, navegación de cuenta, integridad de pedido, formularios legales y presentación de emails |
| Configuración del comercio | Opciones nativas de WordPress/WooCommerce, Site Editor y estilos; opciones documentadas de los módulos | Identidad, logo/favicon, estilos, menú, moneda, envíos, gateways, remitente y selección de páginas legales |
| Contenido del comercio | Páginas, productos, taxonomías, medios y bloques almacenados en su instalación | Home, categorías, fotos, variantes, lookbook y textos legales aprobados |
| Fixtures DEMO | `demo/` y scripts explícitos de aprovisionamiento | TRAMA y ORIGEN CASA, material ficticio y contenido marcado DEMO |
| Datos privados | `.local/<instancia>/`, volúmenes y secretos de esa instancia | Base, uploads, credenciales, pedidos, clientes, logs y Mailpit |

Una tienda nueva no requiere copiar ni renombrar el theme o los plugins. Mantener la misma base común. Una excepción que requiera código específico debe aislarse en un plugin de ese cliente o, si sólo afecta templates, en una extensión/child theme justificada. Registrar su compatibilidad; no crear una rama permanente por cliente como única estrategia de personalización.

## 4. Preparar una DEMO independiente

Ejecutar desde la raíz del repositorio. Verificar antes que los puertos estén libres y que la instancia elegida no sea otra tienda existente. El ejemplo crea una **tercera DEMO de validación de ORIGEN CASA**, con el mismo fixture ficticio; no genera por sí solo el catálogo de un cliente real.

```powershell
Get-NetTCPConnection -LocalPort 8083,8028 -State Listen -ErrorAction SilentlyContinue
./scripts/h10.ps1 -Action install -Instance h10-repeat -WordPressPort 8083 -MailpitPort 8028
./scripts/h10.ps1 -Action inspect -Instance h10-repeat
./scripts/h10.ps1 -Action verify -Instance h10-repeat
```

La instalación usa configuración, contraseñas, salts, volúmenes, base y correo propios. Consultar el script y el [informe H10](h10-rebranding-portability.md) para conocer las precondiciones y el alcance de cada acción. Si el nombre o puertos ya se usan, preservar esos recursos y elegir otro identificador/puertos. No importar la base de TRAMA ni copiar sus uploads, pedidos, cuentas, solicitudes o logs.

Para otra identidad, usar esta instalación limpia como patrón de procedimiento: preparar un fixture de contenido propio controlado, o cargar el contenido mediante las interfaces nativas. No editar el fixture original de TRAMA para todos los comercios. No convertir esta DEMO de hogar en producción conservando sus usuarios, promociones o datos bancarios ficticios.

## 5. Configurar marca y contenido

1. **Ajustes → Generales:** nombre y descripción del sitio, email administrativo del entorno, idioma y zona horaria. En DEV usar direcciones `.test`, captura local y visibilidad privada/noindex. En esta DEMO de loopback, verificar `woocommerce_coming_soon=no` para probar el catálogo como invitado: una respuesta HTTP 200 con “Se vienen cosas increíbles” no es una ficha de producto válida. Esto no habilita indexación ni autoriza publicación.
2. **Apariencia → Editor → Estilos:** seleccionar/ajustar la variación de estilo, paleta y tipografía. Commerce Base incluye un estilo ORIGEN CASA; revisar ambos modos. Logo y favicon pertenecen a los ajustes nativos de identidad, con medios de esa instalación. Las fuentes deben tener licencia y carga permitida.
3. **Páginas/Home:** construir una composición propia con bloques reutilizables, hero, categorías reales, destacados, beneficios comprobables, editorial y acceso visible a arrepentimiento. Configurar la portada desde los ajustes de lectura. Revisar jerarquía de títulos, recorte de fotos y alt.
4. **Navegación:** revisar el menú principal, enlaces del footer, páginas y redes. Usar URLs propias; no copiar IDs de otra base ni asumir que un slug de TRAMA existe. WhatsApp sólo cuando exista canal auténtico, atención y configuración del comercio.
5. **Lookbook:** seleccionar medios/productos de esta instalación y revisar cada hotspot con mouse, teclado y pantalla pequeña. Es opcional; quitar el bloque si no aporta al rubro. No dejar IDs de productos de otro catálogo.
6. **Exit Intent:** la opción por sitio `commerce_base_exit_intent` permite `enabled`, `title`, `text`, `minimumSeconds` y `cooldownHours`; el filtro de extensión permanece disponible. Desactivarlo impide cargar sus assets y el diálogo. No prometer cupones/descuentos que no estén creados. Verificar su exclusión de checkout, cuenta y trámite legal.
7. **Footer y SEO:** comprobar identidad/contactos, legales y datos estructurados de la instalación; completar descripción y medios sociales auténticos cuando correspondan. Los menús opcionales `footer-shop` y `footer-help` sustituyen las listas iniciales basadas en páginas publicadas; no cambiar el código para otro mapa de enlaces. DEMO sigue sin indexación pública.

Los filtros de catálogo toman atributos globales no vacíos de WooCommerce. La opción por sitio `commerce_base_color_swatches` permite un mapa slug→hexadecimal para los colores que realmente existan; cuando no hay Color, la tarjeta muestra opciones textuales del atributo de variación. Las guías optativas `commerce_base_size_guides` y `_cb_size_guide` permiten adaptar sus rótulos con `summary`/`table_label`; no se necesitan para productos de hogar. Estos son ajustes acotados mediante las APIs/opciones del sitio, sin un panel comercial paralelo.

## 6. Cargar el catálogo

Usar **Productos** o el [importador CSV nativo de WooCommerce](https://woocommerce.com/document/product-csv-importer-exporter/). Preparar UTF-8 contra su formato compatible, conservar un respaldo y ensayar la importación en la tienda de prueba. Mantener SKU estables para actualizaciones; revisar la opción de actualizar existentes y el mapeo antes de ejecutar. No identificar productos por el ID numérico de otra instalación. Revisar el alt en Medios: el importador CSV nativo no lo carga ni lo actualiza por sí solo.

Crear primero categorías y atributos globales cuando se quieran filtros comunes. Crear términos propios, asignar atributos al producto, marcar cuáles generan variaciones y definir cada combinación vendible con precio, stock e imagen. Las variaciones usan los atributos reales del producto; las guías de talles son optativas de indumentaria. Para un artículo de hogar sin talle, no crear S/M/L/XL para que funcione la UI.

Revisar producto simple, variable con atributos ajenos a indumentaria, oferta, agotado y relacionados. Comprobar precio/rango, selección disponible/agotada, cantidad, medios responsive y carrito. Un cambio de precio de esta tienda debe dejar intacta la otra. Los precios de los fixtures H10 son exclusivamente ARS ficticios.

## 7. Envíos, BACS y otros pagos

En **WooCommerce → Ajustes → General**, confirmar moneda, país/regiones admitidas e impuestos según decisión del comercio. En **Envío**, configurar zonas, cobertura y métodos, con tarifa y umbral reales. Revisar prioridades y la zona sin cobertura. El texto editorial debe coincidir con el cálculo de checkout. No copiar la tarifa de TRAMA como supuesto universal.

En **Pagos**, habilitar sólo los medios validados. En la DEMO se prueba BACS sin movimientos de dinero ni cuenta bancaria utilizable. Antes de una tienda real se necesitan titularidad y datos bancarios auténticos, instrucciones, conciliación y responsables. Un pedido BACS queda pendiente de verificación de acreditación; el comprobante del comprador no confirma el pago.

Mercado Pago permanece **completamente desactivado en ORIGEN CASA**. No cargar credenciales productivas, activar cobros ni modificar el plugin oficial. Los bloqueos de autenticidad/replay/temporalidad, validaciones independientes y recursos externos de H7/H9 siguen abiertos; una compra BACS correcta no los resuelve. Cualquier integración futura necesita validación separada.

## 8. Correos, cuenta y atención

Configurar **WooCommerce → Ajustes → Correos electrónicos**: nombre/remitente/respuesta, logo o identidad textual, colores, footer, asuntos y destinatarios internos. Si se mantiene una paleta de emails explícita, revisar la sincronización nativa con el theme: H10 guarda `woocommerce_email_auto_sync_with_theme=no` para que una actualización de estilos no reemplace los colores configurados. Commerce Communications presenta la información y conserva los disparadores, destinatarios, estado de pedido y transporte nativos. Los placeholders como `{site_title}` deben resolver a la tienda actual.

En local, toda salida debe llegar al Mailpit propio y todas las cuentas ficticias deben terminar en `.test`. Abrir recibo BACS, nueva cuenta/restablecimiento y constancia legal; revisar marca, productos, importes, método de pago y enlaces, incluso con imágenes bloqueadas y en pantalla móvil. Capturar evidencia sin contraseñas, enlaces de recuperación o datos privados. El transporte aceptado no garantiza entrega real en bandeja.

En **Cuentas y privacidad**, decidir compra como invitado, creación de cuenta, avisos y conservación. Probar con un usuario exclusivo de esa tienda: pedidos propios, direcciones y acceso a pedidos ajenos denegado. Un cliente de TRAMA no debe existir automáticamente en la instalación nueva.

El correo transaccional real y su dominio, SPF/DKIM/DMARC, autorizaciones, cuotas, entrega y alertas pertenecen a H11. No configurar SMTP real en H10.

## 9. Legales del cliente

Seguir [la guía de adaptación legal H8](h8-client-legal-setup.md). El titular y sus asesores aportan y aprueban identidad, contactos atendidos, privacidad, términos, envío, cambios/devoluciones, circuito de arrepentimiento, conservación y Data Fiscal aplicable. Esta guía no sustituye esa validación ni transforma páginas DEMO en textos definitivos.

Crear/revisar las cinco páginas y seleccionar las correspondientes en **Ajustes → Identidad y legales**; verificar su sincronización con privacidad y términos nativos y los enlaces de checkout. No copiar CUIT, QR, datos bancarios ni solicitudes de otra tienda. Mantener **BOTÓN DE ARREPENTIMIENTO** visible, con acceso de invitado y sin promociones. Probar recepción, código, correos, gestión autorizada y ausencia de divulgación de compras.

Las solicitudes y notas pertenecen a la base de esa instalación. Quien gestiona atención debe conocer sus estados y verificar la relación con un pedido antes de asociarlo; cerrar la solicitud no cancela ni reembolsa automáticamente la compra. Aprobar el procedimiento operativo y los responsables antes de publicar.

## 10. Revisar con el cliente y preparar el lanzamiento

Usar [la lista de entrega](h10-delivery-checklist.md) y registrar resultado/evidencia por área. Revisar Home, catálogo/categorías, producto simple y variable, agotado/oferta, carrito/mini-cart/cupón, direcciones, checkout BACS, recibo, cuenta, correo y circuito legal. Probar 390/768/1366/1920 px, claro/oscuro, teclado/foco y ausencia de overflow. Automatización y axe no sustituyen lectura humana ni certifican accesibilidad.

Mostrar la tienda con contenido aprobado y obtener conformidad sobre marca, precios, stock, plazos, atención y condiciones. Conservar una versión fechada de la aprobación y los pendientes. Para actualizar URLs, usar `wp search-replace` compatible con serializados, primero `--dry-run`, sobre una copia de trabajo propia; excluir `guid` cuando corresponda, revisar medios/enlaces/endpoints y conservar backup. No usar reemplazos globales sobre SQL ni afectar la base de otra tienda. Consultar la [documentación oficial de WP-CLI](https://developer.wordpress.org/cli/commands/search-replace/) antes de preparar una migración real.

Antes de publicar faltan los trabajos H11: compatibilidad del hosting contratado, dominio/HTTPS/proxy, correo transaccional, credenciales por entorno, backup/restauración offsite, caché/CDN y sus exclusiones, cron/colas, monitoreo, privacidad/fiscalidad reales y pagos autorizados. Resolver los bloqueos de [preparación H9](h9-production-readiness.md). La autorización de publicación se obtiene sobre una entrega concreta y verificada. H10 no inicia esos trabajos.

## Reejecución, recuperación y mantenimiento

`install` es el flujo completo de DEMO; `sync` distribuye código común; `seed` aplica el fixture explícito; `inspect` observa y `verify` comprueba sus invariantes. Un seed completado valida su contenido/configuración y no vuelve a escribir; si se editó contenido, stock, opciones o fuentes del fixture, preserva esos cambios y rechaza la reejecución: requiere una migración explícita. Por eso una compra que descuenta stock tampoco debe ser “reparada” restaurando el stock del fixture. La repetibilidad de este fixture no significa que cualquier importador o edición editorial sea idempotente. Antes de reaplicar un seed a una tienda editada, obtener backup y revisar propiedad/colisiones y qué contenido administra. Ante una colisión, preservar y resolverla; no usar un reset como reparación genérica.

Si falla el aprovisionamiento, conservar `.local/<instancia>`, secretos, logs y volúmenes; identificar el paso fallido, corregir la causa y retomar según el script. No se promete una transacción atómica: hooks de terceros pueden ejecutar DDL y dejar objetos persistidos pese a un rollback SQL. El [README del fixture](../demo/origen-casa/README.md) explica las fases y dos recuperaciones limitadas: `certify` sólo completa una importación ya persistida en fase `imported`; `recover-early` sólo limpia dos adjuntos propios con hashes verificados, sin entidad comercial y con configuración exacta. Ninguna sirve para adoptar silenciosamente un catálogo parcialmente importado. No regenerar contraseñas ni salts a ciegas y no borrar un proyecto existente. Un restore debe usar un backup de esa instancia, con objetivo/destino comprobados; verificar instalación, medios, usuarios, catálogo, pedidos, correo y aislación después. Las pruebas de fallo/recuperación H10 están documentadas en el informe, con sus límites.

| Responsabilidad del cliente | Responsabilidad del implementador |
| --- | --- |
| Titularidad, derechos de marca/fotos y datos comerciales auténticos | Instalación independiente, código común y configuración acordada |
| Aprobar catálogo, precios, stock, impuestos, logística, condiciones y textos | Carga/importación acordada, adaptación visual y revisión técnica |
| Contratar y autorizar proveedores/pagos; completar verificaciones de titularidad | Integrar únicamente métodos autorizados y comprobados |
| Atender pedidos, conciliación, reclamos, privacidad y trámites legales | Configurar herramientas, permisos y capacitación del circuito |
| Mantener datos y responsables operativos vigentes | Registrar QA, límites, accesos entregados y procedimiento de actualización |

Conviene acordar mantenimiento con responsables y frecuencia: actualización de WordPress/WooCommerce/plugins/theme sobre staging, revisión de compatibilidad y fallos; backups y restauraciones de muestra; monitoreo de sitio/checkout/cola/correo/logs; seguridad y accesos; revisión de rendimiento/caché; controles de pagos y conciliación; vigencia de contenidos, privacidad y atención legal. Definir cómo se aprueban cambios y se atienden incidentes, qué carga de contenido está incluida y qué tareas siguen siendo del comercio. No se establecen precios ni planes de hosting en esta guía.
