=== SWPM Social Login ===
Contributors: smp7, wp.insider
Donate link: https://simple-membership-plugin.com/
Tags: social, login, google, facebook, membership
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 1.0.5
License: GPLv2 or later

An addon for the simple membership plugin to configure after login redirection to a specific page based on the member's level.

== Description ==

Integrates Google, Facebook, and other social login options for Simple Membership users. Enables quick registration & sign-in via social accounts.

[Read the Documentation to Learn More](https://simple-membership-plugin.com/simple-membership-social-login-addon/).

== Installation ==

1. Click on the Add New menu under the plugins interface.
2. Go to the Uploader tab and upload the swpm-social-login.zip file.
3. Hit the Install Now button and then activate the addon.

== Frequently Asked Questions ==

None

== Screenshots ==

None

== Changelog ==

= 1.0.5 =
* Security fix: the page URL the member is redirected back to after a social login or registration is now validated (using 'wp_validate_redirect'). Only URLs of this site (or the hosts allowed by the 'allowed_redirect_hosts' filter hook) are accepted. Otherwise, the member is redirected to the login page.

= 1.0.4 =
* Support added for existing wp user account bind check.

= 1.0.3 =
* Added support for the 2FA addon.

= 1.0.2 =
* Added "Connected Social Account(s)" section in the edit profile page to show linked social accounts.

= 1.0.1 =
* Initial public release.
