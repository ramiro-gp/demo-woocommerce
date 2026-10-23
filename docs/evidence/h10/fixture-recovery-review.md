# Revisión de importación y recuperación H10

Fecha de la prueba local: 22 de octubre de 2026. Entornos independientes ORIGEN CASA (`localhost:8082`) y tercera instalación descartable (`localhost:8083`). Ninguna acción de recuperación apuntó a TRAMA.

## Fallo real observado y recuperación acotada

La primera importación de ORIGEN CASA encontró una llamada a `wp_get_global_styles_post_id()` que no existe en la versión de WordPress instalada. Se corrigió para utilizar `WP_Theme_JSON_Resolver::get_user_global_styles_post_id()`.

La inspección posterior mostró cero productos, dos adjuntos propios de marca, tres adjuntos totales incluyendo el placeholder nativo, y opciones de marca ya persistidas. La transacción no había revertido todo: configurar HPOS puede ejecutar DDL a través de Core y provocar commits implícitos. Por eso el importador y su documentación declaran explícitamente que el rollback SQL es de mejor esfuerzo, no una garantía de atomicidad. La configuración vacía de HPOS se realiza ahora antes de comenzar la transacción de contenido.

Se implementó y ejecutó `recover-early`. Antes de modificar datos comprueba:

- fase `preparing` e integridad de las fuentes;
- configuración nativa exactamente igual al estado temprano esperado;
- ausencia de productos, clientes, pedidos, solicitudes legales, zonas, atributos y menú del fixture;
- exactamente dos adjuntos propios cuyos archivos originales coinciden por SHA-256 con el logo y el icono versionados;
- referencias de logo, favicon y cabecera de email coherentes con esos dos adjuntos.

La recuperación borró únicamente esos dos adjuntos generados mediante `wp_delete_attachment()`, quitó sus referencias y la fase interrumpida, y conservó el placeholder y las opciones DEMO. No eliminó recursos comerciales, bases, volúmenes ni usuarios. La siguiente importación completó diez productos, dos productos variables y las comprobaciones iniciales. Este procedimiento no acepta un catálogo parcialmente importado ni otras modificaciones inesperadas.

## Asociación nativa de estilos y repetibilidad

La primera visita permitió detectar dos diferencias técnicas adicionales: WP-CLI usa usuario 0 y la asignación de `wp_theme` por `tax_input` puede omitirse por capacidad; además WordPress inicializa `custom_css_post_id=-1` al consultar CSS nativo.

El importador ahora asocia siempre el post de Global Styles al tema mediante `wp_set_object_terms()`, comprueba errores y normaliza la ausencia de la referencia de CSS a `-1` al calcular la huella. Un ID positivo conserva su valor y sigue detectándose como cambio. Las huellas de configuración incluyen diagnósticos por opción, theme mod y envío, sin publicar valores privados.

La reparación de ORIGEN CASA verificó antes de actuar que el contenido completo del estilo original 14 conservaba exactamente su huella; todos los productos, páginas, taxonomías, medios, menú y cupón seguían intactos. Se reprodujo la huella completa anterior quitando únicamente la nueva clave de caché `custom_css_post_id=-1`, lo que probó el alcance exacto de la diferencia. Se asoció el estilo original y se enviaron a papelera sólo los dos estilos diagnósticos 68 y 69 previamente inspeccionados: ambos tenían únicamente el JSON nativo vacío y ninguna relación con un tema. No se refrescaron huellas de recursos comerciales para ocultar cambios. `verify` pasó después de la reparación. Evidencia: [style-repair.json](style-repair.json).

## Configuración nativa final de la DEMO

La instalación limpia de WooCommerce traía Coming Soon activo. Se incorporaron `woocommerce_coming_soon=no` y `woocommerce_store_pages_only=no` al fixture para permitir QA sin sesión, conservando `blog_public=0` y acceso exclusivamente local. No se trata de un fallo de Commerce Core.

También se verificó en el código instalado de WooCommerce 11.2.1 que `EmailStyleSync` escucha cambios de tema y Global Styles. Al estar habilitada su sincronización nativa, cinco colores explícitos del fixture habían pasado a los colores derivados del tema. La configuración final usa `woocommerce_email_auto_sync_with_theme=no` y conserva la paleta de correo versionada de `client.json`; el cliente puede cambiar posteriormente este ajuste nativo si prefiere sincronizar estilos.

Se agregó 1 rem (16 px) al espacio superior del hero del preset para separar el contenido del acceso legal en móvil. La migración de las dos instalaciones ya importadas reconstruyó exactamente la huella de fuentes anterior revirtiendo sólo esa cadena CSS; verificó que el estilo nativo completo coincidía con el preset anterior y que los demás recursos estaban intactos. La huella de configuración anterior se reconstruyó admitiendo únicamente los cinco valores de email originales o los valores exactos derivados del tema, comparados con las huellas guardadas. La migración sólo cambió esa regla CSS, los ajustes nativos documentados y las correspondientes huellas de fuente/estilo/configuración; no renovó huellas de productos, páginas ni otros recursos para ocultar diferencias.

Ambas migraciones pasaron. Una petición pública sin sesión a Alba devolvió HTTP 200, sus campos nativos Material y Acabado y ausencia de la pantalla Coming Soon. Evidencia: [ORIGEN CASA](native-demo-finalize-origen.json) y [tercera instalación](native-demo-finalize-third.json).

## Recuperación reproducible de preflight

En la tercera instalación, con WordPress/WooCommerce y páginas nativas preparados pero sin contenido de cliente, se ocultó temporalmente el hero versionado dentro del mismo directorio y se restauró en un bloque `finally`.

El importador falló con `Missing required asset before import: ambiente-origen.jpg` y código distinto de cero. Las listas de IDs de páginas, productos, variaciones, adjuntos, elementos de menú y Global Styles; los menús, pedidos y clientes; la existencia del marcador de contenido y la huella conjunta de credenciales/salts permanecieron idénticos. No apareció un marcador de importación parcial. El archivo fuente fue restaurado. Evidencia: [third-install-preflight.json](third-install-preflight.json).

Después se ejecutó el instalador documentado sobre el mismo entorno preparado y se completó el fixture, sin regenerar credenciales ni copiar datos de otra tienda. La repetición completa posterior se registra en [third-install-repeat.json](third-install-repeat.json): compara IDs de páginas, productos, variaciones, adjuntos, menú y Global Styles, huella completa del fixture y huella de credenciales/salts, además de la disponibilidad pública del producto variable.

## Límites operativos

`seed` no es una sincronización continua de catálogos: un pedido que descuenta stock o una edición comercial hace que la huella deje de coincidir y se rechaza la reimportación sin sobrescribir los datos. Las imágenes de variaciones son representativas de una demo y no documentan un producto real. Para una importación fallida en una etapa no cubierta por `recover-early`, se deben inspeccionar los objetos persistidos y medios potencialmente huérfanos, preparar una reparación explícita o recrear únicamente la instalación descartable. Ningún procedimiento aquí autoriza borrar una tienda comercial.
