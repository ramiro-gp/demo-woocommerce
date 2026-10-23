# H11 — Preparación técnica, riesgos y autorización de lanzamiento

Fecha: **23/10/2026, Argentina**. Alcance: distribución reutilizable, investigación oficial, procedimientos y laboratorio exclusivamente local. **H11 TECHNICAL READY. PRODUCTION NOT READY; lanzamiento NO AUTORIZADO.** Nada de este informe autoriza ventas, pagos, correo real, contratación, DNS o publicación.

**Cierre documental de H11 — 23/10/2026, commit `baae9e4`:** incorporó los datos reales de hPanel aportados por el titular y su decisión definitiva de conservar Premium Web Hosting legacy. Esa actualización modificó únicamente los dos informes de hosting/preparación: no cambió código ni paquetes ni repitió QA funcional. Los PASS de este documento corresponden al laboratorio H11 ya registrado, no a Hostinger. El cierre definitivo del roadmap autoriza ahora la consolidación por fast-forward y una etiqueta Git candidata; el [estado ejecutivo](PROJECT-STATUS.md) registra esa referencia sin habilitar producción.

## 1. H10 consolidado y alcance preservado

Repositorio `ramiro-gp/demo-woocommerce`. Antes de trabajar se comprobó la rama aprobada, árbol limpio, fetch, coincidencia con el remoto y los commits completos:

- `2adc01169c7bae44721946578158e55b67f2f94b`.
- `40dd2a1ba4588e1fd5d618fcdaae433c31d23912`.

El scanner H10/H9 pasó; no se encontraron secretos conocidos, dumps, uploads privados ni archivos runtime entre candidatos versionados. Al consolidar H10 se adelantó `main` **sólo mediante fast-forward**, se hizo push normal y se confirmó entonces `main = origin/main = 40dd2a1ba4588e1fd5d618fcdaae433c31d23912`. H11 se desarrolló en `codex/h11-production-readiness`. La autorización posterior de cierre permite integrarlo por fast-forward y crear `template-v1.0.0-rc1`; se mantiene el [registro histórico sanitizado](evidence/h11/bootstrap.json) y el estado actual se resume en [PROJECT-STATUS](PROJECT-STATUS.md).

No se ejecutaron seeds, pedidos, restauraciones ni cambios de contenido en TRAMA u ORIGEN CASA. No se alteraron pruebas históricas H7–H10. El cambio compartido mínimo es **Commerce Base 1.9.1**: si la vista de muestra opcional no se distribuye, su URL responde 404; con el archivo original presente conserva su comportamiento anterior. Las instalaciones históricas no recibieron sincronización del theme.

## 2. DockerAI y recuperación de la herramienta

`EnableDockerAI` controla **Gordon de Docker Desktop**; no es un servicio de este proyecto ni se necesita en producción. El respaldo `settings-store.json.h10-stale-20261022-213920` confirma que antes de H10 estaba en `true`; el actual sigue en `false`. H10 lo desactivó para recuperar el fallo de inicialización del Inference manager; no significa que antes estuviera funcionando correctamente, pues Docker Desktop estaba detenido/fallando.

En H11 el intento de arrancar Desktop reprodujo el error AF_UNIX del socket `dockerInference`. Se cerró el intento fallido y se preservaron exclusivamente los dos directorios de sockets, con sufijo `.h11-stale-<fecha>`, sin borrar volúmenes, imágenes, bases o WSL. El motor volvió a arrancar, versión 29.6.1. Los contenedores ajenos se dejaron bajo sus propias políticas de reinicio; no se ejecutaron comandos de modificación sobre ellos. No hubo prune, reset de fábrica ni reinstalación.

**DockerAI no se restauró y el titular no autoriza por ahora su reactivación.** Se mantiene desactivado según el último estado documentado; no se modifican configuraciones globales ni se reinicia Docker para recuperarlo. La actualización documental no interviene en el motor operativo. Una eventual reconsideración requiere una nueva autorización expresa por el impacto global y riesgo de repetir el fallo; no queda como tarea pendiente que impida cerrar H11. Los respaldos se conservan; no se debe copiar el JSON antiguo entero sobre configuraciones nuevas. [Documentación Gordon](https://docs.docker.com/ai/gordon/how-to/docker-desktop/), [incidencia oficial consultada durante la investigación H11](https://github.com/docker/desktop-feedback/issues/531).

## 3. Decisión económica y aislamiento

**Datos verificados por el titular en hPanel y comunicados el 23/10/2026**, sin inspección directa del agente ni factura revisada. Sustituyen la estimación anterior de aproximadamente 3 sitios y los campos contractuales entonces desconocidos:

| Campo de la suscripción | Dato aportado |
| --- | --- |
| Producto | **Premium Web Hosting — legacy** |
| Sitios permitidos / creados | **25 / 8** |
| Disco / inodos | **25 GB / 400.000** |
| CPU / RAM | **1 núcleo / 2048 MB**, compartidos por la suscripción |
| Procesos / PHP workers | **80 / 40**, límites distintos; no garantizan concurrencia de checkout |
| Ancho de banda | Ilimitado según el campo mostrado, sin implicar CPU/RAM ilimitadas |
| Servidor / backups | **South America (Brazil) / Estados Unidos** |
| Uso de Recursos observado | **0,04 GB de disco / 763 inodos**; alcance agregado de los ocho sitios no comprobado |
| Renovación mostrada actualmente | **ARS 130.788** |
| Renovación automática | **Activada**, sin cambios |
| Próxima facturación / vencimiento | **15/08/2029 / 29/08/2029** |

**Decisión del titular: conservar Premium. No actualizar a Unlimited, contratar otro servicio, renovar anticipadamente, cambiar de hosting ni modificar suscripción/facturación.** Premium queda destinado a las webs actuales, landings y eventuales pruebas controladas autorizadas por separado. La [evaluación completa](h11-hosting-decision.md) conserva las alternativas como referencias informativas, sin recomendar ni ejecutar un cambio del plan existente.

Para ecommerce reales se priorizarán cuentas administradas independientes, preferentemente contratadas por el propio cliente, cuando aislamiento, volumen de ventas, recursos o autonomía lo justifiquen. No se agrupan tiendas indiscriminadamente bajo el Premium. El VPS autogestionado sigue descartado como opción inicial por su carga operativa; cualquier elección futura de un cliente requerirá su evaluación y autorización propias.

**Evaluación técnica, sin mediciones de carga:** un núcleo y 2048 MB constituyen el presupuesto compartido del plan, no de cada sitio ni de cada worker. Solicitudes dinámicas de WooCommerce y cron/colas/administración de distintos sitios pueden competir por CPU y memoria, con límites de I/O/DB/conexiones aún por conocer. Los 40 workers y 80 procesos no garantizan ese número de checkouts fluidos; la caché pública no elimina el trabajo privado de carrito/checkout/Store API. El cupo de 25 sitios no es capacidad para 25 WooCommerce, y los ocho creados tampoco revelan su carga o actividad.

Las lecturas de 0,04 GB/763 inodos no se extrapolan a CPU, RAM, margen disponible o visitas. Falta comprobar selector/alcance de pantalla, período/actualización y correspondencia con los ocho sitios. No se inspeccionaron sus aplicaciones ni se hizo instalación, QA remoto o prueba de carga en Hostinger o sitios públicos. Premium puede ser candidato a una instalación controlada si sus servicios cumplen H11; **no está validado para WooCommerce en producción**.

Bases y usuarios SQL separados deben complementarse con restricciones reales de archivos y usuarios de sistema: PHP comprometido bajo permisos compartidos podría alcanzar archivos/credenciales de otros sitios. No se verificó aislamiento remoto entre estos sitios. Brasil como región de servidor no demuestra latencia efectiva; backups en EE.UU. requieren revisar cobertura, acceso/cifrado, retención y tratamiento/transferencias con el titular/asesores antes de datos reales. La ubicación de la copia no acredita por sí sola privacidad, independencia de cuenta ni recuperación.

| Referencia económica | Primer período / estado | Renovación mostrada o publicada |
| --- | --- | --- |
| **Premium legacy del titular — seleccionado** | Contratado; importe original no aportado. Vencimiento mostrado 29/08/2029; H11 no añade compras ni renovación anticipada | **ARS 130.788** actualmente en hPanel; ciclo e impuestos pendientes, sin garantía para 2029 |
| Hostinger Unlimited AR — referencia pública, no seleccionado | ARS 239.952 adelantados por 48 meses; equivalente ARS 4.999/mes | ARS 21.999/mes; equivalente aritmético ARS 263.988/año |
| SiteGround GrowBig — referencia pública, no contratado | USD 59,88 por 12 meses, cálculo de USD 4,99/mes | USD 359,88 por 12 meses, cálculo de USD 29,99/mes |

Los precios públicos se conservan de la investigación H11 del 23/10/2026, sin volver a consultarlos en esta actualización; impuestos/extras no incluidos. El importe de hPanel es un dato distinto: sin ciclo confirmado no se mensualiza, anualiza ni calcula ahorro frente a esas ofertas. No es un precio garantizado hasta 2029 ni una oferta anticipada aceptada. La próxima facturación del 15/08/2029 y el vencimiento del 29/08/2029 son campos separados, sin inferir la causa de su diferencia. Permanecen pendientes desglose fiscal, ciclo e importe original. [Hostinger AR](https://www.hostinger.com/ar/hosting-web), [SiteGround WooCommerce](https://www.siteground.com/woocommerce-hosting.htm).

Conservar Premium no añade una contratación por H11. El costo incremental de futuras instalaciones no se deriva del cupo de sitios: hay consumo y mantenimiento, y servicios/dominios/email/backup pueden tener costos propios. Una cuenta independiente de un cliente requiere presupuesto completo y autorización futura; no se inventan precios ni condiciones comerciales.

## 4. Distribución y compatibilidad

`scripts/build-release.ps1` genera una distribución **1.0.0**, con seis ZIP instalables, versiones individuales e inventario SHA-256. Utiliza exclusivamente archivos confirmados en Git y rechaza cambios de código sin commit. Dos construcciones con la misma revisión produjeron los mismos hashes ZIP. [Manifiesto de los paquetes probados](evidence/h11/release-manifest.json), [control de exclusiones](evidence/h11/release.json).

| Paquete | Versión |
| --- | --- |
| Commerce Base | 1.9.1 |
| Commerce Core | 1.1.2 |
| Commerce Communications | 1.0.1 |
| Commerce Order Integrity | 1.0.0 |
| Commerce Account Navigation | 1.0.0 |
| Commerce Lookbook | 1.0.0 |

Los artefactos están en `.local/h11-release/1.0.0/`, ignorados por Git; se regeneran desde las fuentes. El material de entrega `support/` mantiene documentación y preflight/inicialización separados del webroot. Los ZIP no contienen WordPress, WooCommerce, MP, DB, credenciales, usuarios/pedidos/legales QA, logs, backups, uploads, fotografías editoriales DEMO, patrones hero/story, showcase ni preset ORIGEN CASA. Los originales DEMO siguen en el repositorio, optativos y fuera de esta distribución.

Producción utiliza WordPress/WooCommerce oficiales, PHP y DB del hosting, instaladores nativos/WP-CLI y sus propios medios/configuración. **No utiliza Compose en producción**. `initialize.php` es opt-in y sólo acepta una instalación nueva: comprueba usuarios/contenido/historia/ajustes, mantiene noindex/Coming Soon y apaga gateways. El contenido comercial se configura desde cero; no se entrega una copia de las demos.

La arquitectura de distribución permite evaluar una instalación futura sin Docker; su compatibilidad específica con Premium sigue pendiente. Faltan versiones/parches de PHP web y CLI, extensiones y `memory_limit`, motor/versión/tamaño/conexiones de DB, I/O/IOPS, SSH/WP-CLI, permisos, SSL, cron/Action Scheduler y caché efectivos. No se deduce memoria PHP a partir de los 2048 MB del plan. Los ZIP y resultados de instalación local permanecen sin cambios; no se ejecutó preflight ni se instaló la distribución en Hostinger.

El laboratorio usa WP 7.1.3, WooCommerce 11.2.1, PHP 8.4.27, MariaDB 10.11.19 y WP-CLI 2.12.0, con imágenes fijadas. Tres proyectos/volúmenes propios: fuente `qa` :8184, destino `restore` :8185 y repetición sin fixture :8188. Mailpit sólo loopback :8186/:8187/:8189; MariaDB sin puerto publicado. El código funcional se instaló desde los ZIP, **sin bind del repositorio**. Los únicos montajes de herramientas son de laboratorio. [Infraestructura](evidence/h11/infrastructure.json).

La repetición `install -Instance repeat` completó de principio a fin con el script corregido, sin importar catálogo, usuarios ni historial de otra instalación. Se verificó PHP **web**, no sólo CLI: 256 MiB, errores ocultos, logging, OPcache, uploads escribibles, editor desactivado y archivos/directorios de código no escribibles para el proceso web. Los permisos por propietario root/grupo del laboratorio simulan un canal controlado de despliegue; no prueban separación de usuarios en un hosting compartido. WP-CLI puede sobrescribir memoria/display_errors, por lo que su diagnóstico no reemplaza el SAPI web.

## 5. QA representativa y recuperación

| Evidencia | Resultado y límite |
| --- | --- |
| [Navegador fuente](evidence/h11/browser.json) y [checkout nativo inicial](evidence/h11/browser-native-checkout.json) | PASS: Home/catálogo/variable/swatches/Lookbook, Mini-Cart, Carrito/Checkout Blocks, BACS/HPOS, cuenta/dirección y permisos/gestión legal. Se conserva evidencia del submit inicial para que una repetición sin duplicar pedido no lo oculte |
| [Emails](evidence/h11/emails.json) | PASS: 21 mensajes sólo capturados, nuevo pedido/BACS/Processing/Completed/cancelación/reembolso, recuperación de contraseña y constancia/notificación legal; todos sintéticos. No certifica recepción SMTP real |
| [Cron y Action Scheduler](evidence/h11/operations.json) | PASS: limpieza nativa de transient vencido y sesión QA vencida. La instalación nueva requirió completar la migración nativa de Action Scheduler antes del runner por grupo. No valida cron autónomo del hosting |
| [HTTP/caché](evidence/h11/http-cache.json) | PASS: privados y Store API no-store/private, noindex/robots/sitemap, cabeceras, bloqueo de archivos sensibles y ausencia del showcase/imagen DEMO excluidos. Sin CDN/LiteSpeed/Redis ni TLS remoto probado |
| [Origen](evidence/h11/source-snapshot.json) y [backup](evidence/h11/backup.json) | DB completa y archivos copiados con web quiescida, SHA-256, sólo QA privada. Sin cifrado ni copia externa productiva |
| [Código antes](evidence/h11/code-before.json) y [después](evidence/h11/code-after.json) | PASS: reinstalación de los mismos ZIP sin importar DB, incluyendo pedido QA **36 creado después del backup**. Digests de negocio idénticos. No afirma haber probado una migración de versiones distintas |
| [Guard de destino](evidence/h11/guards.json) | PASS: cliente QA nuevo hace rechazar el destino; tras retirar sólo ese probe con identidad comprobada, acepta el estado vacío. Tar exige rutas seguras y archivos/directorios regulares |
| [Restauración](evidence/h11/restore.json), [recuperación](evidence/h11/recovery.json) y [navegador del destino](evidence/h11/restore-browser.json) | PASS: 70 checks de integridad y 74 de navegador; mismo digest del punto original antes de logins. Cliente recupera acceso, pedido/dirección; gestor abre HPOS y solicitud legal/estado/asociación/eventos. Las sesiones cambian legítimamente después de autenticarse; no se guardaron formularios comerciales. No se restauró sobre el origen con actividad posterior |

Navegador: cero errores JS no controlados y cero recursos locales fallidos. Los intentos nativos de emojis a `s.w.org` se registraron y bloquearon; no se promete ausencia universal de terceros. Mercado Pago no pidió recursos. Se revisaron capturas de Home móvil/escritorio, producto y Lookbook; el contenido es inequívocamente DEMO. No se repitieron las miles de pruebas históricas.

Los helpers HTTP temporales se retiraron, con 404 comprobado. Al cerrar se detienen únicamente los tres proyectos H11, conservando sus volúmenes/secretos/backup privados. Las instalaciones históricas y contenedores ajenos no recibieron comandos de stop/reset/restore.

El backup completo incluye HPOS y tablas auxiliares, posts/meta, usuarios, catálogo/taxonomías, options, stock, sesiones, Action Scheduler, las tres tablas legales y archivos/configuración. La comparación lógica usa tablas comerciales, IDs/estados, opciones seleccionadas y hashes de medios; excluye los cambios efímeros de sesiones/colas/logs/transients. La copia privada conserva esos datos aunque no se compare su actividad posterior byte a byte.

Se recuperaron **2 productos, 2 variaciones, 3 usuarios, 4 pedidos y 1 refund, 2 solicitudes legales con 9 eventos y la imagen original con 6 derivados**. El backup QA tardó 10,81 s (DB 1.083.325 bytes y archivo comprimido 50.725.237 bytes); la fase de restauración tardó 21,70 s antes de QA posterior. Son tiempos del equipo y conjunto locales, no objetivos prometidos para un cliente. El pedido 36 posterior al backup se conserva en el origen tras reemplazar código y no aparece en la restauración al punto anterior: justamente por eso no debe usarse ese backup para sobrescribir el origen activo.

Incidencia resuelta del laboratorio: el cliente MariaDB 11.8.9 de la imagen WP-CLI exigía TLS frente al servidor local sin TLS. El export acepta `--skip-ssl`; el import WP-CLI 2.12 hace una consulta preparatoria que no lo propaga. El import del ensayo usa el cliente MariaDB nativo, leyendo el secreto desde archivo hacia entorno, sin contraseña en argumentos/salida. El primer export fallido reinició la web en `finally`; los import fallidos dejaron el destino detenido. **Esta excepción de TLS se limita a la red local aislada y no se traslada a producción.**

El clon retiene configuración/salts propios de destino para no reutilizar credenciales de otro entorno; se recuperan los datos y el código. Formularios/sesiones/tokens previos pueden quedar invalidados. En recuperación de la misma tienda conservar salts del conjunto salvo rotación deliberada por incidente; no prometer continuidad de formularios abiertos después de una rotación.

## 6. Operación futura, responsables y bloqueos

| Área | Preparado / pendiente | Responsable y criterio de cierre |
| --- | --- | --- |
| Hosting | **Premium legacy seleccionado**; límites y campos de facturación aportados por el titular; validación remota pendiente | Completar ciclo/impuestos, alcance de consumo, límites restantes y aislamiento; verificar PHP web/CLI, DB, cron, caché, SSL, SSH y restore sólo con autorización posterior |
| Backups | Procedimiento y ensayo local; operación externa pendiente | Técnico/proveedor y suplente acordados: DB horaria/RPO orientativo ≤1 h, archivos diarios, completo semanal, retención y copia externa cifrada. RTO 4–8 h orientativo sujeto a cobertura/ensayo, sin SLA implícito |
| Actualizaciones | Reemplazar código, vaciar cachés y comprobar esquema compatible | Técnico: backup actual y staging; jamás empujar DB de staging sobre pedidos recientes. Cambios de esquema/versiones requieren ensayo propio |
| Recuperación | Restaurar aislado, proteger primero estado actual y conciliar posteriores | Técnico + comercio: inventario de pedidos/pagos/stock/cuentas/solicitudes posteriores y conciliación. Un backup antiguo no contiene actividad posterior |
| Email | Un transporte SMTP/API futuro; SPF/DKIM/DMARC y matriz preparados | Titular elige proveedor/remitentes/destinatarios y autoriza prueba; técnico comprueba recepción autenticada, límites/rebotes/alertas/privacidad. Sin emails reales durante H11 |
| Privacidad y ubicación de datos | Servidor Brasil y backups EE.UU. informados por el titular; revisión productiva pendiente | Comercio/asesores/técnico revisan tratamiento/transferencias, proveedores, retención, acceso y cifrado; ubicación por sí sola no acredita cumplimiento |
| Dominio/DNS/HTTPS | Procedimientos NIC/Hostinger/otros y preservación de MX/TXT existentes | Titular autoriza cambio concreto; técnico inventaría zona, prueba TLS/canonical/www/IPv6 y prepara reversión. No transferencias ni modificaciones realizadas |
| Caché | Exclusiones privadas y Store API comprobadas en origen local | Técnico valida caché real del proveedor, cookies y sesiones; un único sistema elegido. OPcache local sí, object cache sólo si disponible; sin plugins superpuestos por defecto |
| Seguridad | Roles mínimos, MFA/canales, parches, logs privados, alertas e incidentes definidos | Cliente/proveedor/técnico asignan cuentas, accesos, aislamiento y cobertura. No se ha configurado MFA/monitoring remoto ni offsite en una cuenta real |
| Mercado Pago | **Bloqueo intacto**, nueva instalación sin MP | Proveedor + técnico: matriz [H11 MP](h11-mercadopago-review.md) y pruebas oficiales. 8.9.7 anunciada no cierra autenticidad/replay/temporalidad/importe/moneda/asociación |
| Legal/comercial | Políticas/datos DEMO sólo de prueba | Titular/asesores: nombre/razón social/CUIT/domicilio/contacto/Data Fiscal, impuestos/facturación, envío/cambios/garantías/arrepentimiento/privacidad/retención y canales atendidos. Aprobación fechada |
| SEO/analítica | DEMO noindex; activación futura preparada | Titular autoriza cuentas/indexación; técnico valida canonical/sitemap/metadatos/structured data/OG. Sin Search Console, analítica ni campañas conectadas |
| Mantenimiento | Plan diario/semanal/mensual e incidentes documentado | Cobertura y suplente por contrato; técnico atiende infraestructura, comercio atiende ventas/stock/pagos/reclamos. No se promete soporte 24/7 |

El [runbook](h11-deployment-runbook.md) contiene los pasos de instalación, actualización, recuperación y operación. El [checklist/playbook](h11-client-launch-checklist.md) cubre desde el presupuesto aceptado hasta accesos, capacitación y mantenimiento, con responsabilidades y autorización por alcance.

## 7. Clasificación separada

| Área | Estado H11 | Fundamento |
| --- | --- | --- |
| A. Código de la plantilla | **READY** | Código funcional/ZIP limpios y QA representativa; no incluye aprobación de MP |
| B. Instalación local reproducible | **READY** | Instalación nueva por ZIP y repetición completa con script final |
| C. Hosting seleccionado y validado | **PENDIENTE DE DATOS** | **Selección Premium legacy confirmada**, con límites y facturación aportados. Quedan datos técnicos/fiscales y QA remoto; no está validado para producción |
| D. Staging remoto | **NO REALIZADO** | No hubo entorno remoto autorizado |
| E. Seguridad y backups | **CONDICIONAL** | Controles/procedimientos locales; cifrado/offsite/MFA/monitoring y recuperación remota pendientes |
| F. Emails reales | **NO CONFIGURADOS** | Sólo Mailpit; no autorización de envío real |
| G. Métodos de pago reales | **NOT READY** | BACS ficticio; MP bloqueado; sin método real aprobado |
| H. Cumplimiento comercial/legal del cliente | **PENDIENTE DE DATOS** | Sin comercio real identificado, datos y políticas definitivas/aprobadas |
| I. Autorización final de lanzamiento | **NO AUTORIZADO** | Prohibición expresa de publicar/contratar/DNS/cobros sin aprobación |

## 8. Acciones manuales y límites de entrega

1. Conservar Premium y la configuración de facturación existente. Completar ciclo e impuestos del importe mostrado, factura/período original y datos técnicos aún ausentes; no solicitar ni aceptar upgrade o renovación anticipada como parte de H11.
2. Elegir el cliente/modelo de titularidad/aislamiento, responsable operativo, cobertura y presupuesto real. Reunir identidad, catálogo, medios, condiciones, datos fiscales y revisión del titular/asesores.
3. Autorizar separadamente un staging remoto restringido; ejecutar allí compatibilidad, restore, cron sin visitas, caché y TLS reales. El laboratorio no certifica LiteSpeed ni recursos del Premium heredado.
4. Elegir y autorizar proveedor/dominio/destinatarios para correo real; resolver pagos reales en un trabajo separado, conservando el bloqueo MP.
5. Ejecutar la consolidación Git y etiqueta candidata autorizadas para este cierre sólo tras sus verificaciones, sin force push ni sobrescribir etiquetas. Cada publicación/DNS/pago/envío mantiene su aprobación y validación independientes.
6. Mantener Gordon desactivado y no modificar configuraciones globales ni reiniciar Docker para reactivarlo: el titular lo denegó por ahora. No queda una aprobación de Gordon pendiente para cerrar este hito.

No hubo publicación, compras, cambios DNS, pagos reales, envío externo ni cuentas Google conectadas. El entorno local HTTP no demuestra Secure cookies/TLS, entregabilidad, rendimiento bajo carga ni aislamiento entre clientes del proveedor. Los límites de plan ahora aportados no demuestran esas prestaciones. Los tiempos locales del ensayo no son un RTO productivo.

**Efecto de esta actualización:** se completa la identificación del Premium, sus límites principales, ubicaciones, ocho sitios, importe mostrado y fechas; se cierra la elección de infraestructura existente. No cambia la conclusión técnica local ni levanta bloqueos de staging remoto, Mercado Pago, correo real, privacidad, configuración legal o lanzamiento. La información todavía pendiente pertenece a validación productiva y futuras decisiones autorizadas; no mantiene abierto el desarrollo de H11.

**H11 queda formalmente cerrado como hito técnico y documental.** La restricción anterior de no integrar su rama queda sustituida por la autorización expresa de consolidación final H0–H11 en main mediante fast-forward y etiqueta candidata `template-v1.0.0-rc1`. No se reescriben la historia ni las evidencias, no se repiten suites funcionales, no se regeneran ZIP válidos y no se comienza H12. [Estado ejecutivo y referencia](PROJECT-STATUS.md).

**H11 TECHNICAL READY.**

**PRODUCTION NOT READY.**
