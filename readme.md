# Compare table

WordPress plugin that creates a table where a visitor can compare services or items or anything really, that you provide from the admin interface.

## Description

Your visitors can easily compare services, products or anything else with the tables you create with this compare-table plugin.

In your dashboard create as many tables as you want. Each table has *subjects* (the entities that are being compared) and *fields* (the properties the entities can be compared on).

You can order the subjects and fields in any way you want. You can also add a description to each field, which will be shown when a visitor hovers over the field name.

Set the initial view of each table (how many columns, which subjects) which the visitor can change with select lists.

Everything is nicely formatted by default, albeit a bit bland. You can easily override the css with your own, should you want to.

The table works fine with touch devices as well. On mobile devices the width is automatically restricted to two columns.

Show a compare-table anywhere by using the shortcode `[compare-table]` (which will show the first table).
To show a specific table that is not the first one, you can provide its title or id (the `type_id` in the querystring), like so:
- `[compare-table type=1]` the id must be the type_id in the querystring when you edit this table
- `[compare-table type="my table"]` this must be the exact title you gave your table 

Note: providing the id is slightly faster.

The plugin adds 4 tables to your database. For each compare-table there are two highly optimized queries executed.

Enjoy the plugin! Let me know if you have any questions.
