<?php

/**
 * hxiilog
 * very simple, constantly evolving logger class for stuff and things
 * by Paul Glushak aka hxii
 * v. 0.1
 * http://github.com/hxii/
 */

defined( 'ABSPATH' ) or die();

class HxiiLogger
{
	public $filename;
	public $loglevel;
	private const levels = array(
		'off'     => 0,
		'info'.   => 100,
		'warning' => 200,
		'debug'   => 999,
	);

	public function __construct( $file = "debug.log", $level = 'info' )
	{
		$this->filename = plugin_dir_path( __FILE__ ) . $file;
		$this->loglevel = $level;
	}

	public function debug( $string) {
		$this->write_to_file( $string, 'debug', 'D' );
	}

	public function info( $string ) {
		$this->write_to_file( $string, 'info', 'I' );
	}

	private function write_to_file( $string, $level, $let ) {
        if ( SELF::levels[$level] <= SELF::levels[$this->loglevel] ) {
			$time = @date( '[d/M/Y:H:i:s]' );
	        $fh = fopen( $this->filename, 'a+' );
        	fwrite( $fh, "$time [$let] $string" . PHP_EOL );
        	fclose( $fh );
        }
	}
}