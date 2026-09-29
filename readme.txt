=== BB Woo Mail Layout ===
Contributors: bleuebuzz
Tags: woocommerce, email, e-mail, template, français
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 11.1
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mise en forme brandée et 100 % française des e-mails transactionnels WooCommerce.

== Description ==

BB Woo Mail Layout remplace l'en-tête, le pied et le style de tous les e-mails WooCommerce (y compris ceux des extensions : Colissimo, GLS, Stripe, factures PDF…) par un layout codé par l'agence, compatible Outlook, Gmail, Apple Mail et les webmails français.

* Trois layouts : « Classique » (fond clair, bandeau coloré), « Sobre » (tout blanc, filet de couleur) et « E-commerce » (photos produit dans le tableau de commande).
* Bloc « Produits mis en avant » (3 produits : photo, titre, prix) et CSS personnalisé.
* Logo, couleurs, police, bloc « Besoin d'aide ? », réseaux sociaux, pied de page légal.
* Textes d'introduction français rédigés pour chaque e-mail natif, modifiables, avec placeholders.
* Activation e-mail par e-mail : un e-mail désactivé garde le rendu WooCommerce natif.
* Aperçu en direct (600 px, ordinateur ou mobile) et e-mail de test avec une commande réelle, via le pipeline d'envoi de WooCommerce, sans avoir à enregistrer.
* Import / export JSON des réglages pour dupliquer un site.
* Contrôle du logo (alerte si l'image ne répond pas en 200).
* Compatible HPOS, WPML et Polylang. Aucun appel externe, aucune télémétrie, aucun chargement en front.

Ce n'est pas un builder : la mise en forme est maîtrisée par le code, le client ne règle que quelques variables.

== Installation ==

1. Téléverser le zip dans Extensions → Ajouter.
2. Activer l'extension (WooCommerce doit être actif).
3. Régler la mise en forme dans WooCommerce → Réglages → E-mails → Mise en forme bleuebuzz.
4. Envoyer un e-mail de test depuis la même page.

== Frequently Asked Questions ==

= Où régler le sujet et le titre des e-mails ? =

Dans chaque e-mail WooCommerce (Réglages → E-mails), comme d'habitude. Le plugin ne duplique pas ces réglages.

= Que devient le nouveau design d'e-mails de WooCommerce ? =

Les « améliorations des e-mails » et l'éditeur d'e-mails en blocs de WooCommerce sont neutralisés tant que le plugin est actif. À la désactivation, le réglage d'origine du site s'applique de nouveau.

= Le plugin modifie-t-il l'envoi des e-mails ? =

Non. Le transport (WP Mail SMTP, FluentSMTP, Amazon SES…) n'est pas touché.

== Changelog ==

= 1.2.1 =
* Aperçu « Ordinateur » : l'iframe fait 640 px, pour ne plus déclencher les styles mobiles des layouts (max-width: 620px).

= 1.2.0 =
* Nouvelle page de réglages : sous-onglets Apparence, Contenu, E-mails, Outils ; aperçu 600 px collant, mis à jour en direct.
* L'aperçu et l'e-mail de test utilisent les réglages non encore enregistrés.
* E-mails et textes d'introduction réunis dans une seule liste (filtres, recherche, activation groupée).

= 1.1.2 =
* Logo : hauteur maximale réglable (80 px par défaut), en plus de la largeur maximale.

= 1.1.1 =
* Réglage « Couleur des bordures ».
* Filet au-dessus du sous-total ramené à 1 px (tous les layouts).

= 1.1.0 =
* Nouveau layout « E-commerce » : barre d’accent et photos produit dans le tableau de commande.
* Bloc « Produits mis en avant » : jusqu’à 3 produits (photo, titre, prix) dans les e-mails client.
* Nouveaux placeholders : {billing_address}, {shipping_address}, {payment_method}, {shipping_method}, {payment_url}, {order_meta:clé}.
* Champ CSS personnalisé (filtré).

= 1.0.4 =
* Traduction anglaise (en_US, en_GB).
* Multilingue : langue de la commande (WPML, Polylang), sinon langue du profil du client ; aucune bascule sans WPML ni Polylang. Nouveaux filtres bb_email_locale, bb_email_order_language, bb_email_admin_uses_order_language.

= 1.0.3 =
* Mises à jour automatiques depuis GitHub (mécanisme maison GitHubUpdater).

= 1.0.2 =
* Aucun changement fonctionnel : correction de l’environnement de tests (CI wp-env).

= 1.0.1 =
* Le CSS du layout est écrit dans le <head> de l’e-mail : il ne peut plus être écrasé par une autre extension de personnalisation d’e-mails (rendu sans style constaté sur « Nouvelle commande »), et reste présent si l’inlining échoue.

= 1.0.0 =
* Première version.
