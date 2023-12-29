<?php

add_action('rest_api_init', 'register_property_api_routes');

function register_property_api_routes() {
    register_rest_route('api/v2', '/for-sale/properties/', array(
        'methods' => 'GET',
        'callback' => 'get_all_forsale_properties_with_meta',
        'args' => array(
            'page' => array(
                'description' => 'The page number',
                'type' => 'integer',
                'default' => 1,
            ),
        ),
    ));
}

function get_post_status_label($status) {
    switch ($status) {
        case 'publish':
            return 'Active';
        case 'pending':
            return 'Pending';
        default:
            return ucfirst($status); // For other post statuses, capitalize the first letter
    }
}
//Get generate page links
function generate_pagination_link($base_url, $page, $total_pages) {
    if ($page < 1 || $page > $total_pages) {
        return null;
    }
    return "{$base_url}?page={$page}";
}



function build_meta_query() {
    // Get saved filters from the options table
    $saved_filters = get_option('property_filters', array());

    $meta_query = array(
        'relation' => 'AND',
        array(
            'key' => 'fave_partner-id',
            'value' => array('a001U00000ljyLOQAY', 'a001U00000ljyLJQAY'),
            'compare' => 'IN',
        ),
    );

    // Add saved filters to the meta query
    foreach ($saved_filters as $filter) {
        if (isset($filter['nested_filter'])) {
            $nested_filter = $filter['nested_filter'];
            $query = array(
                'relation' => $nested_filter['relation'],
                array(
                    'key' => $filter['meta_key'],
                    'value' => $filter['meta_value'],
                    'compare' => $filter['compare_operator'],
                ),
                array(
                    'key' => $nested_filter['meta_key'],
                    'value' => $nested_filter['meta_value'],
                    'compare' => $nested_filter['compare_operator'],
                ),
            );
            $meta_query[] = $query;
        } elseif ($filter['meta_key'] == 'fide_brand') {
            $brand_title = $filter['meta_value'];

            // Get brand ID by title
            $brand_post = get_page_by_title($brand_title, OBJECT, 'cpt_brand');
            if ($brand_post) {
                $brand_id = $brand_post->ID;

                // Get resort posts with the specified brand ID
                $resort_posts = get_posts(array(
                    'post_type' => 'cpt_resort',
                    'meta_key' => 'fide_brand',
                    'meta_value' => $brand_id,
                    'posts_per_page' => -1,
                ));

                // Get resort IDs
                $resort_ids = array();
                foreach ($resort_posts as $resort_post) {
                    $resort_ids[] = $resort_post->ID;
                }

                if (!empty($resort_ids)) {
                    $meta_query[] = array(
                        'key' => 'fide_resort',
                        'value' => $resort_ids,
                        'compare' => 'IN',
                    );
                }
            }
        } elseif ($filter['meta_key'] == 'fide_resort') {
            $resort_title = $filter['meta_value'];

            // Get resort ID by title
            $resort_post = get_page_by_title($resort_title, OBJECT, 'cpt_resort');
            if ($resort_post) {
                $resort_id = $resort_post->ID;
                $meta_query[] = array(
                    'key' => $filter['meta_key'],
                    'value' => $resort_id,
                    'compare' => $filter['compare_operator'],
                );
            }
        } else {
            $meta_query[] = array(
                'key' => $filter['meta_key'],
                'value' => $filter['meta_value'],
                'compare' => $filter['compare_operator'],
            );
        }
    }

    return $meta_query;
}











function get_all_forsale_properties_with_meta($request) {
    // Check the API key
    $api_key = $request->get_header('X-API-KEY');
    if (!$api_key) {
        return new WP_Error('missing_api_key', 'API key is missing', array('status' => 401));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'api_keys';

    $valid_api_key = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE api_key = %s",
            $api_key
        )
    );
    if (!$valid_api_key) {
        return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
    }
    $page = $request->get_param('page') ?: 1;
    $since = $request->get_param('since');

    		$args = array(
		'post_type' => 'property',
		'posts_per_page' => 50,
		'paged' => $page,
		'meta_query' => build_meta_query(),
	);

    if (!empty($since)) {
        $since_date = DateTime::createFromFormat('m-d-Y', $since);
        if ($since_date !== false) {
            $args['date_query'] = array(
                array(
                    'column' => 'post_modified',
                    'after' => $since_date->format('Y-m-d'),
                ),
            );
        }
    }



$query = new WP_Query($args);

    $query = new WP_Query($args);
    $properties_data = array();

    $current_page = (int) $query->get('paged');
    $total_pages = (int) $query->max_num_pages;
    $total_properties = (int) $query->found_posts;

//added in pagination and total property count

	$properties_data['pagination'] = array(
    'current_page' => $current_page,
    'total_pages' => $total_pages,
    'total_properties' => $total_properties,
);

//added in links to pages current, next, and previous
	$properties_data['links'] = array(
		'current' => generate_pagination_link('https://www.fidelityrealestate.com/wp-json/api/v2/for-sale/properties', $current_page, $total_pages),
		'next' => generate_pagination_link('https://www.fidelityrealestate.com/wp-json/api/v2/for-sale/properties', $current_page + 1, $total_pages),
		'previous' => generate_pagination_link('https://www.fidelityrealestate.com/wp-json/api/v2/for-sale/properties', $current_page - 1, $total_pages),
);

    if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        $property_data = array(
            'id' => get_the_ID(),
            'listing_name' => get_the_title(),
			'listing_type' => 'For Sale',
            'last_modified' => get_the_modified_date('Y-m-d H:i:s'),
            // Add more default post fields as needed
        );
		
		// Retrieve the fave_agents ID from the property post
		$fave_agents_id = get_post_meta(get_the_ID(), 'fave_agents', true);

		if (!empty($fave_agents_id)) {
			// Fetch the fave_agents post object
			$agent_post = get_post($fave_agents_id);

			// Add fave_agents information to the property data
			$property_data['agent'] = array(
				'id' => $fave_agents_id,
				'name' => $agent_post->post_title,
				'email' => get_post_meta($fave_agents_id, 'fave_agent_email', true),
				'phone' => get_post_meta($fave_agents_id, 'fave_agent_mobile', true),
			);
		} else {
			$property_data['agent'] = array(
				'id' => '',
				'name' => '',
				'email' => '',
				'phone' => '',
			);
		}


$meta_fields = array(
    'fave_property_sf_id',
    'fave_property_price',
    'fave_price-per-point',
    'fave_property_sec_price',
    'fave_usage',
    'fave_unit-number',
    'fave_annual-maintenancef5d389f4853f0d',
    'fave_pointsf5da86e6a028dc',
    'fave_use-year',
    'fave_seller-notes',
    'fave_banked-points',
    'fave_kitchen',
    'fide_resort',
    'fave_property_address',
    'fave_property_bedrooms',
    'fave_property_bathrooms',
    'fave_weekf5d389f55a5279',
    'fave_season',
    'fave_special-season',
    'fave_unit-type',
    'fave_view',
    'fave_property_zip',
    'houzez_total_property_views',
    'houzez_recently_viewed',
);

				foreach ($meta_fields as $field) {
					if ($field === 'fave_annual-maintenancef5d389f4853f0d') {
						$key = 'annual-maintenance';
					} elseif ($field === 'fave_weekf5d389f55a5279') {
						$key = 'week';
					} elseif ($field === 'fave_pointsf5da86e6a028dc') {
						$key = 'points';
					} else {
						$key = (strpos($field, 'fave_property_') === 0) ? substr($field, 14) : ((strpos($field, 'fave_') === 0) ? substr($field, 5) : ((strpos($field, 'fide_') === 0) ? substr($field, 5) : ((strpos($field, 'houzez_') === 0) ? substr($field, 7) : $field)));


    }
    $value = get_post_meta(get_the_ID(), $field, true);



            if ($field == 'fide_resort') {
                $resort_post = get_post($value);
                if ($resort_post) {
                    $value = $resort_post->post_title;
                    $houzez_resort_sf_id_key = 'resort_sf_id';
                    $property_data[$houzez_resort_sf_id_key] = get_post_meta($resort_post->ID, 'houzez_resort_sf_id', true);

                    $fide_brand_id = get_post_meta($resort_post->ID, 'fide_brand', true);
                    $fide_brand_post = get_post($fide_brand_id);
                    if ($fide_brand_post) {

                        $property_data['fide_brand'] = $fide_brand_post->post_title;
                    } else {
                        $property_data['fide_brand'] = '';
                    }
                }
            }

            $property_data[$key] = $value;
        }

        $taxonomies = array(
            'property_status',
            'property_country',
            'property_state',
            'property_city',
        );

        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms(get_the_ID(), $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                $property_data[($taxonomy == 'property_status' ? 'listing_type' : $taxonomy)] = $terms[0]->name;
            } else {
                $property_data[($taxonomy == 'property_status' ? 'listing_type' : $taxonomy)] = 'For Sale';
            }
        }

        $properties_data['properties'][] = $property_data;
    }
}

wp_reset_postdata();


// Convert the data array to a JSON string with pretty-printing
$json = json_encode($properties_data, JSON_PRETTY_PRINT);

// Set the HTTP response headers and output the JSON string
header('Content-Type: application/json; charset=utf-8');
echo $json;

// Return an empty WP_REST_Response object
}