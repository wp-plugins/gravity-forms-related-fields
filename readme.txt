=== Gravity Forms Related Fields Add-On ===
Contributors: mikemanger
Tags: gravity forms
Requires at least: 2.8.0
Tested up to: 4.3
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A plugin to dynamically populate Gravity Form fields with form submissions.

== Description ==

Any Gravity Form multiple choice field (select, checkbox or radio) can be mapped to have the submission entries
of another form as their values.

To create related fields edit a Gravity Form and go to 'Related Fields' from the Form Settings menu.

Any multiple choice field (select, checkbox or radio) can be mapped to have the submission entries
of another form as their values.

To create related fields edit a Gravity Form and go to 'Related Fields' from the Form Settings menu.

== Installation ==

1. Upload `gravity-forms-related-fields` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= My populated field only displays the first 200 entries =

This limit is set to reduce database timeouts. There is a filter `gfrf_entry_limit` to increase the limit. As an example add the following code to your functions.php file:

`
function my_theme_change_gfrf_entry_limit( $entry_limit ) {
	return 500;
}
add_filter( 'gfrf_entry_limit', 'my_theme_change_gfrf_entry_limit' );
`

= I have found a bug =

Please raise a ticket on the [issue tracker](https://bitbucket.org/lighthouseuk/gravityforms-related-fields/issues). Pull requests also accepted!

== Screenshots ==

1. Adding a new related field connection. This will map the entries in the form "B2B Buyer Personas" to be Checkbox options.
2. You can have multiple related field connections for each form and disable them when they are not needed.

== Changelog ==

= 1.0.3 =
* Fix list table error in WordPress 4.3

= 1.0.2 =
* Add filter for populated entries limit

= 1.0.1 =
* Increase populated entries limit to 200 from 20

= 1.0.0 =
* First public release

== Upgrade Notice ==

= 1.0.3 =
This version fixes compatiablity with WordPress 4.3