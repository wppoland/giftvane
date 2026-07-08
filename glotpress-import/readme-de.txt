=== Plogins Gift Cards - Store Credit for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, gift card, store credit, gift voucher, coupon code
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Verkaufe WooCommerce-Geschenkkarten, Geschenkgutscheine und Shop-Guthabencodes, die Kunden an der Kasse einlösen.

== Description ==

Verkaufe eine Geschenkkarte oder einen Geschenkgutschein als normales WooCommerce-Produkt. Aktiviere bei jedem Produkt das Kästchen „Geschenkkarte“ und stelle den Preis auf den Kartenwert ein. Wenn die Bestellung als abgeschlossen markiert wird, generiert das Plugin einen eindeutigen Store-Guthabencode im Wert dieses Preises, zeichnet den Kontostand in einer eigenen Tabelle auf und sendet den Code per E-Mail an die Bestell-E-Mail-Adresse des Käufers.

Um eine Karte auszugeben, gibt der Kunde den Code in ein Feld an der Kasse ein. Der Restbetrag wird als Rabatt auf diese Bestellung angerechnet. Wenn die Bestellung weniger kostet, als die Karte wert ist, bleibt der Rest für einen späteren Einkauf auf dem Code, sodass eine Karte mehrere Bestellungen abdecken kann, bis sie aufgebraucht ist.

Der Käufer sieht den/die von seiner Bestellung ausgegebenen Code(s) auch auf der Bestellbestätigungsseite und in seinen WooCommerce-Bestell-E-Mails, sodass er den Code zur Hand hat, ohne seinen Posteingang durchsuchen zu müssen.

Der Code wird auf GitHub erstellt und verfolgt. Quellen- und Fehlerberichte: https://github.com/wppoland/plogins-giftcards

= Documentation and links =

* <strong>Dokumentation</strong> - https://plogins.com/de/plogins-giftcards/docs/
* <strong>Plugin-Seite</strong> - https://plogins.com/de/plogins-giftcards/
* <strong>Quellcode</strong> – https://github.com/wppoland/plogins-giftcards
* <strong>Fehlerberichte und Funktionsanfragen</strong> – https://github.com/wppoland/plogins-giftcards/issues


= What it does =

* Verwandelt jedes Produkt mit einem Kontrollkästchen auf der Registerkarte „Allgemein“ des Produkteditors in eine Geschenkkarte. Der Preis entspricht dem Kartenwert.
* Generiert bei Abschluss der Bestellung einen eindeutigen Code und sendet ihn per E-Mail an die Bestell-E-Mail-Adresse des Käufers.
* Fügt dem Checkout ein Einlösecode-Feld hinzu, das das Kartenguthaben als Rabatt anrechnet.
* Behält das ungenutzte Guthaben des Codes nach einer Teilausgabe bei, sodass es über mehrere Bestellungen hinweg funktioniert.
* Ermöglicht das Festlegen des Code-Präfixes, des Rabattetiketts an der Kasse sowie des Betreffs und des Textes der E-Mail des Empfängers.
* Optional werden die ausgegebenen Codes auf der Bestellseite des Käufers und in seinen Bestell-E-Mails aufgeführt.
* Funktioniert mit WooCommerce HPOS (benutzerdefinierte Bestelltabellen) und den Warenkorb- und Checkout-Blöcken.

== Installation ==

1. Lade das Plugin nach „/wp-content/plugins/plogins-giftcards“ hoch oder installiere es über Plugins → Neu hinzufügen.
2. Aktiviere es. WooCommerce muss aktiv sein.
3. Bearbeite ein Produkt, markiere <strong>Geschenkkarte<strong> auf der Registerkarte „Allgemein“ und lege den Preis auf den Wert der Karte fest. 4. Lege unter </strong>WooCommerce → Gift Cards</strong> das Code-Präfix und die E-Mail-Adresse des Empfängers fest.

== Frequently Asked Questions ==

= Does it need WooCommerce? =

Ja. WooCommerce 8.0 oder höher muss installiert und aktiv sein.

= How do I set the value of a gift card? =

Der Wert ist der Preis des Geschenkkartenprodukts. Beim Kauf von zwei 50-Dollar-Karten werden zwei 50-Dollar-Codes ausgegeben.

= Who receives the code? =

Der Code wird per E-Mail an die Rechnungs-E-Mail-Adresse der Bestellung gesendet und der Käufer kann ihn auch auf der Bestellbestätigungsseite und in seinen Bestell-E-Mails sehen. In dieser Version gibt es kein separates Feld „An einen Freund senden“.

= How does redeeming a code work? =

Der Kunde gibt den Code an der Kasse in das Feld ein. Der Restbetrag dieser Bestellung wird als Rabatt abgezogen und alles, was übrig bleibt, bleibt für das nächste Mal auf dem Code.

= Can a gift card be used more than once? =

Ja. Wenn beim Bezahlen nur ein Teil des Guthabens verbraucht wird, bleibt das verbleibende Guthaben für eine spätere Bestellung auf dem gleichen Code.

= Does each quantity create a separate code? =

Ja. Beim Kauf von zwei Einheiten eines Geschenkkartenprodukts werden zwei separate Gutschriftcodes mit dem Produktpreis als Wert ausgegeben.

= Can I customise the email? =

Ja. Lege den Betreff und den Text der E-Mail unter WooCommerce → Geschenkkarten fest, mit Token für den Code und den Betrag.

= Does it work with WooCommerce checkout blocks? =

Ja. Gift Cards erklärt die Kompatibilität mit WooCommerce HPOS und Cart/Checkout Blocks.


= Does this plugin work on WordPress Multisite? =

Ja. Dieses Plugin ist mit WordPress Multisite kompatibel. Aktiviere es im Netzwerk oder auf einzelnen Websites. Jede Site behält ihre eigenen Einstellungen und Daten.

== Screenshots ==

1. Einlösen einer Geschenkkarte an der Kasse, bei der ein Käufer einen Code auf seine Bestellung anwendet.
2. Die Einstellungsseite für Geschenkkarten unter WooCommerce.

== External Services ==

Dieses Plugin stellt keine Verbindung zu externen Diensten, APIs oder CDNs her, sendet keine Daten an diese und verlässt sich nicht auf diese. Alles läuft auf deiner eigenen Website. Geschenkkartencodes und Guthaben werden in einer einzigen benutzerdefinierten Datenbanktabelle („{prefix}giftcards“) gespeichert, das Geschenkkarten-Flag und alle Empfängeradressen werden im WooCommerce-Produkt- und Bestellartikel-Meta („_giftcards_is_gift_card“, „_giftcards_recipient_email“) gespeichert und die Einstellungen befinden sich in den Optionen „giftcards_settings“ und „giftcards_db_version“. Die E-Mail mit einem Code wird über den WooCommerce/WordPress-Mailer deiner Website an die Rechnungsadresse der Bestellung gesendet. Keine Nachricht oder Kundendaten verlassen deinen Server.

== Changelog ==

= 1.0.1 =
* Erste stabile Version.

= 0.2.1 =
* Umbenannt in „Plogins-Geschenkkarten für WooCommerce“, um einen markanteren Plugin-Namen zu erhalten.

= 0.2.0 =
* Der unter <strong>WooCommerce → Geschenkkarten</strong> festgelegte Betreff und Text der Empfänger-E-Mail wird jetzt für die gesendete E-Mail verwendet. Früher wurden diese gespeicherten Werte ignoriert und immer ein integrierter Standardwert verwendet.
* Es wurde eine Einstellung für das Rabattetikett an der Kasse hinzugefügt, das angezeigt wird, wenn ein Code angewendet wird. Es akzeptiert ein {code}-Token.
* Es wurde eine Einstellung hinzugefügt, um die ausgegebenen Codes auf der Bestellbestätigungsseite und in den Bestell-E-Mails des Käufers aufzulisten. Es ist standardmäßig aktiviert.
* Der Standard-E-Mail- und Etikettentext ist jetzt übersetzbar.
* Die Einstellungsseite wurde überarbeitet: Inline-Hilfe, per Mausklick einzufügende E-Mail-Tokens und eine Live-Vorschau der E-Mail.
* Das Einlösefeld an der Kasse wurde überarbeitet und der Liste der ausgegebenen Codes eine Schaltfläche zum Kopieren hinzugefügt.
* Storefront-Stile folgen jetzt dem Thema und respektieren den Dunkelmodus und die Einstellungen für reduzierte Bewegung, ohne dass sich das Layout an der Kasse ändert. Das Markup ist mit ARIA-Beschriftungen und Fokusstilen über die Tastatur zugänglich und alle CSS/JS-Dateien werden als separate Dateien geliefert.

= 0.1.0 =
* Erstveröffentlichung.
