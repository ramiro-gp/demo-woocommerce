# H11 — Checklist de lanzamiento y entrega al cliente

Revisión: **23/10/2026, Argentina**. Plantilla de trabajo por comercio; **ninguna casilla está aprobada por la existencia de este archivo**. Completar fecha, responsable, resultado y evidencia privada/redactada. El cierre técnico local de H11 no publica una DEMO, no habilita ventas y no reemplaza la autorización expresa de lanzamiento.

Referencias: [decisión de hosting](h11-hosting-decision.md), [runbook](h11-deployment-runbook.md), [preparación final H11](h11-production-readiness.md), [playbook de configuración H10](h10-new-client-playbook.md) y [adaptación legal H8](h8-client-legal-setup.md). No duplicar evidencias H7–H10: enlazar su resultado y ejecutar aquí sólo los riesgos propios de la instalación/entorno nuevo.

## Ficha de la entrega

| Campo | Completar de forma privada |
| --- | --- |
| Cliente, titular y aprobador | Nombre comercial, razón social y responsable con facultad para autorizar |
| Alcance aprobado | Productos/variantes iniciales, funciones, regiones, moneda, métodos y exclusiones contractuales |
| Entorno | Local / staging restringido / producción; URL y cuenta de hosting identificadas |
| Distribución | Revisión Git, versión de cada ZIP, manifiesto y SHA-256 |
| Responsable técnico y suplente | Contacto, horario, respuesta acordada y canal de incidentes |
| Responsables comerciales | Pedidos/pagos, stock/logística, atención, arrepentimiento y privacidad |
| Lanzamiento | Ventana, fecha, aprobación explícita, controles y reversión |

Guardar contraseñas/tokens en el canal privado acordado, sin copiar en esta ficha ni en Git. No usar datos personales reales para la QA local.

## Playbook desde el presupuesto aceptado hasta la operación

Cada paso tiene una salida comprobable. El titular aporta/aprueba información y proveedores; el desarrollador prepara y verifica lo técnico. Ningún paso implica contratar o publicar sin su autorización.

| Paso | Trabajo del desarrollador | Trabajo del comercio | Salida y criterio de avance |
| --- | --- | --- | --- |
| **1. Recolección de información** | Convertir alcance aceptado en inventario de material, dependencias y pendientes | Aportar identidad, marca/licencias, catálogo, condiciones, contactos y responsables | Ficha aprobada, material faltante y fechas; sin promesas operativas inventadas |
| **2. Hosting** | Evaluar contrato efectivo, renovación, recursos, backups, cron/SSH y aislamiento; recomendar modelo por criticidad | Mantener titularidad, decidir y autorizar contratación/asignación | Plan real identificado y capacidad/condiciones verificadas; sin cantidad garantizada de ecommerce por número de sitios |
| **3. Dominio** | Inventariar delegación/zona, correo y subdominios; preparar diff DNS/TLS y reversión | Confirmar titularidad, renovación y operador autorizado | Canonical y acceso técnico definidos; ningún MX/NS/registro existente se cambia por defecto |
| **4. Instalación** | Instalar WP/WC oficiales y ZIP propios en base vacía; ejecutar preflight y setup soportados | Aprobar acceso a su recurso y responsables | Instalación independiente, restringida/noindex, sin histórico DEMO, MP inactivo |
| **5. Marca** | Configurar nombre, logo, estilos, Home, menús, footer y opcionales del theme | Entregar y aprobar activos con derechos y mensajes verificables | Revisión responsive/teclado y ausencia de identidad ficticia |
| **6. Catálogo** | Cargar/importar por SKU, atributos y variaciones; revisar imágenes/alt y stock | Aprobar precios, impuestos con asesor, medidas/pesos, disponibilidad y fotos | Catálogo real consistente en ficha, carrito y pedido; sin promociones QA |
| **7. Envíos** | Configurar zonas/códigos, tarifas/umbrales, retiro y exclusiones | Acordar transportes, cobertura, plazos, costo y atención de incidencias | Cálculos y textos coinciden, incluida zona sin cobertura |
| **8. Métodos de pago** | Activar únicamente métodos expresamente aprobados y verificados | Contratar/titularizar, aportar datos auténticos por canal seguro, conciliar | BACS con procedimiento real si se elige; MP sigue bloqueado hasta aceptación separada; H11 no activa cobros |
| **9. Datos legales** | Configurar identidad/páginas/formulario, enlaces y permisos; mostrar los pendientes | Titular/asesores revisan información, fiscalidad, textos, excepciones y atención | Aprobación fechada de contenido y procedimiento; ninguna política DEMO se presenta como definitiva |
| **10. Correos** | Configurar un transporte autorizado, identidad, DNS preparado, pruebas y alertas | Elegir/autorizar proveedor, remitentes, buzones y destinatarios de prueba | Entrega real validada cuando se autorice; Mailpit sólo prueba contenido/eventos locales |
| **11. QA** | Ejecutar matriz focalizada, backup/restore, actualización sin DB y prueba del hosting real | Validar contenido, cálculo comercial y capacidad de gestión | Evidencia y defectos con dueño; críticos resueltos antes de abrir ventas |
| **12. Aprobación del cliente** | Entregar versión concreta, pendientes, costos de terceros y límites | Aprobar marca/catálogo/legal/operación y aceptar la entrega concreta | Registro fechado; aprobación de diseño no equivale a autorización de publicar/cobrar |
| **13. Publicación autorizada** | Tras autorización expresa, aplicar sólo el cambio aprobado, SSL/canonical, QA y monitoreo; preparar reversión | Autorizar dominio/ventana, habilitación comercial y responsables | Permiso verificable y gates cerrados; no existe autorización productiva en H11 |
| **14. Entrega de accesos** | Crear cuentas nominadas con rol mínimo, MFA compatible; retirar temporales y entregar recuperación segura | Recibir, cambiar claves y custodiar titularidad/renovaciones | Acta privada de accesos y revocación; sin compartir un administrador genérico |
| **15. Capacitación básica** | Enseñar catálogo, pedidos/estados, BACS, correo, cuenta, arrepentimiento y escalamiento | Designar operadores y ensayar los circuitos | Pedido QA y trámite legal gestionados correctamente, sin asumir cancelación/reembolso automáticos |
| **16. Mantenimiento** | Acordar cobertura técnica, actualizaciones, backup/restore, alertas e incidentes | Atender ventas, conciliación, catálogo/stock y reclamos, renovar servicios | Responsables y frecuencia por escrito; sin soporte 24/7, precios ni SLA implícitos |

## Controles que bloquean el lanzamiento

### Identidad, legal y operación del titular

- [ ] Nombre comercial, titular/razón social, CUIT, domicilio y contacto auténticos; canal atendido, horarios y persona responsable de privacidad/reclamos. Sin “pendiente”, TRAMA/ORIGEN CASA ficticios, `.test`, teléfonos, banco o QR de ejemplo.
- [ ] Precios finales, moneda, impuestos aplicables, facturación y documentación revisados con el asesor contable. Data Fiscal del titular obtenido desde el servicio oficial y revisado cuando corresponda; no pedir clave fiscal al cliente ni fabricar el QR. El recibo de WooCommerce no se presenta como factura fiscal.
- [ ] Términos, privacidad, envíos, cambios/devoluciones y garantías propios revisados/aprobados. Contenido consistente en ficha, checkout, email y páginas; distinguir arrepentimiento legal, garantías y cambios comerciales voluntarios.
- [ ] Aviso de arrepentimiento y acceso visible **BOTÓN DE ARREPENTIMIENTO**, incluido primer acceso y móvil; sin registro previo obligatorio, promociones que lo tapen ni pedido obligatorio para recibir la solicitud. Formulario, código, correo y gestión autorizada probados.
- [ ] Procedimiento real de recepción diaria, verificación razonable de identidad/relación con pedido, respuesta, devolución, costos, reembolso y trazabilidad. Un caso “recibido” o “cerrado” no cancela ni devuelve dinero por sí mismo.
- [ ] Plazos y excepciones revisados según producto/servicio; no imponer un rechazo automático por fecha o catálogo sin análisis. En el régimen general, revisar el plazo de 10 días, cómputo, vencimiento inhábil y gastos; excepciones sectoriales requieren asesoría.
- [ ] Mapa de datos personales y proveedores: qué se recoge, finalidad/base aplicable, destinatarios, alojamiento/transferencias, contacto/derechos, seguridad y retención de pedidos, legales, logs y backups. Revisar inscripción/obligaciones que correspondan con el titular/asesoría; no fijar conservación indefinida.
- [ ] Cookies/recursos externos y telemetría inventariados; marketing/analítica sólo con decisión y encuadre aprobados. Separar consentimiento comercial de la compra y de la tramitación legal. Páginas DEMO identificadas en entornos de prueba, fuera de la entrega comercial aprobada.
- [ ] Titular aprueba versión fechada de textos y el circuito; requisitos de rubros regulados/jurisdicción revisados. Completar formulario técnicamente no certifica cumplimiento.

Fuentes oficiales reconsultadas el 23/10/2026: [LDC 24.240 actualizada](https://www.argentina.gob.ar/normativa/nacional/ley-24240-638/actualizacion), [CCyC, arts. 1105–1116](https://www.argentina.gob.ar/normativa/nacional/ley-26994-235975/actualizacion), [Disposición 954/2025](https://www.argentina.gob.ar/normativa/nacional/disposici%C3%B3n-954-2025-417152/texto) y [su índice de complementarias](https://www.argentina.gob.ar/normativa/nacional/disposici%C3%B3n-954-2025-417152/normas-modifican). Este último mostraba D3/2026; su [texto oficial](https://www.argentina.gob.ar/normativa/nacional/disposici%C3%B3n-3-2026-423007/texto) contempla verificaciones razonables por medios habituales con finalidad exclusiva de identidad/seguridad. No recuperar el plazo de código de una resolución derogada como si siguiera vigente. El circuito H8 conserva constancia inmediata como decisión técnica.

Para datos, [Ley 25.326 actualizada](https://www.argentina.gob.ar/normativa/nacional/ley-25326-64790/actualizacion): información a los titulares, proporcionalidad, seguridad, confidencialidad, derechos y conservación según finalidad/obligaciones. Para Data Fiscal, [ARCA F.960/D](https://www.arca.gob.ar/960/formulario-960/concepto.asp). La matriz detallada y las limitaciones están en [H8 legal](h8-legal-compliance-matrix.md); la revisión del titular y sus asesores sigue pendiente por cliente.

### Infraestructura, DNS y seguridad

- [ ] Suscripción/versión exacta, moneda, período y renovación identificados en hPanel/factura; capacidad real y costos adicionales aceptados. Confirmado aislamiento de archivos/usuario, no sólo DB separadas.
- [ ] PHP web/CLI, módulos, memoria, DB, rewrites, almacenamiento, SSL, cron, procesos y salida de red comprobados en **el hosting elegido**. Preflight sin errores y reporte WC revisado. Resultado local no cierra este punto.
- [ ] Titular conserva dominio y hosting; MFA, roles y accesos mínimos activos donde corresponda, recovery probado. Sin usuarios QA administrativos innecesarios ni accesos compartidos con otros clientes.
- [ ] No hay secretos, dumps, logs, backups o herramientas QA accesibles por HTTP; uploads sin ejecución PHP, listados desactivados y DB/Mailpit sin exposición. Errores PHP no visibles, registros privados/rotados.
- [ ] Inventario DNS completo y diff aprobado, preservando MX/SPF/DKIM/DMARC, verificaciones, subdominios y DNSSEC; sin transferencia o cambio de NS innecesario. Plan de reversión y renovación nominados.
- [ ] Certificado válido para hosts usados, renovación/alerta, HTTPS/canonical y www/no-www sin loops/cadenas; sin mixed content ni localhost/túneles/URLs QA. REST/Store API/Checkout Blocks funcionan con proxy/WAF.
- [ ] Caché en cada capa excluye carrito, checkout, cuenta, sesiones/cookies, recibos privados, legal, admin y APIs/mutaciones sensibles. Prueba A/B frente al host/CDN, no sólo cabecera de origen. OPcache/objetos sólo si soportados; sin plugins superpuestos.
- [ ] Cron externo ejecutado y observado sin visitas antes de desactivar runner por visitas; Action Scheduler sin backlog/fallos sin dueño, sin jobs duplicados ni integraciones desconocidas.
- [ ] Política de actualización y respuesta a incidentes acordada; backup previo a cambios, rollback compatible y recursos de staging protegidos.

### Backups, correo y pagos

- [ ] Conjunto completo DB+medios+código+config, incluyendo HPOS/items/usuarios/options, tres tablas legales y eventos, colas y ajustes; manifiesto/hashes y retención definidos. Nunca sólo export de productos o cuatro tablas HPOS.
- [ ] Copia cifrada externa a la cuenta/hosting, acceso y claves separados; automatización/alerta verificadas y espacio disponible. RPO/RTO aceptados y contrastados con frecuencia y prueba real.
- [ ] Restore íntegro comprobado en destino aislado y salidas bloqueadas/capturadas; productos/variantes/usuarios/pedidos/stock/legales/eventos/medios coinciden. Evidencia de tiempo recuperado y límites, sin datos personales en Git.
- [ ] Actualización de código ensayada sin importar DB de staging ni reemplazar usuarios, pedidos, catálogo o solicitudes recientes. Backup actual y conciliación de actividad posterior obligatorios ante rollback de datos.
- [ ] Proveedor de correo real, cuenta/dominio, cuota/renovación y privacidad aprobados; un transporte, TLS, remitente/Reply-To atendidos, DNS autenticado y logs mínimos. Mailpit retirado de producción.
- [ ] Autorización y destinatarios expresos para prueba real; matriz de [correo del runbook](h11-deployment-runbook.md#13-correo-transaccional-transición-de-mailpit) ejecutada con recepción y headers SPF/DKIM/DMARC, links e imágenes. Aceptación SMTP sola no acredita entrega.
- [ ] BACS, si se ofrece, con banco/titular auténticos aprobados, instrucciones y conciliador. Pedido on-hold no se prepara/envía como pagado sin acreditar.
- [ ] Mercado Pago permanece completamente inactivo si no se necesita; Checkout Pro real no aprobado. Para habilitarlo en un trabajo futuro se requiere resolver la matriz de riesgos y QA separada; instalar una versión nueva o pasar BACS no los cierra.
- [ ] Otros medios son sólo alternativas informativas hasta evaluación/contratación/autorización propia. No agregar gateways durante H11 ni usar pagos/reembolsos monetarios reales como QA.

### SEO, contenidos y acceso público

- [ ] DEMO/staging siguen restringidos y noindex, sin campañas ni analítica real; sus correos/datos no se mezclan con producción. `robots.txt` no se trata como barrera de privacidad.
- [ ] Sólo tras autorización final: revisar indexación del dominio definitivo, canonical, sitemap, títulos/descripciones, Open Graph, structured data de productos reales y ausencia de duplicación; páginas privadas fuera del sitemap.
- [ ] Search Console/analítica aprobadas con titular y privacidad correspondiente; cuentas y propiedades por entorno. No prometer posicionamiento por instalar un plugin.

## QA representativa de la entrega

Registrar por fila `PENDIENTE / PASS / FAIL / NO APLICA justificado`, entorno, fecha, versión y evidencia redactada. Mantener los datos crudos, tokens, cookies, emails y backups en almacenamiento privado. Un PASS de H10 sirve de antecedente del código; no valida el dominio/host nuevo.

| Flujo | Muestra y resultado necesario |
| --- | --- |
| Home y catálogo | Marca/contacto propios, menú/footer, búsqueda/filtros/categorías, responsive y teclado; sin banners/promos QA |
| Producto simple y variable | Imágenes/alt, precios/stock, variante agotada, swatch textual/color por atributo real, cantidad y medios responsive |
| Lookbook optativo | Hotspot válido a producto de esta base y teclado/móvil; documentar NO APLICA si no se usa |
| Carrito y Mini-Cart | Agregar, cantidad, eliminar, cupón válido/inválido, sesión A/B y totales sin caché cruzada |
| Checkout Blocks / BACS | Invitado y cuenta, direcciones, coberturas/envío/retiro, validación, total, pedido HPOS on-hold y stock; sin pago real |
| Correos capturados | Nuevo/admin/BACS, Processing/Completed, cancelación/reembolso según tipo, alta/password y constancia legal; contenido/links correctos |
| Mi Cuenta y direcciones | Login/logout, edición/envío/facturación, pedido propio; denegar datos/pedido de otro usuario y guardar sin cambiar el ajeno |
| Arrepentimiento | Invitado sin pedido obligatorio, errores, envío/constancia, persistencia ante fallo correo, gestión/notas/reintento autorizado y protección A/B |
| Administración | Roles mínimos, pedidos/HPOS, catálogo, permisos legales y ajustes; sin exposición por REST privada |
| Configuración | URLs/permalinks, PHP web/CLI, sesiones/cookies, caché privada, recursos externos, cron/Action Scheduler y noindex DEMO |
| Distribución/instalación | ZIP instalables en base nueva, versiones/manifiesto, ausencia de usuarios/pedidos/legales previos y herramientas DEV; repetir instalación vacía con el script final |
| Backup/restauración | Recuperación completa aislada al punto original, hashes/agregados, medios/usuarios/pedidos/legales/eventos y tiempos medidos; guard rechaza destino que recibió datos nuevos |
| Actualización | Reposición de ZIP sin reemplazar DB, comparación separada que incluye un pedido posterior al backup; una versión distinta/migración exige su ensayo propio |
| Operación local | WP-Cron limpia transient sintético vencido y Action Scheduler ejecuta limpieza nativa de sesión sintética; no equivale a cron autónomo remoto validado |
| Hosting real | TLS/DNS, reglas/permisos, caché del proveedor, cron sin visitas y correo real autorizado; local no sustituye esta fila |

Las pruebas de recuperación y actualización se hacen sobre instalaciones desechables. El backup inicial no contiene el pedido posterior usado para probar preservación durante la reposición de código: restaurarlo en un segundo entorno recupera sólo su punto de corte y nunca se aplica al origen que recibió actividad. No restaurar una copia antigua sobre clientes activos. Retirar el helper HTTP SAPI después de usarlo y comprobar su 404; el control de escritura del laboratorio no certifica aislamiento de sistema entre clientes del hosting. La prueba del entorno real puede requerir una ventana aprobada y destinatarios expresos; si no existen, registrar pendiente/no realizado. No repetir miles de pruebas históricas para sustituir controles de despliegue.

## Acta de aprobación y clasificación

El acta concreta identifica release, dominio/destino, servicios, contenido, pendientes, evidencia, costos aceptados, responsables, ventana y reversión. **Aprobación comercial, autorización DNS/publicación, autorización de email y autorización de pagos se registran de forma explícita por su alcance.** No se infieren del presupuesto ni del cierre técnico H11.

| Área | Estados admitidos | Evidencia necesaria |
| --- | --- | --- |
| A. Código de la plantilla | READY / NOT READY | Release y QA de código, sin secretos |
| B. Instalación local reproducible | READY / NOT READY | Procedimiento y evidencia del laboratorio desechable |
| C. Hosting seleccionado y validado | READY / NOT READY / PENDIENTE DE DATOS | Contrato real y comprobación sobre ese hosting |
| D. Staging remoto | READY / NOT READY / NO REALIZADO | Entorno autorizado, aislado y reporte real |
| E. Seguridad y backups | READY / NOT READY / CONDICIONAL | Controles efectivos, externo/cifrado, restore y RPO/RTO |
| F. Emails reales | READY / NOT READY / NO CONFIGURADOS | Permiso, proveedor/dominio y recepción real autenticada |
| G. Métodos de pago reales | READY / NOT READY | Método elegido autorizado y aceptación específica; MP mantiene su bloqueo |
| H. Cumplimiento comercial/legal del cliente | READY / NOT READY / PENDIENTE DE DATOS | Datos/textos/circuitos auténticos aprobados por titular/asesores |
| I. Autorización final de lanzamiento | AUTORIZADO / NO AUTORIZADO | Permiso expreso sobre entrega concreta y destino |

El estado efectivo de H11 se completa en [preparación H11](h11-production-readiness.md), no en esta plantilla. Mientras haya un bloqueo crítico, información esencial faltante o una autorización requerida ausente, mantener las ventas cerradas y la clasificación productiva negativa. Un lanzamiento posterior requiere volver a verificar vigencia de plan, software y normas, porque este documento tiene fecha.
