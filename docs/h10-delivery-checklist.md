# H10 — Lista reutilizable de entrega

Copiar esta lista por comercio y registrar responsable, fecha, resultado y enlace a evidencia. Marcar **pendiente**, **aprobado** o **no aplica** con motivo; no convertir una casilla en una afirmación sin prueba. Distinguir la aceptación de DEMO local de la autorización de lanzamiento. [Guía práctica](h10-new-client-playbook.md) · [Resultados H10](h10-rebranding-portability.md).

Comercio/entorno: __________ · Versión/commit: __________ · Fecha: __________

Titular que aprueba: __________ · Implementador: __________ · Pendientes: __________

## Alcance, datos y acceso

- [ ] Alcance y responsables acordados; categorías, variantes, regiones, moneda, pagos y logística definidos.
- [ ] Titularidad de marca/fotos/fuentes y licencias registradas.
- [ ] Identidad legal, contactos atendidos y responsables de atención/privacidad aportados y aprobados por el titular.
- [ ] Accesos nominados mínimos y mecanismo seguro de entrega/rotación; sin contraseñas o API keys en Git.
- [ ] Catálogo recibido con SKU, textos, precios/impuestos, stock, atributos, variantes, peso/medidas e imágenes.

## Entorno independiente

- [ ] Instancia, proyecto Compose, puertos, URLs y configuración propios; el objetivo fue verificado antes de operar.
- [ ] Base, volúmenes, uploads, usuarios, pedidos, solicitudes legales y logs separados.
- [ ] Contraseñas y salts nuevos; ninguna copia de base comercial histórica ni secretos de otra tienda.
- [ ] Mailpit propio o identificación inequívoca; destinatarios DEMO `.test`; noindex/private; pagos reales deshabilitados.
- [ ] Procedimiento de creación limpio probado; hashes/versiones y dependencias registrados.
- [ ] Reejecución revisada: sin duplicados/colisiones ni pérdida de datos; límites de seeds/importadores explícitos.
- [ ] Fallo de instalación/recuperación verificado con datos propios preservados y sin reset de otra instancia.

## Marca y contenido

- [ ] Commerce Base y plugins comunes, sin duplicación/fork por comercio.
- [ ] Nombre/descripción, logo/favicon, paleta, tipografía y modo oscuro correctos.
- [ ] Global Styles asociado al theme de esta instalación y persistente tras visitas de invitado; sin volver al preset anterior.
- [ ] Home propia: hero, categorías, destacados, beneficios verificables, editorial y acceso legal visible.
- [ ] Menú principal/footer/redes/WhatsApp coherentes, URLs de esta instalación y sin IDs copiados.
- [ ] Lookbook funciona con productos propios o está retirado; hotspots accesibles.
- [ ] Exit Intent configurable; sin descuentos inexistentes ni interferencia en checkout/cuenta/legales.
- [ ] Header/footer, metadatos, JSON-LD/Open Graph cuando corresponda y medios sin identidad ajena.
- [ ] Ninguna marca, producto, contacto, banco o fiscalidad de otra tienda visibles por accidente.

## Catálogo y compra

- [ ] Simple y al menos dos variables de la DEMO; atributos propios sin supuesto universal de Talle/Color.
- [ ] Invitado abre fichas/catálogo reales y el título coincide con el producto; ningún HTTP 200 de Coming Soon se toma como prueba de storefront funcional.
- [ ] Selectores, swatches/textos, variaciones disponibles/agotadas y selección por teclado comprobados.
- [ ] Oferta, agotado, stock, precio/rango, imágenes y relacionados correctos.
- [ ] Filtros/categorías/orden y búsquedas corresponden al catálogo actual.
- [ ] Carrito/mini-cart: agregar, cantidades, eliminar, cupón y recálculo correctos.
- [ ] Moneda/países/impuestos y envío nativos revisados: tarifa, umbral gratis, cobertura y falta de cobertura.
- [ ] Checkout con facturación/envío, términos, errores útiles y exclusión de promociones.
- [ ] BACS crea un pedido propio y confirmación con importes, envío y stock esperados; no mueve dinero.
- [ ] Estado/conciliación BACS entendido por el operador; datos de demostración sin cuenta utilizable.
- [ ] Mercado Pago completamente desactivado en ORIGEN CASA; sin SDK/recursos suyos; no declarado aprobado.

## Cuentas, correo y legales

- [ ] Usuario exclusivo: registro/login, direcciones, pedidos propios y acceso ajeno denegado.
- [ ] Cliente de otra tienda ausente y sin exposición de pedidos ajenos.
- [ ] Email de pedido y cuenta: remitente, marca, logo/texto, colores, productos y enlaces de este sitio.
- [ ] Mailpit recibe constancia legal y aviso interno; sin envíos reales, enlaces de recuperación o secretos en evidencia pública.
- [ ] Privacidad, términos, envío, cambios/devoluciones y arrepentimiento propios; DEMO identificado mientras corresponda.
- [ ] Ajustes de identidad/legal y selección nativa de privacidad/términos consistentes con checkout y footer.
- [ ] BOTÓN DE ARREPENTIMIENTO visible y directo; invitado/no-JS recibe código; sin cuenta/motivo exigidos indebidamente.
- [ ] Gestión administrativa autorizada y constancias verificadas; recibir/cerrar no equivale a cancelar o reembolsar.
- [ ] Sin solicitudes históricas/fiscalidad ajenas; no se inventaron CUIT o Data Fiscal.
- [ ] Retención, solicitudes de privacidad y operación de atención revisadas; aprobación legal pendiente identificada cuando corresponda.

## QA, aislamiento y entrega

- [ ] Home/header/footer, catálogo/categoría, ficha/variantes, carrito, checkout, cuenta, correo y legales revisados visualmente.
- [ ] 390/768/1366/1920 px; claro/oscuro; teclado, foco visible, menú, mini-cart, legibilidad y overflow.
- [ ] Recursos locales sin errores; consola sin fallos propios; terceros/cookies inventariados y evaluados según entorno.
- [ ] Cambio de precio/Home/página legal en B deja A intacta; usuario/pedido en B no crea datos ni modifica stock en A.
- [ ] Ambas tiendas simultáneas; evidencia de bases/volúmenes/uploads/configuración/correos separados.
- [ ] Corrección de código común aplicada a ambas conservando identidad y datos; regresiones relevantes H9 comprobadas.
- [ ] Automatización y revisión humana diferenciadas; límites de accesibilidad/rendimiento y casos pendientes declarados.
- [ ] Informe, capturas, comandos y resultados entregados; ningún dump/upload privado/credencial entra en Git.
- [ ] Commits/push en rama de revisión y árbol limpio; no merge a main hasta autorización final.
- [ ] Capacitación de catálogo/pedidos/conciliación/atención legal, accesos y responsabilidades entregadas.
- [ ] Plan de mantenimiento/respuesta a incidentes acordado, sin supuestos de precio o hosting.

## Puerta de lanzamiento — H11 pendiente, no ejecutada en H10

- [ ] Verificar hosting contratado: runtime web seguro, extensiones, límites, DB/HPOS, almacenamiento/permisos y cron/colas.
- [ ] Dominio, DNS, HTTPS/renovación, URLs definitivas, proxy y cookies seguras preparados y probados con autorización.
- [ ] Correo transaccional autenticado, entrega real/alertas, datos y credenciales por entorno autorizados.
- [ ] Backups offsite, retención, restauración y responsables comprobados.
- [ ] Caché/CDN/compresión excluyen carrito/checkout/cuenta/formulario legal y sesiones; dos clientes aislados.
- [ ] Identidad/fiscalidad, logística, privacidad/proveedores/cookies, condiciones y contenido real aprobados; fixtures/usuarios/secretos DEMO retirados mediante proceso seguro.
- [ ] Medios de pago habilitados sólo tras validación separada; bloqueos Mercado Pago resueltos o proveedor excluido por decisión documentada.
- [ ] Monitoreo, métricas, logs/rotación, actualizaciones, incidentes y rollback operativos.
- [ ] QA de producción/staging y autorización expresa de publicación completados en H11.

**Aceptación registrada:** __________ · **Resultado DEMO:** __________ · **Autorización de lanzamiento:** pendiente de H11.
