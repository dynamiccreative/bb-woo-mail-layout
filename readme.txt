=== BB Woo Mail Layout ===
Contributors: bleuebuzz
Tags: woocommerce, email, e-mail, template, français
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 11.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mise en forme brandée et 100 % française des e-mails transactionnels WooCommerce.

== Description ==

BB Woo Mail Layout remplace l'en-tête, le pied et le style de tous les e-mails WooCommerce (y compris ceux des extensions : Colissimo, GLS, Stripe, factures PDF…) par un layout codé par l'agence, compatible Outlook, Gmail, Apple Mail et les webmails français.

* Deux layouts : « Classique » (fond clair, bandeau coloré) et « Sobre » (tout blanc, filet de couleur).
* Logo, couleurs, police, bloc « Besoin d'aide ? », réseaux sociaux, pied de page légal.
* Textes d'introduction français rédigés pour chaque e-mail natif, modifiables, avec placeholders.
* Activation e-mail par e-mail : un e-mail désactivé garde le rendu WooCommerce natif.
* Aperçu et e-mail de test avec une commande réelle, via le pipeline d'envoi de WooCommerce.
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

= 1.0.2 =
* Aucun changement fonctionnel : correction de l’environnement de tests (CI wp-env).

= 1.0.1 =
* Le CSS du layout est écrit dans le <head> de l’e-mail : il ne peut plus être écrasé par une autre extension de personnalisation d’e-mails (rendu sans style constaté sur « Nouvelle commande »), et reste présent si l’inlining échoue.

= 1.0.0 =
* Première version.
