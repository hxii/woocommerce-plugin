<?php

/**
 * hxiilog
 * very simple, constantly evolving logger class for stuff and things
 * by Paul Glushak aka hxii
 * v. 0.2
 * http://github.com/hxii/
 */

defined( 'ABSPATH' ) or die();

class HxiiLogger
{
	public $filename;
	public $filepath;
	public $loglevel;
	public $logsize;
	private const levels = array(
		'off'     => 0,
		'info'    => 100,
		'warning' => 200,
		'debug'   => 999,
	);

	public function __construct( $file = "debug.log", $level = 'info', int $size = 1 )
	{
		$this->filepath = $file; // Now we set the path when initializing the class
		$this->filename = basename( $this->filepath );
		$this->loglevel = $level;
		$this->logsize = ( $size * 1048576 );
	}

	public function debug( $string ) {
		$this->write_to_file( $string, 'debug', 'D' );
	}

	public function info( $string ) {
		$this->write_to_file( $string, 'info', 'I' );
	}

	private function write_to_file( $string, $level, $let ) {
        if ( SELF::levels[$level] <= SELF::levels[$this->loglevel] ) {
			$time = @date( '[d/M/Y:H:i:s]' );
	        $fh = fopen( $this->filepath, 'a+' );
        	fwrite( $fh, "$time [$let] $string" . PHP_EOL );
        	fclose( $fh );
			if ( $this->check_log_size( $this->filepath ) ) {
				$this->rename_old_log();
			}
        }
	}

	private function rename_old_log() {
		if ( file_exists( $this->filepath ) ) {
			$path = pathinfo( $this->filepath );
			$int = '1';
			$newname = "$path[filename].$int";
			while ( file_exists( "$path[dirname]/$newname" ) ) {
				$int++;
				$newname = "$path[filename].$int";
			}
				rename( $this->filepath, $path['dirname'].'/'.$newname );
		}
	}

	private function check_log_size( $file, $return_size = false ) {
		if ( $return_size ) {
			return array( filesize( $file ), $this->logsize );
		} else {
			return ( filesize( $file ) >= $this->logsize );
		}
	}
}