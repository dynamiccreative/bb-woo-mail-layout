<?php
/**
 * Construit build/bb-woo-mail-layout.zip (fichiers de production uniquement, voir .distignore).
 *
 * Usage : php bin/build-zip.php
 *
 * @package BB\WooMailLayout
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- script CLI hors WordPress.

$root    = dirname( __DIR__ );
$slug    = 'bb-woo-mail-layout';
$build   = $root . '/build';
$zipfile = $build . '/' . $slug . '.zip';

$ignore = array_filter(
	array_map( 'trim', file( $root . '/.distignore' ) ),
	static fn( $line ) => '' !== $line && '#' !== $line[0]
);

if ( ! is_dir( $build ) ) {
	mkdir( $build, 0775, true );
}
if ( file_exists( $zipfile ) ) {
	unlink( $zipfile );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zipfile, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Impossible de créer $zipfile\n" );
	exit( 1 );
}

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$count = 0;
foreach ( $files as $file ) {
	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	foreach ( $ignore as $pattern ) {
		$pattern = rtrim( $pattern, '/' );
		if ( $relative === $pattern || str_starts_with( $relative, $pattern . '/' ) || fnmatch( $pattern, $relative ) ) {
			continue 2;
		}
	}
	$zip->addFile( $file->getPathname(), $slug . '/' . $relative );
	++$count;
}
$zip->close();

echo "$zipfile ($count fichiers)\n";
