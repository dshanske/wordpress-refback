# Refback #
**Contributors:** dshanske  
**Tags:** refback, linkback, comment, response  
**Requires at least:** 4.9.9  
**Tested up to:** 5.6  
**Stable tag:** 2.0.0  
**License:** GPLv2  

Enable Refbacks on your WordPress site

## Description ##

Refback is a linkback method that works using the standard HTTP Referer header. Like pingbacks, trackbacks, and webmentions, it attempts to present links of other sites that have linked to you.
Unlike other methods, the other site requires no additional support. The implementation works exactly as the other linkbacks do in WordPress.

## Frequently Asked Questions ##

### Why not use Webmentions? ###

[Webmentions](http://www.w3.org/TR/webmention/) are preferable, however,  webmentions must be supported by the sender

### How can I send and receive Refbacks? ###

On the Settings --> Discussion Page in WordPress:

* Activate receiving refbacks by checking the "Allow link notifications from other blogs (pingbacks and trackbacks) on new articles" option.

### How do I supporting refbacks for my custom post type? ###

When declaring your custom post type, add post type support for refbacks by either including it in your register_post_type entry or adding it later using add_post_type_support.This will also add support for receiving pingbacks and trackbacks as WordPress cannot currently distinguish between different linkback types.

Refbacks will also respect the webmention plugin settings for which post types to support. If you enable webmentions for that type, it will also enable refbacks.

### Do I need the Semantic Linkbacks Plugin? ###

No. This will work independently. However, if you have that plugin, it will parse refbacks for Microformats.

### Do I need the Webmentions Plugin? ###

No. This will work independently. However, it will use the options in the webmentions plugin, if enabled, specifically to accept refbacks to different post types and route homepage and other archive page mentions to pages if configured.

## Changelog ##

Project and support maintained on github at [dshanske/wordpress-refback](https://github.com/dshanske/wordpress-refback).

### 2.0.0 ###

* Rewrite of plugin based on webmention code improvements through 5.X 
* Removal of old dependencies
* Update to hook into Semantic Linkback using a filter. Previously, refback support was enabled by default in that plugin

### 1.0.0 ###

* Initial release based on the Webmention plugin
