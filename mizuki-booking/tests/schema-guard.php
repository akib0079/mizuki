<?php
/**
 * Schema guard — run with: php tests/schema-guard.php
 *
 * dbDelta() parses each line of a CREATE TABLE as a column or key definition.
 * A comment line inside one silently corrupts that table: it is created without
 * the affected columns/indexes, or not updated at all, and nothing is reported.
 *
 * That exact mistake once shipped here and cost real debugging time on a live
 * site, so it is checked automatically now.
 *
 * @package Mizuki_Booking
 */

$file = __DIR__ . '/../includes/class-mzk-install.php';
$src  = file_get_contents( $file );

if ( false === $src ) {
	fwrite( STDERR, "Cannot read {$file}\n" );
	exit( 1 );
}

preg_match_all( '/CREATE TABLE.*?\) \{\$charset\};/s', $src, $matches );

$blocks = $matches[0];
$fail   = 0;

if ( count( $blocks ) < 7 ) {
	echo '  FAIL expected at least 7 CREATE TABLE blocks, found ' . count( $blocks ) . "\n";
	++$fail;
}

foreach ( $blocks as $ddl ) {
	preg_match( '/CREATE TABLE (\S+)/', $ddl, $name );
	$table = $name[1] ?? '?';

	if ( preg_match( '#/\*|\*/|--\s#', $ddl ) ) {
		echo "  FAIL {$table} — CREATE TABLE contains a comment; dbDelta will mis-parse it\n";
		++$fail;
		continue;
	}

	if ( ! preg_match( '/PRIMARY KEY {2}\(/', $ddl ) ) {
		echo "  FAIL {$table} — dbDelta needs two spaces in 'PRIMARY KEY  (id)'\n";
		++$fail;
		continue;
	}

	echo "  ok   {$table}\n";
}

echo "\n" . ( $fail ? "{$fail} problem(s) found\n" : count( $blocks ) . " tables OK\n" );
exit( $fail ? 1 : 0 );
