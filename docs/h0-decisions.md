# H0 — decisiones consolidadas

Estado: aprobado. Esta entrega inicia únicamente H1, por indicación expresa del usuario. No autoriza adelantar diseño, catálogo, pagos o publicación.

## Decisiones definitivas

- Theme/directorio: `commerce-base`; nombre visible: **Commerce Base**.
- Plugin/directorio, si corresponde: `commerce-core`; nombre visible: **Commerce Core**.
- Identidad ficticia provisional: **TRAMA**, independiente del código y de los templates.
- Arquitectura: WordPress, WooCommerce, Block Theme propio, MariaDB local, Docker Compose, WP-CLI, Mailpit y HPOS. Destino inicial: Hostinger Premium. Sin headless ni page builders.
- PHP local intentará coincidir con el que realmente se elija para Hostinger. Las versiones provisionales deben identificarse y registrarse.
- Pagos: **Checkout Pro mediante el plugin oficial de Mercado Pago**. No se implementará una integración custom contra la API de cobros.
- H2 verificará la combinación real de WordPress, WooCommerce, HPOS, Cart/Checkout Blocks, plugin oficial de Mercado Pago y Checkout Pro antes del trabajo visual intensivo. Compatibilidad declarada incompleta no significa incompatibilidad automática.
- `commerce-core` podrá resolver arrepentimiento, almacenamiento y gestión de solicitudes, datos comerciales necesarios y funciones que deban sobrevivir al theme. Será pequeño y no duplicará capacidades nativas.
- Dirección visual inicial: editorial cotidiana/textil/urbana; Newsreader + Manrope; mobile-first; full-width intencional en desktop; paleta aprobada como base; fotografía de producto 4:5; light/dark; motion pequeño y funcional; CSS primero; sin GSAP en V1.
- Imágenes propias, generadas para la demo o con una licencia suficiente para el uso y redistribución correspondientes. Sin recursos de procedencia dudosa.
- H10 probará la reutilización con una segunda identidad sin editar templates del theme base.

## Decisiones postergadas

- Auditoría efectiva del hosting y versiones definitivas de PHP y MariaDB.
- Dominio/subdominio público HTTPS antes o durante H2. No se agregarán túneles salvo necesidad demostrada.
- SEOPress hasta el trabajo de SEO.
- FluentSMTP hasta necesitar transporte real; local usa Mailpit.
- LiteSpeed Cache hasta producción, después de medir y configurar WooCommerce.
- Implementación del plugin propio hasta H8.
- Refinamiento visual e instalación/uso de `frontend-design` antes de implementar el diseño.
- Fotografías y contenido del catálogo en sus hitos correspondientes.
- WooCommerce y Mercado Pago desde H2, sin instalación anticipada en H1.

## Orden aprobado

| Hito | Resultado |
| --- | --- |
| H0 | Decisiones fundacionales |
| H1 | Entorno local reproducible |
| H2 | Compatibilidad temprana de WooCommerce y Mercado Pago |
| H3 | Theme y sistema visual |
| H4 | Estructura y contenido institucional |
| H5 | Catálogo y producto |
| H6 | Carrito, checkout y envíos |
| H7 | Pagos integrados y comunicaciones |
| H8 | Plugin propio y preparación legal |
| H9 | Seguridad, accesibilidad, rendimiento y SEO |
| H10 | Reutilización y entrega comercial |
| H11 | Publicación y operación |

## Contrato de H1

H1 entrega Compose, versiones registradas, configuración privada local, persistencia, instalación reproducible de WordPress, acceso al sitio/wp-admin/Mailpit, comandos de operación y procedimientos separados de backup, restauración y reset deliberado.

Quedan fuera: theme y plugin comerciales, diseño, catálogo, WooCommerce, Mercado Pago, pruebas de pagos, HTTPS público y deploy. El mu-plugin técnico de correo local es una herramienta del entorno, no una implementación comercial.

H0 no identificó ningún bloqueo real para comenzar H1. La comprobación de Docker Desktop, Compose, puertos y recursos pertenece a H1. La ausencia actual de HTTPS público, SMTP real o fotografías no lo bloquea.

H1 puede comenzar y completarse **sin dominio HTTPS público**.

El cierre de H1 exige instalar desde cero, verificar persistencia al reiniciar, capturar correos, realizar un respaldo y restaurarlo con datos consistentes, y documentar el procedimiento sin pasos ocultos. Una limitación encontrada durante la ejecución se registra como tal; no se presenta como una prueba aprobada.
