<?php

declare( strict_types=1 );

namespace ruigehond014_ITOEWERKLKVEIR;

use ruigehond_ITOEWERKLKVEIR_0_4_1;

class ruigehond014 extends ruigehond_ITOEWERKLKVEIR_0_4_1\ruigehond {
	// variables that hold cached items
	private $database_version, $basename, $admin_url;
	private $queue_frontend_css, $empty_cell_contents, $remove_on_uninstall;
	private $table_prefix, $table_type, $table_subject, $table_field, $table_compare;
	private $table_ids;

	public function __construct( $basename ) {
		parent::__construct( 'ruigehond014' );
		$this->basename = $basename;
		// table names
		$wp_prefix           = $this->wpdb->prefix;
		$this->table_prefix  = "{$wp_prefix}ruigehond014_";
		$this->table_type    = "{$wp_prefix}ruigehond014_type";
		$this->table_subject = "{$wp_prefix}ruigehond014_subject";
		$this->table_field   = "{$wp_prefix}ruigehond014_field";
		$this->table_compare = "{$wp_prefix}ruigehond014_compare";
		// settings used in front facing part of plugin
		$this->queue_frontend_css  = $this->getOption( 'queue_frontend_css', true );
		$this->empty_cell_contents = $this->getOption( 'empty_cell_contents', '-' );
	}

	public function initialize() {
//		if ( current_user_can( 'administrator' ) ) {
//			error_reporting( E_ALL );
//			ini_set( 'display_errors', '1' );
//		}
		$plugin_dir_url = plugin_dir_url( __FILE__ );
		if ( is_admin() ) {
			// settings used only for admin pages
			$this->table_ids = array();
			// set some options
			$this->database_version    = $this->getOption( 'database_version', '0.0.0' );
			$this->remove_on_uninstall = $this->getOption( 'remove_on_uninstall', false );
			// standard page url
			$this->admin_url = admin_url( 'admin.php?page=compare-table' );
			// regular
			$this->loadTranslations( 'compare-table' );
			add_action( 'admin_init', array( $this, 'settings' ) );
			add_action( 'admin_menu', array( $this, 'menu_item' ) );
			// styles...
			wp_enqueue_style( 'ruigehond014_admin_stylesheet', "{$plugin_dir_url}admin.css", [], RUIGEHOND014_VERSION );
			//wp_enqueue_style('wp-jquery-ui-dialog');
			// filters
			add_filter( "plugin_action_links_$this->basename", array(
				$this,
				'settings_link'
			) ); // settings link on plugins page
		} else {
			wp_enqueue_script( 'ruigehond014_javascript', "{$plugin_dir_url}client.js", array( 'jquery' ), RUIGEHOND014_VERSION );
			if ( $this->queue_frontend_css ) { // only output css when necessary
				wp_enqueue_style( 'ruigehond014_stylesheet_display', "{$plugin_dir_url}client.css", [], RUIGEHOND014_VERSION );
			}
			add_shortcode( 'compare-table_ITOEWERKLKVEIR', array( $this, 'handle_shortcode' ) );
		}
	}

	public function settings_link( $links ) {
		$link_text = $this->esc_trans__( 'Tables' );
		$link      = "<a href='$this->admin_url'>$link_text</a>";
		array_unshift( $links, $link );

		return $links;
	}

	public function handle_shortcode( $attributes = [], $content = null, $short_code = 'compare-table' ) {
		if ( ( false === get_the_ID() ) ) {
			return '';
		}
		$sql            = 'SELECT DISTINCT t.show_columns, t.list_alphabetically, t.choose_subject,
       		t.title type_title, s.title subject_title, s.o subject_order 
			FROM %i s INNER JOIN %i t ON t.id = s.type_id ';
		$prepare_values = array( $this->table_subject, $this->table_type );
		// build where clause for type
		if ( isset( $attributes['type'] ) && ( $type = $attributes['type'] ) ) {
			if ( 0 !== (int) $type ) {
				$where_table = 'WHERE t.id = %d';
				$where_value = (int) $type;
			} else {
				$where_table = 'WHERE t.title = %s';
				$where_value = $type;
			}
		} else {
			$where_table = 'WHERE t.id = (SELECT id FROM %i ORDER BY o LIMIT 1)';
			$where_value = $this->table_type;
		}
		$prepare_values[] = $where_value;
		// build sql statement to get the overall data
		$sql .= $where_table . ' ORDER BY s.o;';
//		if ( WP_DEBUG && ! wp_is_json_request() ) {
//			echo "<!--\n$sql\n-->";
//		}
		$sql  = $this->wpdb->prepare( $sql, $prepare_values );
		$rows = $this->wpdb->get_results( $sql );
		if ( 0 === count( $rows ) ) {
			return $this->esc_trans__( 'No data found' );
		}
		$row = $rows[1];
		// set common variables
		$show_columns   = $row->show_columns;
		$alphabetical   = '1' === $row->list_alphabetically;
		$choose_subject = $row->choose_subject;
		$type_title     = $row->type_title;
		// the actual sorting of the subjects:
		$all_subjects  = array();
		$show_subjects = array();
		foreach ( $rows as $index => $row ) {
			$all_subjects[] = $row->subject_title;
		}
		// NOTE: apparently 'min' returns a string here...
		$show_columns = (int) min( count( $all_subjects ), $show_columns ); // do not exceed actual number of subjects
		for ( $i = 0; $i < $show_columns; ++ $i ) {
			if (
				isset( $_GET["compare-table-column-$i"] )
				&& in_array( ( $get_subject = sanitize_text_field( $_GET["compare-table-column-$i"] ) ), $all_subjects )
			) {
				$show_subjects[] = $get_subject;
			} else {
				$show_subjects[] = $all_subjects[ $i ];
			}
		}
		// build sql statement to get all compare rows
		$num  = count( $show_subjects );
		$in   = substr( str_repeat( '%s,', $num ), 0, - 1 );
		$sql  = 'SELECT c.*, t.show_columns, t.list_alphabetically, t.choose_subject,
       		s.title subject_title, s.description subject_description, s.o subject_order,
       		f.title field_title, f.description field_description
       		FROM %i c
 			INNER JOIN %i s ON s.id = c.subject_id
     		INNER JOIN %i f ON f.id = c.field_id
     		INNER JOIN %i t ON t.id = f.type_id AND t.id = s.type_id ' . $where_table . '
		    AND s.title IN (' . $in . ') ORDER BY f.o, FIELD(s.title,' . $in . ');';
		$sql  = $this->wpdb->prepare( $sql, array_merge( array(
			$this->table_compare,
			$this->table_subject,
			$this->table_field,
			$this->table_type,
			$where_value
		), $show_subjects, $show_subjects ) );
		$rows = $this->wpdb->get_results( $sql );
		if ( 0 === count( $rows ) ) {
			ob_start();
			echo '<p>';
			echo $this->esc_trans__( 'Nothing found for table %s.', esc_html( var_export( $attributes, true ) ) );
			echo '</p>';

			return ob_get_clean();
		}
		$empty_cell = "<td class='cell compare empty'><p>{$this->empty_cell_contents}</p></td>";
		// now for the actual output
		$current_field = '';
		$count_columns = 0;
		// build data object for frontend javascript
		$data = array(
			//'rows'           => $rows,
			'type_title'     => $type_title,
			'show_columns'   => $show_columns,
			'show_subjects'  => $show_subjects,
			'all_subjects'   => $all_subjects,
			'alphabetical'   => $alphabetical,
			'choose_subject' => $choose_subject,
		);
		// start output
		ob_start();
		echo '<figure class="wp-block-table ruigehond014"><table data-ruigehond014="';
		echo esc_html( str_replace( '"', '&quot;', json_encode( $data, JSON_HEX_QUOT ) ) );
		echo '" id="compare-table-';
		echo str_replace( ' ', '-', esc_html( $type_title ) );
		echo '">';
		// table heading, double row with selectors
		echo '<thead><tr><th class="cell empty">&nbsp;</th>';
		for ( $i = 0; $i < $show_columns; ++ $i ) {
			echo '<th class="cell select index', esc_html( (string) $i ), '"></th>';
		}
		echo '</tr><tr><th class="cell empty"></th>';
		for ( $i = 0; $i < $show_columns; ++ $i ) {
			echo '<th class="cell heading">', esc_html( $show_subjects[ $i ] ), '</th>';
		}
		echo '</tr></thead>';
		// contents of the table
		echo '<tbody><tr>';
		foreach ( $rows as $index => $row ) {
			if ( $row->field_title === $current_field ) {
				++ $count_columns;
				if ( $count_columns >= $show_columns ) {
					continue;
				}
			} else {
				if ( '' !== $current_field ) {
					// finish the row if necessary
					while ( $count_columns < $show_columns - 1 ) {
						echo wp_kses_post( $empty_cell );
						++ $count_columns;
					}
					// new row
					echo '</tr><tr>';
				}
				$current_field = $row->field_title;
				$count_columns = 0;
				echo '<td class="cell field">';
				if ( isset( $row->field_description ) && ( $description = $row->field_description ) ) {
					echo '<div class="description">', wp_kses_post( $description ), '</div>';
				}
				echo '<p>', esc_html( $current_field ), '</p></td>';
			}
			while ( $count_columns < $show_columns && $show_subjects[ $count_columns ] !== $row->subject_title ) {
				echo wp_kses_post( $empty_cell );
				++ $count_columns;
				if ( $count_columns === $show_columns ) {
					continue 2;
				}
			}
			echo '<td class="cell compare">';
			if ( isset( $row->description ) && ( $description = $row->description ) ) {
				echo '<div class="description">', wp_kses_post( $description ), '</div>';
			}
			echo '<p>', esc_html( $row->title ), '</p></td>';
		}
		// finish the row if necessary
		while ( $count_columns < $show_columns - 1 ) {
			echo wp_kses_post( $empty_cell );
			++ $count_columns;
		}
		// end the compare cells
		echo '</tr></tbody>';
		// end
		echo '</table></figure>';

		return ob_get_clean();
	}

	public function handle_input( array $args ): ruigehond_ITOEWERKLKVEIR_0_4_1\returnObject {
		check_ajax_referer( 'ruigehond014_nonce', 'nonce' );
		if ( false === current_user_can( 'edit_posts' ) || ! is_admin() ) {
			return $this->getReturnObject( $this->esc_trans__( 'You do not have sufficient permissions to access this page.' ) );
		}
		$short_table_name = stripslashes( $args['table_name'] );
		if ( false === in_array( $short_table_name, array(
				'type',
				'subject',
				'field',
				'compare',
			) ) ) {
			return $this->getReturnObject( $this->esc_trans__( 'No such table %s', var_export( $args['table_name'], true ) ) );
		}
		$table_name = "{$this->table_prefix}$short_table_name";
		if ( isset( $args['id'] ) ) {
			$id = (int) $args['id'];
		} else {
			$id = 0;
		}
		$handle       = trim( stripslashes( $args['handle'] ) );
		$returnObject = $this->getReturnObject();

		switch ( $handle ) {
			case 'order_rows':
				if ( isset( $args['order'] ) && is_array( $args['order'] ) ) {
					$rows = $args['order'];
					foreach ( $rows as $id => $o ) {
						if ( 0 === $id ) {
							continue;
						}
						$this->upsertDb( $table_name,
							array( 'o' => (int) $o ),
							array( 'id' => (int) $id )
						);
					}
					$returnObject->set_success( true );
					$returnObject->set_data( $args );
				}
				break;
			case 'delete_permanently':
				if ( is_admin() ) {
					// check if it maybe cannot be deleted
					switch ( $short_table_name ) {
						case 'type':
							$sql1 = $this->wpdb->prepare( 'SELECT EXISTS (SELECT 1 FROM %i WHERE type_id = %d);', "{$this->table_prefix}subject", $id );
							$sql2 = $this->wpdb->prepare( 'SELECT EXISTS (SELECT 1 FROM %i WHERE type_id = %d);', "{$this->table_prefix}field", $id );
							if ( $this->wpdb->get_var( $sql1 ) || $this->wpdb->get_var( $sql2 ) ) {
								$returnObject->add_message( $this->esc_trans__( 'Cannot delete this' ), 'warn' );

								return $returnObject;
							}
							break;
						case 'subject':
						case 'field':
							$sql = $this->wpdb->prepare( 'SELECT EXISTS (SELECT 1 FROM %i WHERE %i = %d);', "{$this->table_prefix}compare", "{$short_table_name}_id", $id );
							if ( $this->wpdb->get_var( $sql ) ) {
								$returnObject->add_message( $this->esc_trans__( 'Cannot delete this' ), 'warn' );

								return $returnObject;
							}
							break;
						case 'compare': // can always be deleted, but needs different handle on client
							$args['handle'] = 'clear';
							break;
					}
					$deletedRows = $this->wpdb->delete( $table_name, array( 'id' => $id ) );
					if ( false === $deletedRows ) {
						$returnObject->add_message( $this->esc_trans__( 'Not deleted' ), 'warn' );
					} else {
						$args['id'] = 0; // deleted...
						$returnObject->set_success( true );
						$returnObject->set_data( $args );
					}
				}
				break;
			case 'update':
				$value       = trim( stripslashes( $args['value'] ) ); // don't know how it gets magically escaped, but not relying on it
				$column_name = stripslashes( $args['column_name'] );
				$where       = array( 'id' => $id );
				// validate present id's for table when insert is requested
				if ( 0 === $id ) {
					switch ( $short_table_name ) {
						case 'subject':
						case 'field':
							if ( false === isset( $args['type_id'] ) || 0 === ( $type_id = (int) $args['type_id'] ) ) {
								$returnObject->add_message( $this->esc_trans__( 'Missing id %s', 'type_id' ), 'error' );

								return $returnObject;
							}
							$where['type_id'] = $type_id;
							break;
						case 'compare':
							if (
								false === isset( $args['subject_id'], $args['field_id'] )
								|| 0 === ( $subject_id = (int) $args['subject_id'] )
								|| 0 === ( $field_id = (int) $args['field_id'] )
							) {
								$returnObject->add_message( $this->esc_trans__( 'Missing id %s', 'subject_id, field_id' ), 'error' );

								return $returnObject;
							}
							$where = array( 'subject_id' => $subject_id, 'field_id' => $field_id );
							break;
					}
				}
				// validate input value
				switch ( $column_name ) {
					case 'list_alphabetically':
						$value = '1' === $value ? 1 : 0;
						break;
					case 'show_columns':
						$value = max( abs( (int) $value ), 1 );
						break;
					case 'title':
					case 'description':
					case 'choose_subject':
						// any string is basically valid
						break;
					default:
						$returnObject->add_message( $this->esc_trans__( 'No such column %s', var_export( $column_name, true ) ), 'error' );
				}
				// do the upsert
				$upserted_rows = $this->upsertDb( $table_name, array( $column_name => $value ), $where );
				// report the upsert
				if ( 0 === $upserted_rows ) {
					$returnObject->add_message( $this->esc_trans__( 'Not updated' ), 'warn' );
				} else {
					$returnObject->set_success( true );
					if ( 0 < $upserted_rows ) { // this was an insert
						$id         = $upserted_rows;
						$args['id'] = $id;
						if ( 'compare' !== $short_table_name ) {
							// also set the order so it appears at the bottom
							$this->upsertDb( $table_name, array( 'o' => $id ), array( 'id' => $id ) );
							// return the entire row as html
							$sql          = $this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d;', $table_name, $id );
							$row          = $this->wpdb->get_row( $sql, OBJECT );
							$args['html'] = $this->get_row_html( $row, $short_table_name, $this->admin_url );
						}
					}
					$sql           = $this->wpdb->prepare( 'SELECT %i FROM %i WHERE id = %d;', $column_name, $table_name, $id );
					$args['value'] = $this->wpdb->get_var( $sql );
					$returnObject->set_data( $args );
				}
				break;
			default:
				return $this->getReturnObject( $this->esc_trans__( 'Did not understand handle %s', var_export( $args['handle'], true ) ) );
		}

		return $returnObject;
	}

	public function tables_page() {
		wp_enqueue_script( 'ruigehond014_admin_javascript', plugin_dir_url( __FILE__ ) . 'admin.js', array(
			'jquery-ui-droppable',
			'jquery-ui-sortable',
			'jquery'
		), RUIGEHOND014_VERSION );
		$ajax_nonce = wp_create_nonce( 'ruigehond014_nonce' );
		wp_localize_script( 'ruigehond014_admin_javascript', 'Ruigehond014_global', array(
			'nonce' => $ajax_nonce,
		) );
		$subject_id = 0;
		$field_id   = 0;
		$type_id    = (int) ( $_GET['type_id'] ?? 0 );
		$title      = '';
		if ( isset( $_GET['subject_id'] ) ) {
			$subject_id = (int) $_GET['subject_id'];
			$sql        = $this->wpdb->prepare( 'SELECT type_id, title FROM %i WHERE id = %d;', $this->table_subject, $subject_id );
			if ( ( $row = $this->wpdb->get_row( $sql ) ) ) {
				$type_id = (int) $row->type_id;
				$title   = $row->title;
			} else {
				$subject_id = 0;
			}
		} elseif ( isset( $_GET['field_id'] ) ) {
			$field_id = (int) $_GET['field_id'];
			$sql      = $this->wpdb->prepare( 'SELECT type_id, title FROM %i WHERE id = %d;', $this->table_field, $field_id );
			if ( ( $row = $this->wpdb->get_row( $sql ) ) ) {
				$type_id = (int) $row->type_id;
				$title   = $row->title;
			} else {
				$field_id = 0;
			}
		}
		$this->table_ids = array(
			'type'    => $type_id,
			'subject' => $subject_id,
			'field'   => $field_id,
		);
		echo '<div class="wrap ruigehond014"><h1>';
		echo esc_html( get_admin_page_title() );
		echo '</h1>';
		// get the type(s), provide sortable rows and a button / input to add a new type
		$this->tables_page_section( 'type' );
		// get the subjects for the current type, provide sortable rows and a button to add a new subject
		$this->tables_page_section( 'subject' );
		// get the fields for the current type, provide sortable rows and a button to add a new field
		$this->tables_page_section( 'field' );
		// end
		echo '</div>';
		// if a subject or field is selected, show the table that connects the fields + info box to that subject
		if ( $subject_id + $field_id > 0 ) {
			echo '<div id="ruigehond014-compare-overlay" class="close"><div class="wrap ruigehond014 compare">';
			echo '<p class="spacer top">&nbsp;</p>';
			echo '<button class="close" data-handle="close">×</button>';
			echo '<h2>', esc_html( $title ), '</h2>';
			$this->tables_page_section_compare( $type_id, $subject_id, $field_id );
			echo '<p class="spacer bottom">&nbsp;</p></div></div>';
		}
	}

	private function tables_page_section_compare( int $type_id, int $subject_id, int $field_id ) {
		$__clear = $this->esc_trans__( 'Clear' );
		if ( $subject_id > 0 ) {
			$sql  = $this->wpdb->prepare( '
            SELECT \'field\' parent_name, f.title parent_title, f.id parent_id, c.*
                FROM %i f
                    JOIN %i s ON s.id = %d
                    LEFT OUTER JOIN %i c ON c.field_id = f.id AND c.subject_id = s.id
                WHERE f.type_id = %d
                ORDER BY f.o;
            ', $this->table_field, $this->table_subject, $subject_id, $this->table_compare, $type_id );
			$rows = $this->wpdb->get_results( $sql, OBJECT );
		} elseif ( $field_id > 0 ) {
			$sql  = $this->wpdb->prepare( '
            SELECT \'subject\' parent_name, s.title parent_title, s.id parent_id, c.*
                FROM %i s
                    JOIN %i f ON f.id = %d
                    LEFT OUTER JOIN %i c ON c.subject_id = s.id AND c.field_id = f.id
                WHERE s.type_id = %d
                ORDER BY s.o;
            ', $this->table_subject, $this->table_field, $field_id, $this->table_compare, $type_id );
			$rows = $this->wpdb->get_results( $sql, OBJECT );
		} else {
			return;
		}
		echo '<section class="ruigehond014_rows" data-table_name="compare">';
		foreach ( $rows as $index => $row ) {
			$parent_name = $row->parent_name;
			$label       = $row->parent_title;
			if ( 'subject' === $parent_name ) {
				$subject_id = $row->parent_id;
			} else { // 'field'
				$field_id = $row->parent_id;
			}
			$id = $row->id;
			// write compare specific rows...
			echo '<div class="ruigehond014-row compare-row"><label>';
			echo esc_html( $label );
			echo '</label>';
			foreach ( array( 'title', 'description' ) as $index2 => $column_name ) {
				if ( isset( $row->{$column_name} ) ) {
					$column_value = $row->{$column_name};
				} else {
					$column_value = '';
				}
				echo '<textarea data-id="';
				echo (int) $id;
				echo '" data-handle="update" data-table_name="compare" data-column_name="';
				echo esc_html( $column_name );
				echo '" data-value="';
				echo esc_html( $column_value );
				echo '" data-field_id="';
				echo (int) $field_id;
				echo '" data-subject_id="';
				echo (int) $subject_id;
				echo '"	class="ruigehond014 input ';
				echo esc_html( $column_name );
				echo ' ajaxupdate tabbed"/>';
				echo esc_html( $column_value );
				echo '</textarea>';
			}
			echo '<div class="ruigehond014-delete"><input type="button" data-handle="delete_permanently" data-table_name="compare"';
			if ( null !== $id ) {
				echo ' data-id="', (int) $id, '"';
			}
			echo ' class="delete ruigehond014 ajaxupdate" value="', $__clear, '"/></div>';
			echo '</div>';
		}
		echo '</section>';
	}

	private function tables_page_section( string $table_short_name ) {
		$where   = '';
		$type_id = (int) $this->table_ids['type'];
		switch ( $table_short_name ) {
			case 'subject':
			case 'field':
				if ( 0 < $type_id ) {
					$where = (int) $type_id;
				} else {
					echo '<section class="ruigehond014_rows"><p>';
					echo $this->esc_trans__( 'Choose a type first.' );
					echo '</p></section><hr/>';

					return;
				}
				break;
		}
		if ( '' === $where ) {
			$sql = $this->wpdb->prepare( 'SELECT * FROM %i ORDER BY o;', "$this->table_prefix$table_short_name" );
		} else {
			$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE type_id = %d ORDER BY o;', "$this->table_prefix$table_short_name", $where );
		}
		$rows     = $this->wpdb->get_results( $sql, OBJECT );
		$__title  = $this->esc_trans__( 'Title' );
		$__descr  = $this->esc_trans__( 'Description' );
		$__choose = $this->esc_trans__( 'Choose subject text', 'loep' );
		echo '<section class="rows-sortable ruigehond014_rows" data-table_name="', esc_html( $table_short_name ), '">';
		switch ( $table_short_name ) {
			case 'subject':
				echo '<h2>', $this->esc_trans__( 'Subject' ), '</h2>';
				echo '<div class="ruigehond014-row header-row"><span>', $__title, '</span><span>', $__descr, '</span></div>';
				break;
			case 'field':
				echo '<h2>', $this->esc_trans__( 'Field' ), '</h2>';
				echo '<div class="ruigehond014-row header-row"><span>', $__title, '</span><span>', $__descr, '</span></div>';
				break;
			case 'type':
				echo '<div class="ruigehond014-row header-row"><span>', $__title, '</span><span>', $__choose, '</span></div>';
				break;
			default:
				echo 'THAT IS NOT A TABLE</section><hr/>';

				return;
		}
		foreach ( $rows as $index => $row ) {
			echo $this->get_row_html( $row, $table_short_name, $this->admin_url );
		}
		// new row
		echo '<div class="ruigehond014-row new-row" data-id="0">';
		echo '<div class="sortable-handle">::</div>'; // visibility set to hidden by css, used for spacing
		echo '<textarea data-handle="update" data-table_name="';
		echo esc_html( $table_short_name );
		echo '" data-type_id="';
		echo (int) $type_id;
		echo '" data-column_name="title" class="ruigehond014 input title ajaxupdate tabbed"></textarea>';
		echo '</div>';
		echo '</section><hr/>';
	}

	private function get_row_html( \stdClass $row, string $table_short_name, string $current_url ): string {
		$id       = (int) $row->id;
		$__edit   = $this->esc_trans__( 'Edit' );
		$__delete = $this->esc_trans__( 'Delete' );
		ob_start();
		echo '<div class="ruigehond014-row orderable ';
		echo esc_html( $table_short_name ), '-row';
		if ( isset( $this->table_ids[ $table_short_name ] ) && $id === $this->table_ids[ $table_short_name ] ) {
			echo ' active';
		}
		echo '" data-id="';
		echo (int) $id;
		//echo '" data-inferred_order="';
		//echo $row->o;
		echo '">';
		echo '<div class="sortable-handle">::</div>';
		foreach ( array( 'title', 'description', 'choose_subject' ) as $index => $column_name ) {
			if ( ! property_exists( $row, $column_name ) ) {
				continue;
			}
			if ( isset( $row->{$column_name} ) ) {
				$column_value = $row->{$column_name};
			} else {
				$column_value = '';
			}
			echo '<textarea data-id="';
			echo (int) $id;
			echo '" data-handle="update" data-table_name="';
			echo esc_html( $table_short_name );
			echo '" data-column_name="';
			echo esc_html( $column_name );
			echo '" data-value="';
			echo esc_html( $column_value );
			echo '"	class="ruigehond014 input ';
			echo esc_html( $column_name );
			echo ' ajaxupdate tabbed"/>';
			echo esc_html( $column_value );
			echo '</textarea>';
		}
		if ( property_exists( $row, 'show_columns' ) ) {
			echo '<input type="number" title="';
			echo $this->esc_trans__( 'Number of columns to show initially.' );
			echo '" data-id="';
			echo (int) $id;
			echo '" data-handle="update" data-table_name="';
			echo esc_html( $table_short_name );
			echo '" data-column_name="show_columns" data-value="';
			echo (int) $row->show_columns;
			echo '" value="';
			echo (int) $row->show_columns;
			echo '"	class="ruigehond014 input number ajaxupdate tabbed" min="1"/>';
		}
		if ( property_exists( $row, 'list_alphabetically' ) ) {
			echo '<input type="checkbox" title="';
			echo $this->esc_trans__( 'List alphabetically.' );
			echo '" data-id="';
			echo (int) $id;
			echo '" data-handle="update" data-table_name="';
			echo esc_html( $table_short_name );
			echo '" data-column_name="list_alphabetically" ';
			if ( 0 === (int) $row->list_alphabetically ) {
				echo 'data-checked=false';
			} else {
				echo 'checked="checked" data-checked=true';
			}
			echo ' class="ruigehond014 input checkbox ajaxupdate tabbed"/>';
		}
		echo '<div class="ruigehond014-edit"><a href="';
		echo esc_url( $this->add_query_to_url( $current_url, "{$table_short_name}_id", urlencode( (string) $id ) ) );
		echo '">', $__edit, '</a></div>';
		echo '<div class="ruigehond014-delete"><input type="button" data-handle="delete_permanently" data-table_name="';
		echo esc_html( $table_short_name );
		echo '" data-id="';
		echo (int) $id;
		echo '" class="delete ruigehond014 ajaxupdate" value="', $__delete, '"/></div>';
		echo '</div>';

		return ob_get_clean();
	}

	private function add_query_to_url( string $current_url, string $key, string $value ): string {
		if ( strpos( $current_url, $key ) ) {
			// remove when already present, also any tables selected after it, because they will be invalid
			$pos         = strpos( $current_url, "$key=" );
			$current_url = substr( $current_url, 0, $pos );
		}

		return "$current_url&$key=$value"; // current_url qs always starts with ?page=compare-table
	}

	public function settings_page() {
		// check user capabilities
		if ( false === current_user_can( 'manage_options' ) ) {
			return;
		}
		// start the page
		echo '<div class="wrap"><h1>';
		echo esc_html( get_admin_page_title() );
		echo '</h1><p>';
		echo $this->esc_trans__( 'General settings for your compare tables.' );
		echo '</p>';
		echo '<form action="options.php" method="post">';
		// output security fields for the registered setting
		settings_fields( 'ruigehond014' );
		// output setting sections and their fields
		do_settings_sections( 'ruigehond014' );
		// output save settings button
		submit_button( $this->esc_trans__( 'Save Settings' ) );
		echo '</form></div>';
	}

	public function settings() {
		if ( false === $this->onSettingsPage( 'compare-table' ) ) {
			return;
		}
		if ( false === current_user_can( 'manage_options' ) ) {
			return;
		}
		register_setting( 'ruigehond014', 'ruigehond014', array( $this, 'settings_validate' ) );
		// register a new section in the page
		add_settings_section(
			'global_settings', // section id
			$this->esc_trans__( 'Options' ), // title
			static function () {
			}, //callback
			'ruigehond014' // page id
		);
		$labels = array(
			'remove_on_uninstall' => $this->esc_trans__( 'Check this if you want to remove all data when uninstalling the plugin.' ),
			'queue_frontend_css'  => $this->esc_trans__( 'By default a small css-file is output to the frontend to format the entries. Uncheck to handle the css yourself.', 'faq-with-categories' ),
			'empty_cell_contents' => $this->esc_trans__( 'Type the default contents for empty cells in the table' ),
		);
		foreach ( $labels as $setting_name => $explanation ) {
			add_settings_field(
				$setting_name, // id, As of WP 4.6 this value is used only internally
				$setting_name, // title
				array( $this, 'echo_settings_field' ), // callback
				'ruigehond014', // page id
				'global_settings',
				[
					'setting_name' => $setting_name,
					'label_for'    => $explanation,
					'class_name'   => 'ruigehond014',
				] // args
			);
		}
	}

	public function echo_settings_field( $args ) {
		$setting_name = $args['setting_name'];
		switch ( $setting_name ) {
			case 'queue_frontend_css':
			case 'remove_on_uninstall': // make checkbox that transmits 1 or 0, depending on status
				echo '<label><input type="hidden" name="ruigehond014[', esc_html( $setting_name ), ']" value="';
				if ( $this->$setting_name ) {
					echo '1"><input type="checkbox" checked="checked"';
				} else {
					echo '0"><input type="checkbox"';
				}
				echo ' onclick="this.previousSibling.value=1-this.previousSibling.value" class="';
				echo esc_html( $args['class_name'] ), '"/>', esc_html( $args['label_for'] ), '</label>';
				break;
			default: // make text input
				echo '<input type="text" name="ruigehond014[', esc_html( $setting_name ), ']" value="';
				echo esc_html( $this->$setting_name );
				echo '" style="width: 162px" class="', esc_html( $args['class_name'] ), '"/> <label>', esc_html( $args['label_for'] ), '</label>';
		}
	}

	public function settings_validate( $input ): array {
		$options = (array) get_option( 'ruigehond014' );
		foreach ( $input as $key => $value ) {
			switch ( $key ) {
				// on / off flags (1 vs 0 on form submit, true / false otherwise
				case 'queue_frontend_css':
				case 'remove_on_uninstall':
					$options[ $key ] = ( $value === '1' or $value === true );
					break;
				case 'max':
					if ( abs( intval( $value ) ) > 0 ) {
						$options[ $key ] = abs( intval( $value ) );
					}
					break;
				default:
					$options[ $key ] = $value;
			}
		}

		return $options;
	}

	public function menu_item() {
		// add top level page
		add_menu_page(
			'Compare table',
			'Compare table',

			'edit_posts',
			'compare-table',
			array( $this, 'tables_page' ), // callback
			//'dashicons-editor-table',
			"data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBmaWxsPSIjYTdhYWFkIiBkPSJNMS4xNSwxLjY2djE2LjE2aDE3LjIxVjEuNjZIMS4xNXogTTguMjUsMy44NGwwLjYzLTAuNjNMMTAsNC4zMmwxLjExLTEuMTFsMC42MywwLjYzbC0xLjExLDEuMTFsMS4xMSwxLjExTDExLjExLDYuNwoJTDEwLDUuNTlMOC44OSw2LjdMOC4yNSw2LjA3bDEuMTEtMS4xMUw4LjI1LDMuODR6IE02LjUsMTUuOTdMNS44NiwxNi42bC0xLjExLTEuMTFMMy42NCwxNi42TDMsMTUuOTdsMS4xMS0xLjExTDMsMTMuNzRsMC42My0wLjYzCglsMS4xMSwxLjExbDEuMTEtMS4xMWwwLjYzLDAuNjNsLTEuMTEsMS4xMUw2LjUsMTUuOTd6IE00LjIzLDExLjY1TDIuNDEsOS44M0wzLjA0LDkuMmwxLjE5LDEuMTlsMi4yMy0yLjIzbDAuNjMsMC42M0w0LjIzLDExLjY1egoJIE00LjIzLDYuN0wyLjQxLDQuODhsMC42My0wLjYzbDEuMTksMS4xOWwyLjIzLTIuMjNsMC42MywwLjYzTDQuMjMsNi43eiBNMTEuNzUsMTUuOTdsLTAuNjMsMC42M0wxMCwxNS40OUw4Ljg5LDE2LjZsLTAuNjMtMC42MwoJbDEuMTEtMS4xMWwtMS4xMS0xLjExbDAuNjMtMC42M0wxMCwxNC4yMmwxLjExLTEuMTFsMC42MywwLjYzbC0xLjExLDEuMTFMMTEuNzUsMTUuOTd6IE05LjQ4LDExLjY1TDcuNjYsOS44M0w4LjI5LDkuMmwxLjE5LDEuMTkKCWwyLjIzLTIuMjNsMC42MywwLjYzTDkuNDgsMTEuNjV6IE0xNC43MywxNi41NGwtMS44Mi0xLjgybDAuNjMtMC42M2wxLjE5LDEuMTlsMi4yMy0yLjIzbDAuNjMsMC42M0wxNC43MywxNi41NHogTTEzLjUsOC43NgoJbDAuNjMtMC42M2wxLjExLDEuMTFsMS4xMS0xLjExTDE3LDguNzZsLTEuMTEsMS4xMUwxNywxMC45OWwtMC42MywwLjYzbC0xLjExLTEuMTFsLTEuMTEsMS4xMWwtMC42My0wLjYzbDEuMTEtMS4xMUwxMy41LDguNzZ6CgkgTTE0LjczLDYuN2wtMS44Mi0xLjgybDAuNjMtMC42M2wxLjE5LDEuMTlsMi4yMy0yLjIzbDAuNjMsMC42M0wxNC43Myw2Ljd6Ii8+Cjwvc3ZnPgo=",
			20
		);
		add_submenu_page(
			'compare-table',
			$this->esc_trans__( 'Settings' ), // page_title
			$this->esc_trans__( 'Settings' ), // menu_title
			'manage_options',
			'compare-table-settings',
			array( $this, 'settings_page' )
		);

		global $submenu; // make the first entry go to the tables page
		$submenu['compare-table'][0] = array(
			$this->esc_trans__( 'Tables' ),
			'edit_posts',
			'compare-table', // the slug that identifies with the callback tables_page of the main menu item
			$this->esc_trans__( 'Tables' ),
		);
	}

	public function activate() {
		$sql = $this->wpdb->prepare( 'SHOW TABLES LIKE %s;', $this->table_type );
		if ( $this->wpdb->get_var( $sql ) != $this->table_type ) {
			$sql = $this->wpdb->prepare( 'CREATE TABLE %i (
						id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
						title text CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_bin\' NOT NULL,
						choose_subject text CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_bin\' NOT NULL,
						show_columns int NOT NULL DEFAULT 2,
						list_alphabetically int NOT NULL DEFAULT 0,
						o INT NOT NULL DEFAULT 1
                    );', $this->table_type );
			$this->wpdb->query( $sql );
			$sql = $this->wpdb->prepare( 'INSERT INTO %i (title) VALUES (\'Compare table\');', $this->table_type );
			$this->wpdb->query( $sql );
		}
		$sql = $this->wpdb->prepare( 'SHOW TABLES LIKE %s;', $this->table_subject );
		if ( $this->wpdb->get_var( $sql ) != $this->table_subject ) {
			$sql = $this->wpdb->prepare( 'CREATE TABLE %i (
						id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
						type_id INT NOT NULL,
						title text CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_bin\' NOT NULL,
						description text CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_bin\',
						o INT NOT NULL DEFAULT 1
                    )
					DEFAULT CHARACTER SET = utf8mb4
					COLLATE = utf8mb4_bin
					;', $this->table_subject );
			$this->wpdb->query( $sql );
		}
		$sql = $this->wpdb->prepare( 'SHOW TABLES LIKE %s;', $this->table_field );
		if ( $this->wpdb->get_var( $sql ) != $this->table_field ) {
			$sql = $this->wpdb->prepare( 'CREATE TABLE %i (
						id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
						type_id INT NOT NULL,
						title text CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_bin\' NOT NULL,
						description text CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_bin\',
						o INT NOT NULL DEFAULT 1
                    )
					DEFAULT CHARACTER SET = utf8mb4
					COLLATE = utf8mb4_bin
					;', $this->table_field );
			$this->wpdb->query( $sql );
		}
		$sql = $this->wpdb->prepare( 'SHOW TABLES LIKE %s;', $this->table_compare );
		if ( $this->wpdb->get_var( $sql ) != $this->table_compare ) {
			$sql = $this->wpdb->prepare( 'CREATE TABLE %i (
                        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                        subject_id INT NOT NULL,
                        field_id INT NOT NULL,
                        title text CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_bin\',
                        description text CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_bin\'
                    )
                    DEFAULT CHARACTER SET = utf8mb4                        
                    COLLATE = utf8mb4_bin
                    ;', $this->table_compare );
			$this->wpdb->query( $sql );
		}

		// register the current version
		$this->setOption( 'database_version', RUIGEHOND014_VERSION );
	}

	public function deactivate() {
		// nothing to do here
	}

	public function uninstall() {
		if ( false === $this->remove_on_uninstall ) {
			return;
		}
		// remove tables
		foreach (
			array(
				$this->table_type,
				$this->table_subject,
				$this->table_field,
				$this->table_compare
			) as $table_name
		) {
			$sql = $this->wpdb->prepare( 'SHOW TABLES LIKE %i;', $table_name );
			if ( $this->wpdb->get_var( $sql ) == $table_name ) {
				$sql = $this->wpdb->prepare( 'DROP TABLE %i;', $table_name );
				$this->wpdb->query( $sql );
			}
		}
		// remove settings
		delete_option( 'ruigehond014' );
	}
}
