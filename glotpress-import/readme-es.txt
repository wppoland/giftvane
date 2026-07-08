=== Plogins Gift Cards - Store Credit for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, gift card, store credit, gift voucher, coupon code
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Venda tarjetas de regalo, vales de regalo y códigos de crédito de tienda de WooCommerce que los clientes canjeen al finalizar la compra.

== Description ==

Venda una tarjeta de regalo o un vale de regalo como un producto WooCommerce normal. Marca la casilla "Tarjeta regalo" en cualquier producto y establece su precio según el valor de la tarjeta. Cuando el pedido se marca como completo, el complemento genera un código de crédito de tienda único por ese precio, registra su saldo en su propia tabla y envía el código por correo electrónico a la dirección de correo electrónico del pedido del comprador.

Para gastar una tarjeta, el cliente ingresa el código en un campo al finalizar la compra. El saldo se aplica como descuento en ese pedido. Si el pedido cuesta menos de lo que vale la tarjeta, el sobrante se queda en el código para una compra posterior, por lo que una tarjeta puede cubrir varios pedidos hasta que se agote.

El comprador también ve los códigos emitidos por su pedido en la página de confirmación del pedido y en los correos electrónicos de su pedido de WooCommerce, por lo que tiene el código a mano sin tener que buscar en su bandeja de entrada.

El código se crea y se rastrea en GitHub. Informes de fuentes y errores: https://github.com/wppoland/plogins-giftcards

= Documentation and links =

* <strong>Documentación</strong> - https://plogins.com/es/plogins-giftcards/docs/
* <strong>Página de complementos</strong> - https://plogins.com/es/plogins-giftcards/
* <strong>Código fuente</strong> - https://github.com/wppoland/plogins-giftcards
* <strong>Informes de errores y solicitudes de funciones</strong> - https://github.com/wppoland/plogins-giftcards/issues


= What it does =

* Convierte cualquier producto en una tarjeta de regalo con una casilla de verificación en la pestaña General del editor de productos; el precio es el valor de la tarjeta.
* Genera un código único al finalizar el pedido y lo envía por correo electrónico a la dirección de correo electrónico del pedido del comprador.
* Añade un campo de código de canje al proceso de pago que aplica el saldo de la tarjeta como descuento.
* Mantiene el saldo no utilizado en el código después de un gasto parcial, por lo que funciona en varios pedidos.
* Le permite configurar el prefijo del código, la etiqueta de descuento de pago y el asunto y el cuerpo del correo electrónico del destinatario.
* Opcionalmente, enumera los códigos emitidos en la página de pedido del comprador y en los correos electrónicos de su pedido.
* Funciona con WooCommerce HPOS (tablas de pedidos personalizados) y los bloques Carrito y Pago.

== Installation ==

1. Cargue el complemento en `/wp-content/plugins/plogins-giftcards`, o instálelo desde Complementos → Añadir nuevo.
2. Actívalo. WooCommerce debe estar activo.
3. Edite un producto, marque <strong>Tarjeta regalo<strong> en la pestaña General y establezca su precio según el valor de la tarjeta. 4. Configure el prefijo del código y el correo electrónico del destinatario en </strong>WooCommerce → Tarjetas de regalo</strong>.

== Frequently Asked Questions ==

= Does it need WooCommerce? =

Sí. WooCommerce 8.0 o posterior debe estar instalado y activo.

= How do I set the value of a gift card? =

El valor es el precio del producto de la tarjeta regalo. Al comprar dos de una tarjeta de $50, se emiten dos códigos de $50.

= Who receives the code? =

El código se envía por correo electrónico a la dirección de correo electrónico de facturación del pedido y el comprador también puede verlo en la página de confirmación del pedido y en los correos electrónicos del pedido. No hay un campo separado para "enviar a un amigo" en esta versión.

= How does redeeming a code work? =

El cliente escribe el código en el campo al finalizar la compra. El saldo se deduce de ese pedido como un descuento y todo lo que sobra permanece en el código para la próxima vez.

= Can a gift card be used more than once? =

Sí. Si el pago utiliza solo una parte del saldo, el crédito restante de la tienda permanece en el mismo código para un pedido posterior.

= Does each quantity create a separate code? =

Sí. Al comprar dos unidades de un producto de tarjeta de regalo, se emiten dos códigos de crédito de tienda separados con el precio del producto como cada valor.

= Can I customise the email? =

Sí. Establezca el asunto y el cuerpo del correo electrónico en WooCommerce → Tarjetas de regalo, con tokens para el código y el monto.

= Does it work with WooCommerce checkout blocks? =

Sí. Las tarjetas de regalo declaran compatibilidad con WooCommerce HPOS y carritos/bloques de pago.


= Does this plugin work on WordPress Multisite? =

Sí. Este complemento es compatible con WordPress Multisite. Activarlo en red o activarlo en sitios individuales; Cada sitio mantiene su propia configuración y datos.

== Screenshots ==

1. Canjear una tarjeta de regalo al finalizar la compra, donde un comprador aplica un código a su pedido.
2. La página de configuración de Tarjetas de regalo en WooCommerce.

== External Services ==

Este complemento no se conecta, envía datos ni depende de ningún servicio externo, API o CDN. Todo se ejecuta en tu propio sitio. Los códigos y saldos de las tarjetas de regalo se almacenan en una única tabla de base de datos personalizada (`{prefix}giftcards`), el indicador de la tarjeta de regalo y cualquier dirección del destinatario se guardan en el meta del producto WooCommerce y del elemento de pedido (`_giftcards_is_gift_card`, `_giftcards_recipient_email`), y las configuraciones se encuentran en las opciones `giftcards_settings` y `giftcards_db_version`. El correo electrónico que contiene un código se envía a través del propio correo WooCommerce/WordPress de tu sitio a la dirección de facturación del pedido; ningún mensaje o información del cliente sale de su servidor.

== Changelog ==

= 1.0.1 =
* Primera versión estable.

= 0.2.1 =
* Renombrado a Tarjetas de regalo de Plogins para WooCommerce para obtener un nombre de complemento más distintivo.

= 0.2.0 =
* El asunto y el cuerpo del correo electrónico del destinatario establecidos en <strong>WooCommerce → Tarjetas de regalo</strong> ahora se utilizan para el correo electrónico que se envía. Anteriormente, estos valores almacenados se ignoraban y siempre se usaba un valor predeterminado incorporado.
* Se agregó una configuración para la etiqueta de descuento de pago que se muestra cuando se aplica un código; acepta un token {code}.
* Se agregó una configuración para enumerar los códigos emitidos en la página de confirmación del pedido del comprador y en los correos electrónicos del pedido. Está activado de forma predeterminada.
* El correo electrónico predeterminado y el texto de la etiqueta ahora se pueden traducir.
* Se modificó la página de configuración: ayuda en línea, tokens de correo electrónico con un clic para insertar y una vista previa en vivo del correo electrónico.
* Se modificó el campo de canje de pago y se agregó un botón de copia a la lista de códigos emitidos.
* Los estilos de escaparate ahora siguen el tema y respetan el modo oscuro y la configuración de movimiento reducido, sin cambios de diseño al momento de pagar. Se puede acceder al marcado mediante el teclado con etiquetas ARIA y estilos de enfoque, y todo CSS/JS se envía como archivos separados.

= 0.1.0 =
* Lanzamiento inicial.
