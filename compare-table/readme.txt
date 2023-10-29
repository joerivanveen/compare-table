=== Compare table ===
Contributors: ruigehond
Tags: compare, table, services, items, interactive
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=hallo@ruigehond.nl&lc=US&item_name=Compare+table&no_note=0&cn=&currency_code=USD&bn=PP-DonationsBF:btn_donateCC_LG.gif:NonHosted
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3

Creates a table where a visitor can compare services or items or anything really, that you provide from the admin interface.

== Description ==

Your visitors can easily compare services, products or anything else with the tables you create with this compare-table plugin.

In your dashboard create as many tables as you want. Each table has *subjects* (the entities that are being compared) and *fields* (the properties the entities can be compared on).

You can order the subjects and fields in any way you want. You can also add a description to each field, which will be shown when a visitor hovers over the field name.

Set the initial view of each table (how many columns, which subjects) which the visitor can change with select lists.

Everything is nicely formatted by default, but you can easily override the css with your own, should you want to.

Show a compare-table anywhere by using the shortcode `[compare-table]` (which will show the first table).
To show a specific table that is not the first one, you can provide its id (the `type_id` in the querystring) or title, like so:
- `[compare-table type=1]` the id must be the type_id in the querystring when you edit this table
- `[compare-table type="my table"]` this must be the exact title you gave your table

Note: providing the id is slightly faster.

The plugin adds 4 tables to your database. For each compare-table there are two highly optimized queries executed.

Enjoy the plugin. Let me know if you have any questions.

== Installation ==

Install the plugin by clicking ‘Install now’ below, or the ‘Download’ button, and put the `compare-table` folder in your `plugins` folder. Don’t forget to activate it.

== Screenshots ==
1. Compare table for a visitor.

2. Compare table settings with Subject(s) and Field(s).

3. Provide values for a Subject (or a Field).

4. General settings.

== Changelog ==

1.0.0: release
