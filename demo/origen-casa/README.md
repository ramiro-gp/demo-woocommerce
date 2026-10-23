# ORIGEN CASA · DEMO

Contenido ficticio independiente para probar la portabilidad de Commerce Base. No representa una empresa real, no contiene clientes ni pedidos y no utiliza datos fiscales ni bancarios operativos.

- `client.json`: configuración de este cliente ficticio: marca, contacto `.test`, estilo, moneda, emails, BACS, envíos y navegación.
- `content.json`: contenido editorial, diez productos, cuatro categorías, atributos nativos y páginas DEMO.
- `assets/`: fotografías originales generadas y marca gráfica propia. Sus archivos se importan a los uploads de **cada instalación** mediante las APIs de medios de WordPress.
- `docker/tools/h10-store.php`: importador de desarrollo, montado aparte del contenido. Commerce Base y Commerce Core nunca leen estos JSON durante una visita.

El importador requiere entorno `local`, URL `localhost` con puerto distinto de 8080, marcador `commerce_h10_installation=origen-casa`, usuarios `.test`, Commerce Base y WooCommerce/Core activos, Mercado Pago desactivado y una tienda inicialmente vacía. Los atributos son Material, Acabado, Capacidad y Medidas. No hay Talle ni dependencia de Color.

`seed` importa una sola vez; `inspect` devuelve IDs y URLs calculados en la instalación; `verify` compara huellas de los objetos propios. Una nueva ejecución de `seed` sobre contenido intacto no crea ni modifica objetos. Una edición de precio, stock, variaciones, página, menú, medios o configuración impide reimportar y se conserva. Un pedido que descuenta stock también hace que la huella deje de coincidir: el importador no es una herramienta de restauración de stock ni de actualización continua. Se debe preparar una migración explícita, o recrear únicamente el entorno descartable si ya no es necesario.

La instalación guarda fases `preparing`, `imported` y `complete`. Un fallo antes de importar medios o contenido no deja objetos de cliente. Durante la escritura se intenta una transacción SQL, pero hooks de Core o plugins pueden ejecutar DDL y provocar commits implícitos: no se promete atomicidad. HPOS se configura antes de la transacción para evitar el caso observado. Un proceso abortado deja una fase visible y nunca se adopta silenciosamente. Una interrupción entre el commit y la certificación puede completarse, después de inspeccionar la instalación, con `wp eval-file /opt/local-tools/h10-store.php certify`; esa acción sólo admite la fase `imported` y lee de nuevo los objetos persistidos. Para el fallo temprano observado, `wp eval-file /opt/local-tools/h10-store.php recover-early` sólo admite configuración igual al fixture, ninguna entidad comercial y exactamente los dos adjuntos propios de marca cuyos hashes coinciden con los originales. Borra únicamente esos adjuntos generados mediante la API nativa, quita sus referencias y la fase interrumpida, conserva el placeholder nativo y las opciones DEMO, y permite volver a ejecutar `seed`. No sirve para una importación de catálogo parcial. Otros fallos requieren inspeccionar los objetos persistidos y medios potencialmente huérfanos, o recrear el entorno descartable, sin tocar TRAMA.

La personalización se guarda en mecanismos nativos: opciones WordPress/WooCommerce, Global Styles, Custom Logo y Site Icon, menús, páginas, taxonomías, productos y variaciones. Los ajustes son editables desde WordPress. El preset reutilizable `commerce-base/styles/origen-casa.json` se copia a Global Styles al instalar: cambiar el preset de código no reemplaza ediciones del cliente.

El fixture desactiva `woocommerce_coming_soon` y `woocommerce_store_pages_only` para que visitantes sin sesión puedan probar el catálogo local, conservando `blog_public=0` y la publicación exclusivamente en loopback. Los cinco colores de correo son explícitos en `client.json`; el ajuste nativo `woocommerce_email_auto_sync_with_theme=no` evita que WooCommerce los cambie después de guardar Global Styles. Logo, remitente, enlaces y textos siguen siendo propios de esta instalación. Global Styles se asocia explícitamente a `wp_theme=commerce-base` porque la asignación implícita por `tax_input` puede omitirse cuando WP-CLI ejecuta con usuario 0.

## Procedencia de los recursos visuales

Generados el 22 de octubre de 2026 con el modo integrado de ImageGen, sin fotografías comerciales de terceros ni referencias externas. Las imágenes son ilustrativas y deben reemplazarse por fotografías y fichas verificadas del cliente antes de vender objetos reales. El logotipo y favicon se dibujaron con código local; no contienen marcas de terceros. El logotipo rasterizado usa Georgia y Arial del sistema sin distribuir archivos de esas fuentes.

- `ambiente-origen.jpg`: fotografía editorial original generada, exportada a JPEG (1536 × 1024).
- Diez fotografías de producto `.jpg`: recortes rectangulares de una plancha fotográfica original generada de cinco columnas por dos filas. Se exportaron con System.Drawing; no se agregaron objetos ni se usaron fotografías externas. Las variaciones conservan una fotografía representativa DEMO; no se pretende que documente cada material real.
- `origen-casa-logo.png`: composición tipográfica local, 600 × 160.
- `origen-casa-icon.png`: casa geométrica dentro de un círculo, dibujada localmente, 512 × 512.

Prompts finales utilizados con la herramienta integrada:

```text
Use case: photorealistic-natural. Asset type: original home decor store editorial hero photograph, landscape wide 1536x1024 if possible. Scene: serene contemporary Argentine home corner in warm natural tones, ivory limewash wall, low pale oak console, sculptural unbranded cream ceramic table lamp with linen shade on the right, matte terracotta vase with one dry branch, folded oatmeal linen throw and woven natural basket. Soft afternoon window light from left, authentic material texture, sophisticated minimal styling, generous breathing room, eye-level editorial interior photography. Color palette cream sand terracotta walnut olive. No people, no letters, no logos, no watermark. This is original fictional ORIGEN CASA DEMO imagery, do not imitate any commercial photographer.

Use case: product-mockup. Asset type: one master photograph contact sheet to be cropped into ten square ecommerce product photos, exactly 5 columns and 2 rows, grid cells equal size, no gaps or borders, 2500x1000 landscape if possible. Every cell has identical warm ivory seamless studio background, soft natural side-light and generous margins around its ONE subject, fully visible. Row 1 left to right: (1) one cream ceramic table lamp with beige conical linen shade, (2) one matte terracotta pendant light dome with short neutral cord, (3) one terracotta rounded vase, (4) one cream speckled stoneware jug with handle, (5) one oatmeal linen cushion. Row 2 left to right: (1) folded natural linen throw, (2) one oval woven jute basket with handles, (3) small oak desk organizer tray with compartments, (4) two nested cream ceramic bowls, (5) one small amber glass candle with no label. Exactly ten cells, no text no numbers no branding no watermarks, coherent real product photography, natural texture, warm minimal contemporary home decor.
```

Recursos finales: `demo/origen-casa/assets/`. Ningún archivo depende de una ruta de generación temporal, URL externa o almacenamiento privado de otra instalación.
