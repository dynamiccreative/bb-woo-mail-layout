# Changelog

## 1.4.1

- Filtre `bb_email_default_texts` : une extension déclare le texte d'intro par défaut de ses propres e-mails (utilisé par BB Woo Review Request).
- Accès direct : sous-menu **WooCommerce → Mise en forme e-mails** (juste sous Réglages, capacité `manage_woocommerce`), lien vers l'onglet de réglages, surligné quand il est ouvert.

## 1.4.0

- **Pré-en-tête** (texte affiché sous le sujet dans la boîte de réception) : réglable par e-mail dans l'onglet E-mails, placeholders acceptés ; vide = début de l'intro. Tronqué à 140 caractères, masqué dans le corps de l'e-mail, suivi d'espaces invisibles pour que le client mail n'affiche pas la suite du corps. Filtre `bb_email_preheader`.
- **Bouton d'action par e-mail**, affiché sous l'intro : libellé + lien (placeholder de lien `{order_url}`, `{payment_url}`, `{tracking_url}`, `{site_url}` ou URL http(s)). Un placeholder sans valeur pour la commande (ex. pas de numéro de suivi) masque le bouton ; libellé vide = libellé du placeholder. Filtre `bb_email_action_button`.
- **Commande fictive** dans l'aperçu et l'e-mail de test (toujours proposée, seul choix sur un site sans commande) : jamais enregistrée, derniers produits du site, statut adapté à l'e-mail.
- **Produits mis en avant automatiques** : réglage « Choix des produits » (option `featured_source`) : manuel (par défaut, comportement inchangé), ventes croisées des produits commandés (complétées par des produits apparentés) ou produits apparentés (catégories / étiquettes). Les produits déjà commandés sont exclus ; les produits choisis à la main complètent la sélection automatique et servent dans les e-mails sans commande. Les candidats non visibles ne réduisent plus le bloc : les 3 premiers visibles sont affichés.
- **Textes FR pour WooCommerce Subscriptions, Bookings et Memberships** (renouvellements, rappels, fin d'essai, réservations confirmées / annulées / rappel, adhésions…). Placeholders `{next_payment_date}`, `{subscription_end_date}`, `{booking_product}`, `{booking_date}`, `{membership_plan}`. Pour une réservation ou une adhésion, prénom, numéro de commande et langue sont lus sur la commande ou le client rattachés (`Placeholders::context()`). `{order_url}` s'intitule « Voir mon abonnement » pour un abonnement.
- **WP-CLI** : `wp bb-mail list | export | import | test` (dont `test --all` pour recevoir toute la série). L'envoi de test est factorisé dans `TestEmail::send()`.
- **Correctif** (trouvé par les tests) : le pré-en-tête porte la classe `-emogrifier-keep`, sans quoi WooCommerce le supprimait à l'inlining (`HtmlPruner::removeElementsWithDisplayNone()`).
- Nouvelles options `preheaders`, `button_labels`, `button_urls`, `featured_source` (incluses dans l'import / export, traduisibles WPML / Polylang). Un export 1.4.0 est refusé par un site encore en 1.3.x (clés inconnues) : mettre le plugin à jour d'abord.
- Onglet E-mails : le panneau dépliable devient « Textes par défaut / personnalisés » (intro, pré-en-tête, bouton).
- Layouts tiers : afficher `$v['preheader_html']` juste après `<body>` et `$v['button_html']` sous l'intro (voir README).

## 1.3.0

- **Layout par e-mail** : dans l'onglet E-mails, chaque e-mail peut utiliser son propre layout ou suivre le layout général (option `email_layouts`, incluse dans l'import / export). Couleurs, logo, police et blocs restent communs. Le filtre `bb_email_layout` reçoit ce layout et garde le dernier mot.

## 1.2.1

- Aperçu « Ordinateur » : iframe de 640 px au lieu de 600 px. À 600 px, le `@media (max-width: 620px)` des layouts s'appliquait et l'aperçu montrait la version mobile (colonnes à 100 %).

## 1.2.0

- **Nouvelle page de réglages** : sous-onglets Apparence, Contenu, E-mails, Outils au lieu de 12 sections empilées ; barre d'enregistrement fixe.
- **Aperçu en direct** dans une colonne collante de 600 px (bascule ordinateur / mobile), mis à jour à chaque modification. L'aperçu et l'e-mail de test utilisent les réglages du formulaire **non encore enregistrés** (`Options::draft()`, court-circuit `pre_option_` limité à la requête AJAX, rien n'est écrit).
- Layout choisi par vignette ; couleurs en nuancier (sélecteur + code hexadécimal) avec « Rétablir les couleurs par défaut ».
- Chaque bloc (aide, réseaux, produits, pied de page) a son interrupteur et ses réglages au même endroit ; champs dépendants masqués (nom de Google Font, fond extérieur hors Classique).
- E-mails et textes d'introduction réunis dans une seule liste : filtres Client / Administrateur, recherche, activation groupée, placeholders insérés d'un clic.
- Confirmation d'import intégrée à la page.
- Technique : l'interface est rendue par un seul champ `bb_wml_app` ; chaque clé de l'option reste un champ WooCommerce (`bb_wml_value`) enregistré et assaini comme avant.

## 1.1.2

- Logo : **hauteur maximale réglable** (80 px par défaut, 20 à 300 px). Auparavant fixée à 80 px, elle l'emportait sur la largeur maximale pour les logos carrés ou verticaux.

## 1.1.1

- Réglage **Couleur des bordures** (tableau de commande, adresses, séparateurs) ; vide = teinte calculée depuis la couleur du texte.
- Filet au-dessus du sous-total ramené de 3 px à 1 px, pour tous les layouts.

## 1.1.0

- Nouveau layout **E-commerce** : barre d'accent, en-tête de tableau coloré, photos produit dans le tableau de commande (en-tête `Product Images: yes` du `styles.css` d'un layout).
- Bloc **Produits mis en avant** : jusqu'à 3 produits choisis dans l'admin (photo, titre, prix, promo), dans les e-mails client ; produits non publiés ou masqués ignorés ; filtre `bb_email_featured_products`.
- Nouveaux placeholders : `{billing_address}`, `{shipping_address}`, `{payment_method}`, `{shipping_method}`, `{payment_url}`, `{order_meta:clé}`.
- Champ **CSS personnalisé** (balises, `@import` et scripts retirés ; jetons `{{primary}}`… disponibles).
- `bin/make-pot.php` pour régénérer le catalogue de traduction.

## 1.0.4

- Traduction anglaise livrée (`en_US`, `en_GB`) : libellés du layout, textes d'introduction par défaut, administration.
- Multilingue : l'e-mail client est rendu dans la langue de la commande (WPML `wpml_language`, Polylang), à défaut dans la langue du profil du client. Aucune bascule sans WPML ni Polylang (évite un e-mail à moitié traduit).
- Nouveaux filtres : `bb_email_locale`, `bb_email_order_language`, `bb_email_admin_uses_order_language` (e-mails admin dans la langue de la commande, désactivé par défaut).

## 1.0.3

- Mises à jour automatiques depuis GitHub (mécanisme maison `GitHubUpdater`, comme DC Visibility / GéoPrestations) : une nouvelle version poussée sur `main` est proposée dans Extensions.

## 1.0.2

- Aucun changement fonctionnel : correction de l'environnement de tests (CI wp-env).

## 1.0.1

- Le CSS du layout est écrit dans le `<head>` de l'e-mail : il ne peut plus être écrasé par une autre extension de personnalisation d'e-mails (rendu sans style constaté sur « Nouvelle commande »), et reste présent si l'inlining échoue.

## 1.0.0

- Première version : layouts Classique et Sobre, intros françaises par e-mail, activation e-mail par e-mail, aperçu et e-mail de test, import / export JSON, contrôle du logo, compatibilité HPOS / WPML / Polylang.
