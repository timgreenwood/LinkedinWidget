<?php
/**
 * @package LinkedinWidget
 * @version 1.0
 */
/*
Plugin Name: LinkedinWidget
Plugin URI: https://github.com/timgreenwood/LinkedinWidget
Description: Widget to allow displaying of linkedin data.
Author: Tim Greenwood
Version: 1.0
Author URI: http://timgreenwood.co.uk/
*/

require_once ('oauth/linkedinoauth.php');

class LinkedinWidget extends WP_Widget {

	private $apikey;
	private $secretkey;
	private $user_token;
	private $user_secret;

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'linkedinfeedwidget', // Base ID
			__('LinkedIn Feed Widget', 'linkedin_feed_widget'), // Name
			array(	) // Args
		);
	}
	
	/**
	 * Get data from Linkedin (or cached data)
	 * @param string $widget_id Widget identifier
	 * @param array $instance Saved values from database.
	 * @return array JSON decoded data
	 */
	function get_data($widget_id, $instance) {

		if ( ! $data = wp_cache_get( $widget_id, 'LinkedinWidget_cache' ) ) {
			$data = get_transient('LinkedinWidget_cache');

			// check to see if we have cached data, if we do then validate and return
			// else we need to do the linkedin api call then cache the data
			if ($data === false) {
				$to = new LinkedInOAuth($this->apikey, $this->secretkey,$this->user_token,$this->user_secret);
				$args = array('event-type'=>'status-update','format'=>'json','start'=>0,'count'=>10);
				if ($data = $to->oAuthRequest('http://api.linkedin.com/v1/companies/'.$instance['linkedin_companyid'].'/updates', $args, 'GET')) {
					set_transient('LinkedinWidget_cache', $data, 3600 * 24);
					set_transient('LinkedinWidget_cache_extend', $data, 3600 * 24 * 999);
				} else {
					// if we cant get data from linkedin then we need to get the long-term cache, set it to the main cache and refresh the long-term cache
					$data = get_transient('LinkedinWidget_cache_extend');
					set_transient('LinkedinWidget_cache', $data, 3600 * 24);
					set_transient('LinkedinWidget_cache_extend', $data, 3600 * 24 * 999);
				}
			}

			wp_cache_add( $widget_id, $data, 'LinkedinWidget_cache' );
		}

		return json_decode($data);
	}

	/**
	 * Set variables from widget
	 * @param array $instance Saved values from database.
	 */
	function set_vars($instance) {
		$this->apikey = isset( $instance[ 'linkedin_apikey' ] ) ? $instance[ 'linkedin_apikey' ] : null;
		$this->secretkey = isset( $instance[ 'linkedin_secretkey' ] ) ? $instance[ 'linkedin_secretkey' ] : null;
		$this->user_token = isset( $instance[ 'linkedin_user_token' ] ) ? $instance[ 'linkedin_user_token' ] : null;
		$this->user_secret = isset( $instance[ 'linkedin_user_secret' ] ) ? $instance[ 'linkedin_user_secret' ] : null;
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args  Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
	
		$reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

		// set linkedin api vars
		$this->set_vars($instance);

		if (!$this->apikey || !$this->secretkey || !$this->user_token || !$this->user_secret) return false;
	
		$data = $this->get_data($args['widget_id'], $instance);
		echo $args['before_widget'];
		echo '<h3>'.$instance['linkedin_title'].'</h3>';
		echo '<ul>';
		if (isset($data->_total) && $data->_total > 0) {
			foreach($data->values as $update) {
				$share = $update->updateContent->companyStatusUpdate->share;
				echo '<li class="'.($share->content->thumbnailUrl?'':' no-thumbnail').'">';
				if ($share->content->thumbnailUrl) {
				echo '<div class="thumbnail"><a href="'.$share->content->shortenedUrl.'" target="_blank"><img src="'.$share->content->thumbnailUrl.'" /></a></div>';
				}
				echo '<div class="content">';
				if(preg_match($reg_exUrl, $share->comment, $url)) {
			// make the urls hyper links
			echo '<div class="comment">'.preg_replace($reg_exUrl, "<a href='{$url[0]}' target='_blank'>{$url[0]}</a> ", $share->comment).'</div>';
				} else {
			// if no urls in the text just return the text
			echo '<div class="comment">'.$share->comment.'</div>';
				}
			echo '</div>';
			echo '<div class="timestamp">'.date('Y-m-d H:i:s',($share->timestamp/1000)).'</div>';
				echo '</li>';
			}
		} else {
			echo '<li>Sorry, our LinkedIn feed is currently unavailable.</li>';
		}
		echo '</ul>';
		echo '<a href="http://www.linkedin.com/company/'.$instance['linkedin_companyid'].'" target="_blank" class="bottom_link">Find us on LinkedIn</a>';
		echo $args['after_widget'];
	}
	
	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {

		$linkedin_title = isset( $instance[ 'linkedin_title' ] ) ? $instance[ 'linkedin_title' ] : __( 'Widget Title', 'linkedin_title' );
		$linkedin_companyid = isset( $instance[ 'linkedin_companyid' ] ) ? $instance[ 'linkedin_companyid' ] : __( 'Company ID', 'linkedin_companyid' );
		$linkedin_apikey = isset( $instance[ 'linkedin_apikey' ] ) ? $instance[ 'linkedin_apikey' ] : __( 'API Key', 'linkedin_apikey' );
		$linkedin_secretkey = isset( $instance[ 'linkedin_secretkey' ] ) ? $instance[ 'linkedin_secretkey' ] : __( 'Secret Key', 'linkedin_secretkey' );
		$linkedin_user_token = isset( $instance[ 'linkedin_user_token' ] ) ? $instance[ 'linkedin_user_token' ] : __( 'User Token', 'linkedin_user_token' );
		$linkedin_user_secret = isset( $instance[ 'linkedin_user_secret' ] ) ? $instance[ 'linkedin_user_secret' ] : __( 'User Secret', 'linkedin_user_secret' );

		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'linkedin_title' ); ?>"><?php _e( 'Widget Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'linkedin_title' ); ?>" name="<?php echo $this->get_field_name( 'linkedin_title' ); ?>" type="text" value="<?php echo esc_attr( $linkedin_title ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'linkedin_companyid' ); ?>"><?php _e( 'Company ID:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'linkedin_companyid' ); ?>" name="<?php echo $this->get_field_name( 'linkedin_companyid' ); ?>" type="text" value="<?php echo esc_attr( $linkedin_companyid ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'linkedin_apikey' ); ?>"><?php _e( 'API Key:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'linkedin_apikey' ); ?>" name="<?php echo $this->get_field_name( 'linkedin_apikey' ); ?>" type="text" value="<?php echo esc_attr( $linkedin_apikey ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'linkedin_secretkey' ); ?>"><?php _e( 'Secret Key:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'linkedin_secretkey' ); ?>" name="<?php echo $this->get_field_name( 'linkedin_secretkey' ); ?>" type="text" value="<?php echo esc_attr( $linkedin_secretkey ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'linkedin_user_token' ); ?>"><?php _e( 'User Token:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'linkedin_user_token' ); ?>" name="<?php echo $this->get_field_name( 'linkedin_user_token' ); ?>" type="text" value="<?php echo esc_attr( $linkedin_user_token ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'linkedin_user_secret' ); ?>"><?php _e( 'User Secret:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'linkedin_user_secret' ); ?>" name="<?php echo $this->get_field_name( 'linkedin_user_secret' ); ?>" type="text" value="<?php echo esc_attr( $linkedin_user_secret ); ?>">
		</p>
		<?php 
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['linkedin_title'] = ( ! empty( $new_instance['linkedin_title'] ) ) ? strip_tags( $new_instance['linkedin_title'] ) : '';
		$instance['linkedin_companyid'] = ( ! empty( $new_instance['linkedin_companyid'] ) ) ? strip_tags( $new_instance['linkedin_companyid'] ) : '';
		$instance['linkedin_apikey'] = ( ! empty( $new_instance['linkedin_apikey'] ) ) ? strip_tags( $new_instance['linkedin_apikey'] ) : '';
		$instance['linkedin_secretkey'] = ( ! empty( $new_instance['linkedin_secretkey'] ) ) ? strip_tags( $new_instance['linkedin_secretkey'] ) : '';
		$instance['linkedin_user_token'] = ( ! empty( $new_instance['linkedin_user_token'] ) ) ? strip_tags( $new_instance['linkedin_user_token'] ) : '';
		$instance['linkedin_user_secret'] = ( ! empty( $new_instance['linkedin_user_secret'] ) ) ? strip_tags( $new_instance['linkedin_user_secret'] ) : '';

		return $instance;
	}

} // class LinkedinWidget

add_action('widgets_init', function() {
	register_widget("LinkedinWidget");
});