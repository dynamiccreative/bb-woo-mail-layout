<?php
/**
 * Génère languages/bb-woo-mail-layout.pot à partir des appels __(), esc_html__()… du plugin.
 *
 * Usage : php bin/make-pot.php   (puis mettre à jour les .po avec Poedit : Catalogue → Mettre à jour depuis le fichier POT)
 *
 * @package BB\WooMailLayout
 */

// phpcs:disable -- script CLI hors WordPress.

$argv[1] = $argv[1] ?? dirname( __DIR__ );
$root   = $argv[1];
$domain = 'bb-woo-mail-layout';
$fns    = array( '__' => 1, '_e' => 1, 'esc_html__' => 1, 'esc_html_e' => 1, 'esc_attr__' => 1, 'esc_attr_e' => 1, '_x' => 2, 'esc_html_x' => 2, 'esc_attr_x' => 2 );
$entries = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	$rel = str_replace( chr( 92 ), '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	if ( 'php' !== $file->getExtension() || preg_match( '#^(vendor|tests|bin|node_modules|build)/#', $rel ) ) {
		continue;
	}
	$tokens  = token_get_all( file_get_contents( $file->getPathname() ) );
	$n       = count( $tokens );
	$comment = '';
	for ( $i = 0; $i < $n; $i++ ) {
		$t = $tokens[ $i ];
		if ( is_array( $t ) && T_COMMENT === $t[0] && str_contains( $t[1], 'translators:' ) ) {
			$comment = trim( preg_replace( '#^/\*|\*/$|^//#', '', $t[1] ) );
			continue;
		}
		if ( ! is_array( $t ) || T_STRING !== $t[0] || ! isset( $fns[ $t[1] ] ) ) {
			continue;
		}
		$j = $i + 1;
		while ( $j < $n && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}
		if ( '(' !== $tokens[ $j ] ) {
			continue;
		}
		$args  = array();
		$depth = 0;
		$cur   = null;
		for ( $k = $j + 1; $k < $n; $k++ ) {
			$tk = $tokens[ $k ];
			if ( '(' === $tk ) {
				$depth++;
			}
			if ( ')' === $tk ) {
				if ( 0 === $depth ) {
					$args[] = $cur;
					break;
				}
				$depth--;
			}
			if ( ',' === $tk && 0 === $depth ) {
				$args[] = $cur;
				$cur    = null;
				continue;
			}
			if ( is_array( $tk ) && T_CONSTANT_ENCAPSED_STRING === $tk[0] && null === $cur && 0 === $depth ) {
				$cur = eval( 'return ' . $tk[1] . ';' );
			}
		}
		$d = $args[ $fns[ $t[1] ] ] ?? null;
		if ( $domain !== $d || ! is_string( $args[0] ?? null ) ) {
			$comment = '';
			continue;
		}
		$ctx = 2 === $fns[ $t[1] ] ? $args[1] : null;
		$key = $ctx . "\x04" . $args[0];
		$entries[ $key ]['msgid']  = $args[0];
		$entries[ $key ]['ctx']    = $ctx;
		$entries[ $key ]['refs'][] = $rel . ':' . $t[2];
		if ( $comment ) {
			$entries[ $key ]['comment'] = $comment;
		}
		$comment = '';
	}
}
$esc = function ( $s ) {
	$s = str_replace( array( chr( 92 ), '"' ), array( chr( 92 ) . chr( 92 ), chr( 92 ) . '"' ), $s );
	if ( str_contains( $s, "\n" ) ) {
		$parts = explode( "\n", $s );
		$last  = array_pop( $parts );
		$lines = array_map( fn( $p ) => '"' . $p . chr( 92 ) . 'n"', $parts );
		if ( '' !== $last ) {
			$lines[] = '"' . $last . '"';
		}
		return "\"\"\n" . implode( "\n", $lines );
	}
	return '"' . $s . '"';
};
$nl  = chr( 92 ) . 'n';
$out = "# Copyright (C) " . gmdate( 'Y' ) . " bleuebuzz\n# This file is distributed under the GPL-2.0-or-later.\nmsgid \"\"\nmsgstr \"\"\n"
	. "\"Project-Id-Version: BB Woo Mail Layout 1.0.0$nl\"\n\"MIME-Version: 1.0$nl\"\n\"Content-Type: text/plain; charset=UTF-8$nl\"\n"
	. "\"Content-Transfer-Encoding: 8bit$nl\"\n\"POT-Creation-Date: " . gmdate( 'Y-m-d H:i' ) . "+0000$nl\"\n\"X-Domain: $domain$nl\"\n";
foreach ( $entries as $e ) {
	$out .= "\n";
	if ( ! empty( $e['comment'] ) ) {
		$out .= '#. ' . $e['comment'] . "\n";
	}
	$out .= '#: ' . implode( ' ', array_unique( $e['refs'] ) ) . "\n";
	if ( $e['ctx'] ) {
		$out .= 'msgctxt ' . $esc( $e['ctx'] ) . "\n";
	}
	$out .= 'msgid ' . $esc( $e['msgid'] ) . "\nmsgstr \"\"\n";
}
@mkdir( "$root/languages" );
file_put_contents( "$root/languages/$domain.pot", $out );
echo count( $entries ), " chaînes\n";
