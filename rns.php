<?php
/*
Plugin Name: React & Share
Plugin URI: http://reactandshare.com
Description: Interact with your visitors with customizable reaction buttons. Click on the settings link on the left to <strong>customize your reaction buttons</strong>!
Author: Dekko
Author URI: http://reactandshare.com
Licence: GPLv2
Version: 3.6.1
Stable Tag: 3.6.1
*/

class ReactAndShare {

	// $reactions = array( 'like', 'love', 'happy', 'surprised', 'sad', 'angry' );


	function __construct() {

		$this->defaults = array(
			'like' => "Thumbs up",
			'love' => "Love",
			'happy' => "Joy",
			'surprised' => "Surprised",
			'sad' => "Sad",
			'angry' => "Angry",
			'fb' => "Share on Facebook",
			'twitter' => "Share on Twitter",
			'whatsapp' => "Share on Whatsapp",
			'pinterest' => "Share on Pinterest",
			'linkedin' => "Share on LinkedIn",
			'heading' => "Your reaction?"
		);
		$this->settings_url = urlencode(admin_url( 'options-general.php?page=rns_options'));

		$file = basename( __FILE__ );
		$folder = basename( dirname( __FILE__ ) );

		add_action('the_content', array($this,'addContent'));
		add_action('the_excerpt', array($this, 'disablePlugin'));
		add_action('admin_menu', array($this, 'addMenu'));
		add_action('admin_init', array($this, 'registerSettings'));
		add_action('admin_init', array($this, 'activationRedirect'));

		add_action( 'wp_ajax_rns_react', array($this,'react'));
		add_action( 'wp_ajax_nopriv_rns_react', array($this,'react' ));
		add_action( 'wp_ajax_rns_get_reactions', array($this,'getReactions'));
		add_action( 'wp_ajax_nopriv_rns_get_reactions', array($this,'getReactions' ));
		add_action( 'wp_ajax_rns_get_html', array($this,'getPluginHTML'));
		add_action( 'wp_ajax_nopriv_rns_get_html', array($this,'getPluginHTML' ));

		add_action('wp_enqueue_scripts', array($this,'addStylesAndScripts'));
		add_action( 'load-post.php', array($this, 'initMetaBox'));
		add_action( 'load-post-new.php', array($this, 'initMetaBox'));

		add_shortcode( 'rns_reactions', array($this, 'shortCode') );
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'addSettingsLink' ));
		add_filter( 'plugin_row_meta', array($this, 'addMetaLink'), 10, 2 );

		add_action( 'admin_notices', array($this, 'pluginActivationMsg') );
		add_filter( 'pre_update_option_rns_settings', array($this, 'updateSettings'), 10, 2);

		register_activation_hook(__FILE__, array($this, 'activationHook'));

	}

	function activationRedirect() {
	  if (get_option('rns_do_activation_redirect', false)) {
	    delete_option('rns_do_activation_redirect');
	    wp_redirect("options-general.php?page=rns_options");
	    exit;
		}
	}

	function activationHook() {
		add_option('rns_do_activation_redirect', true);
	}

	function getAmount($reaction, $id) {
		$meta_key = "rns_reaction_".$reaction;
		$amount = get_post_meta($id, $meta_key, true) ? intval(get_post_meta($id, $meta_key, true)) : 0;
		return $amount;
	}

	function updateSettings($newValue, $oldValue) {

		if (empty($newValue['enable_facebook'])) {
			$newValue['enable_facebook'] = "off";
		}
		if (empty($newValue['enable_twitter'])) {
			$newValue['enable_twitter'] = "off";
		}
		if (empty($newValue['enable_whatsapp'])) {
			$newValue['enable_whatsapp'] = "off";
		}
		if (empty($newValue['enable_pinterest'])) {
			$newValue['enable_pinterest'] = "off";
		}
		if (empty($newValue['enable_linkedin'])) {
			$newValue['enable_linkedin'] = "off";
		}

		return $newValue;
	}

	function pluginActivationHook() {}

	function pluginActivationMsg() {
		if( get_transient( 'rns-plugin-activation-notice' ) ){
			$settings_url = urlencode(admin_url( 'options-general.php?page=rns_options'));
			?>
				<div class="updated notice is-dismissible">
					<p>React & Share Plugin is activated.<p>
				<div>
			<?php
			delete_transient( 'rns-plugin-activation-notice' );
		}
	}

	function updateMessage( $plugin_data, $r ) {
		echo "asdf";
	}

	function addSettingsLink ( $links ) {
		$link = array('<a href="' . admin_url( 'options-general.php?page=rns_options' ) . '">Settings</a>');
		return array_merge( $links, $link );
	}

	function addMetaLink( $links, $file ) {
		$plugin = plugin_basename(__FILE__);
		$options = get_option('rns_settings');
		$api_key = $options['rns_api_key'];
		if ( $file == $plugin ) {
			return array_merge(
				$links,
				array( '<a href="https://dashboard.reactandshare.com">Customize your reaction buttons!</a>' )
			);
		}
		return $links;
	}

	function initMetaBox() {
		add_action( 'add_meta_boxes', array($this, 'addMetaBox'));
		add_action( 'save_post', array($this, 'savePostMeta'), 10, 2 );
	}

	function savePostMeta($post_id, $post) {
		if ( !isset( $_POST['rns_enable_meta_nonce'] ) || !wp_verify_nonce( $_POST['rns_enable_meta_nonce'], basename( __FILE__ ) ) )
		return $post_id;

		$post_type = get_post_type_object( $post->post_type );

		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
		return $post_id;

		$meta_value = ( isset( $_POST['rns_enable'] ) ? sanitize_html_class( $_POST['rns_enable'] ) : '' );
		if (empty($meta_value)) {
			$meta_value = "off";
		}
		update_post_meta( $post_id, 'rns_enable', $meta_value );
	}


	function addMetaBox() {
		add_meta_box('rns-enable-on-post', 'React & Share', array($this, 'renderMetaBox'), 'post', 'normal', 'default');
	}

	function renderMetaBox() {
		$options = get_option( 'rns_settings' );
		$enable = isset($options['rns_auto_enable']) ? $options['rns_auto_enable']: 'on';
		$post_id = get_the_ID();
		$meta_enable = get_post_meta( $post_id, 'rns_enable', true );
		if (!empty($meta_enable)) {
			$enable = $meta_enable;
		}
		wp_nonce_field( basename( __FILE__ ), 'rns_enable_meta_nonce' );
		?>

		<label><input type="checkbox" name="rns_enable" id="rns-enable" <?php checked($enable, 'on')?>>Enable reactions on this post</label>
		<?php
	}

	function addMenu() {
		add_options_page('React & Share Settings', 'React & Share', 'manage_options', 'rns_options', array($this, 'renderOptionsPage'));
	}

	function registerSettings() {
		$options = get_option('rns_settings');
		$apiKey = (isset($options) && isset($options['rns_api_key']) ) ? $options['rns_api_key'] : '';

		register_setting('rns_options', 'rns_settings');

		add_settings_section( 'rns_plugin_settings', '', array($this, 'renderReactionTranslations'), 'rns_options' );


		add_settings_field( 'rns_api_key', 'API Key', array($this, 'renderField'), 'rns_options', 'rns_plugin_settings', array('label' => 'api_key'));

		add_settings_section( 'rns_content', '', array($this, 'renderContent'), 'rns_options' );

		add_settings_section( 'rns_enable', '', array($this, 'renderEnableGuide'), 'rns_options' );
		add_settings_field( 'rns_options-auto-enable-on', 'Show buttons on posts by default', array($this, 'renderRadio'), 'rns_options', 'rns_enable', array('value' => 'on'));
		add_settings_field( 'rns_options-auto-enable-off', "Don't show buttons on posts by default", array($this, 'renderRadio'), 'rns_options', 'rns_enable', array('value' => 'off'));
		add_settings_field( 'rns_options-enable-only-single', "Enable only on single posts", array($this, 'renderCheckbox'), 'rns_options', 'rns_enable', array('key' => 'rns_enable_only_single','default' => 'off'));


		add_settings_section( 'rns_content_customstart', '', array($this, 'customStart'), 'rns_options' );

		if (empty($apiKey)) {
			add_settings_section( 'rns_heading', 'Reactions heading', array($this, 'renderReactionTranslations'), 'rns_options' );
			add_settings_field( 'rns_options-heading', 'Heading (leave blank for none)', array($this, 'renderHeaderField'), 'rns_options', 'rns_heading');


			add_settings_section( 'rns_translations', 'Reaction translations', array($this, 'renderReactionTranslations'), 'rns_options' );
			add_settings_field( 'rns_options-like', 'Like', array($this, 'renderField'), 'rns_options', 'rns_translations', array('label' => 'like'));
			add_settings_field( 'rns_options-love', 'Love', array($this, 'renderField'), 'rns_options', 'rns_translations', array('label' => 'love'));
			add_settings_field( 'rns_options-happy', 'Happy', array($this, 'renderField'), 'rns_options', 'rns_translations', array('label' => 'happy'));
			add_settings_field( 'rns_options-surprised', 'Surprised', array($this, 'renderField'), 'rns_options', 'rns_translations', array('label' => 'surprised'));
			add_settings_field( 'rns_options-sad', 'Sad', array($this, 'renderField'), 'rns_options', 'rns_translations', array('label' => 'sad'));
			add_settings_field( 'rns_options-angry', 'Angry', array($this, 'renderField'), 'rns_options', 'rns_translations', array('label' => 'angry'));


			add_settings_section( 'rns_share_buttons', 'Select share buttons', array($this, 'renderReactionTranslations'), 'rns_options');
			add_settings_field( 'rns_options-enable_facebook', "Facebook", array($this, 'renderCheckbox'), 'rns_options', 'rns_share_buttons', array('key' => 'enable_facebook', 'default' => 'on'));
			add_settings_field( 'rns_options-enable_twitter', "Twitter", array($this, 'renderCheckbox'), 'rns_options', 'rns_share_buttons', array('key' => 'enable_twitter', 'default' => 'on'));
			add_settings_field( 'rns_options-enable_whatsapp', "Whatsapp", array($this, 'renderCheckbox'), 'rns_options', 'rns_share_buttons', array('key' => 'enable_whatsapp', 'default' => 'on'));
			add_settings_field( 'rns_options-enable_pinterest', "Pinterest", array($this, 'renderCheckbox'), 'rns_options', 'rns_share_buttons', array('key' => 'enable_pinterest', 'default' => 'on'));
			add_settings_field( 'rns_options-enable_linkedin', "LinkedIn", array($this, 'renderCheckbox'), 'rns_options', 'rns_share_buttons', array('key' => 'enable_linkedin','default' => 'on'));


			add_settings_section( 'rns_share_translations', 'Share translations', array($this, 'renderReactionTranslations'), 'rns_options');
			add_settings_field( 'rns_options-fb', 'Facebook', array($this, 'renderField'), 'rns_options', 'rns_share_translations', array('label' => 'fb'));
			add_settings_field( 'rns_options-twitter', 'Twitter', array($this, 'renderField'), 'rns_options', 'rns_share_translations', array('label' => 'twitter'));
			add_settings_field( 'rns_options-whatsapp', 'Whatsapp', array($this, 'renderField'), 'rns_options', 'rns_share_translations', array('label' => 'whatsapp'));
			add_settings_field( 'rns_options-pinterest', 'Pinterest', array($this, 'renderField'), 'rns_options', 'rns_share_translations', array('label' => 'pinterest'));
			add_settings_field( 'rns_options-linkedin', 'LinkedIn', array($this, 'renderField'), 'rns_options', 'rns_share_translations', array('label' => 'linkedin'));
		}

		add_settings_section( 'rns_content_customend', '', array($this, 'customEnd'), 'rns_options' );

	}

	function shortCode() {
		$options = get_option('rns_settings');
		$post_id = get_the_ID();
		$post_object = $this->getPostObject($post_id);
		return $this->renderPlugin($options, $post_object);
	}

	function renderField($args) {
		$label = $args['label'];
		$options = get_option('rns_settings');
		$value = isset($options['rns_'.$label]) ? $options['rns_'.$label]: $this->defaults[$label];
		echo "<input type='text' name='rns_settings[rns_$label]' value='".esc_attr($value)."'>";
		if ($label == 'api_key' && isset($options['rns_api_key_wrong'])) {
			echo "<span style='margin-left: 1em; color: red'>API key does not match your domain!</span>";
		}
	}

	function renderHeaderField($args) {
		$options = get_option('rns_settings');
		$value = isset($options['rns_heading']) ? $options['rns_heading']: '';
		$defaultHeading = $this->defaults['heading'];
		echo "<input type='text' name='rns_settings[rns_heading]' value='".esc_attr($value)."' placeholder='".esc_attr($defaultHeading)."'>";
	}

	function renderContent() {
		?>
			<div style="border-top: 1px solid #bbb; width: 100%; padding: 30px 0; margin: 70px 0; border-bottom: 1px solid #bbb;">
				<p>Adding reactions manually (short code)</p>
				<ol>
					<li>You can use shortcode <code>[rns_reactions]</code> within post or page text.</li>
					<li>You can add <code>if (function_exists('rns_reactions')) { rns_reactions(); }</code> into your templates.</li>
				</ol>
			</div>
		<?php
	}

	function customStart() {
		?>
			<div style="display: none">
		<?php
	}

	function customEnd() {
		?>
			</div>
		<?php
	}



	function renderRadio($args) {
		$options = get_option( 'rns_settings' );
		$value = $args['value'];
		$set_value = isset($options['rns_auto_enable']) ? $options['rns_auto_enable']: 'on';
		?>
		<input type='radio' name='rns_settings[rns_auto_enable]' <?php checked( $set_value, $value ); ?> value='<?php echo $value ?>'>
		<?php
	}

	function renderCheckbox($args) {
		$options = get_option( 'rns_settings' );
		$key = $args['key'];
		$default = $args['default'];
		$value = isset($options[$key]) ? $options[$key]: $default;
		?>

		<input type="checkbox" name="rns_settings[<?php echo $key ?>]" <?php checked($value, 'on')?> >
		<?php
	}

	function renderReactionTranslations() {
		echo "";
	}

	function renderEnableGuide() {
		echo "";
	}

	function renderOptionsPage() {
		$settings_url = urlencode(admin_url( 'options-general.php?page=rns_options'));
		$options = get_option('rns_settings');
		$api_key = $options['rns_api_key'];
		?>


		<form action='options.php' method='post'>
			<img style="margin: 15px 0 10px;" height="74" width="302" src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/settings-logo.png' ?>">
			<?php if (isset($api_key) && !empty($api_key)) { ?>
				<p style="font-weight: 600; font-size: 22px">1. You can edit your buttons on our <a href="https://reactandshare.com" target="_blank">dashboard</a>.</p>
			<?php } else { ?>

				<p style="font-weight: 600; font-size: 22px">
					1. Get your API key by registering on our <a href="https://www.reactandshare.com/" target="_blank">website</a>.
					After registeration you can check your API key on our <a href="https://dashboard.reactandshare.com" target="_blank">dashboard</a>.
				</p>
			<?php } ?>


			<div style="position: relative;">
			<p style="font-weight: 600; font-size: 22px">2. Enter your API key here</p>
			<p>Remember to click Save changes</p>


			<div style="position: absolute; top: 130px">
			<?php submit_button(); ?>
			</div>



			<?php
			settings_fields( 'rns_options' );
			?>

			<?php
			do_settings_sections( 'rns_options' );
			?>

			</div>



			<?php submit_button(); ?>

		</form>

		<?php
	}

	function disablePlugin($excerpt) {
		$pattern = '/rnss.*/i';
		return preg_replace($pattern, '', $excerpt);
	}

	function getPostObject($post_id) {
		$post_url = get_permalink($post_id);
		$title = strip_tags(get_the_title($post_id));
		$tagObjects = get_the_tags($post_id);
		$single = is_single();
		$tags = "";
		if (!is_wp_error($tagObjects) && !empty($tagObjects)) {
			$tags .= $tagObjects[0]->name;
			for ($i = 1; $i < count($tagObjects); $i++) {
				$tags .=  ",".$tagObjects[$i]->name;
			}
		}
		$category = get_the_category($post_id);
		$categories = "";
		if (!is_wp_error($category) && !empty($category)) {
			$categories .= $category[0]->name;
			for ($i=1; $i<count($category); $i++) {
				$categories .= ",".$category[$i]->name;
			}
		}
		$author = get_the_author();
		$date = get_the_date('U', $post_id) * 1000;
		$comments = get_comments_number($post_id);

		$post_object = array(
			'id' => $post_id,
			'url' => $post_url,
			'title' => $title,
			'tags' => $tags,
			'categories' => $categories,
			'comments' => $comments,
			'date' => $date,
			'author' => $author,
			'single' => $single,
			'img' => get_the_post_thumbnail_url($post_id)
		);
		return $post_object;
	}

	function addContent($content) {
		$options = get_option('rns_settings');
		$show_on_every_post = isset($options['rns_auto_enable']) ? $options['rns_auto_enable'] : 'on';
		$post_id = get_the_ID();
		$enabled = get_post_meta( $post_id, 'rns_enable', true );
		$enable_only_single = isset($options['rns_enable_only_single']) ? $options['rns_enable_only_single'] : 'off';
		if (!is_page() && ($enabled=="on" || (empty($enabled) && $show_on_every_post=='on')) && (is_single() || (!is_single() && $enable_only_single != 'on')))  {
			$post_object = $this->getPostObject($post_id);
			$plugin = $this->renderPlugin($options, $post_object);
			$content .= $plugin;
		}
		return $content;
	}

	function getPluginHTML() {
		$options = get_option('rns_settings');

		$label_like =isset($options['rns_like']) ? $options['rns_like']: $this->defaults['like'];
		$label_love =isset($options['rns_love']) ? $options['rns_love']: $this->defaults['love'];
		$label_happy =isset($options['rns_happy']) ? $options['rns_happy']: $this->defaults['happy'];
		$label_surprised =isset($options['rns_surprised']) ? $options['rns_surprised']: $this->defaults['surprised'];
		$label_sad =isset($options['rns_sad']) ? $options['rns_sad']: $this->defaults['sad'];
		$label_angry =isset($options['rns_angry']) ? $options['rns_angry']: $this->defaults['angry'];

		$label_fb =isset($options['rns_fb']) ? $options['rns_fb']: $this->defaults['fb'];
		$label_twitter =isset($options['rns_twitter']) ? $options['rns_twitter']: $this->defaults['twitter'];
		$label_whatsapp =isset($options['rns_whatsapp']) ? $options['rns_whatsapp']: $this->defaults['whatsapp'];
		$label_pinterest =isset($options['rns_pinterest']) ? $options['rns_pinterest']: $this->defaults['pinterest'];
		$label_linkedin =isset($options['rns_linkedin']) ? $options['rns_linkedin']: $this->defaults['linkedin'];

		if (!empty($options['rns_heading'])) {
			$heading = $options['rns_heading'];
			echo "<h3 class='rns-header'>$heading</h3>";
		} ?>

		<ul>
			<li class="rns-reaction-button" data-reaction="like">
				<a href="">
					<em><?php echo $label_like ?></em>
					<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/like.png' ?>" />
					<span>0</span>
				</a>
			</li>
			<li class="rns-reaction-button" data-reaction="love">
				<a href="">
					<em><?php echo $label_love ?></em>
					<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/love.png' ?>" />
					<span>0</span>
				</a>
			</li>
			<li class="rns-reaction-button" data-reaction="happy">
				<a href="">
					<em><?php echo $label_happy ?></em>
					<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/happy.png' ?>" />
					<span>0</span>
				</a>
			</li>
			<li class="rns-reaction-button" data-reaction="surprised">
				<a href="">
					<em><?php echo $label_surprised ?></em>
					<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/surprised.png' ?>" />
					<span>0</span>
				</a>
			</li>
				<li class="rns-reaction-button" data-reaction="sad">
					<a href="">
						<em><?php echo $label_sad ?></em>
						<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/sad.png' ?>" />
						<span>0</span>
					</a>
				</li>
			<li class="rns-reaction-button" data-reaction="angry">
				<a href="">
					<em><?php echo $label_angry ?></em>
					<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/angry.png' ?>" />
					<span>0</span>
				</a>
			</li>
		</ul>


		<div style="clear: both;"></div>

		<?php if ($options['enable_twitter'] != 'off' || $options['enable_facebook'] != 'off' || $options['enable_whatsapp'] != 'off' || $options['enable_pinterest'] != 'off' || $options['enable_linkedin'] != 'off' ) { ?>
			<div class="d_reactions_shares">
				<?php if ($options['enable_facebook'] != 'off' ) { ?>
					<a href="" class="rns-share-link rns-fb-share">
						<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/fb-icon.png' ?>" />
						<span class="rns-share-label"><?php echo $label_fb ?></span>
					</a><?php }
				if ($options['enable_linkedin'] != 'off' ) { ?>
					<a href="" class="d_linkedin rns-share-link rns-linkedin-share">
						<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/linkedin64.png' ?>" />
						<span class="rns-share-label"><?php echo $label_linkedin ?></span>
					</a><?php }
				if ($options['enable_twitter'] != 'off' ) { ?>
					<a href="" class="d_twitter rns-share-link rns-twitter-share">
						<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/twitter-64.png' ?>" />
						<span class="rns-share-label"><?php echo $label_twitter ?></span>
					</a><?php }
				if ($options['enable_whatsapp'] != 'off' ) { ?>
					<a href="" class="d_whatsapp rns-share-link rns-whatsapp-share">
						<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/whatsapp-icon.png' ?>" />
						<span class="rns-share-label"><?php echo $label_whatsapp ?></span>
					</a><?php }
				if ($options['enable_pinterest'] != 'off' ) { ?>
					<a href="" class="d_pinterest rns-share-link rns-pinterest-share">
						<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/pinterest-icon.png' ?>" />
						<span class="rns-share-label"><?php echo $label_pinterest ?></span>
					</a>
				<?php } ?>


				<div class="rns-footer">
					<a class="rns-footer-link" href="https://reactandshare.com" target="_blank" rel="nofollow">
						<img src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/logo.svg' ?>">
					</a>
				</div>
			</div>
		 <?php }

		die();
	}

	function renderPlugin($options, $post_object) {
		$wp_title = get_bloginfo('name');
		$apiKey = (isset($options) && isset($options['rns_api_key']) ) ? $options['rns_api_key'] : '';

		if (!empty($apiKey)) {

			$plugin = '<div class="rns"';
			$plugin .= ' data-title="'.$post_object['title'].'"';
			$plugin .= ' data-tags="'.$post_object['tags'].'"';
			$plugin .= ' data-categories="'.$post_object['categories'].'"';
			$plugin .= ' data-comments="'.$post_object['comments'].'"';
			$plugin .= ' data-date="'.$post_object['date'].'"';
			$plugin .= ' data-author="'.$post_object['author'].'"';
			$plugin .= ' data-single="'.$post_object['single'].'"';
			$plugin .= ' data-url="'.$post_object['url'].'"';
			$plugin .= '></div> <!-- Check out https://reactandshare.com -->';
		}
		else {
			$plugin = '<div class="d_reactions"';
			$plugin .= ' data-post-id="'.$post_object['id'].'"';
			$plugin .= ' data-post-url="'.$post_object['url'].'"';
			$plugin .= ' data-post-title="'.$post_object['title'].'"';
			$plugin .= ' data-post-img="'.$post_object['img'].'"';
			$plugin .= '></div> <!-- Check out https://reactandshare.com -->';
		}
		return $plugin;
	}

	function getClass($reaction, $post_id) {
		return ('');
	}


	function react() {
		if (isset($_POST["postid"])) {
			$post_id = intval($_POST["postid"]);
			$reaction = $_POST["reaction"];
			$unreact = $_POST["unreact"];
		}
		$amount = $this->getAmount($reaction, $post_id);
		if (isset($unreact) && $unreact === "true") {
			$amount = (int) $amount - 1;
			if ($amount >=0) {
				update_post_meta($post_id, "rns_reaction_".$reaction, $amount);
			}
		}
		else {
			$amount = (int) $amount + 1;
			if ($amount >=0) {
				update_post_meta($post_id, "rns_reaction_".$reaction, $amount);
			}
		}
		wp_send_json(array( 'amount' => $amount)); // return;
	}


	function getReactions() {
		$response = array();
		foreach($_POST["posts"] as $id) {
			$id = intval($id);
			$meta = get_post_meta($id);
			$post = array();
			$reactions = array("like", "love", "happy", "surprised", "sad", "angry");
			foreach($reactions as $reaction) {
				$post[$reaction] = isset($meta["rns_reaction_".$reaction]) ? intval($meta["rns_reaction_".$reaction][0]) : 0;
			}
			$response[$id] = $post;
		}

		wp_send_json($response);
	}

	function addStylesAndScripts() {
		$options = get_option('rns_settings');
		$apiKey = (isset($options) && isset($options['rns_api_key']) ) ? $options['rns_api_key'] : '';

		if (!empty($apiKey)) {
  		wp_enqueue_script('rns-new-script', 'https://cdn.reactandshare.com/plugin/rns.js', array('jquery'), null);
			wp_add_inline_script('rns-new-script', "jQuery(document).ready(function() {window.rnsData = {apiKey: '$apiKey', multiple: true}; initRns();});", 'before');
		}
		else {
			wp_enqueue_style( 'rns-font', 'https://fonts.googleapis.com/css?family=Open+Sans' );
			wp_enqueue_style( 'rns-style', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/css/styles.css', array(), "3.3" );
			wp_enqueue_script( 'idle-js', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/js/idle.min.js', array(), "0.0.2" );
			wp_enqueue_script( 'js-cookie', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/js/js.cookie.min.js', array(), "3.3" );
			wp_enqueue_script( 'rns-script', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/js/rns.js', array( 'jquery', 'js-cookie', 'idle-js' ), "3.3" );
		}

		$localize = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'api_key' => $options['rns_api_key']
		);

		wp_localize_script( 'rns-script', 'rns_data', $localize );
	}
}

function rns_reactions() {
	// Call from templates
	// if (function_exists('rns_reactions')) { rns_reactions(); }
	$rns = new ReactAndShare();
	$options = get_option('rns_settings');
	$post_id = get_the_ID();
	$post_object = $rns->getPostObject($post_id);
	echo $rns->renderPlugin($options, $post_object);
}

new ReactAndShare();
?>
