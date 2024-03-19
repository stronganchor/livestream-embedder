<?php
/*
Plugin Name: YouTube Live Stream Embed
Plugin URI: https://github.com/stronganchor/youtube-livestream-embed/
Description: Embeds a YouTube live stream using a shortcode.
Version: 1.0
Author: Strong Anchor Tech
Author URI: https://stronganchortech.com/
*/

// Add settings page
function youtube_live_stream_settings_page() {
    add_options_page(
        'YouTube Live Stream Settings',
        'YouTube Live Stream',
        'manage_options',
        'youtube-live-stream-settings',
        'youtube_live_stream_settings_page_content'
    );
}
add_action('admin_menu', 'youtube_live_stream_settings_page');

// Settings page content
function youtube_live_stream_settings_page_content() {
    ?>
    <div class="wrap">
        <h1>YouTube Live Stream Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('youtube_live_stream_settings');
            do_settings_sections('youtube-live-stream-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function youtube_live_stream_register_settings() {
    register_setting('youtube_live_stream_settings', 'youtube_live_stream_api_key');
    add_settings_section(
        'youtube_live_stream_section',
        'API Key',
        'youtube_live_stream_section_callback',
        'youtube-live-stream-settings'
    );
    add_settings_field(
        'youtube_live_stream_api_key',
        'YouTube Data API Key',
        'youtube_live_stream_api_key_callback',
        'youtube-live-stream-settings',
        'youtube_live_stream_section'
    );
}
add_action('admin_init', 'youtube_live_stream_register_settings');

// Section callback
function youtube_live_stream_section_callback() {
    echo '<p>Enter your YouTube Data API key below:</p>';
}

// API key field callback
function youtube_live_stream_api_key_callback() {
    $api_key = get_option('youtube_live_stream_api_key');
    echo '<input type="text" name="youtube_live_stream_api_key" value="' . esc_attr($api_key) . '" size="50" />';
}

// Shortcode callback
function youtube_live_stream_shortcode($atts) {
    $channel_id = isset($atts['channel_id']) ? $atts['channel_id'] : '';
    $api_key = get_option('youtube_live_stream_api_key');

    if (empty($channel_id) || empty($api_key)) {
        return '<p>Please provide a valid channel ID and API key.</p>';
    }

    $embed_code = '<div id="livestream-container"></div>';
    $embed_code .= '<script src="https://www.youtube.com/iframe_api"></script>';
    $embed_code .= '<script>
        var player;

        function onYouTubeIframeAPIReady() {
            player = new YT.Player("livestream-container", {
                width: "640",
                height: "360",
                events: {
                    "onReady": onPlayerReady
                }
            });
        }

        function onPlayerReady(event) {
            var channelId = "' . $channel_id . '";
            fetch("https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" + channelId + "&eventType=live&type=video&key=' . $api_key . '")
                .then(response => response.json())
                .then(data => {
                    if (data.items && data.items.length > 0) {
                        var videoId = data.items[0].id.videoId;
                        player.loadVideoById(videoId);
                    }
                });
        }
    </script>';

    return $embed_code;
}
add_shortcode('youtube_live_stream', 'youtube_live_stream_shortcode');
