# D1.1 — Auditoría de la experiencia del vendedor

Fecha: **25/10/2026, Argentina**. Alcance: WordPress/WooCommerce nativos y los módulos aprobados de la plantilla, en laboratorios locales independientes con datos ficticios. No se autoriza publicación, cambios de Hostinger, un panel propio, roles nuevos ni D2. El informe se apoya en la evidencia efectiva del recorrido; una capacidad leída en código no se presenta como una operación probada por interfaz.

**Conclusión:** WooCommerce cubre las operaciones de un ecommerce pequeño y no se encontró una necesidad demostrada de construir otro panel. La entrega a un dueño sin experiencia todavía necesita preparación/capacitación y corregir la configuración de comunicación pendiente en ORIGEN. **SELLER BACKOFFICE: NEEDS IMPROVEMENTS**, referido a esa entrega autónoma, no a ausencia del motor de gestión. Las demos públicas conservan su preparación D1 y sus restricciones.

## Pregunta comercial y criterio

El vendedor entra en `/wp-admin/` y usa la administración de WordPress/WooCommerce. Commerce Base presenta la tienda y Commerce Core añade gestión legal; no se desarrolló otro backoffice. La decisión es si las herramientas nativas cubren una tienda pequeña y qué acompañamiento necesita el titular, antes de considerar desarrollo adicional.

La evaluación separa capacidad funcional, facilidad para una persona nueva y condiciones de producción. Un flujo disponible que necesita explicación no demuestra que haga falta reemplazar WooCommerce. Tampoco un pedido QA exitoso demuestra que una tienda esté habilitada para cobrar o que el dueño ya sepa operarla.

## Entornos y método

| Entorno | Uso en D1.1 |
| --- | --- |
| TRAMA original `8080`, ORIGEN CASA original `8082` | Referencia preservada; lectura directa de roles/capacidades y huellas de DB. WordPress histórico no se inicia ni recibe login; no crear/modificar pedidos, stock o usuarios históricos |
| Demos públicas D1 `8190`/`8191` | Mantener guardas públicas: panel, checkout y operaciones comerciales bloqueados |
| TRAMA vendedor `8192`, ORIGEN CASA vendedor `8193` | Laboratorios privados de loopback para escritura QA, con modo público apagado sólo allí |
| Mailpit `8194`/`8195` | Captura de comunicaciones ficticias sin entrega externa |

Se combina recorrido experto de las pantallas, operaciones nativas de navegador, comprobación de permisos efectivos por usuario y lectura de estado persistido para distinguir un mensaje visual de un cambio guardado. Los usuarios, SKUs, pedidos, solicitudes y direcciones de prueba son exclusivamente QA. Se revisa la presentación desktop/tablet/mobile en los recorridos que registren evidencia; no se extrapola cobertura a pantallas o dispositivos no recorridos.

Los originales se observan con lecturas SQL de definición de roles y huellas/tablas, sin iniciar sesión en su WordPress ni tocar sus datos históricos. Los laboratorios de escritura derivan del contenido ficticio limpio D1, con DB/usuarios/configuración separados, no de las bases originales. La [definición de roles originales](evidence/d1-seller/original-role-definitions.json) no incluye identidades ni credenciales personales; la preservación final se verifica contra las huellas iniciales.

La [comparación de roles](evidence/d1-seller/role-provenance.json) confirma las tres definiciones almacenadas coincidentes entre cada original y su laboratorio (seis comprobaciones), sin escribir roles ni generar sesiones en los originales. Los controles de seguridad [TRAMA](evidence/d1-seller/lab-safety-trama.json) y [ORIGEN](evidence/d1-seller/lab-safety-origen.json) registran trece comprobaciones por laboratorio: modo público apagado sólo en estos clones, BACS ficticio como único gateway, Mercado Pago ausente, sin bancos, usuarios `.test` y captura/bloqueo de entregas externas, incluso destinatarios mezclados. Esto no equivale a haber probado pantallas administrativas de los originales mediante login.

Las huellas de [antes](evidence/d1-seller/original-history-before.json) y [después](evidence/d1-seller/original-history-after.json) coinciden en las once tablas observadas por cada original: contenido/meta, usuarios/meta, pedidos HPOS/direcciones/meta/artículos y solicitudes/eventos legales. WordPress original permaneció detenido; sus DB se iniciaron sólo para lectura y después volvieron a detenerse. Esta evidencia tiene ese alcance de tablas y comandos, sin prometer una certificación de toda la infraestructura.

La [comparación de código](evidence/d1-seller/code-provenance.json) registra catorce árboles iguales entre fuente D1 y laboratorios (por marca: theme, WooCommerce y cinco plugins propios). Las pruebas del vendedor usan ese código normal con el flag público apagado, sin parches de productos/pedidos/roles y sin instalar un panel nuevo. Las herramientas de QA, captura y transporte local son auxiliares aislados, no cambios funcionales distribuidos a las tiendas.

**No es una prueba de usabilidad con participantes.** El titular todavía no revisó personalmente el backoffice; no hay tiempos de aprendizaje, porcentajes de éxito de comerciantes, satisfacción, entrevistas ni tamaños de muestra para reportar. Las fricciones se describen como evaluación experta sobre pasos y resultados observables.

La [guía del vendedor](d1-seller-guide.md) evita lenguaje de programación y la [colección de evidencias](evidence/d1-seller/README.md) reúne pantallas reales. La revisión no cambia las capacidades funcionales del producto ni oculta opciones para aparentar menor complejidad.

## Roles: evidencia de implementación y comprobación efectiva

WooCommerce 11.2.1 define Shop Manager con gestión de tienda, productos/pedidos/cupones, informes, medios y facultades de editor de páginas. El rol también incluye `edit_theme_options`, importación/exportación y listado de usuarios. Por eso **Shop Manager no equivale a un operador limitado a precios y stock**. Es menos privilegiado que Administrator, pero conserva decisiones de impacto comercial y acceso a datos de clientes.

`manage_woocommerce` habilita ajustes de WooCommerce, incluidos pagos, envíos y otros parámetros críticos. `manage_options` se reserva para ajustes generales WordPress y configuración de identidad/legal de Commerce Core. La recepción/gestión de arrepentimiento exige `manage_woocommerce`, por lo que corresponde a Administrator o Shop Manager; Customer no administra esas solicitudes. [Roles oficiales WooCommerce](https://woocommerce.com/document/roles-capabilities/).

Para usuarios hay una distinción adicional: WooCommerce concede `edit_users` dinámicamente al Shop Manager, pero evalúa el usuario objetivo. De forma nativa sólo permite editar cuentas cuyos **todos** los roles estén en la lista editable —Customer por defecto— y no permite editar administradores. Ver una lista de usuarios no acredita poder cambiar sus roles o administrar al técnico. La comprobación efectiva tiene que combinar capacidades generales, permisos sobre el objetivo y acceso real a la pantalla.

Fuentes locales exactas leídas: `includes/class-wc-install.php::create_roles`, `includes/wc-user-functions.php::wc_shop_manager_has_capability`, `wc_modify_editable_roles`, `wc_modify_map_meta_cap`, y `commerce-core/includes/class-legal.php::menu/manage/save_settings`. El código WooCommerce inspeccionado declara versión 11.2.1; no se sustituyen los permisos reales por una tabla genérica de otra versión.

La [matriz efectiva del laboratorio](evidence/d1-seller/products-role-capabilities.json) registra estos resultados con cuentas ficticias y evaluación del permiso sobre el usuario objetivo:

| Facultad efectiva | Administrator | Shop Manager | Customer |
| --- | --- | --- | --- |
| Productos/categorías/medios | Sí | Sí | No |
| Pedidos/cupones/informes | Sí | Sí | No |
| Ajustes WooCommerce | Sí | Sí | No |
| Gestión de arrepentimiento (`manage_woocommerce`) | Sí | Sí | No |
| Ajustes generales/identidad legal (`manage_options`) | Sí | No | No |
| Páginas y opciones de apariencia | Sí | Sí | No |
| Activar/instalar/actualizar plugins o cambiar theme | Sí | No | No |
| Listar usuarios | Sí | Sí | No |
| Editar la cuenta Customer objetivo | Sí | Sí | Sólo su propia cuenta |
| Editar Administrator objetivo | Sí | No | No |
| Crear/eliminar/promover desde el menú Usuarios (`create_users/delete_users/promote_users`) | Sí | No | No |

La lista de roles editables que una función devuelve no concede por sí sola la capacidad de promover usuarios: se comprueban ambas condiciones. Por ejemplo, la cuenta Customer conserva acceso a su perfil, pero no puede administrar el comercio. La UI y sus denegaciones se complementan con la matriz; acceder a una pantalla de ajustes no se confunde con haber cambiado un medio de pago real.

La definición nativa también contiene `create_customers`: no debe confundirse una eventual alta de cliente desde herramientas WooCommerce con crear/promover administradores desde Usuarios. No se afirma que `create_users=false` impida todas las vías propias de WooCommerce para dar de alta un cliente; las operaciones concretas efectivamente recorridas constan en la evidencia.

### Recomendación de mínimo privilegio

Para **el propietario que decide precios, ventas y configuración comercial**, el Shop Manager nativo es el punto de partida adecuado después de capacitación y una lista explícita de ajustes que se revisan con soporte. Evita trabajar diariamente con plugins/actualizaciones y ajustes WordPress de Administrador. El titular conserva acceso administrativo de recuperación mediante un procedimiento seguro; no se le quita titularidad ni se publica una contraseña compartida.

Para **un empleado que sólo prepara pedidos o carga stock**, el mismo rol puede ser excesivo por la configuración comercial, datos personales y edición de contenido que permite. Eso requiere acordar tareas y validar una política específica en una etapa autorizada; no se crearon roles personalizados aquí. Una instrucción en el manual no revoca capacidades y esconder un menú no constituye control de acceso.

## Lectura de operaciones y límites del modelo nativo

### Productos e inventario

Los productos simples y variables comparten el editor WooCommerce. El simple concentra precio/inventario en pocas secciones; el variable agrega atributos, combinaciones y datos por variación. Eso permite reutilizar la base para Color/Talle y para Material/Acabado/Capacidad sin hacer otro panel por rubro. La dificultad está en mantener coherentes precio, combinación, estado, stock y el guardado de cada sección.

En las capturas reales, el menú usa **Productos → Agregar nuevo producto**, el recuadro Datos del producto divide **General / Stock / Envío / Productos relacionados / Atributos / Avanzado**, y los precios se llaman **Precio normal / Precio de oferta / Vigencia**. El bloque de precio queda por debajo de la primera pantalla de escritorio si la descripción ocupa su altura habitual. No es una función ausente: el vendedor debe reconocer el recuadro y desplazarse hasta él. La captura utiliza Código para automatizar la carga QA; la guía indica Visual para el comerciante.

Los atributos usados sólo para describir no generan automáticamente variantes comprables. Una variación sin precio puede quedar indisponible; crear todas las combinaciones sin revisar puede ofrecer opciones inexistentes. El stock puede administrarse por producto o por variación y debe elegirse una política clara. El CSV nativo sirve para operaciones masivas, con mapeo y revisión previa; no es un sustituto de respaldo o validación del contenido. [Variaciones](https://woocommerce.com/document/variable-product/), [CSV nativo](https://woocommerce.com/document/product-csv-importer-exporter/).

Una reserva temporal y un descuento persistido de inventario son hechos distintos. El código nativo reduce stock al pasar a on-hold/processing/completed si todavía no fue reducido; cancelación y otros estados liberadores pueden reponerlo. El vencimiento de una reserva no significa que se devuelva por sí solo el stock de cualquier pedido BACS En espera. Las cantidades exactas de cada transición se juzgan con snapshots QA y no sólo por el texto de la interfaz. [Estados y stock](https://woocommerce.com/document/managing-orders/order-statuses/), [configuración de inventario](https://woocommerce.com/document/configuring-woocommerce-settings/products/).

### Pedidos, pagos y devoluciones

El flujo BACS es manual: WooCommerce registra el pedido En espera, el comercio confirma acreditación por el banco y después decide Procesando/Completado. El sistema no convierte la foto de un comprobante ni el cambio de estado en confirmación bancaria. No se realizaron pagos en esta auditoría. [BACS oficial](https://woocommerce.com/document/bacs/).

El recorrido incluyó el pedido **En espera** y su paso a Procesando/Completado; la captura final del detalle está tomada después de completarlo. Muestra «Pago a través de Transferencia bancaria · QA», dirección/variación/SKU, importes y notas. El primer texto identifica el medio, no confirma acreditación. Las notas nativas conservan el pago pendiente inicial y la reducción de stock, lo que ayuda a reconstruir el estado si el vendedor sabe qué revisar. El selector Estado y **Actualizar** están separados del recuadro **Notas del Pedido**, que distingue **Nota privada / Nota para el cliente**.

En la plantilla no se agregaron estados propios Preparado/Enviado ni un transportista que conozca el despacho físico. Las notas permiten registrar la gestión, pero una nota al cliente puede generar email y una privada tiene otra audiencia. Commerce Communications mejora presentación y copy de avisos: conserva los disparadores, destinatarios y transporte nativos; no confirma bancos, crea pedidos ni ejecuta devoluciones.

Mailpit detectó en el clon limpio el asunto nativo de Completado **«¡Tu pedido de ORIGEN CASA · DEMO está en camino!»**. No hubo despacho ni integración que lo confirme. El fixture opcional aprobado `demo/communications/emails.json` ya contiene el asunto neutral «Finalizamos tu pedido»: la mejora mínima es revisar y aplicar asuntos nativos coherentes en la configuración de cada entrega, sin desarrollar nada. Una [lectura acotada posterior de los originales](evidence/d1-seller/original-email-copy.json), limitada a asunto/encabezado/habilitación y sin destinatarios, confirma que **TRAMA ya tiene el asunto neutral H7** y ORIGEN conserva los valores por defecto sin override. No se atribuye el problema a ambas tiendas ni se modifica su configuración durante esta auditoría. El cuerpo de Commerce Communications sí dice «Finalizamos la gestión de tu pedido», de modo que asunto y cuerpo del clon no expresan lo mismo.

Un reembolso manual registra importe y, si se elige, reposición de cantidades. **No devuelve dinero.** Pasar directamente a Cancelado o Reembolsado tampoco efectúa una devolución bancaria. En BACS el comercio debe devolver por el medio correspondiente y conciliar el registro. Reposición debe elegirse según la mercadería efectivamente recuperada, evitando sumarla otra vez a mano. [Reembolsos oficiales](https://woocommerce.com/document/woocommerce-refunds/).

La traducción observada del estado `wc-refunded` es **Devuelto**. Además, al pasar el pedido BACS manualmente a Procesando, el detalle llega a mostrar **Pagado** aunque en QA no hubo dinero. Son registros internos de WooCommerce y no una prueba de banco o recepción física. Esta ambigüedad concreta requiere una regla de operación y práctica; no se modifica el estado ni su traducción mediante código durante la auditoría.

### Cupones, envíos y estadísticas

Los cupones nativos admiten porcentaje, importe fijo de carrito o por producto, restricciones, vencimiento y límites. El fijo por producto puede multiplicarse por unidades; envío gratis necesita un método compatible. Antes de anunciar una promoción hay que probar un carrito elegible y uno excluido. Las zonas/tarifas determinan opciones ofrecidas; no contratan transporte ni acreditan cobertura física. [Cupones nativos](https://woocommerce.com/document/coupon-management/).

Analytics ofrece informes y filtros; su lectura exige distinguir ventas brutas/netas/totales, descuentos y devoluciones. El tipo de fecha y los estados incluidos afectan resultados. La configuración nativa incluye En espera entre estados contables admitidos y usa fecha de pago por defecto; un número de ventas no es saldo bancario, acreditación BACS ni rentabilidad. Los reembolsos y su fecha pueden cambiar comparaciones. En QA sólo se valida consistencia sobre pedidos sintéticos; no se infieren demanda, márgenes, facturación fiscal ni rendimiento con volúmenes reales. [Analytics](https://woocommerce.com/document/woocommerce-analytics/), [informe de ingresos](https://woocommerce.com/document/woocommerce-analytics/revenue-report/).

Una estrella «destacado» del catálogo es curaduría comercial; no equivale a mayor venta. No se construye un reporte propio para reproducir informes que ya ofrece WooCommerce. Si el laboratorio necesita sincronización técnica por cron deshabilitado, se registra como apoyo de QA, sin presentarlo como un paso normal del vendedor ni como prueba de jobs automáticos productivos.

### Gestión legal y notificaciones

Commerce Core separa gestión de solicitud, pedido y devolución financiera. Muestra código, datos informados no verificados, estados, correos y notas. Para vincular pedido pide su ID interno, una confirmación de verificación y nota; esa protección reduce asociaciones ciegas, pero requiere explicar al vendedor cómo obtener el ID correcto. El código no abre una consulta pública de pedidos.

Los estados Recibida/En gestión/Esperando información/Gestión cerrada documentan actuaciones; ninguno cancela el pedido, ejecuta un reembolso ni acredita recepción de email. La administración indica fecha UTC y estados de entrega técnicos que deben interpretarse en contexto. Revisar Mailpit demuestra composición y llegada a la captura local, **no entregabilidad externa**, autenticación de dominio ni llegada a la bandeja real del comercio.

## Resultados locales comprobados

| Circuito y evidencia | Resultado y alcance |
| --- | --- |
| [Roles efectivos](evidence/d1-seller/products-role-capabilities.json) y [procedencia](evidence/d1-seller/role-provenance.json) | Capacidades/objetivos probados en TRAMA vendedor y definiciones almacenadas comparadas con ambos originales; no se inició sesión en WordPress histórico |
| [Productos/cupones/CSV por UI](evidence/d1-seller/products-role-ui.json) | Producto simple con imagen/categoría/precio/oferta, agotado/stock bajo/reposición/borrador; dos variables QA con cuatro combinaciones cada una (Talle/Color y Material/Capacidad), SKU/precio/stock propios y selección en storefront. Cupones de 15%/ARS 2.500, rechazo de vencido/gasto insuficiente y CSV nativo de alta/exportación; el resultado final por fase consta en ese archivo |
| [Atributos/envíos/retiro por UI](evidence/d1-seller/products-role-ui.json) | Atributo global QA con términos Mate/Brillante, producto propio enviado a papelera y zona exclusiva CP 9999 con tarifa ARS 1.800, mínimo de gratis ARS 50.000 y retiro ARS 0, editados por Shop Manager. Se confirma persistencia de ajustes; los checkouts del otro circuito prueban métodos QA independientes de esta zona |
| [Persistencia de productos/configuración QA](evidence/d1-seller/products-role-data.json) | 20 comprobaciones de datos guardados que complementan la UI: catálogo propio, precios/stock/variaciones, cupón/importación y configuración QA; no datos históricos |
| [Checkout BACS](evidence/d1-seller/orders-checkout.json) | Tres pedidos ficticios enviados por Checkout Blocks nativo: tarifa, envío gratis y retiro. Direcciones `.test`/QA, sin banco ni pago real; 75 comprobaciones del recorrido |
| [Pedidos del vendedor](evidence/d1-seller/orders-orders.json) | 23 comprobaciones: lista/detalle, notas privadas/al cliente, En espera → Procesando → Completado, cancelación y reembolso manual parcial/restante con reposición |
| [Hechos finales de pedidos/stock](evidence/d1-seller/orders-refund-total-restante-facts.json) | Pedido #84 Completado, #85 Cancelado y #87 Devuelto; stock final simple 6, Roble 5 y Nogal 4. Cero dinero transferido/devuelto y sin reservas finales |
| [Reserva QA técnica](evidence/d1-seller/orders-reservation-probe-facts.json) | En esta versión, abrir checkout generó draft pero no reserva; el POST final aplicó la reserva y BACS la convirtió en reducción de stock. La prueba separada de reservar/liberar un pending propio se hizo por API interna/CLI, preservó stock y retiró ese pedido temporal. No prueba vencimiento de cron ni una pantalla nativa de reservas |
| [Legal](evidence/d1-seller/legal.json) | 22 comprobaciones: recepción ficticia, Shop Manager En gestión/asociación verificada/nota y Administrator Esperando información/nota; el pedido vinculado conserva Completado |
| [Analytics](evidence/d1-seller/analytics.json) | 19 comprobaciones: Resumen/Ingresos/Pedidos/Productos/Cupones/Clientes, comparación de fecha donde el informe la admite y descarga CSV nativa de dos productos QA; no se atribuye el filtro after/before al endpoint Clientes |
| [Correo](evidence/d1-seller/mail.json) | 55 comprobaciones sobre 20 mensajes Mailpit: nuevo pedido/cancelación/stock bajo-agotado, avisos al comprador, nota al cliente, reembolsos y solicitud legal; destinatarios `.test`, sin enlaces a originales/demos públicas y sin entrega externa. La nota privada no dispara email al cliente |
| [Personalización de lectura](evidence/d1-seller/personalization.json) | 15 comprobaciones y 14 pantallas de roles/desktop/tablet/mobile; se abrieron los controles Estilos/Identity, sin guardar nombre/logo/colores/Home/legales |
| [Preservación pública D1](evidence/d1-seller/public-d1-preserved.json) | 12 comprobaciones finales sobre `8190`/`8191`: admin/checkout/WC AJAX/Store API checkout siguen en 403; catálogo y carrito responden 200. El modo público no se quitó para probar al vendedor |

Los números son aserciones registradas de cada recorrido e incluyen carga de pantallas; no representan comerciantes evaluados ni una certificación de usabilidad. Las pruebas de permisos/datos y las de UI tienen alcances diferentes. Las capturas se revisan y se enmascaran en el navegador únicamente valores de sesión/cuentas privadas y geolocalización incidental; no se rediseña la interfaz ni se ocultan errores de negocio para la evidencia.

El retiro recorrido aquí es el método nativo **`local_pickup` dentro de una zona**; no se debe confundir con el retiro moderno/global **`pickup_location` de Blocks**, validado en H6.2 en la tienda histórica y separado de las zonas. D1.1 no cambia esa configuración original ni repite toda su matriz. La lista de países también limita destinos: Uruguay no aparece en el ensayo de zona porque General admite Argentina; no es un fallo del selector de zonas.

### Ejemplo de inventario observado

El simple QA comenzó con 8 unidades. El pedido principal tomó 2 y quedó en 6; el pedido de cancelación tomó las 6 restantes y quedó agotado. Cancelarlo desde el panel restauró 6. El siguiente pedido tomó 2, dejando 4; dos reembolsos manuales de una unidad, con la casilla de reposición elegida, lo llevaron a 5 y 6. Roble pasó de 6 a 5 por la variación del pedido principal; Nogal se mantuvo en 4. Cambiar En espera → Procesando → Completado no descontó otra vez las mismas unidades.

Esto demuestra automatismos nativos bajo gestión de stock habilitada. No demuestra reposición física, múltiples depósitos ni que cambiar sólo el estado a Devuelto reponga cantidades. En la UI se eligió expresamente **Reponer inventario de los artículos reembolsados** y se registraron dos importes de ARS 10.000, sin ejecutar devoluciones monetarias.

### Lectura de Analytics sin inventar ventas

Para **25/10/2026**, los informes y datos nativos del laboratorio conciliaron: ventas brutas ARS 58.000 − devoluciones ARS 20.000 − cupones ARS 3.800 = ventas netas ARS 34.200; más envío ARS 1.500 = total ARS 35.700. Se cuentan dos pedidos (el Completado y el Devuelto, con su ajuste), tres unidades netas y un cupón; el Cancelado queda fuera. La comparación del día anterior dio cero para los informes recorridos. El resumen muestra el ranking por artículos vendidos, separado de la estrella editorial «destacado».

Son **importes sintéticos registrados**, no ingresos reales. El laboratorio tiene cron externo deshabilitado: se sincronizaron los pedidos QA mediante datastores nativos para observar los informes; no se certificó actualización automática, histórica o en segundo plano. El texto de próxima actualización que aparece en la UI no prueba que ese job se haya ejecutado. Tampoco se deduce ganancia, saldo bancario o confiabilidad fiscal de estos datos.

En cupones se probó además una compra QA elegible de ARS 63.000: el 15% descontó ARS 9.450 y el cupón fijo descontó ARS 2.500. Otro caso rechazó gasto inferior al mínimo y otro rechazó fecha vencida. Son pruebas separadas de los ARS 3.800 de descuento del circuito de pedidos ORIGEN; no se mezclan sus importes ni se interpretan como la misma promoción.

## Clasificación y prioridades

La clasificación se apoya en resultados UI y capturas del [índice de evidencia](evidence/d1-seller/README.md). «Fácil de usar» expresa la evaluación experta de ese recorrido, no una medición con comerciantes principiantes. Las propuestas se limitan a capacitación, procedimientos y mejoras pequeñas sujetas a aprobación; no se compra una extensión ni se modifica el backoffice por iniciativa de esta auditoría.

| Área | Interpretación funcional | Clasificación de entrega |
| --- | --- | --- |
| Acceso a Pedidos/Productos y lectura de listas | Menús visibles y listas reconocibles | Disponible y fácil de usar |
| Producto simple y cambios de precio | Función nativa localizada en ficha; encontrar Datos del producto y guardar | Disponible y fácil de usar después de ubicar el recuadro |
| Preparación inicial del Escritorio | Widgets genéricos, onboarding y contenido inicial del laboratorio | Disponible, pero necesita alguna mejora: revisión de entrega y opciones nativas de pantalla |
| Variaciones/atributos/stock por combinación | Resuelve rubros distintos; varios pasos y guardados | Disponible, pero requiere capacitación |
| Pedidos BACS/estados/notas | Flujo completo con confirmación bancaria humana | Disponible, pero requiere capacitación |
| Cancelación/reembolso/reposición | Cambios contables y de stock no equivalen a dinero | Disponible, pero requiere capacitación |
| Cupones y condiciones | Descuentos nativos; restricciones y cálculo a revisar | Disponible, pero requiere capacitación |
| Zonas/tarifas/retiro | Configuración comercial sensible, funcional en escritorio; desborde real a 390 px | Disponible, pero requiere capacitación; necesita alguna mejora si se exige edición móvil |
| Analytics y exportaciones | Indicadores nativos sobre datos/fechas/estados | Disponible, pero requiere capacitación |
| Arrepentimiento | Gestión separada y trazable con permiso comercial | Disponible, pero necesita alguna mejora de ayuda contextual para ID/estados técnicos; mientras tanto requiere capacitación |
| Asunto del email Completado en clon limpio | Sugiere despacho sin un transportista que lo confirme | Disponible, pero necesita alguna mejora: configuración nativa de copy antes de entregar |
| Rol estrecho para empleado de tareas acotadas | No se definió política especial ni se crearon roles | No implementada en esta etapa |
| Confirmación bancaria o devolución automática por BACS | WooCommerce registra estados; el banco se verifica/gestiona por un humano | No implementada; BACS es un circuito manual |
| Multi-depósito/ERP/contabilidad/seguimiento de transportista | No lo añade la plantilla | Fuera de alcance |
| Cobros Mercado Pago y operación remota | Bloqueos H7–H11 y admisión/servicios reales pendientes | Bloqueada para producción |

| Impacto | Riesgo o fricción a considerar | Respuesta mínima propuesta, sin desarrollo automático |
| --- | --- | --- |
| Alto | Delegar Shop Manager como si fuese una cuenta sólo de catálogo permite cambios críticos que el dueño no esperaba | Acordar tareas, asignación nominada, formación y lista de ajustes bajo soporte; analizar permisos adicionales sólo si el comercio delega funciones limitadas |
| Alto | Interpretar En espera/Procesando o Reembolsado como prueba de movimiento de dinero | Checklist de conciliación BACS/devolución y práctica con casos QA; conservar copy de Commerce Communications |
| Alto | El asunto nativo de Completado del clon anuncia «está en camino» aunque no hubo envío real | Revisar WooCommerce → Ajustes → Correos electrónicos y usar el asunto neutral aprobado, alineado al procedimiento de cumplimiento; no cambiar código ni afirmar que ya se aplicó |
| Medio | Variaciones exigen atributos, combinaciones, precios/stock y varios guardados | Cargar una ficha patrón por rubro, repetir acompañado y revisar storefront antes de publicar; no generar combinaciones indiscriminadamente |
| Medio | Cupones/envíos pueden ofrecer condiciones distintas de las anunciadas | Ensayo de carrito elegible/no elegible y dirección dentro/fuera de zona antes de comunicar cambios |
| Medio | Analytics depende de filtros/fecha/actualización y no representa caja | Explicar un informe de referencia y dejar configuración técnica a soporte; no cambiar pedidos para ajustar métricas |
| Medio | Asociación legal por ID y términos de correo/fecha UTC pueden ser poco claros para un comerciante nuevo | Guía breve de identificación/verificación; proponer ayuda contextual/fecha local sólo después de revisión humana y aprobación |
| Medio | En 390 px el detalle de pedido es muy largo, con Acciones/Notas antes de los datos e ítems con desplazamiento interno; 1024 px también requiere revisar tablas | Usar teléfono para consultas puntuales y computadora para cargas, variaciones y devoluciones; practicar el recorrido y desplegar columnas sólo cuando haga falta |
| Medio | Envíos a 390 px desborda el documento (aproximadamente 460 px) y recorta pestañas; es un hallazgo responsive de la pantalla nativa | Configurar envíos desde escritorio; si el comercio exige esa tarea desde teléfono, evaluar una mejora acotada y autorizada. No se aplicó un rediseño ni un parche durante D1.1 |
| Bajo | El panel contiene opciones editoriales/técnicas que no se usan todos los días | Recorrido de los accesos frecuentes y manual con capturas; no ocultar menús como sustituto de permisos |

La captura de Escritorio muestra widgets de borrador/actividad, contenido inicial «Hello world!», configuración WooCommerce todavía en paso 5 de 6 y noticias con error RSS porque el laboratorio intercepta HTTP externo. Es ruido real de esta entrega inicial; el error RSS es una restricción deliberada del laboratorio y no demuestra un fallo de ventas o de hosting. Como preparación comercial mínima se propone revisar contenido inicial y opciones de pantalla por usuario mediante mecanismos nativos, sin escribir un dashboard ni esconder las capacidades reales. No se aplicaron esas modificaciones durante el recorrido.

La administración es predominantemente española y usa «Estadísticas», «Stock», «Precio de oferta» y «Devuelto»; conserva términos parciales en inglés, como «Identity» del Site Editor y valores técnicos de la gestión legal. No se certifica una traducción completa. Los avisos nativos de primer uso pueden tapar un informe hasta pulsar Entendido; las capturas incluyen ese estado y la pantalla despejada, sin contarlo como un error del cálculo.

No se declara una vulnerabilidad sólo por observar permisos comerciales amplios documentados. Un defecto crítico confirmado exigiría evidencia reproducible y autorización antes de cambiar código funcional. Las condiciones de producción heredadas siguen separadas de estas fricciones de aprendizaje.

No se encontró un defecto funcional crítico en los circuitos auditados. Las correcciones durante la ejecución fueron del arnés de QA —sincronización con guardados/filas dinámicas, respuesta de denegación nativa que podía ser 500 con mensaje de permisos y recursos estáticos bloqueados por el aislamiento—; no se parcheó WooCommerce, Commerce Base/Core o D1 para hacer pasar pruebas. Los fallos de automatización no se presentan como defectos del vendedor ni se suman como PASS. El reporte final de productos contiene 176 aserciones verdaderas y fases finales explícitas, sin fallos heredados de intentos anteriores.

## Personalización y responsabilidades

| Trabajo | Responsable sugerido |
| --- | --- |
| Productos, variantes existentes, fotos, precios, stock, pedidos, descuentos y solicitudes | Comercio capacitado |
| Nuevos atributos/categorías, imports, tarifas/zonas y textos comerciales | Comercio con revisión ocasional de soporte |
| Nombre/logo/colores/Home/menús | Herramientas nativas WordPress según permiso efectivo; acompañamiento inicial por su alcance global |
| Identidad/legal y reglas de operación | Titular y responsables pertinentes; soporte aplica configuración revisada |
| Plugins/theme, actualizaciones, seguridad/backups, correo/pagos, claves, DNS/hosting | Soporte técnico autorizado |

Esta separación es un procedimiento de trabajo y no una afirmación de que todas las funciones de la columna técnica estén inaccesibles por rol. En especial Shop Manager conserva ajustes WooCommerce y edición de apariencia/contenido que deben confirmarse en la instalación.

El [recorrido de personalización](evidence/d1-seller/personalization.json) verifica acceso a Páginas/Editor y controles Estilos/Identity para Shop Manager, restricción de General/Identidad y legales, y acceso de Administrator a General, Home e identidad. **Fue de lectura, sin guardar cambios de marca o contenido.** Las pantallas acreditan acceso a los controles; no una edición/guardado de logo, colores, navegación o plantilla ni un resultado visual posterior. No se confunde ese alcance con un rediseño validado.

## Límites y fuentes

No se valida un pago real, devolución monetaria, envío físico, conciliación automática, entregabilidad SMTP, hPanel, carga productiva o cumplimiento integral. No se cambiaron roles productivos ni la política pública D1. La evaluación de usuario nuevo queda pendiente del titular y se sugiere como siguiente revisión del material, sin iniciar otra fase técnica.

Fuentes públicas primarias consultadas el **25/10/2026**, enlazadas junto a las afirmaciones; fuentes locales de WooCommerce 11.2.1 y plugins aprobados. La documentación actual sirve de referencia; donde difiera el comportamiento de esta versión/instalación prevalece la evidencia efectiva y se registra la diferencia. No se instalaron plugins de roles ni se recomienda automáticamente una extensión paga.

## Decisión para revisión del titular

Antes de entregar el manejo autónomo del comercio, resolver por procedimientos y configuración nativos:

1. **ORIGEN: asunto de Completado.** Adaptar el copy neutral ya disponible en el fixture aprobado y acordar qué significa Completado para la operación. TRAMA ya conserva ese asunto neutral; no se trata de rehacer ambos correos ni de desarrollar una integración logística.
2. **Práctica del dueño con esta guía.** Hacer que cargue un producto simple, actualice una variación, gestione un BACS ficticio y registre una devolución manual ficticia explicando qué ocurrió con dinero y stock. La auditoría experta no sustituye esa revisión personal.
3. **Acceso y responsabilidades.** Usar Shop Manager para el propietario capacitado, mantener el circuito técnico de Administrador y acordar qué ajustes comerciales se revisan con soporte. Si se incorpora un empleado con tareas estrechas, evaluar sus permisos específicamente antes de asignar el mismo rol amplio.
4. **Preparación de entrega.** Revisar widgets/contenido inicial y completar la configuración pertinente mediante herramientas nativas, sin comprar plugins ni crear dashboard. La ayuda adicional para ID legal, estados técnicos y fecha local queda como mejora menor a evaluar, sin implementación automática.

Los entregables de esta etapa son [manual](d1-seller-guide.md), [capturas y evidencia](evidence/d1-seller/README.md) y herramientas reproducibles de laboratorio/QA. No se modificó código funcional distribuido, no se cambiaron roles/configuraciones originales, no se publicaron cuentas de prueba ni se inició D2. Las decisiones de commit/push y el estado final de Git corresponden al cierre de esta rama; no se modifica main ni se hace merge como parte de la auditoría.

**SELLER BACKOFFICE: NEEDS IMPROVEMENTS** — para entrega autónoma a un comerciante nuevo; capacidad nativa suficiente, con mejoras mínimas de configuración y acompañamiento.

**PUBLIC DEMOS D1: READY** — preparación técnica local preservada; no cambia la admisión pendiente del hosting ni concede autorización de publicación.

**PUBLIC DEPLOYMENT: NOT PERFORMED.**
