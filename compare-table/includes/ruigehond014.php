<?php

declare(strict_types=1);

namespace ruigehond014;

use ruigehond_0_4_0;

class ruigehond014 extends ruigehond_0_4_0\ruigehond
{
    // variables that hold cached items
    private $database_version, $basename, $queue_frontend_css, $remove_on_uninstall;
    private $table_prefix, $table_type, $table_subject, $table_field, $table_compare;

    public function __construct($basename)
    {
        parent::__construct('ruigehond014');
        $this->basename = $basename;
        // table names
        $wp_prefix = $this->wpdb->prefix;
        $this->table_prefix = "{$wp_prefix}ruigehond014_";
        $this->table_type = "{$wp_prefix}ruigehond014_type";
        $this->table_subject = "{$wp_prefix}ruigehond014_subject";
        $this->table_field = "{$wp_prefix}ruigehond014_field";
        $this->table_compare = "{$wp_prefix}ruigehond014_compare";
        // set some options
        $this->database_version = $this->getOption('database_version', '0.0.0');
        $this->queue_frontend_css = $this->getOption('queue_frontend_css', true);
        $this->remove_on_uninstall = $this->getOption('remove_on_uninstall', false);
    }

    public function initialize()
    {
        /*if (current_user_can('administrator')) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }*/
        $plugin_dir_url = plugin_dir_url(__FILE__);
        if (is_admin()) {
            $this->load_translations('compare-table');
            add_action('admin_init', array($this, 'settings'));
            add_action('admin_menu', array($this, 'menuitem'));
            // styles...
            wp_enqueue_style('ruigehond014_admin_stylesheet', "{$plugin_dir_url}admin.css", [], RUIGEHOND014_VERSION);
            wp_enqueue_style('wp-jquery-ui-dialog');
            // filters
            add_filter("plugin_action_links_$this->basename", array($this, 'settings_link')); // settings link on plugins page
        } else {
            wp_enqueue_script('ruigehond014_javascript', "{$plugin_dir_url}client.js", array('jquery'), RUIGEHOND014_VERSION);
            if ($this->queue_frontend_css) { // only output css when necessary
                wp_enqueue_style('ruigehond014_stylesheet_display', "{$plugin_dir_url}display.css", [], RUIGEHOND014_VERSION);
            }
            //add_action('wp_head', array($this, 'outputSchema'));
            //add_shortcode('compare-table', array($this, 'getHtmlForFrontend'));
        }
    }

    public function settings_link($links)
    {
        $admin_url = get_admin_url();
        $link_text = __('Settings');
        $link = "<a href=\"{$admin_url}options-general.php?page=compare-table\">$link_text</a>";
        array_unshift($links, $link);

        return $links;
    }

    public function getHtmlForFrontend($attributes = [], $content = null, $short_code = 'compare-table')
    {
        if ((!$post_id = get_the_ID())) return '';
        $chosen_exclusive = isset($attributes['exclusive']) ? $attributes['exclusive'] : null;
        $chosen_term = isset($attributes['category']) ? strtolower($attributes['category']) : null;
        ob_start();
        echo ' TEST compare table ';
        return ob_get_clean();
    }

    private function getTerms()
    {
        if (isset($this->terms)) return $this->terms; // return cached value if available
        // get the terms for this registered taxonomies from the db
        $taxonomies = addslashes(sanitize_text_field($this->taxonomies)); // just for the h#ck of it
        $wp_prefix = $this->wpdb->prefix;
        $sql = "select t.term_id, tt.parent, t.name as term, o.t, o.post_id from
                {$wp_prefix}terms t inner join
                {$wp_prefix}term_taxonomy tt on t.term_id = tt.term_id left outer join
                {$this->table_type} o on o.term_id = t.term_id where tt.taxonomy = '$taxonomies'
                order by o.o, t.name;";
        $rows = $this->wpdb->get_results($sql, OBJECT);
        $terms = array();
        foreach ($rows as $key => $row) {
            if (!isset($terms[$parent = intval($row->parent)])) $terms[$parent] = array();
            $terms[$parent][] = array(
                'term_id' => intval($row->term_id),
                'term' => $row->term,
                't' => $row->t,
                'post_id' => $row->post_id,
            );
        }
        $this->terms = $terms;

        return $terms;
    }

    /**
     * @param string|null $exclusive
     * @param null $term
     * @return array the rows from db as \stdClasses in an indexed array
     */
    private function getPosts($exclusive = null, $term = null)
    {
        $term_ids = null; // we are going to collect all the term_ids that fall under the requested $term
        $wp_prefix = $this->wpdb->prefix;
        if (is_string($term)) {
            $sql_term = addslashes($term);
            $sql = "select term_id from {$wp_prefix}terms t where lower(t.name) = '$sql_term';";
            // now for as long as rows with term_ids are returned, keep building the array
            while ($rows = $this->wpdb->get_results($sql)) {
                foreach ($rows as $index => $row) {
                    $term_ids[] = $row->term_id;
                }
                // new sql selects all the children from the term_ids that are in the array
                $str_term_ids = implode(',', $term_ids);
                $sql = "select term_id from {$wp_prefix}term_taxonomy tt 
                        where tt.parent in ($str_term_ids) 
                        and term_id not in ($str_term_ids);"; // excluding the term_ids already in the array
                // so it returns no rows if there are no more children, ending the while loop
            }
        }
        $sql = "select p.ID, p.post_title, p.post_content, p.post_date, p.post_name, t.term_id, pm.meta_value AS exclusive from
                {$wp_prefix}posts p left outer join 
                {$wp_prefix}term_relationships tr on tr.object_id = p.ID left outer join 
                {$wp_prefix}term_taxonomy tt on tt.term_taxonomy_id = tr.term_taxonomy_id left outer join 
                {$wp_prefix}terms t on t.term_id = tt.term_id left outer join 
                {$wp_prefix}postmeta pm on pm.post_id = p.ID and pm.meta_key = '_ruigehond014_exclusive' 
                where p.post_type = 'ruigehond014_faq' and post_status = 'publish'";
        // setup the where condition regarding exclusive and term....
        if (is_array($term_ids)) {
            $sql .= ' and t.term_id IN (' . implode(',', $term_ids) . ')';
        } elseif (is_string($exclusive)) {
            $sql .= ' and pm.meta_value = \'' . addslashes(sanitize_text_field($exclusive)) . '\'';
        }
        $sql = "$sql order by p.post_date desc;";
        $rows = $this->wpdb->get_results($sql, OBJECT);
        $return_arr = array();
        $current_id = 0;
        foreach ($rows as $index => $row) {
            if ($row->ID === $current_id) { // add the category to the current return value
                $return_arr[count($return_arr) - 1]->term_ids[] = $row->term_id;
            } else { // add the row, when not exclusive is requested posts without terms must be filtered out
                if (($term_id = $row->term_id) or $exclusive) {
                    $row->term_ids = array($term_id);
                    unset($row->term_id);
                    $return_arr[] = $row;
                    $current_id = $row->ID;
                }
            }
        }
        unset($rows);

        return $return_arr;
    }

    public function handle_input($args)
    {
        $returnObject = $this->getReturnObject();
        $wp_prefix = $this->wpdb->prefix;
        if (isset($args['id'])) {
            $id = (int)$args['id']; // this must be the same as $this->row->id
        } else {
            $id = 0;
        }
        $value = trim(stripslashes($args['value'])); // don't know how it gets magically escaped, but not relying on it
        $handle = trim(stripslashes($args['handle']));
        // cleanup the array, can this be done more elegantly?
        $args['id'] = $id;
        $args['value'] = $value;
        switch ($handle) {
            case 'order_taxonomy':
                if (isset($args['order']) and is_array($args['order'])) {
                    $rows = $args['order'];
                    foreach ($rows as $term_id => $o) {
                        $this->upsertDb($this->table_type,
                            array('o' => $o),
                            array('term_id' => $term_id)
                        );
                    }
                    $returnObject->set_success(true);
                    $returnObject->set_data($args);
                }
                break;
            case 'update':
                if (is_admin()) {
                    $table_name = stripslashes($args['table_name']);
                    $column_name = stripslashes($args['column_name']);
                    $id_column = (isset($args['id_column'])) ? $args['id_column'] : "{$table_name}_id";
                    switch ($column_name) {
                        case 't': // you need to save the title and the id as well
                            if (strrpos($value, ')') === strlen($value) - 1) {
                                $post_id = (int)str_replace(')', '', substr($value, strrpos($value, '(') + 1));
                                //$post_title = trim( substr( $value, 0, strrpos( $value, '(' ) ) );
                                if ($post_title = $this->wpdb->get_var("SELECT post_title FROM {$wp_prefix}posts WHERE ID = {$post_id};")) {
                                    $args['value'] = "$post_title ($post_id)";
                                    $update = array('t' => $args['value'], 'post_id' => $post_id);
                                } else {
                                    $update = array();
                                    $returnObject->add_message(sprintf(__('post_id %d not found', 'faq-with-categories'), $post_id), 'warn');
                                }
                            } else {
                                $post_title = $value;
                                if ('' === $value) {
                                    $update = array('t' => '', 'post_id' => null);
                                } elseif ($post_id = $this->wpdb->get_var("SELECT ID 
										FROM {$wp_prefix}posts WHERE post_title = '" . addslashes($post_title) . "';")) {
                                    $args['value'] = "$post_title ($post_id)";
                                    $update = array('t' => $args['value'], 'post_id' => $post_id);
                                } else {
                                    $update = array('t' => $args['value'], 'post_id' => 0);
                                    $args['nonexistent'] = true;
                                    $returnObject->add_message(sprintf(__('Could not find post_id based on title: %s', 'faq-with-categories'), $post_title), 'warn');
                                }
                            }
                    }
                    if (count($update) > 0) {
                        $rowsaffected = $this->upsertDb(
                            $this->table_prefix . $table_name, $update,
                            array($id_column => $id));
                        if ($rowsaffected === 0) {
                            $returnObject->add_message(__('Update with same value not necessary...', 'faq-with-categories'), 'warn');
                        }
                        if ($rowsaffected === false) {
                            $returnObject->add_message(__('Operation failed', 'faq-with-categories'), 'error');
                        } else {
                            $returnObject->set_success(true);
                            $args['value'] = $this->wpdb->get_var(
                                "SELECT $column_name FROM {$this->table_prefix}$table_name WHERE $id_column = $id;");
                            if ($column_name === 'rating_criteria') {
                                $args['value'] = implode(PHP_EOL, json_decode($args['value']));
                            }
                            $returnObject->set_data($args);
                        }
                    }
                }
                break;
            case 'suggest_t':
                // return all valid post titles that can be used for this tag
                $rows = $this->wpdb->get_results(
                    "SELECT CONCAT(post_title, ' (', ID, ')') AS t 
						FROM {$wp_prefix}posts 
						WHERE post_status = 'publish' AND NOT post_type = 'nav_menu_item'
						ORDER BY post_title ASC;");
                if (count($rows) > 0) {
                    $returnObject->set_success(true);
                }
                $returnObject->suggestions = $rows;
                $returnObject->set_data($args);
                break;
            default:
                return $this->getReturnObject(sprintf(__('Did not understand handle %s', 'faq-with-categories'),
                    var_export($args['handle'], true)));
        }

        return $returnObject;
    }

    public function ordertaxonomypage()
    {
        wp_enqueue_script('ruigehond014_admin_javascript', plugin_dir_url(__FILE__) . 'admin.js', array(
            'jquery-ui-droppable',
            'jquery-ui-sortable',
            'jquery'
        ), RUIGEHOND014_VERSION);
        echo '<div class="wrap ruigehond014"><h1>';
        echo esc_html(get_admin_page_title());
        echo '</h1><p>';
        echo __('This page only concerns itself with the order. The hierarchy is determined by the taxonomy itself.', 'faq-with-categories');
        echo '<br/>';
        echo __('If you assign a page to a taxonomy, the faq shortcut on that page will display faq-posts from that taxonomy.', 'faq-with-categories');
        echo '</p><hr/>';
        $terms = $this->getTerms(); // these are ordered to the best of the knowledge of the system already, but with parents
        foreach ($terms as $index => $sub_terms) {
            echo '<section class="rows-sortable">';
            foreach ($sub_terms as $o => $term) {
                echo '<div class="ruigehond014-order-term" data-id="';
                echo $term['term_id'];
                echo '" data-inferred_order="';
                echo $o;
                echo '">';
                // ajax input to link a page to the taxonomy / explaining the taxonomy
                echo '<input type="text" data-id_column="term_id" data-id="';
                echo $term['term_id'];
                echo '" data-handle="update" data-table_name="taxonomy_o" data-column_name="t" data-value="';
                echo htmlentities($term['t']);
                echo '" value="';
                echo htmlentities($term['t']);
                echo '"	class="ruigehond014 input post_title ajaxupdate ajaxsuggest tabbed';
                if ($term['post_id'] === '0') {
                    echo ' nonexistent';
                }
                echo '"/>';
                // ordering handle
                echo '<div class="sortable-handle">';
                echo $term['term'];
                echo '</div></div>';
            }
            echo '</section><hr/>';
        }
        echo '</div>';
    }

    public function tables_page()
    {
        echo 'hallo tables page';
    }

    public function settings_page()
    {
        // check user capabilities
        if (false === current_user_can('manage_options')) return;
        // start the page
        echo '<div class="wrap"><h1>';
        echo esc_html(get_admin_page_title());
        echo '</h1><p>';
        echo __('Blah blah blah', 'compare-table');
        echo '</p>';
        echo '<form action="options.php" method="post">';
        // output security fields for the registered setting
        settings_fields('ruigehond014');
        // output setting sections and their fields
        do_settings_sections('ruigehond014');
        // output save settings button
        submit_button(__('Save Settings', 'compare-table'));
        echo '</form></div>';
    }

    public function settings()
    {
        if (false === $this->onSettingsPage('compare-table')) return;
        if (false === current_user_can('manage_options')) return;
        register_setting('ruigehond014', 'ruigehond014', array($this, 'settings_validate'));
        // register a new section in the page
        add_settings_section(
            'global_settings', // section id
            __('Options', 'compare-table'), // title
            function () {
            }, //callback
            'ruigehond014' // page id
        );
        $labels = array(
            'remove_on_uninstall' => __('Check this if you want to remove all data when uninstalling the plugin.', 'compare-table'),
            'queue_frontend_css' => __('By default a small css-file is output to the frontend to format the entries. Uncheck to handle the css yourself.', 'faq-with-categories'),
        );
        foreach ($labels as $setting_name => $explanation) {
            add_settings_field(
                $setting_name, // id, As of WP 4.6 this value is used only internally
                $setting_name, // title
                array($this, 'echo_settings_field'), // callback
                'ruigehond014', // page id
                'global_settings',
                [
                    'setting_name' => $setting_name,
                    'label_for' => $explanation,
                    'class_name' => 'ruigehond014',
                ] // args
            );
        }
    }

    public function echo_settings_field($args)
    {
        $setting_name = $args['setting_name'];
        switch ($setting_name) {
            case 'queue_frontend_css':
            case 'remove_on_uninstall': // make checkbox that transmits 1 or 0, depending on status
                echo '<label><input type="hidden" name="ruigehond014[', $setting_name, ']" value="';
                if ($this->$setting_name) {
                    echo '1"><input type="checkbox" checked="checked"';
                } else {
                    echo '0"><input type="checkbox"';
                }
                echo ' onclick="this.previousSibling.value=1-this.previousSibling.value" class="';
                echo $args['class_name'], '"/>', $args['label_for'], '</label>';
                break;
            default: // make text input
                echo '<input type="text" name="ruigehond014[', $setting_name, ']" value="';
                echo htmlentities($this->$setting_name);
                echo '" style="width: 162px" class="', $args['class_name'], '"/> <label>', $args['label_for'], '</label>';
        }
    }

    public function settings_validate($input)
    {
        $options = (array)get_option('ruigehond014');
        foreach ($input as $key => $value) {
            switch ($key) {
                // on / off flags (1 vs 0 on form submit, true / false otherwise
                case 'queue_frontend_css':
                case 'exclude_from_search':
                case 'title_links_to_overview':
                case 'max_ignore_elsewhere':
                case 'exclude_from_count':
                    $options[$key] = ($value === '1' or $value === true);
                    break;
                case 'max':
                    if (abs(intval($value)) > 0) $options[$key] = abs(intval($value));
                    break;
                case 'taxonomies': // check if it's an existing taxonomy
                    if (false === taxonomy_exists($value)) $value = 'category';
                // intentional fall through, just validated the value
                // by default just accept the value
                default:
                    $options[$key] = $value;
            }
        }

        return $options;
    }

    public function menuitem()
    {
        // add top level page
        add_menu_page(
            'Compare table',
            'Compare table',

            'edit_posts',
            'compare-table',
            array($this, 'tables_page'), // callback
            'dashicons-editor-table',
            20
        );
        add_submenu_page(
            'compare-table',
            __('Settings'), // page_title
            __('Settings'), // menu_title
            'manage_options',
            'compare-table-settings',
            array($this, 'settings_page')
        );

        global $submenu; // make the first entry go to the tables page
        $submenu['compare-table'][0] = array(
            __('Tables', 'compare-table'),
            'edit_posts',
            'compare-table', // the slug that identifies with the callback tables_page of the main menu item
            'blub' // WHOA
        );
    }

    public function activate()
    {
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$this->table_type';") != $this->table_type) {
            $sql = "CREATE TABLE $this->table_type (
						id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
						title text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_bin' NOT NULL
                    );";
            $this->wpdb->query($sql);
            $sql = "INSERT INTO $this->table_type (title) VALUES ('Compare table');";
            $this->wpdb->query($sql);
        }
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$this->table_subject';") != $this->table_subject) {
            $sql = "CREATE TABLE $this->table_subject (
						id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
						type_id INT NOT NULL,
						title text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_bin' NOT NULL,
						description text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_bin',
						o INT NOT NULL DEFAULT 1
                    )
					DEFAULT CHARACTER SET = utf8mb4
					COLLATE = utf8mb4_bin
					;";
            $this->wpdb->query($sql);
        }
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$this->table_field';") != $this->table_field) {
            $sql = "CREATE TABLE $this->table_field (
						id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
						type_id INT NOT NULL,
						title text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_bin' NOT NULL,
						description text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_bin',
						o INT NOT NULL DEFAULT 1
                    )
					DEFAULT CHARACTER SET = utf8mb4
					COLLATE = utf8mb4_bin
					;";
            $this->wpdb->query($sql);
        }
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$this->table_compare';") != $this->table_compare) {
            $sql = "CREATE TABLE $this->table_compare (
                        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                        subject_id INT NOT NULL,
                        field_id INT NOT NULL,
                        title text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_bin',
                        description text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_bin'
                    )
                    DEFAULT CHARACTER SET = utf8mb4                        
                    COLLATE = utf8mb4_bin
                    ;";
            $this->wpdb->query($sql);
        }

        // register the current version
        $this->setOption('database_version', RUIGEHOND014_VERSION);
    }

    public function deactivate()
    {
        // nothing to do here
    }

    public function uninstall()
    {
        if (false === $this->remove_on_uninstall) return;
        // remove tables
        foreach (array(
                     $this->table_type,
                     $this->table_subject,
                     $this->table_field,
                     $this->table_compare
                 ) as $table_name) {
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name';") == $table_name) {
                $sql = "DROP TABLE $table_name;";
                $this->wpdb->query($sql);
            }
        }
        // remove settings
        delete_option('ruigehond014');
    }
}
