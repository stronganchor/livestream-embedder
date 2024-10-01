<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*
Plugin Name: Livestream Embedder
Plugin URI: https://github.com/stronganchor/livestream-embedder/
Description: Embeds a YouTube livestream or most recent video from a YouTube channel.
Version: 1.0.0
Author: Strong Anchor Tech
Author URI: https://stronganchortech.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.en.html
*/

// Add settings page
function livestream_embedder_settings_page() {
    add_options_page(
        esc_html__('Livestream Embedder Settings', 'livestream-embedder'),
        esc_html__('Livestream Embedder', 'livestream-embedder'),
        'manage_options',
        'livestream-embedder-settings',
        'livestream_embedder_settings_page_content'
    );
}
add_action('admin_menu', 'livestream_embedder_settings_page');

// Add settings link to plugin page
function livestream_embedder_settings_link($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=livestream-embedder-settings')) . '">' . esc_html__('Settings', 'livestream-embedder') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'livestream_embedder_settings_link');

// Settings page content
function livestream_embedder_settings_page_content() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Livestream Embedder Settings', 'livestream-embedder'); ?></h1>
        <form method="post" action="options.php">
            <?php
            wp_nonce_field('livestream_embedder_settings_action', 'livestream_embedder_nonce');
            settings_fields('livestream_embedder_settings');
            do_settings_sections('livestream-embedder-settings');
            submit_button();
            ?>
        </form>
        <h2><?php esc_html_e('Shortcode Usage', 'livestream-embedder'); ?></h2>
        <p><?php esc_html_e('To embed a YouTube live stream or the most recent live stream video, use the following shortcode:', 'livestream-embedder'); ?></p>
        <code>[livestream_embedder channel_id="CHANNEL_ID"]</code>

        <p><?php esc_html_e('If you have set the channel ID sitewide setting, you can use the shortcode without entering the ID:', 'livestream-embedder'); ?></p>
        <code>[livestream_embedder]</code>
        <p><?php esc_html_e('Replace CHANNEL_ID with the actual YouTube channel ID (e.g., UCabcdefghijklmnopqrstuvwx).', 'livestream-embedder'); ?></p>
        <h3><?php esc_html_e('How to Find the Channel ID', 'livestream-embedder'); ?></h3>
        <ol>
            <li><?php esc_html_e('Go to the YouTube channel\'s page.', 'livestream-embedder'); ?></li>
            <li><?php esc_html_e('Look at the URL in your browser\'s address bar. The channel ID is the string of characters after the "/channel/" part of the URL.', 'livestream-embedder'); ?></li>
            <li><?php esc_html_e('For example, if the URL is https://www.youtube.com/channel/UCxyzXXXXXXXXXXXXXXXXXXX, the channel ID is UCxyzXXXXXXXXXXXXXXXXXXX.', 'livestream-embedder'); ?></li>
            <li><?php esc_html_e('If the channel uses a custom URL (e.g., https://www.youtube.com/c/somechannelname), you can still find the channel ID by viewing the page source and searching for "channelId".', 'livestream-embedder'); ?></li>
        </ol>
    </div>
    <?php
}

// Register settings
function livestream_embedder_register_settings() {
    if (isset($_POST['livestream_embedder_nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['livestream_embedder_nonce'])), 'livestream_embedder_settings_action')) {
        wp_die(esc_html__('Nonce verification failed. Please reload the page and try again.', 'livestream-embedder'));
    }

    register_setting('livestream_embedder_settings', 'livestream_embedder_api_key', 'sanitize_text_field');
    register_setting('livestream_embedder_settings', 'livestream_embedder_default_channel', 'sanitize_text_field');

    add_settings_section(
        'livestream_embedder_section',
        esc_html__('API Key', 'livestream-embedder'),
        'livestream_embedder_section_callback',
        'livestream-embedder-settings'
    );

    add_settings_field(
        'livestream_embedder_api_key',
        esc_html__('YouTube Data API Key', 'livestream-embedder'),
        'livestream_embedder_api_key_callback',
        'livestream-embedder-settings',
        'livestream_embedder_section'
    );

    add_settings_field(
        'livestream_embedder_default_channel',
        esc_html__('Default Channel ID', 'livestream-embedder'),
        'livestream_embedder_default_channel_callback',
        'livestream-embedder-settings',
        'livestream_embedder_section'
    );
}
add_action('admin_init', 'livestream_embedder_register_settings');

// Section callback
function livestream_embedder_section_callback() {
    echo '<p>' . esc_html__('Enter your YouTube Data API key and default channel ID below:', 'livestream-embedder') . '</p>';
}

function livestream_embedder_api_key_callback() {
    $api_key = sanitize_text_field(get_option('livestream_embedder_api_key'));
    echo '<input type="text" name="livestream_embedder_api_key" value="' . esc_attr($api_key) . '" size="50" />';
}

function livestream_embedder_default_channel_callback() {
    $default_channel = sanitize_text_field(get_option('livestream_embedder_default_channel'));
    echo '<input type="text" name="livestream_embedder_default_channel" value="' . esc_attr($default_channel) . '" size="50" />';
}

// Enqueue scripts
function livestream_embedder_enqueue_scripts() {
    wp_enqueue_script('youtube-iframe-api', 'https://www.youtube.com/iframe_api', array(), null, true);
    
    $inline_script = '
        var player;

        function onYouTubeIframeAPIReady() {
            player = new YT.Player("livestream-container", {
                height: "360",
                width: "640",
                videoId: "",
                events: {
                    "onReady": onPlayerReady
                }
            });
        }

        function onPlayerReady(event) {
            var channelId = "' . esc_js(get_option('livestream_embedder_default_channel')) . '";
            fetch("https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" + channelId + "&eventType=live&type=video&key=' . esc_js(get_option('livestream_embedder_api_key')) . '")
                .then(response => response.json())
                .then(data => {
                    if (data.items && data.items.length > 0) {
                        var videoId = data.items[0].id.videoId;
                        player.loadVideoById(videoId);
                    } else {
                        fetch("https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" + channelId + "&order=date&type=video&key=' . esc_js(get_option('livestream_embedder_api_key')) . '")
                            .then(response => response.json())
                            .then(data => {
                                if (data.items && data.items.length > 0) {
                                    var videoId = data.items[0].id.videoId;
                                    player.loadVideoById(videoId);
                                } else {
                                    document.getElementById("livestream-container").innerHTML = "<p>' . esc_js(esc_html__('No live stream or recent video found. Please check your API key and Channel ID.', 'livestream-embedder')) . '</p>";
                                }
                            })
                            .catch(error => {
                                document.getElementById("livestream-container").innerHTML = "<p>' . esc_js(esc_html__('Unable to load live stream. Please check your API key and Channel ID.', 'livestream-embedder')) . '</p>";
                            });
                    }
                })
                .catch(error => {
                    document.getElementById("livestream-container").innerHTML = "<p>' . esc_js(esc_html__('Unable to load live stream. Please check your API key and Channel ID.', 'livestream-embedder')) . '</p>";
                });
        }
    ';
    wp_add_inline_script('youtube-iframe-api', $inline_script);
}
add_action('wp_enqueue_scripts', 'livestream_embedder_enqueue_scripts');

// Shortcode callback
function livestream_embedder_shortcode($atts) {
    $channel_id = isset($atts['channel_id']) ? sanitize_text_field($atts['channel_id']) : sanitize_text_field(get_option('livestream_embedder_default_channel'));
    $api_key = sanitize_text_field(get_option('livestream_embedder_api_key'));

    if (empty($channel_id)) {
        return '<p>' . esc_html__('Please provide a channel ID or set a default channel in the plugin settings.', 'livestream-embedder') . '</p>';
    }

    if (empty($api_key)) {
        return '<p>' . esc_html__('Please provide a valid YouTube Data API key in the plugin settings.', 'livestream-embedder') . '</p>';
    }

    return '<div id="livestream-container" style="height:360px; width:640px"></div>';
}
add_shortcode('livestream_embedder', 'livestream_embedder_shortcode');
