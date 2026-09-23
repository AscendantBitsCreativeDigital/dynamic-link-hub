=== Dynamic Link Hub ===
Contributors: ascendantbits
Tags: linktree, link hub, shortcode, social menu, buttons
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A configurable Linktree-style button hub shortcode: always links your most
recent post, plus editable custom link buttons and an optional social menu.

== Description ==

Drop `[dynamic_link_hub]` on any page or post (or in a Shortcode block) to
render a self-contained, centered hub:

1. An optional round avatar image at the top, with configurable size and
   border thickness/color.
2. A button that always links to your most recently published post
   (label and on/off are configurable).
3. Any number of custom buttons you add, each linking to an existing page
   or post, or to a custom URL — add, edit, remove, and drag to reorder
   from **Link Hub** in the admin menu.
4. A horizontally centered row of large, official-brand-color social
   icons below the buttons, built from its own **Social Links** repeater
   — pick a platform (Facebook, Instagram, X/Twitter, Threads, Bluesky,
   YouTube, TikTok, Pinterest, Reddit, LinkedIn, WhatsApp, Telegram,
   Discord, Spotify, SoundCloud, Patreon, Ko-fi, Etsy, email, phone, or
   a custom link), paste the URL/handle/email/number, and add, edit,
   remove, or drag to reorder as many as you want. No WordPress menu
   required. Brand icons and colors are sourced from Simple Icons
   (CC0 1.0 Universal / public domain); LinkedIn uses a plain "in"
   wordmark since LinkedIn had its logo removed from that project.

Button color, text color, hover colors, corner radius, and the overall
container width are all configurable under the **Appearance** tab.

The original shortcode tag (`[dynamic_link_hub_final]`) still works, so
this is a drop-in replacement if you were using the earlier hand-rolled
snippet this plugin was built from.

== Installation ==

1. Upload the `dynamic-link-hub` folder to `/wp-content/plugins/`, or
   install the zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to **Link Hub** in the admin menu to configure colors, links, and
   social icons.
4. Place `[dynamic_link_hub]` wherever you want the button stack to show.

== Changelog ==

= 1.3.0 =
* The whole hub now renders inside its own self-contained container, with
  a configurable width (Appearance tab).
* Added an optional round avatar image above the buttons, with
  configurable image, size, and border thickness/color.

= 1.2.1 =
* Social icons: background is now always transparent (no more colored
  circle); the icon itself lightens on hover/focus instead of the
  background changing color.

= 1.2.0 =
* Social icons now use official brand logos and colors (via Simple
  Icons) instead of plain letter badges, and are noticeably larger.
* Added Bluesky and Reddit to the Social Links platform list.

= 1.1.0 =
* Replaced the WordPress-menu-based social menu with a dedicated Social
  Links repeater (pick a platform, paste a link — no menu needed).

= 1.0.0 =
* Initial release.
