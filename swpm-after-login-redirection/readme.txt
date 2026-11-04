=== Simple Membership After Login Redirection ===
Contributors: smp7, wp.insider
Donate link: https://simple-membership-plugin.com/
Tags: login, redirection, member, members, membership, login redirection, access, level
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 2.2
License: GPLv2 or later

An addon for the simple membership plugin to configure after login redirection to a specific page based on the member's level.

== Description ==

This addon allows you to configure an after login page for each of the membership access levels you create in your [simple membership plugin](http://wordpress.org/plugins/simple-membership/).

This addon plugin will automatically redirect the members to the appropriate page after they log into your site.

After you install this addon, edit your membership levels and specify the redirect pages and the addon will take care of the rest.

== Installation ==

1. Upload `swpm-after-login-redirection` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

None

== Screenshots ==

None

== Changelog ==

= 2.2 =
- Security fix: the 'swpm_redirect_to' URL is now validated (using 'wp_validate_redirect') when the member logs in using the Social Login addon, the same as the normal login. Only URLs of this site (or the hosts allowed by the 'allowed_redirect_hosts' filter hook) are accepted. Thanks to Sanjay Babu for reporting this.

= 2.1 =
- PHP warning fix for the case when nothing has been set in the addon's settings yet.

= 2.0 =
* Support added for the [Social Login Addon](https://simple-membership-plugin.com/simple-membership-social-login-addon/) - now the after login redirection will also work when the member logs in using social login.

= 1.9 =
* Fixed a minor version number formatting issue which was causing the addon to show an update notification even when there is no update.

= 1.8 =
* Fixed a PHP debug warning that occurred when the addon's settings had never been saved.

= 1.7 =
* Added 'wp_validate_redirect' function to validate the redirect URL when the 'swpm_redirect_to' parameter is set.

= 1.6 =
* Added an extra line debug logging output to show which page it is redirecting to. Useful for troubleshooting.

= 1.5 =
* Added a new feature to enable custom redirections (the custom redirection can be set by defining the parameter 'swpm_redirect_to' in the URL)

= 1.4 =
* Fixed an "Undefined index" PHP notice when the settings is saved and WP Debug is enabled on the site.

= 1.3 =
* Added a new feature to enable redirection to the last page (where they clicked the login link) after the member logs in. You need to upgrade simple membership plugin for this to work.

= 1.2 =
* Registering the actions and hooks earlier so they don't have a race condition on some sites.

= 1.1 =
* Moved the after login redirection action inside the plugins_loaded hook

= 1.0 =
* First commit to wordpress.org

== Upgrade Notice ==

None
