<?php

function create_api_keys_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'api_keys';

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
    return;
    }

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        company_name VARCHAR(255) NOT NULL,
		company_email VARCHAR(255) NOT NULL,
        api_key VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    dbDelta($sql);

}

// Render the API Key page
function api_key_generator_create_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'api_keys';

    echo '<div class="wrap">';
    echo '<h1>Create New API Keys</h1>';
	// If the form is submitted, create a new API Key and store it in the database
if (isset($_POST['create_api_key'])) {
    $company_name = sanitize_text_field($_POST['company_name']);
    $company_email = sanitize_email($_POST['company_email']);
    $api_key = bin2hex(random_bytes(20));

    $data = array(
        'company_name' => $company_name,
        'company_email' => $company_email, // add email to data array
        'api_key' => $api_key,
    );
    $format = array('%s', '%s', '%s');

    $wpdb->insert($table_name, $data, $format);

    echo '<div class="notice notice-success"><p>API Key created successfully!</p></div>';
}

    echo '<form method="post" style="margin-bottom: 20px;">';
	echo '<table class="form-table" style="width: 100%;">';
	echo '<tr><th><label for="company_name">Company Name</label></th><td><input type="text" name="company_name" required></td></tr>';
	echo '<tr><th><label for="company_email">Company Email</label></th><td><input type="email" name="company_email" required></td></tr>';
	echo '</table>';
	echo '<p><input type="submit" class="button button-primary" name="create_api_key" value="Create API Key"></p>';
	echo '</form>';
	echo '</div>';
}

function api_key_generator_existing_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'api_keys';

    echo '<div class="wrap">';
    echo '<h1>Existing API Keys</h1>';
	// Display existing API keys
    $api_keys = $wpdb->get_results("SELECT id, company_name, company_email, api_key, created_at FROM $table_name");

    if (!empty($api_keys)) {
		echo '<hr>';
        echo '<h2>Existing API Keys</h2>';
        echo '<form method="post" style="margin-bottom: 20px;">';
        echo '<label for="search_company_name">Search Company Name:</label>';
        echo '<input type="text" id="search_company_name" name="search_company_name" style="margin-right: 10px;">';
        echo '<input type="submit" class="button" name="search_api_key" value="Search">';
        echo '</form>';

        if (isset($_POST['delete_api_key'])) {
            $api_key_id = $_POST['api_key_id'];
            $wpdb->delete($table_name, array('id' => $api_key_id));
            echo '<div class="notice notice-success"><p>API Key deleted successfully!</p></div>';
        }

        // Add the inline CSS
        echo '<style>
            .api-keys-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            .api-keys-table thead {
                background-color: #f1f1f1;
            }

            .api-keys-table th,
            .api-keys-table td {
                text-align: left;
                padding: 10px;
                border: 1px solid #f1f1f1;
            }

            .api-keys-table tr:nth-child(even) {
                background-color: #f8f8f8;
            }

            .api-keys-table tr:hover {
                background-color: #e8e8e8;
            }

            .button-secondary {
                background-color: #dc3232;
                color: #fff;
            }
        </style>';

                echo '<table class="api-keys-table">';
        echo '<thead><tr><th>ID</th><th>Company Name</th><th>Company Email</th><th>API Key</th><th>Created At</th><th></th></tr></thead>';
        echo '<tbody>';
		$has_results = false;
        foreach ($api_keys as $api_key) {
            if (isset($_POST['search_api_key'])) {
                $search_company_name = $_POST['search_company_name'];
                if (stripos($api_key->company_name, $search_company_name) === false) {
                    continue;
                }
            }

            echo '<tr>';
			echo '<td>' . esc_html($api_key->id) . '</td>';
            echo '<td>' . esc_html($api_key->company_name) . '</td>';
			echo '<td>' . esc_html($api_key->company_email) . '</td>';
            echo '<td>' . esc_html($api_key->api_key) . '</td>';
            echo '<td>' . esc_html($api_key->created_at) . '</td>';
            echo '<td><form method="post" name="delete_api_key_form" style="display:inline-block;"><input type="hidden" name="api_key_id" value="' . esc_attr($api_key->id) . '"><input type="submit" class="button button-secondary" name="delete_api_key" value="Delete"></form></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No API Keys found.</p>';
    }

    echo '</div>';
}

function properties_api_enqueue_scripts() {
    wp_enqueue_script('properties-api-js', plugin_dir_url(__FILE__) . 'properties-api.js', array('jquery'), '1.0.0', true);
    wp_localize_script('properties-api-js', 'propertiesApi', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('delete_api_key_nonce')
    ));
}


function delete_api_key_callback() {
    check_ajax_referer('delete_api_key_nonce', 'security');

    if (isset($_POST['api_key_id'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'api_keys';
        $api_key_id = intval($_POST['api_key_id']);
        $result = $wpdb->delete($table_name, array('id' => $api_key_id));

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to delete API key.'));
        }
    } else {
        wp_send_json_error(array('message' => 'API key ID not provided.'));
    }
}

//Begin Filter Page Functions

function get_unique_meta_keys() {
    global $wpdb;

    $post_type = 'property';
    $resort_post_type = 'cpt_resort';

    $query = "
        SELECT DISTINCT meta_keys.meta_key
        FROM (
            SELECT $wpdb->postmeta.meta_key
            FROM $wpdb->posts
            LEFT JOIN $wpdb->postmeta
            ON $wpdb->posts.ID = $wpdb->postmeta.post_id
            WHERE $wpdb->posts.post_type = %s

            UNION ALL

            SELECT $wpdb->postmeta.meta_key
            FROM $wpdb->posts
            LEFT JOIN $wpdb->postmeta
            ON $wpdb->posts.ID = $wpdb->postmeta.post_id
            WHERE $wpdb->posts.post_type = %s
        ) AS meta_keys
    ";

    $prepared_query = $wpdb->prepare($query, $post_type, $resort_post_type);
    $meta_keys = $wpdb->get_col($prepared_query);

    // Sort the meta keys in alphabetical order
    sort($meta_keys);

    return $meta_keys;
}


function property_filter_page() {
    echo '<div class="wrap">';
    echo '<h1 class="page-title">Property Filter</h1>';

    // Fetch all unique post meta keys
    $meta_keys = get_unique_meta_keys();

    // Comparison operators
    $compare_operators = array(
        '=' => 'Equal',
        '!=' => 'Not Equal',
        '>' => 'Greater Than',
        '>=' => 'Greater Than or Equal To',
        '<' => 'Less Than',
        '<=' => 'Less Than or Equal To',
    );

    $relations = array(
        'AND' => 'AND',
        'OR' => 'OR',
    );

    // Handle form submission and save the filter settings
    if (isset($_POST['filter_properties'])) {
        $meta_key = sanitize_text_field($_POST['meta_key']);
        $compare_operator = sanitize_text_field($_POST['compare_operator']);
        $meta_value = sanitize_text_field($_POST['meta_value']);

        $nested_meta_key = isset($_POST['nested_meta_key']) ? sanitize_text_field($_POST['nested_meta_key']) : '';
        $nested_compare_operator = isset($_POST['nested_compare_operator']) ? sanitize_text_field($_POST['nested_compare_operator']) : '';
        $nested_meta_value = isset($_POST['nested_meta_value']) ? sanitize_text_field($_POST['nested_meta_value']) : '';
        $relation = isset($_POST['relation']) ? sanitize_text_field($_POST['relation']) : 'AND';

        // Save the filter settings in the options table
        $filter = array(
            'meta_key' => $meta_key,
            'compare_operator' => $compare_operator,
            'meta_value' => $meta_value,
        );

        if (!empty($nested_meta_key) && !empty($nested_compare_operator) && !empty($nested_meta_value)) {
            $filter['nested_filter'] = array(
                'meta_key' => $nested_meta_key,
                'compare_operator' => $nested_compare_operator,
                'meta_value' => $nested_meta_value,
                'relation' => $relation,
            );
        }

        $filters = get_option('property_filters', array());
        $filters[] = $filter;
        update_option('property_filters', $filters);

        echo '<div class="notice notice-success"><p>Filter settings saved successfully!</p></div>';
    }

    // Handle filter deletion
    if (isset($_POST['delete_filter'])) {
        $filter_index = intval($_POST['filter_index']);

        $filters = get_option('property_filters', array());
        if (isset($filters[$filter_index])) {
            array_splice($filters, $filter_index, 1);
            update_option('property_filters', $filters);

            echo '<div class="notice notice-success"><p>Filter removed successfully!</p></div>';
        }
    }

	// Display filter form
		echo '<form method="post" class="filter-form" style="display: flex; flex-wrap: wrap;">';
		echo '<div style="width: 100%; max-width: 300px; margin-right: 10px;">';
		echo '<label for="meta_key" class="form-label" style="display: block; margin-bottom: 5px;">Meta Key:</label>';
		echo '<select name="meta_key" required class="form-select" style="width: 100%; height: 38px; padding: 8px; font-size: 16px; line-height: 1.2; color: #555; background-color: #fff; border: 1px solid #d8dbe0; border-radius: 4px;">';
		foreach ($meta_keys as $key) {
			echo '<option value="' . esc_attr($key) . '">' . esc_html($key) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '<div style="width: 100%; max-width: 300px; margin-right: 10px;">';
		echo '<label for="compare_operator" class="form-label" style="display: block; margin-bottom: 5px;">Operator:</label>';
		echo '<select name="compare_operator" required class="form-select" style="width: 100%; height: 38px; padding: 8px; font-size: 16px; line-height: 1.2; color: #555; background-color: #fff; border: 1px solid #d8dbe0; border-radius: 4px;">';
		foreach ($compare_operators as $op => $label) {
			echo '<option value="' . esc_attr($op) . '">' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '<div style="width: 100%; max-width: 300px; margin-right: 10px;">';
		echo '<label for="meta_value" class="form-label" style="display: block; margin-bottom: 5px;">Value:</label>';
		echo '<input type="text" name="meta_value" required class="form-input" style="width: 100%; height: 38px; padding: 8px; font-size: 16px; line-height: 1.2; color: #555; background-color: #fff; border: 1px solid #d8dbe0; border-radius: 4px;">';
		echo '</div>';
		echo '<div style="width: 100%; max-width: 300px; margin-right: 10px;">';
echo '<label for="relation" class="form-label" style="display: block; margin-bottom: 5px;">Relation:</label>';
echo '<select name="relation" class="form-select" style="width: 100%; height: 38px; padding: 8px; font-size: 16px; line-height: 1.2; color: #555; background-color: #fff; border: 1px solid #d8dbe0; border-radius: 4px;">';
foreach ($relations as $rel => $label) {
    echo '<option value="' . esc_attr($rel) . '">' . esc_html($label) . '</option>';
}
echo '</select>';
echo '</div>';
echo '<input type="submit" class="btn btn-primary form-submit" name="filter_properties" value="Add Filter" style="background-color: #007cba; color: #fff; padding: 10px 20px; font-size: 16px; line-height: 1.2; border: none; border-radius: 4px; margin-top: 20px; cursor: pointer;">';

		echo '</form>';
		// Display saved filters
		$filters = get_option('property_filters', array());
		if (!empty($filters)) {
			echo '<h2 style="text-align: center; font-size: 24px; font-weight: bold; margin-bottom: 30px;">Saved Filters</h2>';
			echo '<table style="margin: 0 auto; border-collapse: collapse; width: 80%;">';
			echo '<thead>';
			echo '<tr>';
			echo '<th style="padding: 10px; background-color: #FF972E; color: #fff; text-align: center; border: 1px solid #ddd;">Meta Key</th>';
			echo '<th style="padding: 10px; background-color: #FF972E; color: #fff; text-align: center; border: 1px solid #ddd;">Operator</th>';
			echo '<th style="padding: 10px; background-color: #FF972E; color: #fff; text-align: center; border: 1px solid #ddd;">Value</th>';
			echo '<th style="padding: 10px; background-color: #FF972E; color: #fff; text-align: center; border: 1px solid #ddd;">Action</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
			foreach ($filters as $index => $filter) {
				echo '<tr>';
				echo '<td style="padding: 10px; text-align: center; border: 1px solid #ddd;">' . esc_html($filter['meta_key']) . '</td>';
				echo '<td style="padding: 10px; text-align: center; border: 1px solid #ddd;">' . esc_html($filter['compare_operator']) . '</td>';
				echo '<td style="padding: 10px; text-align: center; border: 1px solid #ddd;">' . esc_html($filter['meta_value']) . '</td>';
				echo '<td style="padding: 10px; text-align: center; border: 1px solid #ddd;"><form method="post" style="display: inline-block;">';
				echo '<input type="hidden" name="filter_index" value="' . esc_attr($index) . '">';
				echo '<button type="submit" class="btn btn-danger" name="delete_filter" style="padding: 6px 12px; border-radius: 5px; background-color: #dc3545; border-color: #dc3545; color: #fff; font-size: 14px;">Remove</button>';
				echo '</form></td>';
				echo '</tr>';
			}
			echo '</tbody>';
			echo '</table>';
		} else {
			echo '<p style="text-align: center; font-size: 18px; margin-top: 30px;">No filters saved.</p>';
		}

		echo '</div>';
		}
