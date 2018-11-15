<?php

/**
 * Singleton for registering default WP purges.
 */
class Purgely_Purges
{
    /**
     * The one instance of Purgely_Purges.
     *
     * @var Purgely_Purges
     */
    private static $instance;

    /**
     * Instantiate or return the one Purgely_Purges instance.
     *
     * @return Purgely_Purges
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initiate actions.
     */
    public function __construct()
    {
        foreach ($this->_purge_actions() as $action) {
            add_action($action, array($this, 'purge'), 10, 1);
        }
        
        add_action( 'shutdown', [ $this, 'maybe_send_purge' ], 100 ); // Purge requests should be the last thing WP does
    }

    public function maybe_send_purge() {
    	$collections = apply_filters( 'fastly_purge_collections', [] );

    	if ( ( current_user_can( 'publish_posts' ) && ! empty( $collections ) ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		    $purgely = new Purgely_Purge();
		    foreach ($collections as $collection) {
			    $purgely->purge('key-collection', $collection);
		    }
	    }
    }

    /**
     * Callback for post changing events to purge keys.
     *
     * @param  int $post_id Post ID.
     * @return void
     */
    public function purge($post_id)
    {

        if (!in_array(get_post_status($post_id), array('publish', 'trash', 'future', 'draft'))) {
            return;
        }

        // Check credentials
        $fastly_hostname = Purgely_Settings::get_setting('fastly_api_hostname');
        $fastly_service_id = Purgely_Settings::get_setting('fastly_service_id');
        $fastly_api_key = Purgely_Settings::get_setting('fastly_api_key');
        $test = test_fastly_api_connection($fastly_hostname, $fastly_service_id, $fastly_api_key);
        if (!$test['status']) {
            return;
        }

        $related_collection_object = new Purgely_Related_Surrogate_Keys($post_id);
        $collections = $related_collection_object->locate_all();

        add_filter( 'fastly_purge_collections', function( $c ) use ( $collections ) {
        	return array_merge( $c, $collections );
        }, 10, 1 );
    }

    /**
     * A list of actions to purge URLs.
     *
     * @return array    List of actions.
     */
    private function _purge_actions()
    {
        return array(
            'save_post',
            'deleted_post',
            'trashed_post',
            'delete_attachment',
        );
    }
}

/**
 * Instantiate or return the one Purgely_Purges instance.
 *
 * @return Purgely_Purges
 */
function get_purgely_purges_instance()
{
    return Purgely_Purges::instance();
}

get_purgely_purges_instance();
