=== Livestream Embedder ===
Contributors: stronganchortech
Donate link: https://stronganchortech.com/sponsor
Tags: youtube, livestream, embed, shortcode, video
Requires at least: 5.0
Tested up to: 6.6.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embeds a YouTube live stream or the most recent video from a channel using a simple shortcode.

== Description ==

Livestream Embedder allows you to embed a live stream from a YouTube channel or the most recent video using a shortcode. You can configure the API key and channel ID via the plugin's settings page.

**Features:**
* Embed a live stream or most recent video with a shortcode.
* Configure the YouTube Data API key and default channel ID via settings.
* Display error messages if no live stream is available.
* Responsive video embeds.

**Note on External Services:**
This plugin uses the YouTube iframe API and YouTube Data API to fetch video data from YouTube and display it. Users must provide their own YouTube API key, which is required to make requests to the YouTube Data API v3. The data requested includes information about the live streams or videos on a given YouTube channel.

By using this plugin, you agree to YouTube's [Terms of Service](https://www.youtube.com/t/terms) and [Privacy Policy](https://policies.google.com/privacy).

**Shortcode Usage:**

1. To embed a live stream from a specific channel:
   `[livestream_embedder channel_id="CHANNEL_ID"]`

   Replace `CHANNEL_ID` with the actual YouTube channel ID (e.g. `UCabcdefghijklmnopqrstuvwx`).

2. If you've configured a default channel ID in the plugin settings, you can use the shortcode without the `channel_id` attribute:
   `[livestream_embedder]`

**How to Find the Channel ID:**

1. Go to the YouTube channel's page.
2. Look at the URL in your browser's address bar. The channel ID is the string of characters after the "/channel/" part of the URL.
3. If the channel uses a custom URL, you can find the channel ID by viewing the page source and searching for "channelId".

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/livestream-embedder` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > Livestream Embedder** to configure the API key and default channel ID.
4. Use the `[livestream_embedder]` shortcode in your posts or pages to embed the live stream.

== Frequently Asked Questions ==

= How does this plugin interact with YouTube? =

This plugin uses the YouTube iframe API and the YouTube Data API to fetch video data and display YouTube live streams or recent videos. You must provide a YouTube API key in order to access YouTube's video data.

= How do I get a YouTube Data API key? =

You can obtain a YouTube Data API key by creating a project in the [Google Cloud Console](https://console.cloud.google.com/). Once created, enable the YouTube Data API v3 and generate an API key.

= What happens if no live stream is available? =

If no live stream is found, the plugin will attempt to load the most recent video from the channel. If neither a live stream nor a recent video is available, an error message will be displayed.

= Can I embed a live stream from multiple channels? =

Yes, by specifying a different `channel_id` in the shortcode, you can embed live streams from different YouTube channels.

= What is the required YouTube URL format? =

For best results, use the full YouTube channel ID (e.g., `UCxyz123abcDEF`) instead of custom URLs or names.

== Screenshots ==

1. **Plugin Settings Page:** Settings page where you enter the API key and default channel ID.
2. **Live Stream Embed Example:** Example of a live stream embed in a post using the shortcode.
3. **Error Message Example:** Example of an error message displayed when no live stream or video is available.

== Changelog ==

= 1.0.0 =
* Initial release.

== License ==

This plugin is licensed under the GPLv2 or later. You can modify and distribute it under the terms of the GNU General Public License as published by the Free Software Foundation.
