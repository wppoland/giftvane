=== Plogins Gift Cards - Store Credit for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, gift card, store credit, gift voucher, coupon code
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sprzedawaj karty podarunkowe WooCommerce, bony podarunkowe i kody kredytu sklepowego, które klienci realizują przy kasie.

== Description ==

Sprzedaj kartę podarunkową lub kupon podarunkowy jako zwykły produkt WooCommerce. Zaznacz pole „Karta podarunkowa” przy dowolnym produkcie i ustaw jego cenę na wartość karty. Gdy zamówienie zostanie oznaczone jako zakończone, wtyczka generuje unikalny kod kredytowy sklepu o wartości tej ceny, rejestruje saldo we własnej tabeli i wysyła kod e-mailem na adres e-mail zamówienia kupującego.

Aby wydać kartę, klient wpisuje kod w polu przy kasie. Saldo jest stosowane jako rabat od tego zamówienia. Jeśli zamówienie kosztuje mniej niż wartość karty, pozostała część kodu zostaje na kodzie do późniejszych zakupów, dzięki czemu jedna karta może pokryć kilka zamówień aż do jej wyczerpania.

Kupujący widzi również kody wydane w ramach zamówienia na stronie potwierdzenia zamówienia oraz w wiadomościach e-mail z zamówieniami WooCommerce, dzięki czemu ma kod pod ręką, bez konieczności szukania w skrzynce odbiorczej.

Kod jest tworzony i śledzony w GitHubie. Źródło i raporty o błędach: https://github.com/wppoland/plogins-giftcards

= Documentation and links =

* <strong>Dokumentacja</strong> - https://plogins.com/pl/plogins-giftcards/docs/
* <strong>Strona wtyczki</strong> - https://plogins.com/pl/plogins-giftcards/
* <strong>Kod źródłowy</strong> - https://github.com/wppoland/plogins-giftcards
* <strong>Raporty o błędach i prośby o dodanie funkcji</strong> - https://github.com/wppoland/plogins-giftcards/issues


= What it does =

* Zamienia dowolny produkt w kartę podarunkową z jednym polem wyboru w zakładce Ogólne w edytorze produktów; cena jest wartością karty.
* Generuje unikalny kod po zakończeniu zamówienia i wysyła go e-mailem na adres e-mail zamówienia kupującego.
* Dodaje do kasy pole z kodem realizacji, które stosuje saldo karty jako rabat.
* Zachowuje niewykorzystane saldo kodu po częściowym wydaniu, dzięki czemu działa w przypadku wielu zamówień.
* Umożliwia ustawienie prefiksu kodu, etykiety rabatu przy kasie oraz tematu i treści wiadomości e-mail odbiorcy.
* Opcjonalnie wyświetla wydane kody na stronie zamówienia kupującego oraz w wiadomościach e-mail dotyczących zamówień.
* Współpracuje z WooCommerce HPOS (tabele zamówień niestandardowych) oraz blokami Koszyk i Kasa.

== Installation ==

1. Prześlij wtyczkę do `/wp-content/plugins/plogins-giftcards` lub zainstaluj ją z Wtyczki → Dodaj nowe.
2. Aktywuj. WooCommerce musi być aktywny.
3. Edytuj produkt, w zakładce Ogólne zaznacz <strong>Karta podarunkowa</strong> i ustaw jego cenę na wartość karty.
4. Ustaw prefiks kodu i adres e-mail odbiorcy w <strong>WooCommerce → Karty podarunkowe</strong>.

== Frequently Asked Questions ==

= Does it need WooCommerce? =

Tak. WooCommerce 8.0 lub nowszy musi być zainstalowany i aktywny.

= How do I set the value of a gift card? =

Wartość jest ceną produktu będącego kartą podarunkową. Kupując dwie karty o wartości 50 USD, otrzymujesz dwa kody o wartości 50 USD.

= Who receives the code? =

Kod jest wysyłany pocztą elektroniczną na adres e-mail rozliczeniowy zamówienia, a kupujący może go również zobaczyć na stronie potwierdzenia zamówienia oraz w wiadomościach e-mail dotyczących zamówienia. W tej wersji nie ma osobnego pola „wyślij znajomemu”.

= How does redeeming a code work? =

Klient wpisuje kod w polu przy kasie. Saldo jest ujmowane w ramach tego zamówienia w postaci rabatu, a wszystko, co zostaje, pozostaje w kodzie na następny raz.

= Can a gift card be used more than once? =

Tak. Jeśli podczas realizacji transakcji wykorzystana zostanie tylko część salda, pozostały kredyt sklepowy pozostanie na tym samym kodzie do późniejszego zamówienia.

= Does each quantity create a separate code? =

Tak. Kupując dwie jednostki produktu w postaci karty podarunkowej, wystawiane są dwa oddzielne kody kredytowe sklepu, z ceną produktu jako wartością.

= Can I customise the email? =

Tak. Ustaw temat i treść wiadomości e-mail w obszarze WooCommerce → Karty podarunkowe wraz z tokenami na kod i kwotę.

= Does it work with WooCommerce checkout blocks? =

Tak. Karty podarunkowe deklarują kompatybilność z WooCommerce HPOS i blokami koszyka/kasy.


= Does this plugin work on WordPress Multisite? =

Tak. Ta wtyczka jest kompatybilna z WordPress Multisite. Włącz ją dla całej sieci lub na poszczególnych stronach; każda witryna przechowuje własne ustawienia i dane.

== Screenshots ==

1. Realizacja karty podarunkowej przy kasie, podczas której kupujący dodaje kod do swojego zamówienia.
2. Strona ustawień kart podarunkowych w WooCommerce.

== External Services ==

Ta wtyczka nie łączy się, nie wysyła danych ani nie polega na żadnej zewnętrznej usłudze, API ani CDN. Wszystko działa na Twojej własnej stronie. Kody i salda kart podarunkowych są przechowywane w jednej niestandardowej tabeli bazy danych (`{prefix}giftcards`), flaga karty podarunkowej i ewentualny adres odbiorcy są przechowywane w metadanych produktu i pozycji zamówienia WooCommerce (`_giftcards_is_gift_card`, `_giftcards_recipient_email`), a ustawienia są przechowywane w opcjach `giftcards_settings` i `giftcards_db_version`. Wiadomość e-mail zawierająca kod jest dostarczana za pośrednictwem poczty WooCommerce/WordPress Twojej witryny na adres rozliczeniowy zamówienia; żadna wiadomość ani dane klienta nie opuszczą Twojego serwera.

== Translations ==

Plogins Gift Cards zawiera tłumaczenia interfejsu wtyczki na język polski, niemiecki i hiszpański. Domena tekstowa to `plogins-giftcards`, więc pakiety językowe WordPress.org mogą również zastąpić lub rozszerzyć te dołączone tłumaczenia.

== Changelog ==

= 1.0.2 =
* Dodano dołączone tłumaczenia na język polski, niemiecki i hiszpański dla interfejsu wtyczki.

= 1.0.1 =
* Pierwsza stabilna wersja.

= 0.2.1 =
* Zmieniono nazwę na Plogins Gift Cards for WooCommerce, aby nadać wtyczce bardziej charakterystyczną nazwę.

= 0.2.0 =
* Temat i treść wiadomości e-mail odbiorcy ustawione w sekcji <strong>WooCommerce → Karty podarunkowe</strong> są teraz używane w wysyłanej wiadomości e-mail. Wcześniej te przechowywane wartości były ignorowane i zawsze używano wbudowanych wartości domyślnych.
* Dodano ustawienie etykiety rabatu przy kasie wyświetlanej po zastosowaniu kodu; akceptuje token {code}.
* Dodano ustawienie umożliwiające wyświetlenie listy wydanych kodów na stronie potwierdzenia zamówienia kupującego i w wiadomościach e-mail dotyczących zamówień. Domyślnie jest włączone.
* Domyślny tekst wiadomości e-mail i etykiety można teraz przetłumaczyć.
* Przerobiono stronę ustawień: pomoc wbudowana, tokeny e-mail typu „kliknij, aby wstawić” i podgląd wiadomości e-mail na żywo.
* Przerobiono pole realizacji transakcji i dodano przycisk kopiowania do listy wydanych kodów.
* Style witryn sklepowych są teraz zgodne z motywem i uwzględniają tryb ciemny i ustawienia ograniczonego ruchu, bez zmiany układu przy kasie. Znacznik jest dostępny za pomocą klawiatury z etykietami ARIA i stylami fokusu, a wszystkie CSS/JS są dostarczane jako osobne pliki.

= 0.1.0 =
* Pierwsze wydanie.
