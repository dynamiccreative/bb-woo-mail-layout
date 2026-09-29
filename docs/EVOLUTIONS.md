# Propositions d'évolution — BB Woo Mail Layout

Feuille de route issue de la comparaison avec les principaux concurrents (YayMail, Kadence Email Designer, Flycart Email Customizer, Decorator, VillaTheme, éditeur d'e-mails en blocs de WooCommerce).

Effort : estimation de développement. Valeur : ★ utile, ★★ attendu par les commerçants, ★★★ différenciant ou très demandé.

| # | Évolution | Effort | Valeur | Statut |
|---|---|---|---|---|
| **v1.4 : gains rapides** |||||
| 1 | Texte de pré-en-tête par e-mail (avec placeholders) | ½ j | ★★★ | Fait (1.4.0) |
| 2 | Bouton d'action configurable par e-mail (libellé + lien via placeholders) | ½ j | ★★★ | Fait (1.4.0) |
| 3 | Aperçu avec une commande fictive si aucune commande n'existe | ½ j | ★★★ | Fait (1.4.0) |
| 4 | Poids du HTML et alerte Gmail 102 Ko, alertes logo SVG / trop lourd | ¼ j | ★★ | À faire |
| 5 | Réseaux supplémentaires (Pinterest, X, WhatsApp, Threads) et icônes monochromes | ¼ j | ★ | À faire |
| **v1.5 : contenu** |||||
| 6 | Blocs conditionnels simples (moyen de paiement, mode de livraison, pays) : texte ajouté après l'intro | 1 j | ★★★ | À faire |
| 7 | E-mail admin enrichi (téléphone, lien admin, n° de commande du client) | ½ j | ★★ | À faire |
| 8 | Options du tableau de commande (SKU, image dans tous les layouts, métadonnées) | 1 j | ★★ | À faire |
| 9 | Produits mis en avant « automatiques » (ventes croisées ou produits apparentés à la commande), le choix manuel restant possible | ½ j | ★★ | Fait (1.4.0) |
| 10 | Textes FR dédiés pour Subscriptions / Bookings / Memberships | ½ j | ★★ | Fait (1.4.0) |
| **Côté agence : différenciation bleuebuzz** |||||
| 11 | Commandes WP-CLI : `wp bb-mail export / import / test --all` pour déployer en série | ½ j | ★★★ | Fait (1.4.0) |
| 12 | Préréglage agence verrouillable : l'agence fixe certains réglages que le client ne peut plus modifier | 1 j | ★★★ | À faire |
| 13 | Envoi de toute la série de tests en un clic depuis l'admin (déjà possible en WP-CLI) | ¼ j | ★★ | À faire |
| 14 | Paramètres UTM optionnels sur les liens (pas de pixel, fidèle au principe « zéro tracking ») | ¼ j | ★ | À faire |
| **Stratégique** |||||
| 15 | Suivre l'éditeur d'e-mails en blocs de WooCommerce : proposer les layouts bleuebuzz comme modèles de cet éditeur plutôt que de le neutraliser | à étudier | ★★★ long terme | À étudier |

## Hors périmètre, volontairement

Builder glisser-déposer, e-mails marketing, relance de paniers abandonnés : exclus par le cahier des charges, et ce choix reste pertinent. Les paniers abandonnés relèvent de l'outil d'e-mailing du client (Brevo, Klaviyo…) : capture d'e-mail sur le checkout, consentement et statistiques hors de portée d'un plugin de mise en forme, et ces e-mails ne passent pas par WooCommerce.

Seule exception retenue : la **demande d'avis après achat**, développée dans un plugin séparé, **BB Woo Review Request** (`../bb-woo-review-request`, v0.1.0). Il fonctionne seul (e-mail WooCommerce standard) et, quand ce plugin est actif, il est mis en page par le layout via ses hooks publics (`bb_email_default_texts` ajouté en 1.4.1, `bb_email_intro_text`, `bb_email_action_button`), sans dépendance dans un sens ni dans l'autre. Les principes de ce plugin restent intacts : aucun code marketing, aucun tracking, rien en front.
