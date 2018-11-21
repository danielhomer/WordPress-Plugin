<?php

/**
 * Control the surrogate control header.
 *
 * The surrogate control header controls the TTL for objects on Fastly. This class extends the basic header class and
 * ensures Surrogate-Control header is set.
 */
class Purgely_Surrogate_Control_Header extends Purgely_Header
{

    /**
     * Header name.
     *
     * @var string
     */
    protected $_header_name = 'Surrogate-Control';
    
    public function __construct() {
        add_filter( 'fastly_should_add_header_stale-while-revalidate', [ $this, 'serve_stale_on_revalidate' ] );
        parent::__construct();
    }

	/**
     * Sets original headers
     */
    public function build_original_headers()
    {
        if (
        	true === Purgely_Settings::get_setting('enable_stale_while_revalidate')
	        && $this->should_add_header( 'max-age' )
        ) {
            $this->_headers['max-age'] = absint(Purgely_Settings::get_setting('surrogate_control_ttl'));
        }
        if (
        	true === Purgely_Settings::get_setting('enable_stale_while_revalidate')
	        && $this->should_add_header( 'stale-while-revalidate' )
        ) {
            $this->_headers['stale-while-revalidate'] = absint(Purgely_Settings::get_setting('stale_while_revalidate_ttl'));
        }
        if (
        	true === Purgely_Settings::get_setting('enable_stale_if_error')
	        && $this->should_add_header( 'stale-if-error' )
        ) {
            $this->_headers['stale-if-error'] = absint(Purgely_Settings::get_setting('stale_if_error_ttl'));
        }
    }

    public function should_add_header( $header = '' ) {
    	return apply_filters( 'fastly_should_add_header_' . $header, true );
    }

    public function serve_stale_on_revalidate( $val ) {
    	if ( $this->is_amp_endpoint() || $this->is_sitemap() ) {
    		$val = false;
	    }

    	return $val;
    }

	public function is_amp_endpoint() {
		$is_amp = false;

		if (function_exists('amp_get_slug')) {
			$amp_slug = '/' . amp_get_slug() . '/';
			$amp_slug_end = '/' . amp_get_slug();
		} else {
			$amp_slug = '/amp/';
			$amp_slug_end = '/amp';
		}

		$url = strtok($_SERVER["REQUEST_URI"], '?');
		$amp_slug_length = strlen($amp_slug_end);

		if (strpos($url, $amp_slug) !== false || substr($url, -$amp_slug_length) === $amp_slug_end) {
			$is_amp = true;
		}

		return $is_amp;
	}

	public function is_sitemap() {
    	$url = strtok($_SERVER["REQUEST_URI"], '?');
    	$keys = [
    	    'sitemap.xml',
		    'sitemap_news.xml',
	    ];

    	foreach ( $keys as $key ) {
    		if ( stristr( $url, $key ) ) {
    			return true;
		    }
	    }

	    return false;
	}
}
