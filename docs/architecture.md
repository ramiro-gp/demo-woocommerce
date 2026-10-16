# Arquitectura y límites de H1

## Producto aprobado

La base comercial será WordPress + WooCommerce, con un Block Theme propio, HPOS y Checkout Pro del plugin oficial de Mercado Pago. Cada cliente tendrá su propia instalación y base. No se utilizarán headless, page builders ni una integración custom contra la API de cobros.

La separación acordada es:

| Capa | Responsabilidad | Momento |
| --- | --- | --- |
| Infraestructura local | Contenedores, persistencia, herramientas y recuperación | H1 |
| WordPress | Contenido, usuarios y administración nativa | H1 |
| WooCommerce | Catálogo, variantes, stock, carrito, pedidos y clientes | Desde H2 |
| `commerce-base` / Commerce Base | Presentación y composición del Block Theme | H3 en adelante |
| `commerce-lookbook` / Commerce Lookbook | Bloque editorial persistente Shoppable Lookbook, independiente del theme | H5.1 |
| `commerce-core` / Commerce Core | Funcionalidad persistente comercial completa; podrá integrar el bloque existente | H8 |
| TRAMA | Identidad ficticia y contenido de la demo | Hitos visuales y de contenido |

H1 no crea directorios vacíos del theme o plugin comercial ni un esqueleto de funcionalidad futura. La marca no debe quedar escrita dentro de templates o lógica reutilizable.

## Servicios locales

```text
Navegador en Windows
  ├─ http://localhost:8080 ─ WordPress + Apache + PHP
  └─ http://localhost:8025 ─ Mailpit (lectura de correos)

Red interna de Docker
  ├─ WordPress ─ MariaDB
  ├─ WordPress ─ Mailpit (SMTP)
  └─ WP-CLI ─ archivos de WordPress + MariaDB
```

Solo HTTP del sitio y HTTP de Mailpit se publican, ligados a loopback. MariaDB y SMTP quedan dentro de la red de Docker. El entorno local no necesita conexiones entrantes desde Internet.

| Servicio | Función | Persistencia |
| --- | --- | --- |
| `wordpress` | Servidor HTTP local y PHP | Volumen completo de `/var/www/html`, incluidos uploads y futuras extensiones |
| `db` | MariaDB | Volumen de datos independiente |
| `wpcli` | Tareas puntuales; no es un proceso permanente | Comparte WordPress y acceso a MariaDB |
| `mailpit` | Captura local de correos | Volumen de almacenamiento de Mailpit |

No se monta un `wp-content` vacío encima de la instalación. WordPress y sus archivos permanecen en el volumen; no se versiona una copia de WordPress Core.

`docker/local-mail.php` es una herramienta local para dirigir `wp_mail()` a Mailpit. Se monta como mu-plugin del entorno y no pertenece a `commerce-core`. No se incorpora al futuro paquete comercial ni al deploy de producción.

## Versiones y compatibilidad

`compose.yaml` es la fuente de verdad de imágenes, etiquetas y digests. Los valores iniciales son WordPress 7.1.3 con PHP 8.4/Apache, MariaDB 10.11.19, WP-CLI 2.12.0 con PHP 8.4 y Mailpit 1.31.4.

PHP y MariaDB siguen siendo elecciones provisionales mientras no haya una auditoría de hPanel. La preferencia es alinear PHP con la versión que se usará en Hostinger Premium. Docker local usa Apache; no reproduce exactamente los límites ni LiteSpeed del hosting compartido.

Los tags y digests hacen repetible la selección de imágenes, pero no garantizan por sí solos compatibilidad comercial. H2 deberá verificar WordPress, WooCommerce, HPOS, Cart/Checkout Blocks, el plugin oficial de Mercado Pago y Checkout Pro. Una declaración incompleta de compatibilidad será una señal para probar, no un rechazo automático.

## Estado, secretos y aislamiento

Cada instancia usa un proyecto de Compose y una carpeta propios:

```text
.local/<instancia>/
├── .env
├── secrets/
│   ├── db-password.txt
│   ├── db-root-password.txt
│   └── wp-admin-password.txt
└── backups/<id>/
```

La instancia predeterminada es `local`, cuyo nombre de confirmación es `demo-woocommerce-local`. Una instancia alternativa, por ejemplo `h1-check`, tiene sus propios volúmenes y secretos. Debe usar puertos distintos si corre al mismo tiempo que `local`.

Las contraseñas se generan aleatoriamente. Los scripts no las imprimen y Git excluye `.local/`. Esto evita versionarlas; no convierte el directorio en una bóveda cifrada. Su protección depende también del acceso a la cuenta de Windows y al disco.

Los respaldos contienen la base y el árbol de WordPress. Aunque no incluyan `.env` ni los archivos de secretos, esos datos pueden contener usuarios, hashes de contraseñas, direcciones, tokens o futuras credenciales de plugins. Se tratan como privados.

## Contrato operativo

- `init` prepara y valida configuración y secretos; no levanta servicios ni instala WordPress.
- `start` levanta servicios e instala WordPress si la base está vacía. Preserva una instalación existente y no importa fixtures ni resetea datos.
- `stop` conserva todos los volúmenes.
- `backup` detiene temporalmente WordPress para obtener una copia coherente y recupera su estado anterior.
- `restore` valida el respaldo, requiere el mismo proyecto y digests de WordPress/MariaDB y crea un respaldo preventivo antes de sustituir datos. La confirmación exige el nombre completo de la instancia.
- `reset-db` limpia la base con WP-CLI y vuelve a instalar WordPress; conserva el volumen de MariaDB y los archivos.
- `reset-all` elimina solo los tres volúmenes identificados de esa instancia y vuelve a iniciar una instalación limpia. Ambos resets exigen confirmación y respaldo previo.

No se usan limpiezas globales de Docker ni `down --volumes` como parte de la rutina de inicio o detención. Los backups excluyen el mu-plugin montado de correo local y no incluyen la bandeja de Mailpit: sus mensajes son datos de prueba, no datos de la tienda.

## Evolución prevista

H1 se completa de manera local. Antes o durante H2 se preparará un dominio o subdominio público con HTTPS para probar Mercado Pago y sus notificaciones. No se agrega un túnel salvo que una necesidad real lo justifique.

SEOPress se difiere hasta SEO; FluentSMTP hasta necesitar transporte real; LiteSpeed Cache hasta producción y después de medir. El código de `commerce-core` se implementará en su hito, sin convertirlo en un framework ni duplicar funciones nativas.

H10 deberá probar otra identidad completa sin editar los templates de `commerce-base`. Publicar en Hostinger y comenzar a recibir ventas reales son etapas posteriores con sus propias validaciones.
