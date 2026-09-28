<?php
/**
 * Configuration de la suite de tests WordPress dans le conteneur tests-cli de wp-env.
 *
 * @package BB\WooMailLayout
 */

define( 'ABSPATH', '/var/www/html/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );

define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ? getenv( 'WORDPRESS_DB_NAME' ) : 'tests-wordpress' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ? getenv( 'WORDPRESS_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ? getenv( 'WORDPRESS_DB_PASSWORD' ) : 'password' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ? getenv( 'WORDPRESS_DB_HOST' ) : 'tests-mysql' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Boutique de test' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
