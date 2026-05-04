<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Plugin Name: Livestream Embedder
Plugin URI: https://github.com/stronganchor/livestream-embedder/
Description: Embeds a YouTube livestream or most recent video from a YouTube channel.
Version: 1.0.1
Author: Strong Anchor Tech
Author URI: https://stronganchortech.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.en.html
*/

function livestream_embedder_settings_page() {
	add_options_page(
		esc_html__( 'Livestream Embedder Settings', 'livestream-embedder' ),
		esc_html__( 'Livestream Embedder', 'livestream-embedder' ),
		'manage_options',
		'livestream-embedder-settings',
		'livestream_embedder_settings_page_content'
	);
}
add_action( 'admin_menu', 'livestream_embedder_settings_page' );

function livestream_embedder_settings_link( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=livestream-embedder-settings' ) ) . '">' . esc_html__( 'Settings', 'livestream-embedder' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'livestream_embedder_settings_link' );

function livestream_embedder_sanitize_channel_id( $channel_id ) {
	$channel_id = sanitize_text_field( wp_unslash( $channel_id ) );
	$channel_id = preg_replace( '/[^A-Za-z0-9_-]/', '', $channel_id );

	return substr( $channel_id, 0, 128 );
}

function livestream_embedder_settings_page_content() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Livestream Embedder Settings', 'livestream-embedder' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'livestream_embedder_settings' );
			do_settings_sections( 'livestream-embedder-settings' );
			submit_button();
			?>
		</form>
		<h2><?php esc_html_e( 'Shortcode Usage', 'livestream-embedder' ); ?></h2>
		<p><?php esc_html_e( 'To embed a YouTube live stream or the most recent video, use:', 'livestream-embedder' ); ?></p>
		<code>[livestream_embedder channel_id="CHANNEL_ID"]</code>
		<p><?php esc_html_e( 'If you have set the channel ID sitewide setting, you can use:', 'livestream-embedder' ); ?></p>
		<code>[livestream_embedder]</code>
	</div>
	<?php
}

function livestream_embedder_register_settings() {
	register_setting( 'livestream_embedder_settings', 'livestream_embedder_api_key', 'sanitize_text_field' );
	register_setting( 'livestream_embedder_settings', 'livestream_embedder_default_channel', 'livestream_embedder_sanitize_channel_id' );

	add_settings_section(
		'livestream_embedder_section',
		esc_html__( 'API Key', 'livestream-embedder' ),
		'livestream_embedder_section_callback',
		'livestream-embedder-settings'
	);

	add_settings_field(
		'livestream_embedder_api_key',
		esc_html__( 'YouTube Data API Key', 'livestream-embedder' ),
		'livestream_embedder_api_key_callback',
		'livestream-embedder-settings',
		'livestream_embedder_section'
	);

	add_settings_field(
		'livestream_embedder_default_channel',
		esc_html__( 'Default Channel ID', 'livestream-embedder' ),
		'livestream_embedder_default_channel_callback',
		'livestream-embedder-settings',
		'livestream_embedder_section'
	);
}
add_action( 'admin_init', 'livestream_embedder_register_settings' );

function livestream_embedder_section_callback() {
	echo '<p>' . esc_html__( 'Enter your YouTube Data API key and default channel ID below.', 'livestream-embedder' ) . '</p>';
}

function livestream_embedder_api_key_callback() {
	$api_key = sanitize_text_field( get_option( 'livestream_embedder_api_key', '' ) );
	echo '<input type="password" name="livestream_embedder_api_key" value="' . esc_attr( $api_key ) . '" size="50" autocomplete="off" />';
}

function livestream_embedder_default_channel_callback() {
	$default_channel = livestream_embedder_sanitize_channel_id( get_option( 'livestream_embedder_default_channel', '' ) );
	echo '<input type="text" name="livestream_embedder_default_channel" value="' . esc_attr( $default_channel ) . '" size="50" />';
}

function livestream_embedder_fetch_youtube_video_id( $channel_id, $api_key, $mode ) {
	$args = array(
		'part'      => 'snippet',
		'channelId' => $channel_id,
		'maxResults'=> 1,
		'type'      => 'video',
		'key'       => $api_key,
	);

	if ( 'live' === $mode ) {
		$args['eventType'] = 'live';
	} else {
		$args['order'] = 'date';
	}

	$url      = add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/search' );
	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 10,
			'redirection' => 0,
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return '';
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $data['items'][0]['id']['videoId'] ) ) {
		return '';
	}

	return sanitize_text_field( $data['items'][0]['id']['videoId'] );
}

function livestream_embedder_ajax_get_video() {
	check_ajax_referer( 'livestream_embedder_video', 'nonce' );

	$channel_id = isset( $_POST['channel_id'] ) ? livestream_embedder_sanitize_channel_id( $_POST['channel_id'] ) : '';
	$api_key    = sanitize_text_field( get_option( 'livestream_embedder_api_key', '' ) );

	if ( '' === $channel_id || '' === $api_key ) {
		wp_send_json_error( array( 'message' => __( 'Missing YouTube configuration.', 'livestream-embedder' ) ), 400 );
	}

	$cache_key = 'livestream_embedder_video_' . md5( $channel_id );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && ! empty( $cached['video_id'] ) ) {
		wp_send_json_success( $cached );
	}

	$video_id = livestream_embedder_fetch_youtube_video_id( $channel_id, $api_key, 'live' );
	$source   = 'live';

	if ( '' === $video_id ) {
		$video_id = livestream_embedder_fetch_youtube_video_id( $channel_id, $api_key, 'recent' );
		$source   = 'recent';
	}

	if ( '' === $video_id ) {
		wp_send_json_error( array( 'message' => __( 'No live stream or recent video found.', 'livestream-embedder' ) ), 404 );
	}

	$payload = array(
		'video_id' => $video_id,
		'source'   => $source,
	);
	set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );

	wp_send_json_success( $payload );
}
add_action( 'wp_ajax_livestream_embedder_get_video', 'livestream_embedder_ajax_get_video' );
add_action( 'wp_ajax_nopriv_livestream_embedder_get_video', 'livestream_embedder_ajax_get_video' );

function livestream_embedder_enqueue_scripts() {
	if ( is_admin() ) {
		return;
	}

	global $post;
	if ( ! isset( $post ) || ! has_shortcode( $post->post_content, 'livestream_embedder' ) ) {
		return;
	}

	$script_handle = 'youtube-iframe-api';
	wp_enqueue_script( $script_handle, 'https://www.youtube.com/iframe_api', array(), '1.0.1', true );

	$config = array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'livestream_embedder_video' ),
		'action'  => 'livestream_embedder_get_video',
	);

	$inline_script = 'window.livestreamEmbedderConfig = ' . wp_json_encode( $config ) . ';
(function () {
	function setMessage(container, message) {
		container.textContent = "";
		var paragraph = document.createElement("p");
		paragraph.textContent = message;
		container.appendChild(paragraph);
	}

	function loadVideo(container, player) {
		var channelId = container.getAttribute("data-channel-id") || "";
		var settings = window.livestreamEmbedderConfig || {};
		var body = new URLSearchParams({
			action: settings.action,
			nonce: settings.nonce,
			channel_id: channelId
		});

		fetch(settings.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: body
		})
			.then(function (response) { return response.json(); })
			.then(function (payload) {
				if (payload && payload.success && payload.data && payload.data.video_id) {
					player.loadVideoById(payload.data.video_id);
					return;
				}
				setMessage(container, "Unable to load live stream.");
			})
			.catch(function () {
				setMessage(container, "Unable to load live stream.");
			});
	}

	window.onYouTubeIframeAPIReady = function () {
		document.querySelectorAll(".livestream-embedder-container").forEach(function (container, index) {
			if (container.dataset.livestreamReady === "1") {
				return;
			}
			container.dataset.livestreamReady = "1";
			if (!container.id) {
				container.id = "livestream-embedder-container-" + index;
			}

			var player = new YT.Player(container.id, {
				height: "360",
				width: "640",
				videoId: "",
				events: {
					onReady: function () {
						loadVideo(container, player);
					}
				}
			});
		});
	};

	if (window.YT && window.YT.Player) {
		window.onYouTubeIframeAPIReady();
	}
}());';

	wp_add_inline_script( $script_handle, $inline_script );
}
add_action( 'wp_enqueue_scripts', 'livestream_embedder_enqueue_scripts' );

function livestream_embedder_shortcode( $atts ) {
	$atts       = shortcode_atts( array( 'channel_id' => '' ), $atts, 'livestream_embedder' );
	$channel_id = livestream_embedder_sanitize_channel_id( $atts['channel_id'] );

	if ( '' === $channel_id ) {
		$channel_id = livestream_embedder_sanitize_channel_id( get_option( 'livestream_embedder_default_channel', '' ) );
	}

	if ( '' === $channel_id ) {
		return '<p>' . esc_html__( 'Please provide a channel ID or set a default channel in the plugin settings.', 'livestream-embedder' ) . '</p>';
	}

	if ( '' === sanitize_text_field( get_option( 'livestream_embedder_api_key', '' ) ) ) {
		return '<p>' . esc_html__( 'Please configure the YouTube Data API key in the plugin settings.', 'livestream-embedder' ) . '</p>';
	}

	return '<div class="livestream-embedder-container" data-channel-id="' . esc_attr( $channel_id ) . '" style="height:360px; width:640px;"></div>';
}
add_shortcode( 'livestream_embedder', 'livestream_embedder_shortcode' );
