<?php
function custom_social_share_shortcode() {
    ob_start();
    ?>
    <div class="entry-header">
        <div class="row pt-5">
            <div class="offset-md-2 col-md-10 text-end">
                <?php $postURL = 'http' . ( isset( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";  ?>
                <div class="share-button-wrapper">
                    <a target="_blank" class="share-button share-twitter social-twitter" href="https://twitter.com/intent/tweet?url=<?php echo esc_url($postURL); ?>&text=<?php echo esc_attr(get_the_title()); ?>&via=<?php echo esc_attr(get_the_author_meta( 'twitter' )); ?>" title="Tweet"><em class="fab fa-twitter"></em> Tweet</a>
                    <a target="_blank" class="share-button share-facebook social-facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_url($postURL); ?>" title="Share on Facebook"><em class="fab fa-facebook-f"></em> Share</a>
                    <a target="_blank" class="share-button share-pinterest social-pinterest" href="http://pinterest.com/pin/create/link/?url=<?php echo esc_url($postURL); ?>"><em class="fab fa-pinterest-p"></em> Pin</a>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('social_share', 'custom_social_share_shortcode');

// Temporarily show hidden custom fields
add_filter('is_protected_meta', '__return_false', 999);

/**
 * Set up a background processing system for Events Calendar data backup
 */
// Add an admin page to trigger and monitor the backup
add_action('admin_menu', 'add_events_backup_page');

function add_events_backup_page() {
    add_submenu_page(
        'edit.php?post_type=tribe_events',
        'Backup Event Data',
        'Backup Event Data',
        'manage_options',
        'backup-events-data',
        'backup_events_page_callback'
    );
}

function backup_events_page_callback() {
    ?>
    <div class="wrap">
        <h1>Backup Events Calendar Data</h1>

        <?php
        // Handle starting a new backup
        if (isset($_POST['start_backup']) && check_admin_referer('backup_events_nonce')) {
            // Initialize backup process
            $events = get_posts(array(
                'post_type' => 'tribe_events',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post_status' => 'any'
            ));

            $total_events = count($events);

            if ($total_events > 0) {
                // Store event IDs in an option for processing
                update_option('tribe_events_backup_ids', $events);
                update_option('tribe_events_backup_total', $total_events);
                update_option('tribe_events_backup_processed', 0);
                update_option('tribe_events_backup_status', 'in_progress');

                echo '<div class="notice notice-info"><p>Backup initialized for ' . $total_events . ' events. Processing in batches...</p></div>';

                // Trigger the first batch
                wp_schedule_single_event(time(), 'process_events_backup_batch');
            } else {
                echo '<div class="notice notice-warning"><p>No events found to backup.</p></div>';
            }
        }

        // Check current status
        $backup_status = get_option('tribe_events_backup_status', 'not_started');
        $total_events = get_option('tribe_events_backup_total', 0);
        $processed_events = get_option('tribe_events_backup_processed', 0);

        if ($backup_status == 'in_progress') {
            $percent = $total_events > 0 ? floor(($processed_events / $total_events) * 100) : 0;
            ?>
            <div class="notice notice-info">
                <p>Backup in progress: <?php echo $processed_events; ?> out of <?php echo $total_events; ?> events processed (<?php echo $percent; ?>%)</p>
                <div style="height: 20px; width: 100%; background-color: 
#f0f0f0; margin-top: 10px;">
                    <div style="height: 100%; width: <?php echo $percent; ?>%; background-color: 
#0073aa;"></div>
                </div>
            </div>
            <p><em>This page will automatically refresh every 10 seconds to show progress.</em></p>
            <script>
                setTimeout(function() {
                    location.reload();
                }, 10000);
            </script>
            <?php
        } elseif ($backup_status == 'completed') {
            echo '<div class="notice notice-success"><p>Backup completed successfully! ' . $total_events . ' events have been backed up to custom fields.</p></div>';

            // Add option to reset
            ?>
            <form method="post">
                <?php wp_nonce_field('backup_events_nonce'); ?>
                <p>
                    <input type="submit" name="reset_backup" class="button" value="Reset Backup Status">
                </p>
            </form>
            <?php
        } elseif ($backup_status == 'failed') {
            echo '<div class="notice notice-error"><p>Backup process encountered errors. ' . $processed_events . ' out of ' . $total_events . ' events were processed.</p></div>';

            // Add option to resume or reset
            ?>
            <form method="post">
                <?php wp_nonce_field('backup_events_nonce'); ?>
                <p>
                    <input type="submit" name="resume_backup" class="button button-primary" value="Resume Backup">
                    <input type="submit" name="reset_backup" class="button" value="Reset Backup Status">
                </p>
            </form>
            <?php
        } else {
            // Not started or reset
            ?>
            <p>This tool will copy all your Events Calendar data to custom fields, ensuring you won't lose any data if the plugin is deactivated.</p>
            <p><strong>Note:</strong> With a large number of events (11,000+), this process will run in batches to avoid timeouts.</p>
            <form method="post">
                <?php wp_nonce_field('backup_events_nonce'); ?>
                <p>
                    <input type="submit" name="start_backup" class="button button-primary" value="Start Backup Process">
                </p>
            </form>
            <?php
        }

        // Handle reset request
        if (isset($_POST['reset_backup']) && check_admin_referer('backup_events_nonce')) {
            delete_option('tribe_events_backup_ids');
            delete_option('tribe_events_backup_total');
            delete_option('tribe_events_backup_processed');
            delete_option('tribe_events_backup_status');

            echo '<div class="notice notice-success"><p>Backup status has been reset.</p></div>';
            echo '<meta http-equiv="refresh" content="1;url=' . admin_url('edit.php?post_type=tribe_events&page=backup-events-data') . '" />';
        }

        // Handle resume request
        if (isset($_POST['resume_backup']) && check_admin_referer('backup_events_nonce')) {
            update_option('tribe_events_backup_status', 'in_progress');
            wp_schedule_single_event(time(), 'process_events_backup_batch');

            echo '<div class="notice notice-info"><p>Resuming backup process...</p></div>';
            echo '<meta http-equiv="refresh" content="1;url=' . admin_url('edit.php?post_type=tribe_events&page=backup-events-data') . '" />';
        }
        ?>
    </div>
    <?php
}

// Register the batch processing action
add_action('process_events_backup_batch', 'process_events_backup_batch_callback');

function process_events_backup_batch_callback() {
    // Get the queue of events to process
    $event_ids = get_option('tribe_events_backup_ids', array());
    $processed = get_option('tribe_events_backup_processed', 0);
    $batch_size = 100; // Process 100 events at a time - adjust based on your server capacity

    if (empty($event_ids)) {
        // No more events to process, mark as complete
        update_option('tribe_events_backup_status', 'completed');
        return;
    }

    // Take a batch from the queue
    $batch = array_slice($event_ids, 0, $batch_size);
    $remaining = array_slice($event_ids, $batch_size);

    // Update the queue
    update_option('tribe_events_backup_ids', $remaining);

    // Process this batch
    $success_count = 0;

    foreach ($batch as $event_id) {
        try {
            backup_single_event_data($event_id);
            $success_count++;
        } catch (Exception $e) {
            // Log error but continue with other events
            //error_log('Error backing up event #' . $event_id . ': ' . $e->getMessage());
        }
    }

    // Update progress
    $new_processed = $processed + $success_count;
    update_option('tribe_events_backup_processed', $new_processed);

    // If there are more events, schedule the next batch
    if (!empty($remaining)) {
        wp_schedule_single_event(time() + 1, 'process_events_backup_batch');
    } else {
        // All done!
        update_option('tribe_events_backup_status', 'completed');
    }
}

/**
 * Backup a single event's data
 */
function backup_single_event_data($event_id) {
    // Verify the post exists and is an event
    $post_type = get_post_type($event_id);
    if ($post_type !== 'tribe_events') {
        return false;
    }

    // Back up event date/time data
    $event_datetime = array(
        'start_date' => get_post_meta($event_id, '_EventStartDate', true),
        'end_date' => get_post_meta($event_id, '_EventEndDate', true),
        'timezone' => get_post_meta($event_id, '_EventTimezone', true),
        'all_day' => get_post_meta($event_id, '_EventAllDay', true),
        'recurrence' => get_post_meta($event_id, '_EventRecurrence', true)
    );
    update_post_meta($event_id, '_custom_event_datetime', $event_datetime);

    // Back up venue data
    $venue_id = get_post_meta($event_id, '_EventVenueID', true);
    if ($venue_id) {
        $venue_data = array(
            'venue_id' => $venue_id,
            'name' => get_the_title($venue_id),
            'address' => get_post_meta($venue_id, '_VenueAddress', true),
            'city' => get_post_meta($venue_id, '_VenueCity', true),
            'country' => get_post_meta($venue_id, '_VenueCountry', true),
            'state' => get_post_meta($venue_id, '_VenueState', true),
            'province' => get_post_meta($venue_id, '_VenueProvince', true),
            'zip' => get_post_meta($venue_id, '_VenueZip', true),
            'phone' => get_post_meta($venue_id, '_VenuePhone', true),
            'website' => get_post_meta($venue_id, '_VenueURL', true),
            'show_map' => get_post_meta($venue_id, '_VenueShowMap', true),
            'show_map_link' => get_post_meta($venue_id, '_VenueShowMapLink', true),
            'latitude' => get_post_meta($venue_id, '_VenueLat', true),
            'longitude' => get_post_meta($venue_id, '_VenueLng', true)
        );
        update_post_meta($event_id, '_custom_venue_data', $venue_data);
    }

    // Back up organizer data
    $organizer_ids = get_post_meta($event_id, '_EventOrganizerID', true);
    $organizer_data = array();

    if (!empty($organizer_ids)) {
        if (!is_array($organizer_ids)) {
            $organizer_ids = array($organizer_ids);
        }

        foreach ($organizer_ids as $organizer_id) {
            $organizer = array(
                'organizer_id' => $organizer_id,
                'name' => get_the_title($organizer_id),
                'phone' => get_post_meta($organizer_id, '_OrganizerPhone', true),
                'website' => get_post_meta($organizer_id, '_OrganizerWebsite', true),
                'email' => get_post_meta($organizer_id, '_OrganizerEmail', true)
            );

            $organizer_data[] = $organizer;
        }

        update_post_meta($event_id, '_custom_organizer_data', $organizer_data);
    }

    // Back up cost data
    $cost_data = array(
        'cost' => get_post_meta($event_id, '_EventCost', true),
        'cost_description' => get_post_meta($event_id, '_EventCostDescription', true),
        'currency_symbol' => get_post_meta($event_id, '_EventCurrencySymbol', true),
        'currency_position' => get_post_meta($event_id, '_EventCurrencyPosition', true)
    );
    update_post_meta($event_id, '_custom_event_cost', $cost_data);

    // Back up additional fields
    update_post_meta($event_id, '_custom_event_url', get_post_meta($event_id, '_EventURL', true));
    update_post_meta($event_id, '_custom_event_show_map', get_post_meta($event_id, '_EventShowMap', true));
    update_post_meta($event_id, '_custom_event_show_map_link', get_post_meta($event_id, '_EventShowMapLink', true));

    // Back up any Series data if available (Pro feature)
    $series_id = get_post_meta($event_id, '_tribe_event_series_id', true);
    if ($series_id) {
        update_post_meta($event_id, '_custom_event_series_id', $series_id);
        update_post_meta($event_id, '_custom_event_series_title', get_the_title($series_id));
    }

    // Back up taxonomy relationships
    backup_event_taxonomies_for_single_event($event_id);

    return true;
}

/**
 * Back up taxonomy relationships for a single event
 */
function backup_event_taxonomies_for_single_event($event_id) {
    // Get and store event categories
    $event_cats = wp_get_object_terms($event_id, 'tribe_events_cat');
    if (!empty($event_cats) && !is_wp_error($event_cats)) {
        $cat_data = array();
        foreach ($event_cats as $cat) {
            $cat_data[] = array(
                'term_id' => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug
            );
        }
        update_post_meta($event_id, '_custom_event_categories', $cat_data);
    }

    // Get and store tags
    $event_tags = wp_get_object_terms($event_id, 'post_tag');
    if (!empty($event_tags) && !is_wp_error($event_tags)) {
        $tag_data = array();
        foreach ($event_tags as $tag) {
            $tag_data[] = array(
                'term_id' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug
            );
        }
        update_post_meta($event_id, '_custom_event_tags', $tag_data);
    }

    // Save venue categories if they exist
    $venue_id = get_post_meta($event_id, '_EventVenueID', true);
    if ($venue_id) {
        $venue_cats = wp_get_object_terms($venue_id, 'tec_venue_category');
        if (!empty($venue_cats) && !is_wp_error($venue_cats)) {
            $venue_cat_data = array();
            foreach ($venue_cats as $cat) {
                $venue_cat_data[] = array(
                    'term_id' => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug
                );
            }
            update_post_meta($event_id, '_custom_venue_categories', $venue_cat_data);
        }
    }

    // Save organizer categories if they exist
    $organizer_ids = get_post_meta($event_id, '_EventOrganizerID', true);
    if (!empty($organizer_ids)) {
        if (!is_array($organizer_ids)) {
            $organizer_ids = array($organizer_ids);
        }

        $all_organizer_cats = array();

        foreach ($organizer_ids as $organizer_id) {
            $organizer_cats = wp_get_object_terms($organizer_id, 'tec_organizer_category');
            if (!empty($organizer_cats) && !is_wp_error($organizer_cats)) {
                $organizer_cat_data = array();
                foreach ($organizer_cats as $cat) {
                    $organizer_cat_data[] = array(
                        'organizer_id' => $organizer_id,
                        'term_id' => $cat->term_id,
                        'name' => $cat->name,
                        'slug' => $cat->slug
                    );
                }
                $all_organizer_cats = array_merge($all_organizer_cats, $organizer_cat_data);
            }
        }

        if (!empty($all_organizer_cats)) {
            update_post_meta($event_id, '_custom_organizer_categories', $all_organizer_cats);
        }
    }
}

// Add AJAX endpoint for checking backup status
add_action('wp_ajax_check_events_backup_status', 'ajax_check_events_backup_status');
function ajax_check_events_backup_status() {
    $response = array(
        'status' => get_option('tribe_events_backup_status', 'not_started'),
        'total' => get_option('tribe_events_backup_total', 0),
        'processed' => get_option('tribe_events_backup_processed', 0)
    );

    $response['percent'] = $response['total'] > 0 ? floor(($response['processed'] / $response['total']) * 100) : 0;

    wp_send_json($response);
}

/**
 * Access functions to get backed up data
 */
function get_event_custom_venue_data($event_id) {
    return get_post_meta($event_id, '_custom_venue_data', true);
}

function get_event_custom_organizer_data($event_id) {
    return get_post_meta($event_id, '_custom_organizer_data', true);
}

function get_event_custom_datetime_data($event_id) {
    return get_post_meta($event_id, '_custom_event_datetime', true);
}

function get_event_custom_cost_data($event_id) {
    return get_post_meta($event_id, '_custom_event_cost', true);
}

function get_event_custom_categories($event_id) {
    return get_post_meta($event_id, '_custom_event_categories', true);
}

function get_event_custom_tags($event_id) {
    return get_post_meta($event_id, '_custom_event_tags', true);
}

// Add WP-CLI command if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('events backup', 'cli_backup_events_data');
}

/**
 * WP-CLI command to backup events data
 */
function cli_backup_events_data($args, $assoc_args) {
    WP_CLI::line('Starting Events Calendar data backup...');

    $events = get_posts(array(
        'post_type' => 'tribe_events',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'post_status' => 'any'
    ));

    $total = count($events);

    if ($total === 0) {
        WP_CLI::error('No events found to backup.');
        return;
    }

    WP_CLI::line(sprintf('Found %d events to process.', $total));

    $progress = \WP_CLI\Utils\make_progress_bar('Backing up events', $total);
    $success_count = 0;

    foreach ($events as $event_id) {
        try {
            backup_single_event_data($event_id);
            $success_count++;
        } catch (Exception $e) {
            WP_CLI::warning(sprintf('Error backing up event #%d: %s', $event_id, $e->getMessage()));
        }

        $progress->tick();
    }

    $progress->finish();

    WP_CLI::success(sprintf('Backup completed. Successfully backed up %d out of %d events.', $success_count, $total));
}

// Support for custom event meta fields
function get_custom_event_meta($key, $post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    return get_post_meta($post_id, $key, true);
}

// Add custom event URL from meta
function get_custom_event_url($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    return get_post_meta($post_id, '_custom_event_url', true);
}

// Check if event should show map
function should_show_event_map($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    return get_post_meta($post_id, '_custom_event_show_map', true) === '1';
}

function get_event_start_date($post_id, $format = 'Y-m-d H:i:s') {
    $date = get_post_meta($post_id, '_EventStartDate', true);
    if ($date) {
        return date($format, strtotime($date));
    }
    return '';
}

function get_event_end_date($post_id, $format = 'Y-m-d H:i:s') {
    $date = get_post_meta($post_id, '_EventEndDate', true);
    if ($date) {
        return date($format, strtotime($date));
    }
    return '';
}

function get_event_venue($post_id) {
    $venue_id = get_post_meta($post_id, '_EventVenueID', true);
    if ($venue_id) {
        return get_the_title($venue_id);
    }
    return '';
}

function get_event_venue_address($post_id) {
    $venue_id = get_post_meta($post_id, '_EventVenueID', true);
    if ($venue_id) {
        $address = get_post_meta($venue_id, '_VenueAddress', true);
        $city = get_post_meta($venue_id, '_VenueCity', true);
        $state = get_post_meta($venue_id, '_VenueState', true);

        $full_address = array_filter(array($address, $city, $state));
        return implode(', ', $full_address);
    }
    return '';
}

function get_event_cost($post_id) {
    return get_post_meta($post_id, '_custom_event_cost', true);
}

function is_all_day_event($post_id) {
    return get_post_meta($post_id, '_EventAllDay', true) === 'yes';
}

/**
 * ===============================================================================
 * Event Category and Tag/Keyword Rewrites
 * ===============================================================================
 */

 // Register custom query vars with WordPress
function add_events_query_vars($vars) {
    $vars[] = 'tribe_events_cat';
    $vars[] = 'category';  // Pretty param for tribe_events_cat/category
    $vars[] = 'keyword';   // Pretty param for post_tag
    $vars[] = 'dayofweek';
    $vars[] = 'timeperiod'; 
    $vars[] = 'cost';
    $vars[] = 'organizer';
    $vars[] = 'country';
    $vars[] = 'state';
    $vars[] = 'city';
    $vars[] = 'address';
    $vars[] = 'start_date';
    $vars[] = 'end_date';
    $vars[] = 'search';
    $vars[] = 'featured';
    $vars[] = 'virtual';
    return $vars;
}
add_filter('query_vars', 'add_events_query_vars');

/**
 * Add custom rewrite rules for event taxonomy archives
 */
add_action('init', 'add_event_taxonomy_rewrite_rules');
function add_event_taxonomy_rewrite_rules() {
    // Event category archives: /events/category/business/
    add_rewrite_rule(
        '^events/category/([^/]+)/?$',
        'index.php?tribe_events_cat=$matches[1]&post_type=tribe_events',
        'top'
    );

    // Event tag archives: /events/keyword/networking/
    // CRITICAL FIX: Add post_type=tribe_events to the query
    add_rewrite_rule(
        '^events/keyword/([^/]+)/?$',
        'index.php?post_tag=$matches[1]&post_type=tribe_events',
        'top'
    );
    /*
    // Event tag archives: /events/tag/networking/
    add_rewrite_rule(
        '^events/tag/([^/]+)/?$',
        'index.php?post_tag=$matches[1]&post_type=tribe_events',
        'top'
    );
    */

    // CRITICAL: Only flush on theme activation, not every page load
    // Flush rewrite rules on activation
    //flush_rewrite_rules();
}

function flush_events_rewrite_rules() {
    flush_rewrite_rules();
}
// Call this only once after updating the rules:
// add_action('init', 'flush_events_rewrite_rules');

/**
 * 
 * Handles both pretty URLs and legacy parameter names
 * 
 */
function generate_event_date_archives($cpt, $wp_rewrite) {
    $rules = array();
    $post_type = get_post_type_object($cpt);
    $slug_archive = $post_type->has_archive;

    if ($slug_archive === false) return $rules;

    if ($slug_archive === true) {
        $slug_archive = isset($post_type->rewrite['slug']) ? $post_type->rewrite['slug'] : $post_type->name;
    }

    $dates = array(
        array(
            'rule' => "([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})",
            'vars' => array('year', 'monthnum', 'day')
        ),
        array(
            'rule' => "([0-9]{4})/([0-9]{1,2})",
            'vars' => array('year', 'monthnum')
        ),
        array(
            'rule' => "([0-9]{4})",
            'vars' => array('year')
        )
    );

    foreach ($dates as $data) {
        $query = 'index.php?post_type='.$cpt;
        $rule = $slug_archive.'/'.$data['rule'];
        $i = 1;

        foreach ($data['vars'] as $var) {
            $query .= '&'.$var.'='.$wp_rewrite->preg_index($i);
            $i++;
        }

        $rules[$rule."/?$"] = $query;
        $rules[$rule."/feed/(feed|rdf|rss|rss2|atom)/?$"] = $query."&feed=".$wp_rewrite->preg_index($i);
        $rules[$rule."/(feed|rdf|rss|rss2|atom)/?$"] = $query."&feed=".$wp_rewrite->preg_index($i);
        $rules[$rule."/page/([0-9]{1,})/?$"] = $query."&paged=".$wp_rewrite->preg_index($i);
    }

    return $rules;
}
add_action('generate_rewrite_rules', 'event_date_archives_rewrite_rules');

function event_date_archives_rewrite_rules($wp_rewrite) {
    $post_types = get_post_types(array('has_archive' => true), 'objects');

    foreach ($post_types as $post_type) {
        if ($post_type->name !== 'post') { // Skip default posts
            $rules = generate_event_date_archives($post_type->name, $wp_rewrite);
            $wp_rewrite->rules = $rules + $wp_rewrite->rules;
        }
    }

    return $wp_rewrite;
}

/*
 * ===============================================================================
 * Archive Template
 * ===============================================================================
 */

function is_event_taxonomy_archive() {
    global $wp_query;

    // Check URL pattern for event non-date taxonomy archives
    $current_url = $_SERVER['REQUEST_URI'];
    if (preg_match('/^\/events\/(category|keyword|tag)\/[^\/]+/', $current_url)) {
        return true;
    }

    return false;
}

// Force tribe_events_cat and post_tag archives to use archive-tribe_events.php
function force_tribe_events_archive_template($template) {
    global $wp_query;

    // Method 1: Direct tribe_events queries
    if (is_tax('tribe_events_cat') || is_post_type_archive('tribe_events') || 
        (is_tag() && get_query_var('post_type') === 'tribe_events')) {
        $new_template = locate_template(array('archive-tribe_events.php'));
        if (!empty($new_template)) {
            error_log('🎯 Using archive-tribe_events.php for direct tribe_events query');
            return $new_template;
        }
    }

    // Method 2: Check for events date archives by URL pattern
    $request_uri = $_SERVER['REQUEST_URI'];
    if (preg_match('#^/events/\d{4}(?:/\d{1,2}(?:/\d{1,2})?)?/?#', $request_uri)) {
        $new_template = locate_template(array('archive-tribe_events.php'));
        if (!empty($new_template)) {
            error_log('🎯 Using archive-tribe_events.php for events date archive: ' . $request_uri);
            return $new_template;
        }
    }

    // Method 3: Check for events taxonomy archives by URL pattern
    if (preg_match('#^/events/(category|keyword)/[^/]+/?#', $request_uri)) {
        $new_template = locate_template(array('archive-tribe_events.php'));
        if (!empty($new_template)) {
            error_log('🎯 Using archive-tribe_events.php for events taxonomy archive: ' . $request_uri);
            return $new_template;
        }
    }

    // Method 4: Check query flags we set in pre_get_posts
    if (isset($wp_query->query_vars['is_events_date_archive']) || 
        isset($wp_query->query_vars['is_events_taxonomy_archive'])) {
        $new_template = locate_template(array('archive-tribe_events.php'));
        if (!empty($new_template)) {
            error_log('🎯 Using archive-tribe_events.php based on query flags');
            return $new_template;
        }
    }

    return $template;
}
add_filter('template_include', 'force_tribe_events_archive_template', 5);

function force_tribe_events_context_for_taxonomy_archives($query) {
    // Only run on main query, not admin
    if (!$query->is_main_query() || is_admin()) {
        return;
    }

    $current_url = $_SERVER['REQUEST_URI'];

    // Check if we're on an events taxonomy archive
    if (preg_match('#^/events/(category|keyword)/([^/]+)/?$#', $current_url, $matches)) {
        $taxonomy_type = $matches[1];
        $term_slug = $matches[2];

        // Force post_type to tribe_events
        $query->set('post_type', 'tribe_events');

        // Set appropriate taxonomy query
        if ($taxonomy_type === 'category') {
            $query->set('tribe_events_cat', $term_slug);
        } elseif ($taxonomy_type === 'keyword') {
            $query->set('post_tag', $term_slug);
        }

        // Debug output for browser (visible)
        add_action('wp_footer', function() use ($taxonomy_type, $term_slug) {
            echo '<div style="position: fixed; bottom: 0; left: 0; background: #000; color: #0f0; padding: 10px; z-index: 9999; font-family: monospace; font-size: 12px;">';
            echo '<strong>🔧 FORCED TRIBE_EVENTS CONTEXT</strong><br>';
            echo 'URL Pattern: /events/' . $taxonomy_type . '/' . $term_slug . '/<br>';
            echo 'Post Type: tribe_events<br>';
            echo 'Taxonomy: ' . ($taxonomy_type === 'category' ? 'tribe_events_cat' : 'post_tag') . '<br>';
            echo 'Term Slug: ' . $term_slug;
            echo '</div>';
        });
    }
}
add_action('pre_get_posts', 'force_tribe_events_context_for_taxonomy_archives');

/**
 * CONTEXT CORRECTION for Date Archives
 * Your existing function only handles taxonomy archives, not date archives!
 */

function force_tribe_events_context_for_date_archives($query) {
    // Only run on main query, not admin
    if (!$query->is_main_query() || is_admin()) {
        return;
    }

    $current_url = $_SERVER['REQUEST_URI'];

    // Handle DATE archives with parameters (like your URL)
    if (preg_match('#^/events/\d{4}(?:/\d{1,2}(?:/\d{1,2})?)?/?#', $current_url)) {
        $old_post_type = $query->get('post_type') ?: 'not_set';
        
        // Force post_type to tribe_events
        $query->set('post_type', 'tribe_events');
        $query->set('post_status', array('publish', 'future'));
        
        error_log("📅 DATE ARCHIVE CONTEXT CORRECTION: Changed post_type from '{$old_post_type}' to 'tribe_events' for URL: {$current_url}");
        
        // Set flag for debugging
        $query->set('_date_archive_corrected', true);
    }
    
    // Your existing taxonomy archive handling
    if (preg_match('#^/events/(category|keyword)/([^/]+)/?$#', $current_url, $matches)) {
        $taxonomy_type = $matches[1];
        $term_slug = $matches[2];

        $query->set('post_type', 'tribe_events');
        $query->set('post_status', array('publish', 'future'));

        if ($taxonomy_type === 'category') {
            $query->set('tribe_events_cat', $term_slug);
        } elseif ($taxonomy_type === 'keyword') {
            $query->set('post_tag', $term_slug);
        }
        
        error_log("🏷️ TAXONOMY ARCHIVE CONTEXT CORRECTION: Set tribe_events context for {$taxonomy_type}/{$term_slug}");
    }
}

// Replace your existing context correction with enhanced version
remove_action('pre_get_posts', 'force_tribe_events_context_for_taxonomy_archives');
add_action('pre_get_posts', 'force_tribe_events_context_for_date_archives', 5);


/**
 * BACKWARDS COMPATIBLE: Archive template helper
 * For use in your archive template
 */
function get_universal_events_for_archive() {
    // Use the universal query builder with backwards compatibility
    //$query_args = build_filtered_query_args('tribe_events', [], $_SERVER['REQUEST_URI']);
    $query_args = build_filtered_query_args();
    
    // Run the query
    $events_query = new WP_Query($query_args);
    
    return $events_query;
}

// Simplified URL context detection
function is_events_archive_url($url = null) {
    if (!$url) {
        $url = $_SERVER['REQUEST_URI'];
    }
    
    // Match any URL that starts with /events/
    return (strpos($url, '/events/') === 0);
}

// Simplified URL building
function build_events_url_with_filters($base_path = '/events/', $filters = []) {
    $url = home_url($base_path);
    
    if (!empty($filters)) {
        $url .= '?' . http_build_query($filters);
    }
    
    return $url;
}


/**
 * ===============================================================================
 * Event Archive Context
 * ===============================================================================
 */

function get_current_page_url($current_page_url = null) {
    $url = null;

    // Priority 1: If we have the current_page_url parameter ajax sends, use it
    if (!empty($current_page_url)) {
        $url = $current_page_url;
    }

    // Priority 2: If we're on admin-ajax.php, use HTTP referrer as backup
    elseif ((defined('DOING_AJAX') && DOING_AJAX && !empty($_SERVER['HTTP_REFERER'])) || strpos(esc_url_raw($_SERVER['REQUEST_URI']), 'admin-ajax.php') !== false && !empty($_SERVER['HTTP_REFERER'])) {
        $url = $_SERVER['HTTP_REFERER'];
    }

    // Priority 3: Regular REQUEST_URI
    else {
        $url = esc_url_raw($_SERVER['REQUEST_URI']);
    }

    // Extract path from full URL if it contains a domain
    if (strpos($url, 'http') === 0) {
        $url = parse_url($url, PHP_URL_PATH);
    }

    // Remove query string and trailing slash for consistency
    $url = strtok($url, '?');
    $url = rtrim($url, '/');

    return $url;

}

/**
 * UPDATED: Detect if we're on a DATE archive (explicitly date-focused)
 * Only returns true for pure date URLs like /events/2024/12/
 * Does NOT return true for /events/category/business/
 */

function is_custom_post_type_date_archive_by_url($custom_url = null) {
    //$current_url = $custom_url ? $custom_url : $_SERVER['REQUEST_URI'];

    // Initialize $current_url properly
    if(empty($custom_url)) {
        $current_url = get_current_page_url();
    } else {
        $current_url = $custom_url; // This line was missing
    }
    
    // Extract path from full URL if it contains a domain
    if (strpos($current_url, 'http') === 0) {
        $current_url = parse_url($current_url, PHP_URL_PATH);
    }

    // Remove query string and trailing slash for consistency
    $current_url = strtok($current_url, '?');
    $current_url = rtrim($current_url, '/');
    

    //error_log('🔍 Date Archive Detection - Input URL: ' . $current_url);

    // CRITICAL: Only match pure date patterns, exclude taxonomy patterns
    // Matches: /events/2024, /events/2024/12, /events/2024/12/09
    // Does NOT match: /events/category/business, /events/tag/networking
    $pattern = '/^\/events\/(\d{4})(?:\/(\d{1,2})(?:\/(\d{1,2}))?)?$/';

    if (preg_match($pattern, $current_url, $matches)) {
        $year = $matches[1];
        $month = isset($matches[2]) && $matches[2] !== '' ? $matches[2] : null;
        $day = isset($matches[3]) && $matches[3] !== '' ? $matches[3] : null;

        // Determine archive type
        $type = 'yearly';
        if ($month) {
            $type = 'monthly';
            if ($day) {
                $type = 'daily';
            }
        }

        // Ensure month is zero-padded
        if ($month) {
            $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        }
        if ($day) {
            $day = str_pad($day, 2, '0', STR_PAD_LEFT);
        }

        $result = array(
            'post_type' => 'tribe_events',
            'slug' => 'events',
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'type' => $type,
            'context' => 'date' // EXPLICIT CONTEXT
        );

        //error_log('✅ Date Archive Detected: ' . print_r($result, true));
        return $result;
    }

    //error_log('❌ No Date Archive Pattern Match');
    return false;
}
// Helper function to check if we're on any custom post type date archive
function is_on_custom_post_type_date_archive() {
    return is_custom_post_type_date_archive_by_url() !== false;
}
// Get current archive post type from URL
function get_current_archive_post_type_by_url() {
    $archive_info = is_custom_post_type_date_archive_by_url();
    return $archive_info ? $archive_info['post_type'] : 'post';
}

/**
 * NEW: Detect if we're on a TAXONOMY archive (explicitly taxonomy-focused)
 * Only returns true for taxonomy URLs like /events/category/business/
 * Does NOT return true for /events/2024/12/
 */
function is_custom_post_type_taxonomy_archive_by_url($custom_url = null) {
    //$current_url = $custom_url ? $custom_url : $_SERVER['REQUEST_URI'];

    //$current_url = $custom_url ? $custom_url : $_SERVER['REQUEST_URI'];

    if(empty($custom_url)) {
        $current_url = get_current_page_url();
    }

    /*
    // Extract path from full URL if it contains a domain
    if ($custom_url && strpos($current_url, 'http') === 0) {
        $current_url = parse_url($current_url, PHP_URL_PATH);
    }

    // Remove query string and trailing slash for consistency
    $current_url = strtok($current_url, '?');
    $current_url = rtrim($current_url, '/');
    */

    //error_log('🏷️ Taxonomy Archive Detection - Input URL: ' . $current_url);

    // CRITICAL: Only match taxonomy patterns, exclude date patterns
    // Matches: /events/category/business, /events/tag/networking
    // Does NOT match: /events/2024, /events/2024/12
    $pattern = '/^\/events\/(category|tag|keyword)\/([^\/]+)$/';

    if (preg_match($pattern, $current_url, $matches)) {
        $taxonomy_type = $matches[1]; // 'category', 'tag', or 'keyword'
        $term_slug = $matches[2];

        // Map URL segment to actual taxonomy
        $taxonomy_map = array(
            'category' => 'tribe_events_cat',
            'tag' => 'post_tag',
            'keyword' => 'post_tag'
        );

        $taxonomy = isset($taxonomy_map[$taxonomy_type]) ? $taxonomy_map[$taxonomy_type] : $taxonomy_type;

        // Get term details
        $term = get_term_by('slug', $term_slug, $taxonomy);
        $term_id = $term ? $term->term_id : null;
        $term_name = $term ? $term->name : $term_slug;

        $result = array(
            'post_type' => 'tribe_events',
            'taxonomy' => $taxonomy,
            'taxonomy_type' => $taxonomy_type,
            'term_slug' => $term_slug,
            'term_id' => $term_id,
            'term_name' => $term_name,
            'context' => 'taxonomy' // EXPLICIT CONTEXT
        );

        //error_log('✅ Taxonomy Archive Detected: ' . print_r($result, true));
        return $result;
    }

    //error_log('❌ No Taxonomy Archive Pattern Match');
    return false;
}

/**
 * MASTER FUNCTION: Get current archive context (date OR taxonomy, never both)
 */
function get_current_archive_context() {
    // Try date archive detection first
    $date_archive = is_custom_post_type_date_archive_by_url();
    if ($date_archive) {
        return $date_archive;
    }

    // Try taxonomy archive detection
    $taxonomy_archive = is_custom_post_type_taxonomy_archive_by_url();
    if ($taxonomy_archive) {
        // ADD: Extract term ID for filter building
        $taxonomy_archive['filter_params'] = array(
            $taxonomy_archive['taxonomy'] => array($taxonomy_archive['term_id'])
        );
        return $taxonomy_archive;
    }

    // Fallback: base events archive
    return array(
        'context' => 'base',
        'post_type' => 'tribe_events'
    );
}

/**
 * ===============================================================================
 * Event Filtering
 * ===============================================================================
 */

/**
 * Helper: Parses taxonomy filters from URL query parameters.
 * Handles both pretty URL parameters ('category', 'keyword')
 * and legacy parameters ('tribe_events_cat', 'post_tag').
 *
 * @return array Processed taxonomy filters.
 */
//Helper function to get taxonomy filters from URL parameters
function _get_taxonomy_filters_from_url() {
    $filters = [];

    // Check for 'category' parameter 
    if (!empty($_GET['category'])) {
        $category_values = is_array($_GET['category']) ? $_GET['category'] : explode(',', $_GET['category']);
        // By default, map 'category' to 'tribe_events_cat' for event-related contexts
        $filters['tribe_events_cat'] = array_map('intval', $category_values);
        //error_log('🔍 URL parameter "category" filter (mapped to tribe_events_cat): ', $filters['tribe_events_cat']);
    }

    // Check for 'keyword' parameter 
    if (!empty($_GET['keyword'])) {
        $keyword_values = is_array($_GET['keyword']) ? $_GET['keyword'] : explode(',', $_GET['keyword']);
        // Map 'keyword' to 'post_tag'
        $filters['post_tag'] = array_map('intval', $keyword_values);
        //error_log('🔍 URL parameter "keyword" filter (mapped to post_tag): ', $filters['post_tag']);
    }

    // Legacy parameter 'tribe_events_cat'
    if (!empty($_GET['tribe_events_cat'])) {
        $tribe_cat_values = is_array($_GET['tribe_events_cat']) ? $_GET['tribe_events_cat'] : explode(',', $_GET['tribe_events_cat']);
        $filters['tribe_events_cat'] = array_map('intval', $tribe_cat_values);
        //error_log('🔍 URL parameter legacy "tribe_events_cat" filter: ', $filters['tribe_events_cat']);
    }

    // Legacy parameter 'post_tag'
    if (!empty($_GET['post_tag'])) {
        $tag_values = is_array($_GET['post_tag']) ? $_GET['post_tag'] : explode(',', $_GET['post_tag']);
        $filters['post_tag'] = array_map('intval', $tag_values);
        //error_log('🔍 URL parameter legacy "post_tag" filter: ', $filters['post_tag']);
    }

    // Special case: If 'category' URL parameter exists BUT the current post type
    // is NOT 'tribe_events', then map 'category' to the standard 'category' taxonomy
    if (!empty($_GET['category']) && get_query_var('post_type') !== 'tribe_events') {
        $category_values = is_array($_GET['category']) ? $_GET['category'] : explode(',', $_GET['category']);
        $filters['category'] = array_map('intval', $category_values);
        // Remove 'tribe_events_cat' mapping since this is for regular posts
        unset($filters['tribe_events_cat']);
        //error_log('🔍 URL parameter general post "category" filter (mapped to standard category): ', $filters['category']);
    }

    return $filters;
}

//Helper function to get day-of-week filters from URL parameters
function _get_dayofweek_filters_from_url() {
    $filters = [];

    // Day of week filter with validation
    if (!empty($_GET['dayofweek'])) {
        try {
            $days = is_array($_GET['dayofweek']) ? $_GET['dayofweek'] : explode(',', $_GET['dayofweek']);
            $valid_days = array_filter($days, function($day) {
                return is_numeric($day) && $day >= 1 && $day <= 7;
            });
            if (!empty($valid_days)) {
                $filters['dayofweek'] = array_map('intval', $valid_days);
            }
        } catch (Exception $e) {
            //error_log("Day of week filter error: " . $e->getMessage());
        }
    }

    return $filters;
}

//Helper function to get time period filters from URL parameters  
function _get_timeperiod_filters_from_url() {
    $filters = [];

    // Time period filter with validation
    if (!empty($_GET['timeperiod'])) {
        try {
            $periods = is_array($_GET['timeperiod']) ? $_GET['timeperiod'] : explode(',', $_GET['timeperiod']);
            $valid_periods = array('allday', 'morning', 'afternoon', 'evening', 'night');
            $filtered_periods = array_filter($periods, function($period) use ($valid_periods) {
                return in_array($period, $valid_periods);
            });
            if (!empty($filtered_periods)) {
                $filters['timeperiod'] = $filtered_periods;
            }
        } catch (Exception $e) {
            //error_log("Time period filter error: " . $e->getMessage());
        }
    }

    return $filters;
}


function _get_cost_filters_from_url() {
    $filters = [];
    
    error_log("=== COST FILTER DEBUG START ===");
    error_log("1. Raw URL cost parameter: " . ($_GET['cost'] ?? 'NOT SET'));

    // Get the highest possible event cost once
    global $wpdb;
    $highest_event_cost = $wpdb->get_var("SELECT MAX(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} WHERE meta_key = '_EventCost'");
    $default_max_cost = ($highest_event_cost !== null && $highest_event_cost !== '') ? floatval($highest_event_cost) : 10000;
    
    error_log("2. Default max cost from DB: " . $default_max_cost);

    if (!empty($_GET['cost'])) {
        try {
            $cost_value = sanitize_text_field($_GET['cost']);
            error_log("3. Sanitized cost value: " . $cost_value);
            
            if (strpos($cost_value, ',') !== false) {
                // Range format: "min,max"
                $cost_range = explode(',', $cost_value);
                error_log("4. Exploded cost range: " . print_r($cost_range, true));
                
                if (count($cost_range) === 2) {
                    $min = is_numeric($cost_range[0]) ? floatval($cost_range[0]) : 0;
                    $max = is_numeric($cost_range[1]) ? floatval($cost_range[1]) : $default_max_cost;
                    
                    error_log("5. Parsed min: " . $min . ", max: " . $max);
                    
                    $filters['cost_range'] = array('min' => $min, 'max' => $max);
                    
                    error_log("6. Final cost_range filter set: " . print_r($filters['cost_range'], true));
                } else {
                    error_log("4. ERROR: Malformed range, not exactly 2 parts");
                    $filters['cost_range'] = array('min' => 0, 'max' => $default_max_cost);
                }
            } elseif (is_numeric($cost_value)) {
                // Single value format: treat as max cost
                $filters['cost_range'] = array('min' => 0, 'max' => floatval($cost_value));
                error_log("4. Single value format - min: 0, max: " . floatval($cost_value));
            } else {
                error_log("4. ERROR: Invalid format, using defaults");
                $filters['cost_range'] = array('min' => 0, 'max' => $default_max_cost);
            }
        } catch (Exception $e) {
            error_log("3. EXCEPTION: " . $e->getMessage());
            $filters['cost_range'] = array('min' => 0, 'max' => $default_max_cost);
        }
    } else {
        error_log("3. No cost parameter in URL");
    }

    error_log("=== COST FILTER DEBUG END ===");
    return $filters;
}

//Helper function to get organizer filters from URL parameters 
/* 
function _get_organizer_filters_from_url() {
    $filters = [];
    
    error_log("=== ORGANIZER FILTER DEBUG START ===");
    error_log("1. Raw URL organizer parameter: " . ($_GET['organizer'] ?? 'NOT SET'));

    if (!empty($_GET['organizer'])) {
        try {
            $organizer_value = sanitize_text_field($_GET['organizer']);
            error_log("2. Sanitized organizer value: " . $organizer_value);
            
            if (strpos($organizer_value, ',') !== false) {
                // Multiple organizer IDs: "123,456,789"
                $organizer_ids = explode(',', $organizer_value);
                $organizer_ids = array_map('intval', $organizer_ids);
                $organizer_ids = array_filter($organizer_ids); // Remove zeros
                
                error_log("3. Multiple organizer IDs: " . print_r($organizer_ids, true));
                $filters['organizer'] = $organizer_ids;
            } else {
                // Single organizer ID
                $organizer_id = intval($organizer_value);
                if ($organizer_id > 0) {
                    error_log("3. Single organizer ID: " . $organizer_id);
                    $filters['organizer'] = [$organizer_id];
                }
            }
            
            if (!empty($filters['organizer'])) {
                error_log("4. Final organizer filter set: " . print_r($filters['organizer'], true));
            }
        } catch (Exception $e) {
            error_log("2. EXCEPTION: " . $e->getMessage());
        }
    }

    error_log("=== ORGANIZER FILTER DEBUG END ===");
    return $filters;
}*/

//Helper function to get organizer filters from URL parameters  
function _get_organizer_filters_from_url() {
    $filters = [];
    
    if (!empty($_GET['organizer'])) {
        try {
            $organizer_value = sanitize_text_field($_GET['organizer']);
            
            if (strpos($organizer_value, ',') !== false) {
                // Multiple organizer values: "123,sample-organizer,456"
                $organizer_values = explode(',', $organizer_value);
                $clean_values = array_filter(array_map('sanitize_text_field', $organizer_values));
                $filters['organizer'] = $clean_values; // Keep as mixed array
            } else {
                // Single organizer value (could be ID or text)
                $filters['organizer'] = [$organizer_value];
            }
            
        } catch (Exception $e) {
            error_log("Error processing organizer parameter: " . $e->getMessage());
        }
    }

    return $filters;
}

//Helper function to get venue filters from URL parameters  
function _get_venue_filters_from_url() {
    $filters = [];
    
    error_log("=== VENUE FILTER DEBUG START ===");
    error_log("1. Raw URL venue parameter: " . ($_GET['venue'] ?? 'NOT SET'));

    if (!empty($_GET['venue'])) {
        try {
            $venue_value = sanitize_text_field($_GET['venue']);
            error_log("2. Sanitized venue value: " . $venue_value);
            
            if (strpos($venue_value, ',') !== false) {
                // Multiple venue IDs: "84641,98647,92092"
                $venue_ids = explode(',', $venue_value);
                $venue_ids = array_map('intval', $venue_ids);
                $venue_ids = array_filter($venue_ids); // Remove zeros
                
                error_log("3. Multiple venue IDs: " . print_r($venue_ids, true));
                $filters['venue'] = $venue_ids;
            } else {
                // Single venue ID
                $venue_id = intval($venue_value);
                if ($venue_id > 0) {
                    error_log("3. Single venue ID: " . $venue_id);
                    $filters['venue'] = [$venue_id];
                }
            }
            
            if (!empty($filters['venue'])) {
                error_log("4. Final venue filter set: " . print_r($filters['venue'], true));
            }
        } catch (Exception $e) {
            error_log("2. EXCEPTION: " . $e->getMessage());
        }
    }

    error_log("=== VENUE FILTER DEBUG END ===");
    return $filters;
}

function _get_venue_location_filters_from_url() {
    $filters = [];
    
    error_log("=== VENUE LOCATION FILTER DEBUG START ===");
    
    // Use existing parameter names that match JavaScript
    $venue_filter_map = [
        'country' => 'venue_country',
        'state' => 'venue_state', 
        'city' => 'venue_city',
        'address' => 'venue_address'
    ];
    
    foreach ($venue_filter_map as $url_param => $filter_key) {
        error_log("Checking URL parameter: " . $url_param . " = " . ($_GET[$url_param] ?? 'NOT SET'));
        
        if (!empty($_GET[$url_param])) {
            try {
                $param_value = sanitize_text_field($_GET[$url_param]);
                $values = strpos($param_value, ',') !== false ? explode(',', $param_value) : [$param_value];
                $clean_values = array_filter(array_map('sanitize_text_field', $values));
                
                if (!empty($clean_values)) {
                    // Apply normalization using existing functions
                    if ($url_param === 'state') {
                        $clean_values = array_map('normalize_venue_state', $clean_values);
                    } elseif ($url_param === 'country') {
                        $clean_values = array_map('normalize_venue_country', $clean_values);
                    }
                    
                    $filters[$filter_key] = $clean_values;
                    error_log("Set {$filter_key} filter: " . print_r($clean_values, true));
                }
            } catch (Exception $e) {
                error_log("Error processing {$url_param}: " . $e->getMessage());
            }
        }
    }
    
    error_log("=== VENUE LOCATION FILTER DEBUG END ===");
    return $filters;
}

function tribe_events_venue_country_posts_where_filter($where, $query) {
    global $wpdb;
    
    if (!isset($query->query_vars['_custom_venue_country_filter'])) {
        return $where;
    }
    
    $country_filter = $query->query_vars['_custom_venue_country_filter'];
    
    if (!is_array($country_filter) || empty($country_filter)) {
        return $where;
    }
    
    $placeholders = implode(',', array_fill(0, count($country_filter), '%s'));
    
    $venue_where = $wpdb->prepare(" AND EXISTS (
        SELECT 1 FROM {$wpdb->postmeta} pm_venue
        INNER JOIN {$wpdb->postmeta} pm_country ON pm_venue.meta_value = pm_country.post_id
        WHERE pm_venue.post_id = {$wpdb->posts}.ID
        AND pm_venue.meta_key = '_EventVenueID'
        AND pm_country.meta_key = '_VenueCountry'
        AND pm_country.meta_value IN ({$placeholders})
    )", ...$country_filter);
    
    $where .= $venue_where;
    return $where;
}
add_filter('posts_where', 'tribe_events_venue_country_posts_where_filter', 10, 2);

function tribe_events_venue_state_posts_where_filter($where, $query) {
    global $wpdb;
    
    if (!isset($query->query_vars['_custom_venue_state_filter'])) {
        return $where;
    }
    
    $state_filter = $query->query_vars['_custom_venue_state_filter'];
    
    if (!is_array($state_filter) || empty($state_filter)) {
        return $where;
    }
    
    $placeholders = implode(',', array_fill(0, count($state_filter), '%s'));
    
    $venue_where = $wpdb->prepare(" AND EXISTS (
        SELECT 1 FROM {$wpdb->postmeta} pm_venue
        INNER JOIN {$wpdb->postmeta} pm_state ON pm_venue.meta_value = pm_state.post_id
        WHERE pm_venue.post_id = {$wpdb->posts}.ID
        AND pm_venue.meta_key = '_EventVenueID'
        AND pm_state.meta_key = '_VenueState'
        AND pm_state.meta_value IN ({$placeholders})
    )", ...$state_filter);
    
    $where .= $venue_where;
    return $where;
}
add_filter('posts_where', 'tribe_events_venue_state_posts_where_filter', 10, 2);

function tribe_events_venue_city_posts_where_filter($where, $query) {
    global $wpdb;
    
    if (!isset($query->query_vars['_custom_venue_city_filter'])) {
        return $where;
    }
    
    $city_filter = $query->query_vars['_custom_venue_city_filter'];
    
    if (!is_array($city_filter) || empty($city_filter)) {
        return $where;
    }
    
    $placeholders = implode(',', array_fill(0, count($city_filter), '%s'));
    
    $venue_where = $wpdb->prepare(" AND EXISTS (
        SELECT 1 FROM {$wpdb->postmeta} pm_venue
        INNER JOIN {$wpdb->postmeta} pm_city ON pm_venue.meta_value = pm_city.post_id
        WHERE pm_venue.post_id = {$wpdb->posts}.ID
        AND pm_venue.meta_key = '_EventVenueID'
        AND pm_city.meta_key = '_VenueCity'
        AND pm_city.meta_value IN ({$placeholders})
    )", ...$city_filter);
    
    $where .= $venue_where;
    return $where;
}
add_filter('posts_where', 'tribe_events_venue_city_posts_where_filter', 10, 2);

function tribe_events_venue_address_posts_where_filter($where, $query) {
    global $wpdb;
    
    if (!isset($query->query_vars['_custom_venue_address_filter'])) {
        return $where;
    }
    
    $address_filter = $query->query_vars['_custom_venue_address_filter'];
    
    if (!is_array($address_filter) || empty($address_filter)) {
        return $where;
    }
    
    // Address filter uses venue IDs, not address strings
    $placeholders = implode(',', array_fill(0, count($address_filter), '%d'));
    
    $venue_where = $wpdb->prepare(" AND EXISTS (
        SELECT 1 FROM {$wpdb->postmeta} pm_venue
        WHERE pm_venue.post_id = {$wpdb->posts}.ID
        AND pm_venue.meta_key = '_EventVenueID'
        AND pm_venue.meta_value IN ({$placeholders})
    )", ...$address_filter);
    
    $where .= $venue_where;
    return $where;
}
add_filter('posts_where', 'tribe_events_venue_address_posts_where_filter', 10, 2);


/**
 * MASTER FUNCTION: Get all active taxonomy filters based on priority.
 * This function determines what categories/tags are currently being filtered.
 *
 * @param array $ajax_data Optional AJAX data containing filters.
 * @return array An array of active taxonomy filters, e.g., ['tribe_events_cat' => [1, 2], 'post_tag' => [10]].
 */

/**
 * @deprecated Use get_active_filters() instead
 * This function only checks $_GET and doesn't handle AJAX context properly
*/
function get_current_filters() {
    return get_active_filters();
}


/**
 * BACKWARDS COMPATIBLE: get_active_filters()
 * Maintains original signature: get_active_filters($ajax_data = array())
 */
function get_active_filters($ajax_data = array()) {
    // If AJAX data is provided, process it first (for backwards compatibility)
    if (!empty($ajax_data)) {
        return _get_filters_from_ajax_data($ajax_data);
    }
    
    // Otherwise use the new universal system
    return get_active_filters_universal();
}

/**
 * NEW UNIVERSAL FUNCTION: get_active_filters_universal()
 * This is the new system that always checks for all filter types
 */
function get_active_filters_universal() {
    $filters = [];

    // Get ALL filter types from URL parameters, regardless of context
    
    // Search filter
    if (!empty($_GET['search']) || !empty($_GET['s'])) {
        $search_term = !empty($_GET['search']) ? $_GET['search'] : $_GET['s'];
        $filters['search'] = sanitize_text_field($search_term);
    }
    // Taxonomy filters (categories/tags)
    if (!empty($_GET['category'])) {
        $category_values = is_array($_GET['category']) ? $_GET['category'] : explode(',', $_GET['category']);
        $filters['tribe_events_cat'] = array_map('intval', $category_values);
    }

    if (!empty($_GET['keyword'])) {
        $keyword_values = is_array($_GET['keyword']) ? $_GET['keyword'] : explode(',', $_GET['keyword']);
        $filters['post_tag'] = array_map('intval', $keyword_values);
    }

    // Day of week filter - ALWAYS available on any /events/ URL
    if (!empty($_GET['dayofweek'])) {
        $days = is_array($_GET['dayofweek']) ? $_GET['dayofweek'] : explode(',', $_GET['dayofweek']);
        $valid_days = array_filter($days, function($day) {
            return is_numeric($day) && $day >= 1 && $day <= 7;
        });
        if (!empty($valid_days)) {
            $filters['dayofweek'] = array_map('intval', $valid_days);
        }
    }

    // Time period filter - ALWAYS available on any /events/ URL
    if (!empty($_GET['timeperiod'])) {
        $periods = is_array($_GET['timeperiod']) ? $_GET['timeperiod'] : explode(',', $_GET['timeperiod']);
        $valid_periods = array('allday', 'morning', 'afternoon', 'evening', 'night');
        $filtered_periods = array_filter($periods, function($period) use ($valid_periods) {
            return in_array($period, $valid_periods);
        });
        if (!empty($filtered_periods)) {
            $filters['timeperiod'] = $filtered_periods;
        }
    }

    // Cost filter - ALWAYS available on any /events/ URL
    if (!empty($_GET['cost'])) {
        $cost_value = sanitize_text_field($_GET['cost']);
        if (strpos($cost_value, ',') !== false) {
            $cost_range = explode(',', $cost_value);
            if (count($cost_range) === 2) {
                $min = is_numeric($cost_range[0]) ? floatval($cost_range[0]) : 0;
                $max = is_numeric($cost_range[1]) ? floatval($cost_range[1]) : 10000;
                $filters['cost_range'] = array('min' => $min, 'max' => $max);
            }
        } elseif (is_numeric($cost_value)) {
            $filters['cost_range'] = array('min' => 0, 'max' => floatval($cost_value));
        }
    }

    // Venue filters - ALWAYS available on any /events/ URL
    $venue_filter_map = [
        'country' => 'venue_country',
        'state' => 'venue_state', 
        'city' => 'venue_city',
        'address' => 'venue_address'
    ];
    
    foreach ($venue_filter_map as $url_param => $filter_key) {
        if (!empty($_GET[$url_param])) {
            $param_value = sanitize_text_field($_GET[$url_param]);
            $values = strpos($param_value, ',') !== false ? explode(',', $param_value) : [$param_value];
            $clean_values = array_filter(array_map('sanitize_text_field', $values));
            
            if (!empty($clean_values)) {
                $filters[$filter_key] = $clean_values;
            }
        }
    }

    // Organizer filter - ALWAYS available on any /events/ URL
    if (!empty($_GET['organizer'])) {
        $organizer_value = sanitize_text_field($_GET['organizer']);
        if (strpos($organizer_value, ',') !== false) {
            $organizer_values = explode(',', $organizer_value);
            $clean_values = array_filter(array_map('sanitize_text_field', $organizer_values));
            $filters['organizer'] = $clean_values;
        } else {
            $filters['organizer'] = [$organizer_value];
        }
    }

    // Featured events filter
    if (!empty($_GET['featured']) && $_GET['featured'] == '1') {
        $filters['featured'] = '1';
    }
    
    // Virtual events filter  
    if (!empty($_GET['virtual']) && $_GET['virtual'] == '1') {
        $filters['virtual'] = '1';
    }

    return $filters;
}

/**
 * ===============================================================================
 * AJAX Handler for filtering posts
 * ===============================================================================
 */

/**
 * Fetches an indexed list of country names from a remote Gist.
 * This function handles fetching and parsing the plain text list.
 *
 * @return array An indexed array of country names (e.g., ['Afghanistan', 'Albania', ...]).
 */

function get_country_mapping() {
    //https://github.com/leadstartorg/country-list
    return array(
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua And Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia And Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo, The Democratic Republic Of The',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'TP' => 'East Timor',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands (malvinas)',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island And Mcdonald Islands',
        'VA' => 'Holy See (vatican City State)',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran, Islamic Republic Of',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'Korea, Democratic People\'s Republic Of',
        'KR' => 'Korea, Republic Of',
        'KV' => 'Kosovo',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People\'s Democratic Republic',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
        'MK' => 'Macedonia, The Former Yugoslav Republic Of',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia, Federated States Of',
        'MD' => 'Moldova, Republic Of',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'MS' => 'Montserrat',
        'ME' => 'Montenegro',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'AN' => 'Netherlands Antilles',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestinian Territory, Occupied',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts And Nevis',
        'LC' => 'Saint Lucia',
        'PM' => 'Saint Pierre And Miquelon',
        'VC' => 'Saint Vincent And The Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome And Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia And The South Sandwich Islands',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard And Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan, Province Of China',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania, United Republic Of',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad And Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Viet Nam',
        'VG' => 'Virgin Islands, British',
        'VI' => 'Virgin Islands, U.s.',
        'WF' => 'Wallis And Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe'
    );
}

function get_countries_list() {
    $url = 'https://gist.githubusercontent.com/kalinchernev/486393efcca01623b18d/raw/daa24c9fea66afb7d68f8d69f0c4b8eeb9406e83/countries';
    
    // Use WordPress's HTTP API for fetching remote content securely and reliably
    $response = wp_remote_get($url, [
        'timeout' => 10, // Max time in seconds for the request
        'sslverify' => true, // Only disable for testing if absolutely necessary, prefer true in production
    ]);

    if (is_wp_error($response)) {
        // Log the error for debugging purposes
        error_log('Failed to fetch country list from Gist: ' . $response->get_error_message());
        return []; // Return an empty array on error
    }

    $body = wp_remote_retrieve_body($response);
    
    // Split the body into lines
    $countries = explode("\n", $body);
    
    // Trim whitespace from each country name and remove any empty lines
    $countries = array_map('trim', $countries);
    $countries = array_filter($countries); 

    // Ensure it's a simple indexed array of names, removing any potential non-numeric keys if created by filter
    return array_values($countries);
}

function get_us_states_mapping() {
    // Based on us_cities_states_counties.csv from leadstartorg/USA-cities-and-states
    return array(
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
        'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
        'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
        'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'DC' => 'District of Columbia',
        // Military and territories
        'AS' => 'American Samoa', 'GU' => 'Guam', 'MP' => 'Northern Mariana Islands',
        'PR' => 'Puerto Rico', 'VI' => 'U.S. Virgin Islands', 'AA' => 'Armed Forces Americas',
        'AE' => 'Armed Forces Europe', 'AP' => 'Armed Forces Pacific'
    );
}

function get_us_cities_mapping() {
    return [
        'Abilene' => ['The Key City'],
        'Akron' => ['Rubber City', 'Summit City', 'City of Invention'],
        'Albany' => ['A-Town', 'Cradle of the Union', 'Smallbany'],
        'Albuquerque' => ['ABQ', 'Duke City', 'The Q'],
        'Alexandria' => ['Old Town', 'DC\'s Old Town', 'Port City'],
        'Allentown' => ['A-Town', 'Cement City', 'Queen City'],
        'Amarillo' => ['Yellow City', 'Bomb City', 'The Big Cactus'],
        'Anaheim' => ['Home of the Anaheim Ducks', 'Home of Disneyland'],
        'Anchorage' => ['Anchortown', 'City of Lights and Flowers'],
        'Ann Arbor' => ['A2', 'Tree Town', 'Arbor', 'Couch Potato Capital'],
        'Arlington' => ['The American Dream City', 'A-Town'],
        'Athens' => ['Classic City'],
        'Atlanta' => ['ATL', 'Hotlanta', 'The A', 'City in a Forest', 'Peach State City', 'Gate City'],
        'Augusta' => ['Garden City', 'Horse Capital of the World'],
        'Aurora' => ['City of Lights', 'Gateway to the Rockies'],
        'Austin' => ['ATX', 'Live Music Capital of the World', 'Keep Austin Weird', 'Violet Crown City', 'River City'],
        'Bakersfield' => ['Bako', 'Nashville West', 'California\'s Country Music Capital'],
        'Baltimore' => ['Charm City', 'B-More', 'Monument City', 'Mobtown', 'The Land of Pleasant Living'],
        'Baton Rouge' => ['Red Stick', 'The Capital City', 'BR'],
        'Beaumont' => ['Boomtown'],
        'Bellevue' => ['City in a Park'],
        'Bend' => ['The Bend'],
        'Berkeley' => ['Berzerkeley', 'The People\'s Republic of Berkeley'],
        'Billings' => ['Magic City', 'Star of the Big Sky'],
        'Birmingham' => ['Magic City', 'Steel City of the South', 'Ham', 'Bham'],
        'Boca Raton' => ['Boca'],
        'Boise' => ['City of Trees', 'Treefort City'],
        'Boston' => ['Beantown', 'The Hub', 'Athens of America', 'America\'s Walking City', 'Titletown'],
        'Boulder' => ['The People\'s Republic of Boulder', 'The City of Boulder'],
        'Bridgeport' => ['Park City'],
        'Brockton' => ['City of Champions'],
        'Broken Arrow' => ['BA'],
        'Brownsville' => ['Resaca City'],
        'Buffalo' => ['Queen City', 'City of Good Neighbors', 'B-Lo', 'Nickel City', 'City of No Illusions'],
        'Burbank' => ['Media Capital of the World'],
        'Cambridge' => ['The People\'s Republic of Cambridge'],
        'Cape Coral' => ['Waterfront Wonderland', 'Cape'],
        'Carlsbad' => ['The Village by the Sea'],
        'Carmel' => ['Carmel-by-the-Sea', 'The Crossroads of America'],
        'Cary' => ['Cary, NC', 'The Technology Town of North Carolina', 'Containment Area for Relocated Yankees'],
        'Cedar Rapids' => ['CR', 'City of Five Seasons'],
        'Charleston' => ['Chuck Town', 'Holy City', 'The Holy City', 'The Cradle of Secession'],
        'Charlotte' => ['CLT', 'Queen City', 'Hornets City', 'The QC'],
        'Chattanooga' => ['Scenic City', 'Dynamo of Dixie', 'Chatt'],
        'Chesapeake' => ['The City That Cares'],
        'Chicago' => ['The Windy City', 'Chi-town', 'Second City', 'City of Big Shoulders', 'Chicagoland', 'The Loop'],
        'Chico' => ['City of Roses'],
        'Chula Vista' => ['CV'],
        'Cincinnati' => ['Cincy', 'Queen City', 'Porkopolis', 'The Nati', 'The Queen City of the West'],
        'Clarksville' => ['Clarksville, TN'],
        'Cleveland' => ['CLE', 'The Forest City', 'Rock and Roll Capital of the World', 'C-Town', 'The 216'],
        'Clovis' => ['Gateway to the Sierras'],
        'College Station' => ['Aggieland'],
        'Colorado Springs' => ['Springs', 'Olympic City', 'The Springs', 'Garden of the Gods City'],
        'Columbia' => ['Soda City', 'CoMo'],
        'Columbus' => ['The Arch City', 'C-Bus', 'Discovery City', 'Cap City'],
        'Concord' => ['Conkurd', 'New Hampshire\'s Capital City'],
        'Conroe' => ['Conroe, TX'],
        'Corona' => ['Circle City'],
        'Corpus Christi' => ['CC', 'Sparkling City by the Sea', 'Body of Christ'],
        'Costa Mesa' => ['City of the Arts'],
        'Daly City' => ['The Gateway to the Peninsula'],
        'Dallas' => ['Big D', 'D-Town', 'Triple D', 'The Metroplex'],
        'Davie' => ['The Western Town'],
        'Dayton' => ['Gem City', 'Birthplace of Aviation', 'The Invention City'],
        'Dearborn' => ['D-Town'],
        'Denver' => ['Mile High City', 'Queen City of the Plains', 'The Mile High', 'Den', 'DNVR'],
        'Denton' => ['Little D'],
        'Des Moines' => ['Des Moines, IA', 'The Hartford of the West'],
        'Detroit' => ['Motor City', 'Motown', 'The D', 'Hockeytown', 'The Renaissance City', 'Detroit Rock City'],
        'Durham' => ['Bull City', 'City of Medicine'],
        'East Los Angeles' => ['East LA'],
        'Edinburg' => ['City of Palms'],
        'Edmond' => ['A Community of Character'],
        'El Monte' => ['The Friendly City'],
        'El Paso' => ['EP', 'Sun City', 'The 915'],
        'Elizabeth' => ['The Crossroads of New Jersey'],
        'Elk Grove' => ['Elk Grove, CA'],
        'Elgin' => ['The City in the Suburbs'],
        'Enterprise' => ['The City of Progress'],
        'Escondido' => ['Hidden Valley', 'Escondido, CA'],
        'Eugene' => ['Track Town USA', 'Emerald City', 'The City of Roses', 'Euge'],
        'Evansville' => ['River City', 'Stoplight City'],
        'Everett' => ['City of Smokestacks'],
        'Fairfield' => ['Fairfield, CA'],
        'Fargo' => ['Gateway to the West'],
        'Fayetteville' => ['Fayettenam', 'Ozark Mountain Town'],
        'Fontana' => ['City of Action'],
        'Fort Collins' => ['FoCo', 'The Choice City'],
        'Fort Lauderdale' => ['Venice of America', 'Fort Laudy', 'The Sunshine City'],
        'Fort Myers' => ['City of Palms'],
        'Fort Wayne' => ['Summit City', 'Fort Wayne, IN'],
        'Fort Worth' => ['Cowtown', 'Panther City', 'Funkytown', 'Where the West Begins'],
        'Fremont' => ['Fremont, CA'],
        'Fresno' => ['The Big F', 'F-Town', 'Grape Capital of the World'],
        'Frisco' => ['Sports City USA'],
        'Fullerton' => ['Fullerton, CA'],
        'Gainesville' => ['The Tree City', 'Horse Capital of the World'],
        'Garden Grove' => ['GG'],
        'Georgetown' => ['Georgetown, TX'],
        'Glendale' => ['Glendale, AZ', 'Glendale, CA'],
        'Grand Prairie' => ['GP'],
        'Grand Rapids' => ['GR', 'Furniture City', 'Beer City USA', 'River City'],
        'Green Bay' => ['Titletown', 'The Frozen Tundra'],
        'Greensboro' => ['Gate City', 'Tournament Town'],
        'Hampton' => ['From the Sea to the Stars', 'The Hamptons'],
        'Hartford' => ['Insurance Capital of the World', 'The Heartbeat'],
        'Hialeah' => ['City of Progress'],
        'High Point' => ['Furniture Capital of the World', 'HP'],
        'Hillsboro' => ['Silicon Forest'],
        'Hollywood' => ['Tinseltown', 'The Entertainment Capital of the World'],
        'Honolulu' => ['Paradise of the Pacific', 'The Big Pineapple'],
        'Houston' => ['Space City', 'H-town', 'Bayou City', 'Energy Capital', 'HTX', 'Clutch City'],
        'Huntington Beach' => ['HB', 'Surf City USA'],
        'Huntsville' => ['Rocket City', 'Space City', 'HSV'],
        'Independence' => ['Queen City of the Trails'],
        'Indianapolis' => ['Indy', 'Circle City', 'Racing Capital of the World', 'Naptown'],
        'Inglewood' => ['City of Champions'],
        'Irvine' => ['The Planned Community'],
        'Jackson' => ['City with Soul', 'Jax'],
        'Jacksonville' => ['Jax', 'River City by the Sea', 'JAX'],
        'Jersey City' => ['JC', 'Wall Street West', 'Chilltown'],
        'Joliet' => ['City of Champions', 'City of Steel'],
        'Kansas City' => ['KC', 'City of Fountains', 'BBQ Capital', 'KCMO', 'The Heart of America'],
        'Killeen' => ['K-Town'],
        'Knoxville' => ['Marble City', 'K-Town', 'The Volunteer City'],
        'Lafayette' => ['Acadiana', 'Hub City'],
        'Lakeland' => ['Swan City'],
        'Lakewood' => ['Lakewood, CO', 'Lakewood, CA'],
        'Lancaster' => ['Antelope Valley', 'Red Rose City'],
        'Lansing' => ['The State Capital'],
        'Laredo' => ['Gateway City', 'L-Town'],
        'Las Cruces' => ['The City of Crosses', 'Cruces'],
        'Las Vegas' => ['Vegas', 'Sin City', 'Entertainment Capital of the World', 'The Gambling Capital of the World', 'The Neon Capital of the World'],
        'Lexington' => ['Horse Capital of the World', 'Lex'],
        'Lincoln' => ['Star City', 'Capital City'],
        'Little Rock' => ['Rock City', 'The Rock'],
        'Long Beach' => ['LBC', 'The LBC', 'Long Beach, CA'],
        'Los Angeles' => ['LA', 'City of Angels', 'Tinseltown', 'Hollywood', 'The Big Orange', 'La La Land'],
        'Louisville' => ['Derby City', 'River City', 'Falls City', 'Louyville'],
        'Lowell' => ['Mill City'],
        'Lubbock' => ['Hub City', 'Lubbock, TX'],
        'Lynn' => ['City of Sin', 'Shoe City'],
        'Macon-Bibb County' => ['Macon', 'The Heart of Georgia'],
        'Madison' => ['Mad City', 'The City of Four Lakes'],
        'Manchester' => ['Queen City'],
        'McAllen' => ['City of Palms', 'McAllen, TX'],
        'McKinney' => ['Unique by Nature'],
        'Memphis' => ['Bluff City', 'Home of the Blues', 'Soul City', 'The Birthplace of Rock and Roll', 'M-Town'],
        'Meridian' => ['Railroad Center of the South'],
        'Mesa' => ['Mesa, AZ'],
        'Miami' => ['Magic City', 'The 305', 'Gateway to the Americas', 'MIA', 'The Vice City'],
        'Midland' => ['Tall City', 'Permian Basin'],
        'Milwaukee' => ['Brew City', 'Cream City', 'MKE', 'Mil-town'],
        'Minneapolis' => ['Mill City', 'City of Lakes', 'Mini Apple', 'The Twin Cities', 'MPLS'],
        'Mobile' => ['Port City', 'Azalea City', 'Mobtown'],
        'Modesto' => ['Water, Wealth, Contentment, Health'],
        'Montgomery' => ['Cradle of the Confederacy', 'Gump City'],
        'Moreno Valley' => ['MoVal'],
        'Murfreesboro' => ['M\'boro'],
        'Nashville' => ['Music City', 'Nash', 'Athens of the South', 'Nashvegas', 'Smashville'],
        'New Bedford' => ['Whaling City'],
        'New Braunfels' => ['NB'],
        'New Haven' => ['Elm City', 'The City of Elms'],
        'New Orleans' => ['NOLA', 'Big Easy', 'Crescent City', 'The Big Crescent', 'The Big Sleazy'],
        'New York' => ['NYC', 'The Big Apple', 'The City That Never Sleeps', 'Gotham', 'The Five Boroughs', 'Capital of the World'],
        'Newark' => ['Brick City', 'Gateway City'],
        'Newport News' => ['NN'],
        'Norfolk' => ['757', 'Mermaid City', 'The Harbor City'],
        'Norman' => ['Boomer Sooner'],
        'North Charleston' => ['Noisette'],
        'North Las Vegas' => ['NLV'],
        'Oakland' => ['The Town', 'Oak Town', 'Oaktown', 'The Bright Side of the Bay'],
        'Oceanside' => ['O-side'],
        'Odessa' => ['Oil Capital of Texas'],
        'Oklahoma City' => ['OKC', 'The Big Friendly', 'Boomtown', ' OKC Thunder'],
        'Omaha' => ['Gateway to the West', 'Big O', 'The 402'],
        'Ontario' => ['The Gateway to Southern California'],
        'Orange' => ['Plaza City'],
        'Orlando' => ['O-Town', 'City Beautiful', 'The Theme Park Capital of the World'],
        'Overland Park' => ['OP'],
        'Oxnard' => ['Strawberry Capital of California'],
        'Palm Bay' => ['Palm Bay, FL'],
        'Palmdale' => ['Aerospace Capital', 'P-Dale'],
        'Pasadena' => ['City of Roses', 'Crown City', 'Tournament of Roses City'],
        'Paterson' => ['Silk City', 'The City of Silk'],
        'Pembroke Pines' => ['PP'],
        'Peoria' => ['Peoria, IL', 'Peoria, AZ'],
        'Philadelphia' => ['Philly', 'City of Brotherly Love', 'The Birthplace of America', 'The City of Magnificient Distances', 'The Cradle of Liberty', 'Illadelph'],
        'Phoenix' => ['PHX', 'Valley of the Sun', 'The Valley'],
        'Pittsburgh' => ['Steel City', 'City of Bridges', 'The Burgh', 'PGH', 'Iron City'],
        'Plano' => ['Plano, TX'],
        'Port St. Lucie' => ['PSL'],
        'Portland' => ['PDX', 'City of Roses', 'Stumptown', 'Rip City', 'Bridgetown'],
        'Providence' => ['Creative Capital', 'The Renaissance City', 'The Beehive of Industry'],
        'Pueblo' => ['Steel City of the West'],
        'Quincy' => ['City of Presidents'],
        'Raleigh' => ['City of Oaks', 'Raleighwood'],
        'Rancho Cucamonga' => ['RC', 'The Cucamonga'],
        'Reno' => ['Biggest Little City in the World', 'The Biggest Little City'],
        'Richmond' => ['River City', 'The RVA', 'Capital of the Confederacy'],
        'Riverside' => ['City of Arts and Innovation', 'The Green City'],
        'Rochester' => ['Flower City', 'World Image Centre', 'Med City'],
        'Rockford' => ['Forest City'],
        'Roseville' => ['Roseville, CA', 'Roseville, MN'],
        'Sacramento' => ['Sac', 'City of Trees', 'River City', 'Sactown', 'The Big Tomato'],
        'Saint Paul' => ['St. Paul', 'The Twin Cities', 'The Capitol City'],
        'Salem' => ['Cherry City'],
        'Salinas' => ['Salad Bowl of the World'],
        'Salt Lake City' => ['SLC', 'Crossroads of the West', 'Salt Lake', 'The Beehive City'],
        'San Angelo' => ['Concho City'],
        'San Antonio' => ['SA', 'Alamo City', 'Military City USA', 'San Antone', 'River City'],
        'San Bernardino' => ['San Berdoo', 'SB'],
        'San Buenaventura (Ventura)' => ['Ventura'],
        'San Diego' => ['SD', "America's Finest City", 'The Big Enchilada', 'Paradise City'],
        'San Francisco' => ['SF', 'The City', 'Baghdad by the Bay', 'Fog City', 'Frisco', 'The Golden City', 'The Bay City'],
        'San Jose' => ['SJ', 'Capital of Silicon Valley', 'The Garden City', 'San Jo'],
        'Santa Ana' => ['SA'],
        'Santa Clara' => ['Heart of Silicon Valley'],
        'Santa Clarita' => ['SCV'],
        'Santa Maria' => ['SM'],
        'Santa Rosa' => ['City Designed for Living'],
        'Savannah' => ['Hostess City of the South', 'Forest City'],
        'Scottsdale' => ['The West\'s Most Western Town'],
        'Seattle' => ['Emerald City', 'The Jet City', 'Rain City', 'Sea-Town', 'Seatown', 'The Coffee Capital'],
        'Shreveport' => ['Ratchet City', 'Port City'],
        'Sioux Falls' => ['Queen City of the West', 'Gateway to the Plains'],
        'South Bend' => ['SB', 'The Bend'],
        'Sparks' => ['Sparky'],
        'Spokane' => ['Lilac City', 'The Gateway to the Pacific Northwest'],
        'Springfield' => ['The City of Firsts', 'Queen City of the Ozarks', 'Birthplace of Route 66'],
        'St. George' => ['Utah\'s Dixie'],
        'St. Louis' => ['STL', 'Gateway City', 'The Gateway to the West', 'River City', 'The Lou'],
        'St. Paul' => ['St. Paul', 'The Twin Cities', 'The Capitol City'],
        'St. Petersburg' => ['St. Pete', 'Sunshine City', 'The Burg'],
        'Stamford' => ['The City That Works'],
        'Stockton' => ['Mudville', 'Stockton, CA'],
        'Syracuse' => ['Salt City', 'Syracuse, NY'],
        'Tacoma' => ['City of Destiny', 'T-Town', 'Gritty City'],
        'Tallahassee' => ['Tally', 'Capital City'],
        'Tampa' => ['Cigar City', 'Lightning Capital', 'The Big Guava', 'TPA'],
        'Temecula' => ['Temecula Valley'],
        'Tempe' => ['Tempe, AZ'],
        'Thousand Oaks' => ['TO'],
        'Toledo' => ['Glass City', 'Frog Town', 'The Waterfowl Capital of the World'],
        'Topeka' => ['Top City'],
        'Tucson' => ['Old Pueblo', 'T-Town', 'Optics Valley'],
        'Tulsa' => ['Oil Capital of the World', 'T-Town', 'Green Country'],
        'Tuscaloosa' => ['Druid City', 'T-Town', 'The City of Champions'],
        'Tyler' => ['Rose Capital of the World'],
        'Vancouver' => ['Van', 'Vancity', 'Terminal City', 'Raincouver'],
        'Ventura' => ['Ventura, CA'],
        'Victorville' => ['VV'],
        'Virginia Beach' => ['VB', 'Resort City', 'The Oceanfront'],
        'Visalia' => ['Gateway to the Sequoias'],
        'Waco' => ['Heart of Texas'],
        'Washington' => ['DC', 'The District', 'DMV', 'Capital of the Free World', 'The Federal City'],
        'Waterbury' => ['Brass City', 'The Q'],
        'West Covina' => ['WC'],
        'West Palm Beach' => ['WPB'],
        'Wichita' => ['ICT', 'Air Capital of the World', 'The ICT'],
        'Wilmington' => ['The First State\'s Largest City', 'Port City'],
        'Winston-Salem' => ['Camel City', 'Twin City', 'The Dash'],
        'Worcester' => ['Wormtown', 'Heart of the Commonwealth', 'The Woo'],
        'Yonkers' => ['City of Seven Hills', 'The Sixth Borough'],
        'Yuma' => ['Sunniest City on Earth']
    ];
}

function normalize_countries($country) {
    $country = trim($country);
    $country_map = get_country_mapping();
    
    // If it's already an abbreviation and exists in our map
    if (strlen($country) == 2 && isset($country_map[strtoupper($country)])) {
        return strtoupper($country);
    }
    
    // If it's a full name, find the abbreviation
    $normalized = ucwords(strtolower($country));
    $abbreviation = array_search($normalized, $country_map);
    if ($abbreviation !== false) {
        return $abbreviation;
    }
    
    // Return original if no match found
    return $country;
}

function normalize_us_state($state) {
    $state = trim($state);
    $states_map = get_us_states_mapping();
    
    // If it's already an abbreviation and exists in our map
    if (strlen($state) == 2 && isset($states_map[strtoupper($state)])) {
        return strtoupper($state);
    }
    
    // If it's a full name, find the abbreviation
    $normalized = ucwords(strtolower($state));
    $abbreviation = array_search($normalized, $states_map);
    if ($abbreviation !== false) {
        return $abbreviation;
    }
    
    // Return original if no match found
    return $state;
}

function normalize_us_city($city) {
    $city = trim($city);
    $lower_city = strtolower($city);
    $cities_map = get_cities_with_nicknames_mapping();

    // First, check if the input matches any official city name exactly (case-insensitive).
    // We iterate through the keys (official city names) of the map.
    foreach ($cities_map as $official_city_name => $nicknames) {
        if (strtolower($official_city_name) === $lower_city) {
            return $official_city_name; // Found a direct match to an official name
        }
    }

    // Next, check if the input matches any of the nicknames.
    // We iterate through the map to check each city's nicknames.
    foreach ($cities_map as $official_city_name => $nicknames) {
        foreach ($nicknames as $nickname) {
            if (strtolower($nickname) === $lower_city) {
                return $official_city_name; // Found a match to a nickname
            }
        }
    }
    
    // If no match found, return the original input.
    return $city;
}

//Avanced Country normalization using existing functions
function normalize_venue_country($country) {
    if (empty($country)) return $country;
    
    $country = trim($country);
    
    // Try existing normalize_countries function
    $normalized = normalize_countries($country);
    if ($normalized !== $country) {
        $country_map = get_country_mapping();
        if (isset($country_map[$normalized])) {
            return $country_map[$normalized];
        }
    }
    
    // Handle US variations
    $us_variations = array(
        'US' => 'United States',
        'USA' => 'United States', 
        'U.S.A.' => 'United States',
        'U.S.' => 'United States',
        'America' => 'United States'
    );
    
    if (isset($us_variations[$country])) {
        return $us_variations[$country];
    }
    
    // Check against countries list
    $countries_list = get_countries_list();
    if (in_array($country, $countries_list)) {
        return $country;
    }
    
    // Case-insensitive match
    foreach ($countries_list as $valid_country) {
        if (strcasecmp($country, $valid_country) === 0) {
            return $valid_country;
        }
    }
    
    return $country;
}

//Avanced State normalization using existing functions
function normalize_venue_state($state) {
    if (empty($state)) return $state;
    
    $state = trim($state);
    $states_map = get_us_states_mapping();
    
    // If already 2-letter abbreviation and exists, return as-is
    if (strlen($state) == 2 && isset($states_map[strtoupper($state)])) {
        return strtoupper($state);
    }
    
    // Find abbreviation by searching values
    $normalized_input = ucwords(strtolower($state));
    $abbreviation = array_search($normalized_input, $states_map);
    if ($abbreviation !== false) {
        return $abbreviation;
    }
    
    // Handle special cases
    $special_cases = array(
        'District of Columbia' => 'DC',
        'Washington DC' => 'DC',
        'Washington D.C.' => 'DC'
    );
    
    if (isset($special_cases[$normalized_input])) {
        return $special_cases[$normalized_input];
    }
    
    return $state;
}


function _get_filters_from_ajax_data($ajax_data) {
    $filters = [];
    if (!empty($ajax_data['taxonomy_filters'])) {
        foreach ($ajax_data['taxonomy_filters'] as $taxonomy => $term_ids) {
            if (!empty($term_ids)) {
                $filters[$taxonomy] = array_map('intval', (array)$term_ids);
            }
        }
    }

    // Handle day-of-week filters from AJAX
    if (!empty($ajax_data['dayofweek_filters'])) {
        $days = array_map('intval', (array)$ajax_data['dayofweek_filters']);
        $valid_days = array_filter($days, function($day) {
            return $day >= 1 && $day <= 7;
        });
        if (!empty($valid_days)) {
            $filters['dayofweek'] = $valid_days;
        }
    }

    // Handle time period filters from AJAX
    if (!empty($ajax_data['timeperiod_filters'])) {
        $periods = (array)$ajax_data['timeperiod_filters'];
        $valid_periods = array('allday', 'morning', 'afternoon', 'evening', 'night');
        $filtered_periods = array_filter($periods, function($period) use ($valid_periods) {
            return in_array($period, $valid_periods);
        });
        if (!empty($filtered_periods)) {
            $filters['timeperiod'] = $filtered_periods;
        }
    }

    if (!empty($ajax_data['cost_filters'])) {
        $cost_value = sanitize_text_field($ajax_data['cost_filters']);
        if (strpos($cost_value, ',') !== false) {
            // Range format: "min,max"
            $cost_range = explode(',', $cost_value);
            if (count($cost_range) === 2) {
                $min = is_numeric($cost_range[0]) ? floatval($cost_range[0]) : 0;
                $max = is_numeric($cost_range[1]) ? floatval($cost_range[1]) : PHP_FLOAT_MAX;
                $filters['cost_range'] = array('min' => $min, 'max' => $max);
            }
        } elseif (is_numeric($cost_value)) {
            // Single value format: treat as max cost, include free events
            $filters['cost_range'] = array('min' => 0, 'max' => floatval($cost_value));
        } else {
            // Invalid format - default to all events by querying the highest actual event cost
            global $wpdb;
            // Query the database for the highest existing '_EventCost'
            // CAST to DECIMAL ensures proper numeric comparison for strings
            $highest_event_cost = $wpdb->get_var( "SELECT MAX(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} WHERE meta_key = '_EventCost'" );
            
            // If no specific costs are found, use a reasonable default (e.g., 10000)
            // Otherwise, use the highest found cost
            $max_default_cost = ($highest_event_cost !== null && $highest_event_cost !== '') ? floatval($highest_event_cost) : 10000; 
            
            $filters['cost_range'] = array('min' => 0, 'max' => $max_default_cost);
        }
    }

    // Organizer filters
    if (!empty($ajax_data['organizer_filters'])) {
        $organizer_value = sanitize_text_field($ajax_data['organizer_filters']);
        if (strpos($organizer_value, ',') !== false) {
            $organizer_ids = explode(',', $organizer_value);
            $organizer_ids = array_map('intval', $organizer_ids);
            $organizer_ids = array_filter($organizer_ids);
            $filters['organizer'] = $organizer_ids;
        } else {
            $organizer_id = intval($organizer_value);
            if ($organizer_id > 0) {
                $filters['organizer'] = [$organizer_id];
            }
        }
    }

    // Venue ID filters (your existing logic)
    if (!empty($ajax_data['venue_filters'])) {
        $venue_value = sanitize_text_field($ajax_data['venue_filters']);
        if (strpos($venue_value, ',') !== false) {
            $venue_ids = explode(',', $venue_value);
            $venue_ids = array_map('intval', $venue_ids);
            $venue_ids = array_filter($venue_ids);
            $filters['venue'] = $venue_ids;
        } else {
            $venue_id = intval($venue_value);
            if ($venue_id > 0) {
                $filters['venue'] = [$venue_id];
            }
        }
    }

    // Venue location filters (country, state, city, address)
    $venue_location_filter_types = array('country', 'state', 'city', 'address');
    foreach ($venue_location_filter_types as $venue_type) {
        $param_key = "venue_{$venue_type}_filters";
        
        if (!empty($ajax_data[$param_key])) {
            $values = is_array($ajax_data[$param_key]) ? $ajax_data[$param_key] : explode(',', $ajax_data[$param_key]);
            $clean_values = array_filter(array_map('sanitize_text_field', $values));
            if (!empty($clean_values)) {
                // Apply normalization based on venue type (using existing functions)
                if ($venue_type === 'state') {
                    $clean_values = array_map('normalize_venue_state', $clean_values);
                } elseif ($venue_type === 'country') {
                    $clean_values = array_map('normalize_venue_country', $clean_values);
                }
                $filters["venue_{$venue_type}"] = $clean_values;
            }
        }
    }

    //error_log('Filters from AJAX data: ', $filters);
    return $filters;
}


/**
 * ===============================================================================
 * Filtered Query Arguments
 * ===============================================================================
 */

/**
 * CENTRALIZED TAXONOMY QUERY BUILDER
 * Apply taxonomy filters to WP_Query arguments
 * Eliminates duplicate tax_query building logic across functions
 * 
 * @param array $args Existing query arguments (passed by reference)
 * @param array $taxonomy_filters Array of taxonomy => term_ids
 * @param string $debug_output Debug output string (passed by reference)
 * @return array Modified query arguments
 */

function apply_taxonomy_filters_to_query_args($args, $taxonomy_filters, &$debug_output = '') {
    if (empty($taxonomy_filters)) {
        return $args;
    }

    $tax_query = array('relation' => 'AND');

    // Preserve existing tax_query if present
    if (!empty($args['tax_query'])) {
        $tax_query[] = $args['tax_query'];
    }

    foreach ($taxonomy_filters as $taxonomy => $term_ids) {
        if (!empty($term_ids)) {
            $term_ids = array_map('intval', (array)$term_ids);

            $tax_query[] = array(
                'taxonomy' => sanitize_text_field($taxonomy),
                'field' => 'term_id',
                'terms' => $term_ids,
                'operator' => 'IN'
            );
        }
    }

    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    return $args;
}


// Build filtered query arguments with proper date archive detection

/**
 * Helper: Determines if the current page is a date archive and extracts date info.
 * It checks URL patterns first, then falls back to WordPress's native query vars.
 *
 * @param string|null $current_page_url Optional URL to check (for testing).
 * @return array|false An array containing date info (year, month, day, type, post_type) if a date archive is detected, otherwise false.
 */
function _get_date_archive_info($current_page_url = null) {
    // Priority 1: Check date archive based on custom URL patterns
    $archive_info_from_url = is_custom_post_type_date_archive_by_url($current_page_url);
    if ($archive_info_from_url) {
        return $archive_info_from_url;
    }

    // Priority 2: Fallback to WordPress's built-in date query variables
    $wp_year = get_query_var('year');
    $wp_month = get_query_var('monthnum');
    $wp_day = get_query_var('day');

    if ($wp_year || $wp_month || $wp_day) {
        // Determine the type of date archive
        $type = 'yearly';
        if ($wp_month) {
            $type = 'monthly';
            if ($wp_day) {
                $type = 'daily';
            }
        }

        // Return a consistent structure
        return array(
            'year' => $wp_year ? (string)$wp_year : null, // Ensure string for consistency
            'month' => $wp_month ? str_pad((string)$wp_month, 2, '0', STR_PAD_LEFT) : null,
            'day' => $wp_day ? str_pad((string)$wp_day, 2, '0', STR_PAD_LEFT) : null,
            'type' => $type,
            'post_type' => get_query_var('post_type') ?: 'post', // Default to 'post' if not specific
            'context' => 'date' // Explicitly mark as date context
        );
    }

    return false; // Not a date archive
}

function tribe_events_search_filter($where, $query) {
    global $wpdb;
    
    // Only apply to tribe_events queries with search
    if ($query->get('post_type') !== 'tribe_events' || empty($query->get('s'))) {
        return $where;
    }
    
    $search_term = $query->get('s');
    $search_like = '%' . $wpdb->esc_like($search_term) . '%';
    
    // Enhanced search conditions
    $search_conditions = array();
    
    // 1. Default WordPress search (title, content)
    $search_conditions[] = $wpdb->prepare("
        ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s)
    ", $search_like, $search_like);
    
    // 2. Venue name
    $search_conditions[] = $wpdb->prepare("
        EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm_venue
            INNER JOIN {$wpdb->posts} venue_post ON pm_venue.meta_value = venue_post.ID
            WHERE pm_venue.post_id = {$wpdb->posts}.ID
            AND pm_venue.meta_key = '_EventVenueID'
            AND venue_post.post_title LIKE %s
        )
    ", $search_like);
    
    // 3. Organizer name
    $search_conditions[] = $wpdb->prepare("
        EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm_org
            INNER JOIN {$wpdb->posts} org_post ON pm_org.meta_value = org_post.ID
            WHERE pm_org.post_id = {$wpdb->posts}.ID
            AND pm_org.meta_key = '_EventOrganizerID'
            AND org_post.post_title LIKE %s
        )
    ", $search_like);
    
    // 4. Category and tag names
    $search_conditions[] = $wpdb->prepare("
        EXISTS (
            SELECT 1 FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE tr.object_id = {$wpdb->posts}.ID
            AND tt.taxonomy IN ('tribe_events_cat', 'post_tag')
            AND t.name LIKE %s
        )
    ", $search_like);
    
    // Replace default WordPress search with enhanced search
    if (!empty($search_conditions)) {
        $combined_search = '(' . implode(' OR ', $search_conditions) . ')';
        
        // Remove default WordPress search WHERE clause
        $where = preg_replace('/AND \(\(\(.*?post_title LIKE.*?\)\)\)/', '', $where);
        
        // Add our enhanced search
        $where .= " AND {$combined_search}";
    }
    
    return $where;
}
add_filter('posts_where', 'tribe_events_search_filter', 14, 2);

function tribe_events_day_of_week_filter($where, $query) {
    global $wpdb;
    
    static $debug_counter = 0;
    $debug_counter++;
    
    // Log EVERYTHING about this query
    error_log("=== DOW FILTER #{$debug_counter} DEBUG START ===");
    error_log("Query post_type: " . ($query->get('post_type') ?: 'empty'));
    error_log("Query vars post_type: " . ($query->query_vars['post_type'] ?? 'not_set'));
    error_log("Query array post_type: " . ($query->query['post_type'] ?? 'not_set'));
    error_log("Is main query: " . ($query->is_main_query() ? 'YES' : 'NO'));
    error_log("URL: " . $_SERVER['REQUEST_URI']);
    error_log("GET dayofweek: " . ($_GET['dayofweek'] ?? 'not_set'));
    
    // Check if post_type is tribe_events
    $post_type = $query->get('post_type') ?: ($query->query_vars['post_type'] ?? null);
    
    if ($post_type !== 'tribe_events') {
        error_log("DOW FILTER #{$debug_counter} - SKIPPED: post_type is '{$post_type}', not 'tribe_events'");
        error_log("=== DOW FILTER #{$debug_counter} DEBUG END ===");
        return $where;
    }
    
    // Check for dayofweek parameter
    if (!isset($_GET['dayofweek']) || empty($_GET['dayofweek'])) {
        error_log("DOW FILTER #{$debug_counter} - SKIPPED: No dayofweek parameter");
        error_log("=== DOW FILTER #{$debug_counter} DEBUG END ===");
        return $where;
    }
    
    $dayofweek_raw = $_GET['dayofweek'];
    $days = explode(',', sanitize_text_field($dayofweek_raw));
    $selected_days = array_map('intval', $days);
    
    error_log("Raw dayofweek: {$dayofweek_raw}");
    error_log("Processed days: " . implode(',', $selected_days));
    
    // Validate days
    $valid_days = array_filter($selected_days, function($day) {
        return $day >= 1 && $day <= 7;
    });
    
    if (empty($valid_days)) {
        error_log("DOW FILTER #{$debug_counter} - SKIPPED: No valid days");
        error_log("=== DOW FILTER #{$debug_counter} DEBUG END ===");
        return $where;
    }
    
    error_log("Valid days for filtering: " . implode(',', $valid_days));
    
    // Log original WHERE clause (truncated)
    error_log("Original WHERE (first 200 chars): " . substr($where, 0, 200) . "...");
    
    // Build the SQL modification
    $placeholders = implode(',', array_fill(0, count($valid_days), '%d'));
    
    $sql_addition = $wpdb->prepare(" AND EXISTS (
        SELECT 1 FROM {$wpdb->postmeta} pm_dow
        WHERE pm_dow.post_id = {$wpdb->posts}.ID
        AND pm_dow.meta_key = '_EventStartDate'
        AND pm_dow.meta_value != ''
        AND DAYOFWEEK(STR_TO_DATE(pm_dow.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) IN ({$placeholders})
    )", ...$valid_days);
    
    error_log("SQL addition: " . $sql_addition);
    
    // Apply the modification
    $where .= $sql_addition;
    
    // Log modified WHERE clause (truncated)
    error_log("Modified WHERE (last 300 chars): ..." . substr($where, -300));
    
    error_log("DOW FILTER #{$debug_counter} - ✅ SUCCESS: Applied filter for days " . implode(',', $valid_days));
    error_log("=== DOW FILTER #{$debug_counter} DEBUG END ===");
    
    return $where;
}
remove_all_filters('posts_where', 'tribe_events_day_of_week_filter');
add_filter('posts_where', 'tribe_events_day_of_week_filter', 15, 2);

// Time period filter function
// Modify your time period filter function with debugging
// Replace your current time period filter function with this one
function tribe_events_time_period_filter($where, $query) {
    global $wpdb;
    
    // Add a debug flag to track every query this filter processes
    static $debug_counter = 0;
    $debug_counter++;
    
    // Debug at the start of the function
    error_log("TIME FILTER #{$debug_counter} - START | Query type: " . ($query->query_vars['post_type'] ?? 'unknown'));
    
    // 1. First, check if this is an events query
    if (!isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'tribe_events') {
        error_log("TIME FILTER #{$debug_counter} - SKIPPED: Not a tribe_events query");
        return $where;
    } 
    
    // 2. Get time period values from all possible sources
    $time_periods = null;
    
    // Check direct URL parameter first (most direct source)
    if (isset($_GET['timeperiod'])) {
        $time_periods = explode(',', sanitize_text_field($_GET['timeperiod']));
        error_log("TIME FILTER #{$debug_counter} - Found time periods in URL: " . print_r($time_periods, true));
    }
    // Next check query vars
    else if (isset($query->query_vars['timeperiod'])) {
        $time_periods = is_array($query->query_vars['timeperiod']) 
                      ? $query->query_vars['timeperiod'] 
                      : explode(',', $query->query_vars['timeperiod']);
        error_log("TIME FILTER #{$debug_counter} - Found time periods in query_vars: " . print_r($time_periods, true));
    }
    // Check nested query vars
    else if (isset($query->query_vars['query_vars']) && isset($query->query_vars['query_vars']['_custom_timeperiod_filter'])) {
        $time_periods = $query->query_vars['query_vars']['_custom_timeperiod_filter'];
        error_log("TIME FILTER #{$debug_counter} - Found time periods in nested query_vars: " . print_r($time_periods, true));
    }
    // Check older direct query var name
    else if (isset($query->query_vars['_custom_timeperiod_filter'])) {
        $time_periods = $query->query_vars['_custom_timeperiod_filter'];
        error_log("TIME FILTER #{$debug_counter} - Found time periods in direct _custom_timeperiod_filter: " . print_r($time_periods, true));
    }
    
    // If no time periods found, skip filtering
    if (empty($time_periods)) {
        error_log("TIME FILTER #{$debug_counter} - SKIPPED: No time periods found");
        return $where;
    }
    
    // Ensure time_periods is an array of valid values
    $valid_periods = ['morning', 'afternoon', 'evening', 'night'];
    $time_periods = array_filter((array)$time_periods, function($period) use ($valid_periods) {
        return in_array($period, $valid_periods);
    });
    
    if (empty($time_periods)) {
        error_log("TIME FILTER #{$debug_counter} - SKIPPED: No valid time periods after filtering");
        return $where;
    }
    
    // Log the SQL before modification
    error_log("TIME FILTER #{$debug_counter} - Original WHERE: " . substr($where, 0, 100) . "...");
    
    // 3. Build the time conditions
    $time_conditions = [];
    foreach ($time_periods as $period) {
        switch ($period) {
            case 'morning':
                $time_conditions[] = "(HOUR(STR_TO_DATE(pm_time.meta_value, '%Y-%m-%d %H:%i:%s')) >= 6 AND HOUR(STR_TO_DATE(pm_time.meta_value, '%Y-%m-%d %H:%i:%s')) < 12)";
                break;
            case 'afternoon':
                $time_conditions[] = "(HOUR(STR_TO_DATE(pm_time.meta_value, '%Y-%m-%d %H:%i:%s')) >= 12 AND HOUR(STR_TO_DATE(pm_time.meta_value, '%Y-%m-%d %H:%i:%s')) < 17)";
                break;
            case 'evening':
                $time_conditions[] = "(HOUR(STR_TO_DATE(pm_time.meta_value, '%Y-%m-%d %H:%i:%s')) >= 17 AND HOUR(STR_TO_DATE(pm_time.meta_value, '%Y-%m-%d %H:%i:%s')) < 21)";
                break;
            case 'night':
                $time_conditions[] = "((HOUR(STR_TO_DATE(pm_time.meta_value, '%Y-%m-%d %H:%i:%s')) >= 21) OR (HOUR(STR_TO_DATE(pm_time.meta_value, '%Y-%m-%d %H:%i:%s')) < 6))";
                break;
        }
    }
    
    // 4. Build the SQL and append to WHERE
    if (!empty($time_conditions)) {
        // IMPORTANT CHANGE: Forcing this filter with high priority
        $sql_addition = " AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm_time
            WHERE pm_time.post_id = {$wpdb->posts}.ID
            AND pm_time.meta_key = '_EventStartDate'
            AND (" . implode(' OR ', $time_conditions) . ")
        )";
        
        error_log("TIME FILTER #{$debug_counter} - Adding time filter SQL: " . $sql_addition);
        $where .= $sql_addition;
        
        // Super aggressive debugging - track if this filter is actually being applied to the final query
        add_action('pre_get_posts', function($query) use ($debug_counter) {
            if ($query->get('post_type') === 'tribe_events') {
                error_log("TIME FILTER #{$debug_counter} - CONFIRMED this filter will affect query: " . print_r($query->query_vars, true));
            }
        }, 999);
    }
    
    // Log the final WHERE clause
    error_log("TIME FILTER #{$debug_counter} - Modified WHERE: " . substr($where, 0, 100) . "...");
    
    return $where;
}
remove_all_filters('posts_where', 'tribe_events_time_period_filter');
add_filter('posts_where', 'tribe_events_time_period_filter', 16, 2);

/**
 * Cost posts_where filter function
 * tribe_events_cost_posts_where_filter with this:
 */
/**
 * FIXED: Cost posts_where filter function that properly handles FREE events
 * Free events can have empty, null, 0, or missing _EventCost meta fields
 */
function tribe_events_cost_posts_where_filter($where, $query) {
    global $wpdb;
    
    // Check if cost filtering is enabled for this query
    if (!isset($query->query_vars['_custom_cost_filter'])) {
        return $where;
    }
    
    $cost_filter = $query->query_vars['_custom_cost_filter'];
    
    error_log("=== FIXED COST POSTS_WHERE FILTER DEBUG ===");
    error_log("Cost filter from query_vars: " . print_r($cost_filter, true));
    
    // Validate cost filter structure
    if (!is_array($cost_filter) || !isset($cost_filter['min']) || !isset($cost_filter['max'])) {
        error_log("ERROR: Invalid cost filter structure");
        return $where;
    }
    
    $min_cost = floatval($cost_filter['min']);
    $max_cost = floatval($cost_filter['max']);
    
    error_log("Applying cost filter: min={$min_cost}, max={$max_cost}");
    
    // FIXED: Build SQL that includes FREE events when min_cost is 0
    if ($min_cost == 0) {
        // When filtering from $0, include:
        // 1. Events with _EventCost between 0 and max_cost
        // 2. Events with empty/null _EventCost (free events)
        // 3. Events without _EventCost meta field at all (also free)
        
        $cost_where = $wpdb->prepare(" AND (
            -- Events with cost between min and max
            EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm_cost
                WHERE pm_cost.post_id = {$wpdb->posts}.ID
                AND pm_cost.meta_key = '_EventCost'
                AND pm_cost.meta_value != ''
                AND pm_cost.meta_value IS NOT NULL
                AND CAST(pm_cost.meta_value AS DECIMAL(10,2)) BETWEEN %f AND %f
            )
            OR
            -- Events with empty/null cost (free events)
            EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm_cost_free
                WHERE pm_cost_free.post_id = {$wpdb->posts}.ID
                AND pm_cost_free.meta_key = '_EventCost'
                AND (
                    pm_cost_free.meta_value = '' 
                    OR pm_cost_free.meta_value IS NULL 
                    OR pm_cost_free.meta_value = '0' 
                    OR pm_cost_free.meta_value = '0.00'
                )
            )
            OR
            -- Events without _EventCost meta field at all (also free)
            NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm_cost_check
                WHERE pm_cost_check.post_id = {$wpdb->posts}.ID
                AND pm_cost_check.meta_key = '_EventCost'
            )
        )", $min_cost, $max_cost);
        
    } else {
        // When min_cost > 0, only include events with actual cost values
        // (exclude free events)
        $cost_where = $wpdb->prepare(" AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm_cost
            WHERE pm_cost.post_id = {$wpdb->posts}.ID
            AND pm_cost.meta_key = '_EventCost'
            AND pm_cost.meta_value != ''
            AND pm_cost.meta_value IS NOT NULL
            AND pm_cost.meta_value != '0'
            AND pm_cost.meta_value != '0.00'
            AND CAST(pm_cost.meta_value AS DECIMAL(10,2)) BETWEEN %f AND %f
        )", $min_cost, $max_cost);
    }
    
    error_log("Cost SQL added: " . $cost_where);
    
    $where .= $cost_where;
    
    return $where;
}

// Remove the old filter and add the fixed one
remove_all_filters('posts_where', 'tribe_events_cost_posts_where_filter');
add_filter('posts_where', 'tribe_events_cost_posts_where_filter', 17, 2);


function tribe_events_organizer_posts_where_filter($where, $query) {
    global $wpdb;
    
    // Check if organizer filtering is enabled for this query
    if (!isset($query->query_vars['_custom_organizer_filter'])) {
        return $where;
    }
    
    $organizer_filter = $query->query_vars['_custom_organizer_filter'];
    
    error_log("=== ENHANCED ORGANIZER FILTER DEBUG ===");
    error_log("Organizer filter from query_vars: " . print_r($organizer_filter, true));
    
    // Validate organizer filter structure
    if (!is_array($organizer_filter) || empty($organizer_filter)) {
        error_log("ERROR: Invalid organizer filter structure");
        return $where;
    }
    
    // Filter to keep only numeric IDs
    $numeric_ids = array_filter($organizer_filter, 'is_numeric');
    $numeric_ids = array_map('intval', $numeric_ids);
    
    if (empty($numeric_ids)) {
        error_log("WARNING: No numeric organizer IDs found in filter, skipping filter");
        return $where;
    }
    
    error_log("Using organizer IDs: " . implode(', ', $numeric_ids));
    
    // Create a single efficient IN clause
    $placeholders = implode(',', array_fill(0, count($numeric_ids), '%d'));
    $organizer_where = $wpdb->prepare(
        " AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} om
            WHERE om.post_id = {$wpdb->posts}.ID
            AND om.meta_key IN ('_EventOrganizerID', '_OrganizerID')
            AND om.meta_value IN ({$placeholders})
        )",
        ...$numeric_ids
    );
    
    error_log("Organizer SQL added: " . $organizer_where);
    $where .= $organizer_where;
    
    return $where;
}
remove_all_filters('posts_where', 'tribe_events_organizer_posts_where_filter');
add_filter('posts_where', 'tribe_events_organizer_posts_where_filter', 18, 2);

/**
 * Venue posts_where filter function
 * This should be registered globally: add_filter('posts_where', 'tribe_events_venue_posts_where_filter', 10, 2);
 */
function tribe_events_venue_posts_where_filter($where, $query) {
    global $wpdb;
    
    // Check if venue filtering is enabled for this query
    if (!isset($query->query_vars['_custom_venue_filter'])) {
        return $where;
    }
    
    $venue_filter = $query->query_vars['_custom_venue_filter'];
    
    error_log("=== VENUE POSTS_WHERE FILTER DEBUG ===");
    error_log("Venue filter from query_vars: " . print_r($venue_filter, true));
    
    // Validate venue filter structure
    if (!is_array($venue_filter) || empty($venue_filter)) {
        error_log("ERROR: Invalid venue filter structure");
        return $where;
    }
    
    // Create placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($venue_filter), '%d'));
    
    error_log("Applying venue filter for IDs: " . implode(', ', $venue_filter));
    
    // Simple SQL using _EventVenueID (as confirmed by your database query)
    $venue_where = $wpdb->prepare(" AND EXISTS (
        SELECT 1 FROM {$wpdb->postmeta} pm_venue
        WHERE pm_venue.post_id = {$wpdb->posts}.ID
        AND pm_venue.meta_key = '_EventVenueID'
        AND pm_venue.meta_value IN ({$placeholders})
    )", ...$venue_filter);
    
    error_log("Venue SQL added: " . $venue_where);
    
    $where .= $venue_where;
    
    return $where;
}
remove_all_filters('posts_where', 'tribe_events_venue_posts_where_filter');
add_filter('posts_where', 'tribe_events_venue_posts_where_filter', 19, 2);

function get_available_venues_for_filter() {
    global $wpdb;
    
    error_log("=== GET AVAILABLE VENUES DEBUG ===");
    
    // Get venues that are actually used in events
    // Based on your database structure: Events._EventVenueID → Venues.ID
    $venue_query = "
        SELECT 
            v.ID as venue_id,
            v.post_title as venue_name,
            pm_address.meta_value as venue_address,
            pm_city.meta_value as venue_city,
            pm_state.meta_value as venue_state,
            pm_country.meta_value as venue_country,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue_id ON e.ID = pm_venue_id.post_id
            AND pm_venue_id.meta_key = '_EventVenueID'
        INNER JOIN {$wpdb->posts} v ON pm_venue_id.meta_value = v.ID
            AND v.post_type = 'tribe_venue'
            AND v.post_status = 'publish'
        LEFT JOIN {$wpdb->postmeta} pm_address ON v.ID = pm_address.post_id
            AND pm_address.meta_key = '_VenueAddress'
        LEFT JOIN {$wpdb->postmeta} pm_city ON v.ID = pm_city.post_id
            AND pm_city.meta_key = '_VenueCity'
        LEFT JOIN {$wpdb->postmeta} pm_state ON v.ID = pm_state.post_id
            AND pm_state.meta_key = '_VenueState'
        LEFT JOIN {$wpdb->postmeta} pm_country ON v.ID = pm_country.post_id
            AND pm_country.meta_key = '_VenueCountry'
        WHERE e.post_type = 'tribe_events'
        AND e.post_status IN ('publish', 'future')
        AND pm_venue_id.meta_value != ''
        GROUP BY v.ID
        ORDER BY event_count DESC, venue_name ASC
        LIMIT 50
    ";
    
    $results = $wpdb->get_results($venue_query);
    
    $venues = [];
    if ($results && !is_wp_error($results)) {
        foreach ($results as $row) {
            // Build location string: "Venue Name - City, State, Country"
            $location_parts = array_filter([
                $row->venue_city,
                $row->venue_state,
                $row->venue_country
            ]);
            
            $display_name = $row->venue_name;
            if (!empty($location_parts)) {
                $display_name .= ' - ' . implode(', ', $location_parts);
            }
            
            $venues[intval($row->venue_id)] = [
                'name' => $row->venue_name,
                'display_name' => $display_name,
                'address' => $row->venue_address,
                'city' => $row->venue_city,
                'state' => $row->venue_state,
                'country' => $row->venue_country,
                'count' => intval($row->event_count)
            ];
        }
    }
    
    error_log("Found venues: " . print_r(array_keys($venues), true));
    
    return $venues;
}

function tribe_events_featured_posts_where_filter($where, $query) {
    global $wpdb;
    
    if (!isset($query->query_vars['_custom_featured_filter'])) {
        return $where;
    }
    
    $featured_filter = $query->query_vars['_custom_featured_filter'];
    
    if ($featured_filter == '1' || $featured_filter === true) {
        $featured_where = " AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm_featured
            WHERE pm_featured.post_id = {$wpdb->posts}.ID
            AND pm_featured.meta_key = '_tribe_featured'
            AND pm_featured.meta_value = '1'
        )";
        
        $where .= $featured_where;
    }
    
    return $where;
}
remove_all_filters('posts_where', 'tribe_events_featured_posts_where_filter');
add_filter('posts_where', 'tribe_events_featured_posts_where_filter', 20, 2);

function tribe_events_virtual_posts_where_filter($where, $query) {
    global $wpdb;
    
    if (!isset($query->query_vars['_custom_virtual_filter'])) {
        return $where;
    }
    
    $virtual_filter = $query->query_vars['_custom_virtual_filter'];
    
    if ($virtual_filter == '1' || $virtual_filter === true || $virtual_filter === 'yes') {
        $virtual_where = " AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm_virtual
            WHERE pm_virtual.post_id = {$wpdb->posts}.ID
            AND pm_virtual.meta_key = '_tribe_events_is_virtual'
            AND (pm_virtual.meta_value = '1' OR pm_virtual.meta_value = 'yes')
        )";
        
        $where .= $virtual_where;
    }
    
    return $where;
}
remove_all_filters('posts_where', 'tribe_events_virtual_posts_where_filter');
add_filter('posts_where', 'tribe_events_virtual_posts_where_filter', 21, 2);

function render_featured_virtual_filters() {
    // Get current values from URL
    $featured_checked = !empty($_GET['featured']) && $_GET['featured'] == '1' ? 'checked' : '';
    $virtual_checked = !empty($_GET['virtual']) && $_GET['virtual'] == '1' ? 'checked' : '';
    
    $html = '<div class="filter-group special-filters-group">';
    $html .= '<label>Special Events:</label>';
    $html .= '<div class="special-filters-checkboxes">';
    
    // Featured events checkbox
    $html .= '<label class="special-filter-checkbox">';
    $html .= '<input type="checkbox" id="featured-filter-checkbox" name="featured" value="1" ' . $featured_checked . ' class="special-filter-checkbox-input">';
    $html .= '<span class="checkmark"></span>';
    $html .= '✨ Featured Events';
    $html .= '</label>';
    
    // Virtual events checkbox
    $html .= '<label class="special-filter-checkbox">';
    $html .= '<input type="checkbox" id="virtual-filter-checkbox" name="virtual" value="1" ' . $virtual_checked . ' class="special-filter-checkbox-input">';
    $html .= '<span class="checkmark"></span>';
    $html .= '💻 Virtual Events';
    $html .= '</label>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Builds and returns an array of query arguments for WP_Query,
 * applying various event filters including custom time periods.
 *
 * @param string $post_type The post type to query (e.g., 'tribe_events').
 * @param array  $event_filters An associative array of active filters.
 * @param string $current_page_url The URL of the current page, used for date archive info.
 * @return array The complete array of WP_Query arguments.
 */

/**
 * BACKWARDS COMPATIBLE: build_filtered_query_args()
 * Maintains original signature: build_filtered_query_args($post_type, $event_filters = array(), $current_page_url = '')
 */
function build_filtered_query_args($post_type = 'tribe_events', $event_filters = array(), $current_page_url = '') {
    // Use the universal system but maintain backwards compatibility
    return build_universal_event_query($event_filters);
}

/**
 * NEW UNIVERSAL FUNCTION: build_universal_event_query()
 * This is the new system that works universally
 */
function build_universal_event_query($additional_filters = []) {
    // Start with basic event query - FIXED PAGINATION
    $args = array(
        'post_type' => 'tribe_events',
        'post_status' => array('publish', 'future'),
        'posts_per_page' => 20, // FIXED: Was -1, now proper pagination
        'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
        'orderby' => 'meta_value',
        'meta_key' => '_EventStartDate',
        'order' => 'ASC'
    );

    // Get current URL to detect date archive
    $current_url = $_SERVER['REQUEST_URI'];
    
    // Handle date filtering if we're on a date archive URL
    if (preg_match('#^/events/(\d{4})(?:/(\d{1,2})(?:/(\d{1,2}))?)?/?#', $current_url, $matches)) {
        $year = $matches[1];
        $month = isset($matches[2]) ? str_pad($matches[2], 2, '0', STR_PAD_LEFT) : null;
        $day = isset($matches[3]) ? str_pad($matches[3], 2, '0', STR_PAD_LEFT) : null;

        // Build date range for meta_query
        if ($day && $month) {
            // Specific day
            $start_date = "{$year}-{$month}-{$day} 00:00:00";
            $end_date = "{$year}-{$month}-{$day} 23:59:59";
        } elseif ($month) {
            // Specific month
            $start_date = "{$year}-{$month}-01 00:00:00";
            $last_day = date('t', strtotime("{$year}-{$month}-01"));
            $end_date = "{$year}-{$month}-{$last_day} 23:59:59";
        } else {
            // Specific year
            $start_date = "{$year}-01-01 00:00:00";
            $end_date = "{$year}-12-31 23:59:59";
        }

        $args['meta_query'] = array(
            'event_start_date' => array(
                'key' => '_EventStartDate',
                'value' => array($start_date, $end_date),
                'compare' => 'BETWEEN',
                'type' => 'DATETIME'
            )
        );
        $args['orderby'] = 'event_start_date';
    }

    // Handle taxonomy archive if we're on a taxonomy URL
    if (preg_match('#^/events/(category|keyword)/([^/]+)/?#', $current_url, $matches)) {
        $taxonomy_type = $matches[1];
        $term_slug = $matches[2];
        
        $taxonomy = ($taxonomy_type === 'category') ? 'tribe_events_cat' : 'post_tag';
        $term = get_term_by('slug', $term_slug, $taxonomy);
        
        if ($term) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => array($term->term_id)
                )
            );
        }
    }

    // Get active filters (always check for all filter types)
    $active_filters = array_merge(get_active_filters_universal(), $additional_filters);

    // Apply all filters regardless of context
    if (!empty($active_filters)) {

        // Apply search filter
        if (!empty($active_filters['search'])) {
            $args['s'] = $active_filters['search'];
        }

        // Apply taxonomy filters
        $taxonomy_filters = array_intersect_key($active_filters, [
            'tribe_events_cat' => true,
            'post_tag' => true,
            'category' => true
        ]);


        if (!empty($taxonomy_filters)) {
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = array('relation' => 'AND');
            } elseif (!isset($args['tax_query']['relation'])) {
                // Convert existing tax_query to proper format
                $existing = $args['tax_query'];
                $args['tax_query'] = array('relation' => 'AND', $existing);
            }

            foreach ($taxonomy_filters as $taxonomy => $term_ids) {
                if (!empty($term_ids)) {
                    $args['tax_query'][] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => $term_ids,
                        'operator' => 'IN'
                    );
                }
            }
        }

        // Apply custom filters (dayofweek, timeperiod, cost, venue, organizer)
        $custom_filter_map = [
            'dayofweek' => '_custom_dayofweek_filter',
            'timeperiod' => '_custom_timeperiod_filter',
            'cost_range' => '_custom_cost_filter',
            'venue_country' => '_custom_venue_country_filter',
            'venue_state' => '_custom_venue_state_filter',
            'venue_city' => '_custom_venue_city_filter',
            'venue_address' => '_custom_venue_address_filter',
            'organizer' => '_custom_organizer_filter'
        ];

        foreach ($custom_filter_map as $filter_key => $query_var) {
            if (!empty($active_filters[$filter_key])) {
                $args[$query_var] = $active_filters[$filter_key];
            }
        }

        // Apply featured filter 
        if (!empty($active_filters['featured'])) { 
            $args['_custom_featured_filter'] = $active_filters['featured']; 
        } 

        // Apply virtual filter 
        if (!empty($active_filters['virtual'])) { 
            $args['_custom_virtual_filter'] = $active_filters['virtual']; 
        }
    }

    return $args;
}

/**
 * NEW: Get filtered event counts for any filter combination
 * This is used to show accurate counts in filter dropdowns
 */
function get_filtered_event_counts($exclude_filter = null) {
    global $wpdb;
    
    // Get current filters but exclude the one we're calculating for
    $active_filters = get_active_filters_universal();
    if ($exclude_filter && isset($active_filters[$exclude_filter])) {
        unset($active_filters[$exclude_filter]);
    }
    
    // Build base query for counting
    $count_args = array(
        'post_type' => 'tribe_events',
        'post_status' => array('publish', 'future'),
        'posts_per_page' => -1, // For counting, we need all IDs
        'fields' => 'ids', // Only get IDs for performance
        'no_found_rows' => true, // Skip SQL_CALC_FOUND_ROWS for performance
        'update_post_meta_cache' => false, // Skip meta cache
        'update_post_term_cache' => false, // Skip term cache
    );
    
    // Apply current URL context (date/taxonomy archive)
    $current_url = $_SERVER['REQUEST_URI'];
    
    // Apply date filtering if on date archive
    if (preg_match('#^/events/(\d{4})(?:/(\d{1,2})(?:/(\d{1,2}))?)?/?#', $current_url, $matches)) {
        $year = $matches[1];
        $month = isset($matches[2]) ? str_pad($matches[2], 2, '0', STR_PAD_LEFT) : null;
        $day = isset($matches[3]) ? str_pad($matches[3], 2, '0', STR_PAD_LEFT) : null;

        if ($day && $month) {
            $start_date = "{$year}-{$month}-{$day} 00:00:00";
            $end_date = "{$year}-{$month}-{$day} 23:59:59";
        } elseif ($month) {
            $start_date = "{$year}-{$month}-01 00:00:00";
            $last_day = date('t', strtotime("{$year}-{$month}-01"));
            $end_date = "{$year}-{$month}-{$last_day} 23:59:59";
        } else {
            $start_date = "{$year}-01-01 00:00:00";
            $end_date = "{$year}-12-31 23:59:59";
        }

        $count_args['meta_query'] = array(
            array(
                'key' => '_EventStartDate',
                'value' => array($start_date, $end_date),
                'compare' => 'BETWEEN',
                'type' => 'DATETIME'
            )
        );
    }
    
    // Apply current taxonomy context if on taxonomy archive
    if (preg_match('#^/events/(category|keyword)/([^/]+)/?#', $current_url, $matches)) {
        $taxonomy_type = $matches[1];
        $term_slug = $matches[2];
        $taxonomy = ($taxonomy_type === 'category') ? 'tribe_events_cat' : 'post_tag';
        $term = get_term_by('slug', $term_slug, $taxonomy);
        
        if ($term) {
            $count_args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => array($term->term_id)
                )
            );
        }
    }
    
    // Apply remaining active filters
    if (!empty($active_filters)) {
        // Apply taxonomy filters
        $taxonomy_filters = array_intersect_key($active_filters, [
            'tribe_events_cat' => true,
            'post_tag' => true,
            'category' => true
        ]);

        if (!empty($taxonomy_filters)) {
            if (!isset($count_args['tax_query'])) {
                $count_args['tax_query'] = array('relation' => 'AND');
            }

            foreach ($taxonomy_filters as $taxonomy => $term_ids) {
                if (!empty($term_ids)) {
                    $count_args['tax_query'][] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => $term_ids,
                        'operator' => 'IN'
                    );
                }
            }
        }

        // Apply custom filters
        $custom_filter_map = [
            'dayofweek' => '_custom_dayofweek_filter',
            'timeperiod' => '_custom_timeperiod_filter', 
            'cost_range' => '_custom_cost_filter',
            'venue_country' => '_custom_venue_country_filter',
            'venue_state' => '_custom_venue_state_filter',
            'venue_city' => '_custom_venue_city_filter',
            'venue_address' => '_custom_venue_address_filter',
            'organizer' => '_custom_organizer_filter'
        ];

        foreach ($custom_filter_map as $filter_key => $query_var) {
            if (!empty($active_filters[$filter_key])) {
                $count_args[$query_var] = $active_filters[$filter_key];
            }
        }
    }
    
    // Run the count query
    $count_query = new WP_Query($count_args);
    return $count_query->posts; // Returns array of post IDs
}

/**
 * OPTIMIZED: Get taxonomy terms with accurate counts
 */
function get_taxonomy_terms_with_counts($taxonomy, $exclude_filter = null) {
    global $wpdb;
    
    // Get filtered event IDs
    $filtered_event_ids = get_filtered_event_counts($exclude_filter);
    
    if (empty($filtered_event_ids)) {
        return array();
    }
    
    // Convert to comma-separated string for SQL
    $event_ids_str = implode(',', array_map('intval', $filtered_event_ids));
    
    // Query to get terms and their counts for the filtered events
    $sql = $wpdb->prepare("
        SELECT t.term_id, t.name, t.slug, COUNT(DISTINCT tr.object_id) as count
        FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        WHERE tt.taxonomy = %s
        AND tr.object_id IN ({$event_ids_str})
        GROUP BY t.term_id, t.name, t.slug
        HAVING count > 0
        ORDER BY t.name ASC
    ", $taxonomy);
    
    $results = $wpdb->get_results($sql);
    
    $terms = array();
    foreach ($results as $result) {
        $terms[$result->term_id] = (object) array(
            'term_id' => $result->term_id,
            'name' => $result->name,
            'slug' => $result->slug,
            'count' => intval($result->count)
        );
    }
    
    return $terms;
}

/**
 * OPTIMIZED: Get organizer options with accurate counts
 */
function get_organizer_options_with_counts($exclude_filter = null) {
    global $wpdb;
    
    // Get filtered event IDs
    $filtered_event_ids = get_filtered_event_counts($exclude_filter);
    
    if (empty($filtered_event_ids)) {
        return array();
    }
    
    $event_ids_str = implode(',', array_map('intval', $filtered_event_ids));
    
    // Query organizers for filtered events
    $sql = "
        SELECT 
            o.ID as organizer_id,
            o.post_title as organizer_name,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_org ON e.ID = pm_org.post_id
            AND pm_org.meta_key = '_EventOrganizerID'
        INNER JOIN {$wpdb->posts} o ON pm_org.meta_value = o.ID
            AND o.post_type = 'tribe_organizer'
            AND o.post_status = 'publish'
        WHERE e.ID IN ({$event_ids_str})
        AND pm_org.meta_value != ''
        GROUP BY o.ID, o.post_title
        HAVING event_count > 0
        ORDER BY event_count DESC, o.post_title ASC
        LIMIT 50
    ";
    
    $results = $wpdb->get_results($sql);
    
    $organizers = array();
    foreach ($results as $result) {
        $organizers[intval($result->organizer_id)] = array(
            'id' => intval($result->organizer_id),
            'name' => $result->organizer_name,
            'display_name' => $result->organizer_name,
            'count' => intval($result->event_count)
        );
    }
    
    return $organizers;
}

/**
 * OPTIMIZED: Get venue countries with accurate counts
 */
function get_venue_countries_with_counts($exclude_filter = null) {
    global $wpdb;
    
    // Get filtered event IDs
    $filtered_event_ids = get_filtered_event_counts($exclude_filter);
    
    if (empty($filtered_event_ids)) {
        return array();
    }
    
    $event_ids_str = implode(',', array_map('intval', $filtered_event_ids));
    
    $sql = "
        SELECT DISTINCT
            pm_country.meta_value as country,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue ON e.ID = pm_venue.post_id
            AND pm_venue.meta_key = '_EventVenueID'
        INNER JOIN {$wpdb->postmeta} pm_country ON pm_venue.meta_value = pm_country.post_id
            AND pm_country.meta_key = '_VenueCountry'
            AND pm_country.meta_value != ''
        WHERE e.ID IN ({$event_ids_str})
        GROUP BY country
        HAVING event_count > 0
        ORDER BY event_count DESC, country ASC
    ";
    
    $results = $wpdb->get_results($sql);
    
    $countries = array();
    foreach ($results as $result) {
        $normalized_country = normalize_venue_country($result->country);
        $countries[$normalized_country] = array(
            'name' => $normalized_country,
            'count' => intval($result->event_count)
        );
    }
    
    return $countries;
}

function get_venue_states_with_counts($country = null, $exclude_filter = null) {
    global $wpdb;
    
    // Get filtered event IDs
    $filtered_event_ids = get_filtered_event_counts($exclude_filter);
    
    if (empty($filtered_event_ids)) {
        return array();
    }
    
    $event_ids_str = implode(',', array_map('intval', $filtered_event_ids));
    
    // Use country parameter or get from active filters
    $active_filters = get_active_filters_universal();
    $filter_country = $country ?: ($active_filters['venue_country'][0] ?? null);
    
    if (!$filter_country) {
        return array(); // No country context
    }
    
    $sql = $wpdb->prepare("
        SELECT DISTINCT
            pm_state.meta_value as state,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue ON e.ID = pm_venue.post_id
            AND pm_venue.meta_key = '_EventVenueID'
        INNER JOIN {$wpdb->posts} v ON pm_venue.meta_value = v.ID
            AND v.post_type = 'tribe_venue'
            AND v.post_status = 'publish'
        INNER JOIN {$wpdb->postmeta} pm_state ON v.ID = pm_state.post_id
            AND pm_state.meta_key = '_VenueState'
            AND pm_state.meta_value != ''
        INNER JOIN {$wpdb->postmeta} pm_country ON v.ID = pm_country.post_id
            AND pm_country.meta_key = '_VenueCountry'
            AND pm_country.meta_value = %s
        WHERE e.ID IN ({$event_ids_str})
        GROUP BY state
        HAVING event_count > 0
        ORDER BY event_count DESC, state ASC
    ", $filter_country);
    
    $results = $wpdb->get_results($sql);
    
    $states = array();
    if ($results && !is_wp_error($results)) {
        foreach ($results as $row) {
            if (!empty($row->state)) {
                $normalized_state = normalize_venue_state($row->state);
                $states[$normalized_state] = array(
                    'name' => $normalized_state,
                    'count' => intval($row->event_count)
                );
            }
        }
    }
    
    return $states;
}

/**
 * Get venue cities with accurate counts (optimized)
 */
function get_venue_cities_with_counts($country = null, $state = null, $exclude_filter = null) {
    global $wpdb;
    
    // Get filtered event IDs
    $filtered_event_ids = get_filtered_event_counts($exclude_filter);
    
    if (empty($filtered_event_ids)) {
        return array();
    }
    
    $event_ids_str = implode(',', array_map('intval', $filtered_event_ids));
    
    // Use parameters or get from active filters
    $active_filters = get_active_filters_universal();
    $filter_country = $country ?: ($active_filters['venue_country'][0] ?? null);
    $filter_state = $state ?: ($active_filters['venue_state'][0] ?? null);
    
    if (!$filter_country) {
        return array(); // No country context
    }
    
    $query_params = [$filter_country];
    $state_condition = '';
    
    if ($filter_state) {
        $state_condition = " AND pm_state.meta_value = %s";
        $query_params[] = $filter_state;
    }
    
    $sql = $wpdb->prepare("
        SELECT DISTINCT
            pm_city.meta_value as city,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue ON e.ID = pm_venue.post_id
            AND pm_venue.meta_key = '_EventVenueID'
        INNER JOIN {$wpdb->posts} v ON pm_venue.meta_value = v.ID
            AND v.post_type = 'tribe_venue'
            AND v.post_status = 'publish'
        INNER JOIN {$wpdb->postmeta} pm_city ON v.ID = pm_city.post_id
            AND pm_city.meta_key = '_VenueCity'
            AND pm_city.meta_value != ''
        INNER JOIN {$wpdb->postmeta} pm_state ON v.ID = pm_state.post_id
            AND pm_state.meta_key = '_VenueState'
        INNER JOIN {$wpdb->postmeta} pm_country ON v.ID = pm_country.post_id
            AND pm_country.meta_key = '_VenueCountry'
            AND pm_country.meta_value = %s
        {$state_condition}
        WHERE e.ID IN ({$event_ids_str})
        GROUP BY city
        HAVING event_count > 0
        ORDER BY event_count DESC, city ASC
    ", ...$query_params);
    
    $results = $wpdb->get_results($sql);
    
    $cities = array();
    if ($results && !is_wp_error($results)) {
        foreach ($results as $row) {
            if (!empty($row->city)) {
                $cities[$row->city] = array(
                    'name' => $row->city,
                    'count' => intval($row->event_count)
                );
            }
        }
    }
    
    return $cities;
}

/**
 * Get venue addresses with accurate counts (optimized)
 */
function get_venue_addresses_with_counts($country = null, $state = null, $city = null, $exclude_filter = null) {
    global $wpdb;
    
    // Get filtered event IDs
    $filtered_event_ids = get_filtered_event_counts($exclude_filter);
    
    if (empty($filtered_event_ids)) {
        return array();
    }
    
    $event_ids_str = implode(',', array_map('intval', $filtered_event_ids));
    
    // Use parameters or get from active filters
    $active_filters = get_active_filters_universal();
    $filter_country = $country ?: ($active_filters['venue_country'][0] ?? null);
    
    if (!$filter_country) {
        return array(); // No country context
    }
    
    $query_params = [$filter_country];
    $additional_conditions = '';
    
    if ($state) {
        $additional_conditions .= " AND pm_state.meta_value = %s";
        $query_params[] = $state;
    }
    
    if ($city) {
        $additional_conditions .= " AND pm_city.meta_value = %s"; 
        $query_params[] = $city;
    }
    
    $sql = $wpdb->prepare("
        SELECT DISTINCT
            v.ID as venue_id,
            v.post_title as venue_name,
            pm_address.meta_value as address,
            pm_city.meta_value as city,
            pm_state.meta_value as state,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue ON e.ID = pm_venue.post_id
            AND pm_venue.meta_key = '_EventVenueID'
        INNER JOIN {$wpdb->posts} v ON pm_venue.meta_value = v.ID
            AND v.post_type = 'tribe_venue'
            AND v.post_status = 'publish'
        INNER JOIN {$wpdb->postmeta} pm_address ON v.ID = pm_address.post_id
            AND pm_address.meta_key = '_VenueAddress'
            AND pm_address.meta_value != ''
        INNER JOIN {$wpdb->postmeta} pm_city ON v.ID = pm_city.post_id
            AND pm_city.meta_key = '_VenueCity'
        INNER JOIN {$wpdb->postmeta} pm_state ON v.ID = pm_state.post_id
            AND pm_state.meta_key = '_VenueState'
        INNER JOIN {$wpdb->postmeta} pm_country ON v.ID = pm_country.post_id
            AND pm_country.meta_key = '_VenueCountry'
            AND pm_country.meta_value = %s
        {$additional_conditions}
        WHERE e.ID IN ({$event_ids_str})
        GROUP BY venue_id, venue_name, address, city, state
        HAVING event_count > 0
        ORDER BY event_count DESC, venue_name ASC
        LIMIT 100
    ", ...$query_params);
    
    $results = $wpdb->get_results($sql);
    
    $addresses = array();
    if ($results && !is_wp_error($results)) {
        foreach ($results as $row) {
            if (!empty($row->address)) {
                // Build display name with location context
                $location_parts = array_filter([$row->city, $row->state]);
                $location_str = !empty($location_parts) ? ' - ' . implode(', ', $location_parts) : '';
                
                $display_name = $row->venue_name . $location_str;
                
                $addresses[intval($row->venue_id)] = array(
                    'id' => intval($row->venue_id),
                    'name' => $row->venue_name,
                    'address' => $row->address,
                    'city' => $row->city,
                    'state' => $row->state,
                    'display_name' => $display_name,
                    'count' => intval($row->event_count)
                );
            }
        }
    }
    
    return $addresses;
}

/**
 * ===============================================================================
 * Event Taxonomy Pretty Parameters
 * ===============================================================================
 */

/**
 * Convert taxonomy filters to pretty URL parameters
 * Maps internal taxonomy names to user-friendly parameter names
 * 
 * @param array $taxonomy_filters Array of taxonomy => term_ids
 * @return array Array of pretty_param => values for URL building
 */
function taxonomy_filters_to_pretty_params($taxonomy_filters) {
    $pretty_params = array();

    foreach ($taxonomy_filters as $taxonomy => $term_ids) {
        if (!empty($term_ids)) {
            // Map to pretty parameter names
            if ($taxonomy === 'tribe_events_cat' || $taxonomy === 'category') {
                $pretty_params['category'] = implode(',', $term_ids);
            } elseif ($taxonomy === 'post_tag') {
                $pretty_params['keyword'] = implode(',', $term_ids);
            }
        }
    }

    return $pretty_params;
}

/**
 * ===============================================================================
 * Event Taxonomy Dropdown functions
 * ===============================================================================
 */

// taxonomies for post type
function get_post_type_taxonomies($post_type) {
    $taxonomies = array();

    if ($post_type === 'tribe_events') {
        $taxonomies = array(
            'category' => array(
                'name' => 'tribe_events_cat',
                'label' => 'Event Categories'
            ),
            'tag' => array(
                'name' => 'post_tag',
                'label' => 'Tags'
            )
        );
    } else {
        $taxonomies = array(
            'category' => array(
                'name' => 'category',
                'label' => 'Categories'
            ),
            'tag' => array(
                'name' => 'post_tag',
                'label' => 'Tags'
            )
        );
    }

    return $taxonomies;
}

// render_taxonomy_dropdown with date archive filtering
function render_taxonomy_dropdown($taxonomy, $post_type, $id, $multiple = false) {
    // Get terms with accurate counts based on current filters
    $exclude_filter = ($taxonomy === 'tribe_events_cat') ? 'tribe_events_cat' : 'post_tag';
    $terms = get_taxonomy_terms_with_counts($taxonomy, $exclude_filter);

    if (empty($terms)) {
        return '<select disabled><option>No terms available</option></select>';
    }

    $multiple_attr = $multiple ? 'multiple' : '';
    $name_attr = $multiple ? $taxonomy . '[]' : $taxonomy;

    $html = '<select class="taxonomy-filter-select" name="' . $name_attr . '" id="' . $id . '" data-taxonomy="' . $taxonomy . '" ' . $multiple_attr . '>';

    if (!$multiple) {
        $html .= '<option value="">All Event Categories</option>';
    }

    // Check for pre-selection based on current filters
    $active_filters = get_active_filters_universal();
    $selected_terms = array();
    if ($taxonomy === 'tribe_events_cat' && isset($active_filters['tribe_events_cat'])) {
        $selected_terms = $active_filters['tribe_events_cat'];
    } elseif ($taxonomy === 'post_tag' && isset($active_filters['post_tag'])) {
        $selected_terms = $active_filters['post_tag'];
    }

    foreach ($terms as $term) {
        $selected = in_array($term->term_id, $selected_terms) ? ' selected' : '';
        $html .= '<option value="' . $term->term_id . '"' . $selected . '>' . $term->name . ' (' . $term->count . ')</option>';
    }

    $html .= '</select>';

    return $html;
}

/**
 * ===============================================================================
 * Event Filter Render Shortcode Options
 * ===============================================================================
 */

function render_search_filter() {
    // Get current search value from URL
    $current_search = '';
    if (!empty($_GET['search'])) {
        $current_search = sanitize_text_field($_GET['search']);
    } elseif (!empty($_GET['s'])) {
        $current_search = sanitize_text_field($_GET['s']);
    }
    
    $html = '<div class="filter-group search-filter-group">';
    $html .= '<label for="event-search-input">Search Events:</label>';
    $html .= '<input type="text" id="event-search-input" name="search" ';
    $html .= 'value="' . esc_attr($current_search) . '" ';
    $html .= 'placeholder="Search events, venues, organizers..." ';
    $html .= 'class="event-search-input">';
    $html .= '</div>';
    
    // Add simple CSS
    $html .= '<style>
    .search-filter-group {
        margin-bottom: 15px;
    }
    .search-filter-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .event-search-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        background: #fff;
    }
    .event-search-input:focus {
        outline: none;
        border-color: #0073aa;
        box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
    }
    </style>';
    
    return $html;
}

function render_cost_filter($max_cost = 5000) {
    $current_min_cost = 0;
    $current_max_cost = $max_cost; // Default to max_cost

    if (!empty($_GET['cost'])) {
        $cost_value = sanitize_text_field($_GET['cost']);

        if (strpos($cost_value, ',') !== false) {
            // It's a range format: "min,max"
            $cost_range = explode(',', $cost_value);
            if (count($cost_range) === 2) {
                $min_val = is_numeric($cost_range[0]) ? floatval($cost_range[0]) : 0;
                $max_val = is_numeric($cost_range[1]) ? floatval($cost_range[1]) : $max_cost;

                // Ensure min is not greater than max and clamp values
                $current_min_cost = max(0, min($min_val, $max_val)); // Clamp min to 0 and ensure it's not above max_val
                $current_max_cost = min($max_cost, max($min_val, $max_val)); // Clamp max to max_cost and ensure it's not below min_val
            }
        } elseif (is_numeric($cost_value)) {
            // It's a single value format: treat as max cost, min remains 0
            $current_max_cost = min($max_cost, floatval($cost_value)); // Clamp to max_cost
        }
        // If cost_value is non-numeric and not a range, defaults remain (0, $max_cost)
    }

    // Ensure values are within bounds after parsing/defaulting
    $current_min_cost = max(0, (int)$current_min_cost);
    $current_max_cost = min($max_cost, (int)$current_max_cost);

    // If for some reason min becomes greater than max, adjust max to min
    if ($current_min_cost > $current_max_cost) {
        $current_max_cost = $current_min_cost;
    }

    $html = '<div class="card">';
    $html .= '  <div class="card-header">';
    $html .= '    <p class="mb-0">Event Cost Range ($)</p>';
    $html .= '  </div>';
    $html .= '  <div class="card-body">';
    $html .= '    <div class="mb-4">';
    $html .= '      <label class="form-label mb-3">Price Range:</label>';
    $html .= '      <div class="range">';
    $html .= '        <div class="range-slider">';
    $html .= '          <span class="range-selected"></span>';
    $html .= '        </div>';
    $html .= '        <div class="range-input">';
    $html .= '          <input type="range" class="min" min="0" max="' . esc_attr($max_cost) . '" value="' . esc_attr($current_min_cost) . '" step="10">';
    $html .= '          <input type="range" class="max" min="0" max="' . esc_attr($max_cost) . '" value="' . esc_attr($current_max_cost) . '" step="10">';
    $html .= '        </div>';
    $html .= '        <div class="range-price">';
    $html .= '          <label for="min-price-input">Min</label>';
    $html .= '          <input type="number" name="min-price-input" value="' . esc_attr($current_min_cost) . '" id="min-price-input">';
    $html .= '          <label for="max-price-input">Max</label>';
    $html .= '          <input type="number" name="max-price-input" value="' . esc_attr($current_max_cost) . '" id="max-price-input">';
    $html .= '        </div>';
    $html .= '      </div>';
    $html .= '    </div>';
    $html .= '    <p class="mt-3 text-muted">Use the sliders or input boxes to define your desired price range. $0 includes free events.</p>';
    $html .= '  </div>';
    $html .= '</div>';
    
    return $html;
}


/**
 * Get organizer options for filter (following same pattern as venues)
 */
function get_organizer_options_for_filter() {
    global $wpdb;
    
    $organizer_options = [];
    
    try {
        // Get active filters using centralized function
        $active_filters = get_active_filters();
        
        // Get all organizer posts first (to map any text names to IDs)
        $organizer_posts = $wpdb->get_results("
            SELECT ID, post_title, post_name 
            FROM {$wpdb->posts} 
            WHERE post_type = 'tribe_organizer' 
            AND post_status = 'publish'
        ");
        
        // Create a lookup map for organizer names to IDs
        $organizer_name_to_id = [];
        foreach ($organizer_posts as $post) {
            $organizer_name_to_id[strtolower($post->post_title)] = $post->ID;
            $organizer_name_to_id[strtolower(str_replace(' ', '-', $post->post_title))] = $post->ID;
        }
        
        // METHOD 1: Events with _EventOrganizerID (references to tribe_organizer posts)
        $query1 = "
            SELECT DISTINCT
                o.ID as organizer_id,
                o.post_title as organizer_name,
                'post_reference' as source_type,
                COUNT(DISTINCT e.ID) as event_count
            FROM {$wpdb->posts} e
            INNER JOIN {$wpdb->postmeta} pm_org_id ON e.ID = pm_org_id.post_id
                AND pm_org_id.meta_key = '_EventOrganizerID'
                AND pm_org_id.meta_value != ''
            INNER JOIN {$wpdb->posts} o ON pm_org_id.meta_value = o.ID
                AND o.post_type = 'tribe_organizer'
                AND o.post_status = 'publish'
            WHERE e.post_type = 'tribe_events'
            AND e.post_status IN ('publish', 'future')
            GROUP BY o.ID, o.post_title
        ";
        
        // METHOD 2: Events with direct _EventOrganizer (text-based organizer names)
        $query2 = "
            SELECT DISTINCT
                0 as organizer_id,
                pm_org.meta_value as organizer_name,
                'direct_text' as source_type,
                COUNT(DISTINCT e.ID) as event_count
            FROM {$wpdb->posts} e
            INNER JOIN {$wpdb->postmeta} pm_org ON e.ID = pm_org.post_id
                AND pm_org.meta_key = '_EventOrganizer'
                AND pm_org.meta_value != ''
            WHERE e.post_type = 'tribe_events'
            AND e.post_status IN ('publish', 'future')
            GROUP BY pm_org.meta_value
        ";
        
        // Execute both queries
        $results1 = $wpdb->get_results($query1);
        $results2 = $wpdb->get_results($query2);
        
        // Process results from ID-based method first
        if (!empty($results1)) {
            foreach ($results1 as $row) {
                $key = intval($row->organizer_id);
                $organizer_options[$key] = [
                    'id' => $key,
                    'name' => $row->organizer_name,
                    'display_name' => $row->organizer_name,
                    'count' => intval($row->event_count),
                    'source' => 'post_reference'
                ];
            }
        }
        
        // Process results from text-based method, trying to map to IDs
        if (!empty($results2)) {
            foreach ($results2 as $row) {
                // Try to find an organizer ID for this text name
                $id_key = 0;
                $name = $row->organizer_name;
                $name_lower = strtolower($name);
                $slug = strtolower(str_replace(' ', '-', $name));
                
                // Check if we have an ID mapping for this name
                if (isset($organizer_name_to_id[$name_lower])) {
                    $id_key = $organizer_name_to_id[$name_lower];
                } elseif (isset($organizer_name_to_id[$slug])) {
                    $id_key = $organizer_name_to_id[$slug];
                }
                
                // If we found an ID, use it as the key
                if ($id_key > 0) {
                    // If this ID already exists, add to its count
                    if (isset($organizer_options[$id_key])) {
                        $organizer_options[$id_key]['count'] += intval($row->event_count);
                    } else {
                        $organizer_options[$id_key] = [
                            'id' => $id_key,
                            'name' => $name,
                            'display_name' => $name,
                            'count' => intval($row->event_count),
                            'source' => 'mapped_text_to_id'
                        ];
                    }
                } else {
                    // Use text hash as fallback (only when absolutely no ID can be found)
                    $fallback_key = 'text_' . md5($name);
                    $organizer_options[$fallback_key] = [
                        'id' => 0,
                        'name' => $name,
                        'display_name' => $name,
                        'count' => intval($row->event_count),
                        'source' => 'direct_text',
                        'text_value' => $name // Store original text value
                    ];
                }
            }
        }
        
        // Sort by event count (descending) and name
        uasort($organizer_options, function($a, $b) {
            if ($a['count'] === $b['count']) {
                return strcmp($a['name'], $b['name']);
            }
            return $b['count'] - $a['count'];
        });
        
        return $organizer_options;
        
    } catch (Exception $e) {
        error_log("Error in get_organizer_options_for_filter: " . $e->getMessage());
        return []; // Return empty array on error
    }
}

/**
 * Render organizer filter with ID-only URLs
 */
function render_organizer_filter($post_type) {
    if ($post_type !== 'tribe_events') {
        return '<p>Organizer filtering only available for events</p>';
    }
    
    // Get organizers with accurate counts
    $organizer_options = get_organizer_options_with_counts('organizer');
    
    // Get currently selected values
    $active_filters = get_active_filters_universal();
    $selected_organizers = $active_filters['organizer'] ?? [];
    
    if (!is_array($selected_organizers)) {
        $selected_organizers = [$selected_organizers];
    }
    
    $selected_organizers = array_map('strval', $selected_organizers);
    $numeric_selected = array_filter($selected_organizers, 'is_numeric');
    
    $html = '<div class="organizer-filter-groups">';
    $html .= '<div class="filter-group">';
    $html .= '<label for="organizer-select">Event Organizer:</label>';
    $html .= '<select id="organizer-select" class="organizer-filter-select" data-filter-type="organizer" multiple>';
    
    $all_selected = empty($numeric_selected) ? 'selected' : '';
    $html .= '<option value="" ' . $all_selected . '>All Organizers</option>';
    
    foreach ($organizer_options as $id => $data) {
        $selected = in_array(strval($id), $numeric_selected) ? 'selected' : '';
        $count_display = $data['count'] > 0 ? ' (' . $data['count'] . ')' : '';
        
        $html .= '<option value="' . esc_attr($id) . '" ' . $selected . ' data-is-id="1">';
        $html .= esc_html($data['display_name']) . $count_display;
        $html .= '</option>';
    }
    
    $html .= '</select>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}


function get_venue_countries_for_filter() {
    global $wpdb;
    
    $countries = [];
    $active_filters = get_active_filters();
    
    // Build base query for events with venues
    $query = "
        SELECT DISTINCT
            pm_country.meta_value as country,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue ON e.ID = pm_venue.post_id
            AND pm_venue.meta_key = '_EventVenueID'
        INNER JOIN {$wpdb->posts} v ON pm_venue.meta_value = v.ID
            AND v.post_type = 'tribe_venue'
            AND v.post_status = 'publish'
        INNER JOIN {$wpdb->postmeta} pm_country ON v.ID = pm_country.post_id
            AND pm_country.meta_key = '_VenueCountry'
            AND pm_country.meta_value != ''
        WHERE e.post_type = 'tribe_events'
        AND e.post_status IN ('publish', 'future')
    ";
    
    // Apply other active filters (taxonomy, date, etc.) but NOT venue_country
    $filter_conditions = [];
    $query_params = [];
    
    // Add taxonomy filters
    $taxonomy_filters = array_intersect_key($active_filters, [
        'tribe_events_cat' => true,
        'post_tag' => true,
        'category' => true
    ]);
    
    foreach ($taxonomy_filters as $taxonomy => $term_ids) {
        if (!empty($term_ids)) {
            $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
            $filter_conditions[] = "e.ID IN (
                SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = %s AND tt.term_id IN ({$placeholders})
            )";
            $query_params[] = $taxonomy;
            $query_params = array_merge($query_params, array_map('intval', $term_ids));
        }
    }
    
    if (!empty($filter_conditions)) {
        $query .= ' AND ' . implode(' AND ', $filter_conditions);
    }
    
    $query .= "
        GROUP BY country
        HAVING event_count > 0
        ORDER BY event_count DESC, country ASC
    ";
    
    if (!empty($query_params)) {
        $results = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
    } else {
        $results = $wpdb->get_results($query);
    }
    
    if ($results && !is_wp_error($results)) {
        foreach ($results as $row) {
            if (!empty($row->country)) {
                // Use existing normalization function
                $normalized_country = normalize_venue_country($row->country);
                $countries[$normalized_country] = [
                    'name' => $normalized_country,
                    'count' => intval($row->event_count)
                ];
            }
        }
    }
    
    return $countries;
}
/*
function get_venue_states_for_filter($country = null) {
    global $wpdb;
    
    $states = [];
    $active_filters = get_active_filters();
    
    // Use country parameter or get from active filters
    $filter_country = $country ?: ($active_filters['venue_country'][0] ?? null);
    
    if (!$filter_country) {
        return $states; // No country context
    }
    
    $query = $wpdb->prepare("
        SELECT DISTINCT
            pm_state.meta_value as state,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue ON e.ID = pm_venue.post_id
            AND pm_venue.meta_key = '_EventVenueID'
        INNER JOIN {$wpdb->posts} v ON pm_venue.meta_value = v.ID
            AND v.post_type = 'tribe_venue'
            AND v.post_status = 'publish'
        INNER JOIN {$wpdb->postmeta} pm_state ON v.ID = pm_state.post_id
            AND pm_state.meta_key = '_VenueState'
            AND pm_state.meta_value != ''
        INNER JOIN {$wpdb->postmeta} pm_country ON v.ID = pm_country.post_id
            AND pm_country.meta_key = '_VenueCountry'
            AND pm_country.meta_value = %s
        WHERE e.post_type = 'tribe_events'
        AND e.post_status IN ('publish', 'future')
        GROUP BY state
        HAVING event_count > 0
        ORDER BY event_count DESC, state ASC
    ", $filter_country);
    
    $results = $wpdb->get_results($query);
    
    if ($results && !is_wp_error($results)) {
        foreach ($results as $row) {
            if (!empty($row->state)) {
                // Use existing normalization function
                $normalized_state = normalize_venue_state($row->state);
                $states[$normalized_state] = [
                    'name' => $normalized_state,
                    'count' => intval($row->event_count)
                ];
            }
        }
    }
    
    return $states;
}

function get_venue_cities_for_filter($country = null, $state = null) {
    global $wpdb;
    
    $cities = [];
    $active_filters = get_active_filters();
    
    // Use parameters or get from active filters
    $filter_country = $country ?: ($active_filters['venue_country'][0] ?? null);
    $filter_state = $state ?: ($active_filters['venue_state'][0] ?? null);
    
    if (!$filter_country) {
        return $cities; // No country context
    }
    
    $query_params = [$filter_country];
    $state_condition = '';
    
    if ($filter_state) {
        $state_condition = " AND pm_state.meta_value = %s";
        $query_params[] = $filter_state;
    }
    
    $query = $wpdb->prepare("
        SELECT DISTINCT
            pm_city.meta_value as city,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue ON e.ID = pm_venue.post_id
            AND pm_venue.meta_key = '_EventVenueID'
        INNER JOIN {$wpdb->posts} v ON pm_venue.meta_value = v.ID
            AND v.post_type = 'tribe_venue'
            AND v.post_status = 'publish'
        INNER JOIN {$wpdb->postmeta} pm_city ON v.ID = pm_city.post_id
            AND pm_city.meta_key = '_VenueCity'
            AND pm_city.meta_value != ''
        INNER JOIN {$wpdb->postmeta} pm_state ON v.ID = pm_state.post_id
            AND pm_state.meta_key = '_VenueState'
        INNER JOIN {$wpdb->postmeta} pm_country ON v.ID = pm_country.post_id
            AND pm_country.meta_key = '_VenueCountry'
            AND pm_country.meta_value = %s
        {$state_condition}
        WHERE e.post_type = 'tribe_events'
        AND e.post_status IN ('publish', 'future')
        GROUP BY city
        HAVING event_count > 0
        ORDER BY event_count DESC, city ASC
    ", ...$query_params);
    
    $results = $wpdb->get_results($query);
    
    if ($results && !is_wp_error($results)) {
        foreach ($results as $row) {
            if (!empty($row->city)) {
                $cities[$row->city] = [
                    'name' => $row->city,
                    'count' => intval($row->event_count)
                ];
            }
        }
    }
    
    return $cities;
}

function get_venue_addresses_for_filter($country = null, $state = null, $city = null) {
    global $wpdb;
    
    $addresses = [];
    $active_filters = get_active_filters();
    
    // Use parameters or get from active filters
    $filter_country = $country ?: ($active_filters['venue_country'][0] ?? null);
    
    if (!$filter_country) {
        return $addresses; // No country context
    }
    
    $query_params = [$filter_country];
    $additional_conditions = '';
    
    if ($state) {
        $additional_conditions .= " AND pm_state.meta_value = %s";
        $query_params[] = $state;
    }
    
    if ($city) {
        $additional_conditions .= " AND pm_city.meta_value = %s"; 
        $query_params[] = $city;
    }
    
    $query = $wpdb->prepare("
        SELECT DISTINCT
            v.ID as venue_id,
            v.post_title as venue_name,
            pm_address.meta_value as address,
            COUNT(DISTINCT e.ID) as event_count
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue ON e.ID = pm_venue.post_id
            AND pm_venue.meta_key = '_EventVenueID'
        INNER JOIN {$wpdb->posts} v ON pm_venue.meta_value = v.ID
            AND v.post_type = 'tribe_venue'
            AND v.post_status = 'publish'
        INNER JOIN {$wpdb->postmeta} pm_address ON v.ID = pm_address.post_id
            AND pm_address.meta_key = '_VenueAddress'
            AND pm_address.meta_value != ''
        INNER JOIN {$wpdb->postmeta} pm_city ON v.ID = pm_city.post_id
            AND pm_city.meta_key = '_VenueCity'
        INNER JOIN {$wpdb->postmeta} pm_state ON v.ID = pm_state.post_id
            AND pm_state.meta_key = '_VenueState'
        INNER JOIN {$wpdb->postmeta} pm_country ON v.ID = pm_country.post_id
            AND pm_country.meta_key = '_VenueCountry'
            AND pm_country.meta_value = %s
        {$additional_conditions}
        WHERE e.post_type = 'tribe_events'
        AND e.post_status IN ('publish', 'future')
        GROUP BY venue_id, venue_name, address
        HAVING event_count > 0
        ORDER BY event_count DESC, venue_name ASC
    ", ...$query_params);
    
    $results = $wpdb->get_results($query);
    
    if ($results && !is_wp_error($results)) {
        foreach ($results as $row) {
            if (!empty($row->address)) {
                $display_name = $row->venue_name . ' - ' . $row->address;
                $addresses[intval($row->venue_id)] = [
                    'id' => intval($row->venue_id),
                    'name' => $row->venue_name,
                    'address' => $row->address,
                    'display_name' => $display_name,
                    'count' => intval($row->event_count)
                ];
            }
        }
    }
    
    return $addresses;
}
*/
function render_venue_filter($post_type) {
    if ($post_type !== 'tribe_events') {
        return '<p>Venue filtering only available for events</p>';
    }
    
    // Get current selections from active filters
    $active_filters = get_active_filters_universal();
    $selected_country = $active_filters['venue_country'][0] ?? '';
    $selected_states = $active_filters['venue_state'] ?? [];
    $selected_cities = $active_filters['venue_city'] ?? [];
    $selected_addresses = $active_filters['venue_address'] ?? [];
    
    // Get available options with accurate counts
    $countries = get_venue_countries_with_counts('venue_country');
    
    $html = '<div class="venue-filter-groups">';
    
    // Country filter (single select)
    $html .= '<div class="filter-group country-filter-group">';
    $html .= '<label for="venue-country-select">Country:</label>';
    $html .= '<select id="venue-country-select" class="venue-filter-select" data-filter-type="venue_country">';
    $html .= '<option value="">All Countries</option>';
    
    foreach ($countries as $country_name => $country_data) {
        $selected = ($selected_country === $country_name) ? 'selected' : '';
        $count_display = $country_data['count'] > 0 ? ' (' . $country_data['count'] . ')' : '';
        
        $html .= '<option value="' . esc_attr($country_name) . '" ' . $selected . '>';
        $html .= esc_html($country_name) . $count_display;
        $html .= '</option>';
    }
    
    $html .= '</select>';
    $html .= '</div>';
    
    // State filter (multi-select, hidden initially)
    $html .= '<div class="filter-group state-filter-group" style="' . 
             ($selected_country ? '' : 'display: none;') . '">';
    $html .= '<label for="venue-state-select">State/Province:</label>';
    $html .= '<select id="venue-state-select" class="venue-filter-select" data-filter-type="venue_state" multiple>';
    
    if ($selected_country) {
        $states = get_venue_states_with_counts($selected_country, 'venue_state');
        foreach ($states as $state_name => $state_data) {
            $selected = in_array($state_name, $selected_states) ? 'selected' : '';
            $count_display = $state_data['count'] > 0 ? ' (' . $state_data['count'] . ')' : '';
            
            $html .= '<option value="' . esc_attr($state_name) . '" ' . $selected . '>';
            $html .= esc_html($state_name) . $count_display;
            $html .= '</option>';
        }
    }
    
    $html .= '</select>';
    $html .= '</div>';
    
    // City filter (multi-select, hidden initially) 
    $html .= '<div class="filter-group city-filter-group" style="' .
             ((!empty($selected_states)) ? '' : 'display: none;') . '">';
    $html .= '<label for="venue-city-select">City:</label>';
    $html .= '<select id="venue-city-select" class="venue-filter-select" data-filter-type="venue_city" multiple>';
    
    if (!empty($selected_states)) {
        $cities = get_venue_cities_with_counts($selected_country, $selected_states[0], 'venue_city');
        foreach ($cities as $city_name => $city_data) {
            $selected = in_array($city_name, $selected_cities) ? 'selected' : '';
            $count_display = $city_data['count'] > 0 ? ' (' . $city_data['count'] . ')' : '';
            
            $html .= '<option value="' . esc_attr($city_name) . '" ' . $selected . '>';
            $html .= esc_html($city_name) . $count_display;
            $html .= '</option>';
        }
    }
    
    $html .= '</select>';
    $html .= '</div>';
    
    // Address filter (multi-select, always available when country selected)
    $html .= '<div class="filter-group address-filter-group" style="' .
             ($selected_country ? '' : 'display: none;') . '">';
    $html .= '<label for="venue-address-select">Venue/Address:</label>';
    $html .= '<select id="venue-address-select" class="venue-filter-select" data-filter-type="venue_address" multiple>';
    
    if ($selected_country) {
        $addresses = get_venue_addresses_with_counts($selected_country, 
                                                   !empty($selected_states) ? $selected_states[0] : null, 
                                                   !empty($selected_cities) ? $selected_cities[0] : null, 
                                                   'venue_address');
        foreach ($addresses as $venue_id => $address_data) {
            $selected = in_array($venue_id, $selected_addresses) ? 'selected' : '';
            $count_display = $address_data['count'] > 0 ? ' (' . $address_data['count'] . ')' : '';
            
            $html .= '<option value="' . esc_attr($venue_id) . '" ' . $selected . '>';
            $html .= esc_html($address_data['display_name']) . $count_display;
            $html .= '</option>';
        }
    }
    
    $html .= '</select>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    // Add CSS for better UX
    $html .= '<style>
.venue-filter-groups .filter-group {
    margin-bottom: 15px;
}
.venue-filter-groups label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}
.venue-filter-groups select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
}
.venue-filter-groups select[multiple] {
    height: 120px;
}
.venue-filter-groups select:disabled {
    background: #f5f5f5;
    color: #999;
}
</style>';
    
    return $html;
}

/**
 * HELPER: Update venue options for backwards compatibility
 */
function get_venue_states_for_filter($country = null) {
    return get_venue_states_with_counts($country);
}

function get_venue_cities_for_filter($country = null, $state = null) {
    return get_venue_cities_with_counts($country, $state);
}

function get_venue_addresses_for_filter($country = null, $state = null, $city = null) {
    return get_venue_addresses_with_counts($country, $state, $city);
}

function event_filter_shortcode($atts) {
    // Redirect to universal filter
    return universal_event_filter_shortcode($atts);
}

/*
 * ===============================================================================
 * AJAX Handler Update Filters
 * ===============================================================================
 */

/**
 * Get updated filter options with accurate counts
 */
function ajax_get_updated_filters() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'filter_update_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Get current filters from request
    $current_filters = [];
    
    // Parse filters from POST data
    if (!empty($_POST['filters'])) {
        $filter_data = $_POST['filters'];
        
        // Convert JavaScript filter object to PHP array
        foreach ($filter_data as $filter_type => $filter_values) {
            if (!empty($filter_values)) {
                $current_filters[$filter_type] = $filter_values;
            }
        }
    }
    
    // Also check URL parameters as fallback
    $url_filters = get_active_filters_universal();
    $current_filters = array_merge($url_filters, $current_filters);
    
    // Generate updated filter HTML
    $response_data = [];
    
    // Get updated category options
    if (isset($_POST['update_categories']) && $_POST['update_categories'] === 'true') {
        $exclude_filter = 'tribe_events_cat';
        $updated_categories = render_taxonomy_dropdown_optimized('tribe_events_cat', 'tribe_events', 'category-filter');
        $response_data['categories_html'] = $updated_categories;
    }
    
    // Get updated tag options
    if (isset($_POST['update_tags']) && $_POST['update_tags'] === 'true') {
        $exclude_filter = 'post_tag';
        $updated_tags = render_taxonomy_dropdown_optimized('post_tag', 'tribe_events', 'tag-filter', true);
        $response_data['tags_html'] = $updated_tags;
    }
    
    // Get updated organizer options
    if (isset($_POST['update_organizers']) && $_POST['update_organizers'] === 'true') {
        $updated_organizers = render_organizer_filter_optimized('tribe_events');
        $response_data['organizers_html'] = $updated_organizers;
    }
    
    // Get updated venue options
    if (isset($_POST['update_venues']) && $_POST['update_venues'] === 'true') {
        $updated_venues = render_venue_filter_optimized('tribe_events');
        $response_data['venues_html'] = $updated_venues;
    }
    
    // Get event count for reference
    $filtered_event_ids = get_filtered_event_counts();
    $response_data['event_count'] = count($filtered_event_ids);
    $response_data['current_filters'] = $current_filters;
    
    wp_send_json_success($response_data);
}

// Register AJAX handlers
add_action('wp_ajax_get_updated_filters', 'ajax_get_updated_filters');
add_action('wp_ajax_nopriv_get_updated_filters', 'ajax_get_updated_filters');

/*
 * ===============================================================================
 * Event Filter Shortcode
 * ===============================================================================
 */

// New universal shortcode (add this)
function universal_event_filter_shortcode($atts) {
    $atts = shortcode_atts(array(
        'ajax_target' => '#events-container'
    ), $atts, 'universal_event_filter');

    ob_start();
    ?>
    <div class="universal-event-filter-container" data-ajax-target="<?php echo esc_attr($atts['ajax_target']); ?>">
        <!-- Search Filter -->
        <div class="filter-section search-filters">
            <?php echo render_search_filter(); ?>
        </div>

        <!-- Category Filter - ALWAYS shown -->
        <div class="filter-group">
            <label for="category-filter">Event Categories:</label>
            <?php echo render_taxonomy_dropdown('tribe_events_cat', 'tribe_events', 'category-filter'); ?>
        </div>

        <!-- Tag Filter - ALWAYS shown -->
        <div class="filter-group">
            <label for="tag-filter">Tags:</label>
            <?php echo render_taxonomy_dropdown('post_tag', 'tribe_events', 'tag-filter', true); ?>
        </div>

        <!-- Day of Week Filter - ALWAYS shown -->
        <div class="filter-group">
            <label>Day of Week:</label>
            <div class="dayofweek-filter-checkboxes">
                <?php
                $days = array(1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday');
                $selected_days = !empty($_GET['dayofweek']) ? explode(',', $_GET['dayofweek']) : array();

                foreach ($days as $day_num => $day_name):
                    $checked = in_array($day_num, $selected_days) ? 'checked' : '';
                ?>
                    <label class="dayofweek-checkbox">
                        <input type="checkbox" name="dayofweek[]" value="<?php echo $day_num; ?>" <?php echo $checked; ?> class="dayofweek-filter-checkbox">
                        <?php echo $day_name; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Time Period Filter - ALWAYS shown -->
        <div class="filter-group">
            <label>Time Period:</label>
            <div class="timeperiod-filter-checkboxes">
                <?php
                $time_periods = array(
                    'morning' => 'Morning (6 AM - 12 PM)',
                    'afternoon' => 'Afternoon (12 PM - 5 PM)', 
                    'evening' => 'Evening (5 PM - 9 PM)',
                    'night' => 'Night (9 PM - 6 AM)'
                );
                $selected_periods = !empty($_GET['timeperiod']) ? explode(',', $_GET['timeperiod']) : array();

                foreach ($time_periods as $period_key => $period_label):
                    $checked = in_array($period_key, $selected_periods) ? 'checked' : '';
                ?>
                    <label class="timeperiod-checkbox">
                        <input type="checkbox" name="timeperiod[]" value="<?php echo $period_key; ?>" <?php echo $checked; ?> class="timeperiod-filter-checkbox">
                        <?php echo $period_label; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cost Filter - ALWAYS shown -->
        <div class="filter-section cost-filters">
            <h4>Cost Range</h4>
            <?php echo render_cost_filter(5000); ?>
        </div>

        <!-- Organizer Filter - ALWAYS shown -->
        <div class="filter-section organizer-filters">
            <h4>Organizer</h4>
            <?php echo render_organizer_filter('tribe_events'); ?>
        </div>

        <!-- Venue Filter - ALWAYS shown -->
        <div class="filter-section venue-filters">
            <h4>Location</h4>
            <?php echo render_venue_filter('tribe_events'); ?>
        </div>

        <!-- Featured/Virtual Filter  
        <div class="filter-section special-filters">
            <h4>Special Events</h4>
            <?php //echo render_featured_virtual_filters(); ?>
        </div>
        -->

        <button type="button" class="clear-filters">Clear All Filters</button>
    </div>

    <style>
    .universal-event-filter-container {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .filter-group {
        margin-bottom: 15px;
    }
    .filter-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .dayofweek-filter-checkboxes,
    .timeperiod-filter-checkboxes {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .dayofweek-checkbox,
    .timeperiod-checkbox {
        display: flex;
        align-items: center;
        font-weight: normal;
        margin-right: 15px;
        margin-bottom: 5px;
    }
    .dayofweek-checkbox input,
    .timeperiod-checkbox input {
        margin-right: 5px;
    }
    .clear-filters {
        background: #dc3545;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
    }
    .clear-filters:hover {
        background: #c82333;
    }
    </style>
    <?php
    return ob_get_clean();
}

// Register both shortcodes
add_shortcode('universal_event_filter', 'universal_event_filter_shortcode');

/*
 * ===============================================================================
 * Calendar Replacement
 * ===============================================================================
 */

 // Force calendar to include future posts
function allow_future_events($query) {
    // Only for non-admin, main queries
    if (!is_admin() && $query->is_main_query()) {

        $post_type = $query->get('post_type');

        // Method 1: Direct post_type queries
        if ($post_type === 'tribe_events') {
            $query->set('post_status', array('publish', 'future'));
        }

        // Method 2: Event archive pages
        elseif (is_post_type_archive('tribe_events')) {
            $query->set('post_status', array('publish', 'future'));
        }

        // Method 3: Event category pages (/events/category/business/)
        elseif (is_tax('tribe_events_cat')) {
            $query->set('post_type', 'tribe_events');
            $query->set('post_status', array('publish', 'future'));
        }

        // Method 4: Event keyword/tag pages (/events/keyword/networking/)
        elseif (is_tag()) {
            // Check if this is an events context (via URL pattern)
            $current_url = $_SERVER['REQUEST_URI'];
            if (strpos($current_url, '/events/') === 0) {
                $query->set('post_type', 'tribe_events');
                $query->set('post_status', array('publish', 'future'));
            }
        }

        // Method 5: Date archives for events (/events/2024/12/)
        elseif (is_date()) {
            $current_url = $_SERVER['REQUEST_URI'];
            if (strpos($current_url, '/events/') === 0) {
                $query->set('post_type', 'tribe_events');
                $query->set('post_status', array('publish', 'future'));
            }
        }
    }
}
add_action('pre_get_posts', 'allow_future_events');

// Global flag to track when we're in events calendar mode
global $calendar_using_events;
$calendar_using_events = false;

function get_first_event_year($post_type) {
    global $wpdb;

    if ($post_type === 'tribe_events') {
        // Get earliest event start date
        $earliest = $wpdb->get_var($wpdb->prepare("
            SELECT MIN(meta_value) 
            FROM {$wpdb->postmeta} pm 
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE pm.meta_key = '_EventStartDate' 
            AND p.post_status IN ('publish', 'future')
            AND p.post_type = %s
        ", $post_type));

        if ($earliest) {
            return date('Y', strtotime($earliest));
        }
    } else {
        // Get earliest post date for other post types
        $earliest = $wpdb->get_var($wpdb->prepare("
            SELECT MIN(post_date) 
            FROM {$wpdb->posts} 
            WHERE post_type = %s 
            AND p.post_status IN ('publish', 'future')
        ", $post_type));

        if ($earliest) {
            return date('Y', strtotime($earliest));
        }
    }

    // Fallback to 5 years ago if no content found
    return date('Y') - 5;
}

function add_calendar_dropdowns($calendar_output, $args) {
    global $calendar_post_type, $show_calendar_dropdowns, $show_calendar_today, $calendar_ajax_target;

    // Only add dropdowns if enabled and for our custom calendar
    if (!$show_calendar_dropdowns) {
        return $calendar_output;
    }

    // Get current date - use actual current date by default
    $current_year = date('Y');
    $current_month = date('m');

    // If on date archive, get from URL detection
    if (is_on_custom_post_type_date_archive()) {
        $archive_info = is_custom_post_type_date_archive_by_url();
        if ($archive_info) {
            $current_year = $archive_info['year'];
            $current_month = $archive_info['month'] ?: date('m');
        }
    }

    // Month dropdown
    $month_dropdown = '<select id="calendar_month" class="calendar-dropdown">';
    for ($i = 1; $i <= 12; $i++) {
        $month_num = str_pad($i, 2, '0', STR_PAD_LEFT);
        $month_name = date('F', mktime(0, 0, 0, $i, 1));
        $selected = ($month_num == $current_month) ? 'selected' : '';
        $month_dropdown .= '<option value="' . $month_num . '" ' . $selected . '>' . $month_name . '</option>';
    }
    $month_dropdown .= '</select>';

    // Year dropdown - get range from first content to current year
    $start_year = get_first_event_year($calendar_post_type);
    $end_year = max(date('Y'), intval($current_year)); // Always include current year

    $year_dropdown = '<select id="calendar_year" class="calendar-dropdown">';
    for ($year = $start_year; $year <= $end_year; $year++) {
        $selected = ($year == $current_year) ? 'selected' : '';
        $year_dropdown .= '<option value="' . $year . '" ' . $selected . '>' . $year . '</option>';
    }
    $year_dropdown .= '</select>';

    // Today button
    $today_button = '';
    if ($show_calendar_today) {
        $today_button = '<button type="button" id="calendar_today" class="calendar-today-btn">Today</button>';
    }

    // Reset button
    //$reset_button = '<button type="button" id="calendar_reset" class="calendar-reset-btn">Reset</button>';

    // Ajax target for JavaScript
    $ajax_target = $calendar_ajax_target ?: '#primary';

    // Wrap calendar with dropdowns and controls
    $dropdown_html = '<div class="calendar-controls" data-ajax-target="' . esc_attr($ajax_target) . '" data-post-type="' . esc_attr($calendar_post_type) . '">';
    $dropdown_html .= '<div class="calendar-nav-controls">';
    $dropdown_html .= '<label for="calendar_month">Month:</label> ' . $month_dropdown;
    $dropdown_html .= ' <label for="calendar_year">Year:</label> ' . $year_dropdown;
    $dropdown_html .= '</div>';

    if ($today_button) {
        $dropdown_html .= '<div class="calendar-action-controls">';
        $dropdown_html .= $today_button;
        $dropdown_html .= '</div>';
    }

    $dropdown_html .= '</div>';

    // Add some basic styling
    $dropdown_html .= '<style>
    .calendar-controls {
        margin-bottom: 15px;
        padding: 10px;
        background: 
#f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .calendar-nav-controls {
        margin-bottom: 10px;
    }
    .calendar-nav-controls label {
        font-weight: bold;
        margin-right: 5px;
    }
    .calendar-dropdown {
        margin-right: 15px;
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 3px;
    }
    .calendar-today-btn, .calendar-reset-btn {
        margin-right: 10px;
        padding: 5px 10px;
        background: 
#0073aa;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    .calendar-today-btn:hover, .calendar-reset-btn:hover {
        background: 
#005a87;
    }
    .calendar-reset-btn {
        background: #666;
    }
    .calendar-reset-btn:hover {
        background: #444;
    }
    </style>';

    return $dropdown_html . $calendar_output;
}
/*
function get_events_month_link($year, $month) {
    global $wp_rewrite;
    
    $base_url = (!$wp_rewrite->using_permalinks()) 
        ? home_url("?post_type=tribe_events&m=$year" . zeroise($month, 2))
        : home_url("/events/$year/" . zeroise($month, 2) . "/");
    
    // Add current filters back
    $current_filters = get_active_filters();
    if (!empty($current_filters)) {
        $filter_params = taxonomy_filters_to_pretty_params($current_filters);
        if (!empty($filter_params)) {
            $separator = ($wp_rewrite->using_permalinks()) ? '?' : '&';
            $base_url .= $separator . http_build_query($filter_params);
        }
    }
    
    return $base_url;
}

function get_events_day_link($year, $month, $day) {
    global $wp_rewrite;
    
    $base_url = (!$wp_rewrite->using_permalinks())
        ? home_url("?post_type=tribe_events&m=$year" . zeroise($month, 2) . zeroise($day, 2))
        : home_url("/events/$year/" . zeroise($month, 2) . "/" . zeroise($day, 2) . "/");
    
    // Add current filters back
    $current_filters = get_active_filters();
    if (!empty($current_filters)) {
        $filter_params = taxonomy_filters_to_pretty_params($current_filters);
        if (!empty($filter_params)) {
            $separator = ($wp_rewrite->using_permalinks()) ? '?' : '&';
            $base_url .= $separator . http_build_query($filter_params);
        }
    }
    
    return $base_url;
}
*/

/**
 * Get events month link with ALL active filters
 */
function get_events_month_link($year, $month) {
    global $wp_rewrite;
    
    $base_url = (!$wp_rewrite->using_permalinks()) 
        ? home_url("?post_type=tribe_events&m=$year" . zeroise($month, 2))
        : home_url("/events/$year/" . zeroise($month, 2) . "/");
    
    // Add ALL current filters back (not just taxonomy)
    $current_filters = get_active_filters_universal();
    if (!empty($current_filters)) {
        $all_filter_params = convert_all_filters_to_url_params($current_filters);
        if (!empty($all_filter_params)) {
            $separator = ($wp_rewrite->using_permalinks()) ? '?' : '&';
            $base_url .= $separator . http_build_query($all_filter_params);
        }
    }
    
    return $base_url;
}

/**
 * Get events day link with ALL active filters
 */
function get_events_day_link($year, $month, $day) {
    global $wp_rewrite;
    
    $base_url = (!$wp_rewrite->using_permalinks())
        ? home_url("?post_type=tribe_events&m=$year" . zeroise($month, 2) . zeroise($day, 2))
        : home_url("/events/$year/" . zeroise($month, 2) . "/" . zeroise($day, 2) . "/");
    
    // Add ALL current filters back (not just taxonomy)
    $current_filters = get_active_filters_universal();
    if (!empty($current_filters)) {
        $all_filter_params = convert_all_filters_to_url_params($current_filters);
        if (!empty($all_filter_params)) {
            $separator = ($wp_rewrite->using_permalinks()) ? '?' : '&';
            $base_url .= $separator . http_build_query($all_filter_params);
        }
    }
    
    return $base_url;
}

/**
 * Convert ALL filter types to URL parameters (not just taxonomy)
 */
function convert_all_filters_to_url_params($filters) {
    $url_params = array();

    // Search filter
    if (!empty($filters['search'])) {
        $url_params['search'] = $filters['search'];
    }
    
    // Taxonomy filters (existing logic)
    if (!empty($filters['tribe_events_cat'])) {
        $url_params['category'] = implode(',', $filters['tribe_events_cat']);
    }
    if (!empty($filters['post_tag'])) {
        $url_params['keyword'] = implode(',', $filters['post_tag']);
    }
    
    // Day of week filter
    if (!empty($filters['dayofweek'])) {
        $url_params['dayofweek'] = implode(',', $filters['dayofweek']);
    }
    
    // Time period filter
    if (!empty($filters['timeperiod'])) {
        $url_params['timeperiod'] = implode(',', $filters['timeperiod']);
    }
    
    // Cost filter
    if (!empty($filters['cost_range'])) {
        $min = $filters['cost_range']['min'] ?? 0;
        $max = $filters['cost_range']['max'] ?? 5000;
        
        if ($min > 0) {
            $url_params['cost'] = $min . ',' . $max;
        } else {
            $url_params['cost'] = $max;
        }
    }
    
    // Venue filters
    if (!empty($filters['venue_country'])) {
        $url_params['country'] = $filters['venue_country'][0];
    }
    if (!empty($filters['venue_state'])) {
        $url_params['state'] = implode(',', $filters['venue_state']);
    }
    if (!empty($filters['venue_city'])) {
        $url_params['city'] = implode(',', $filters['venue_city']);
    }
    if (!empty($filters['venue_address'])) {
        $url_params['address'] = implode(',', $filters['venue_address']);
    }
    
    // Organizer filter
    if (!empty($filters['organizer'])) {
        $url_params['organizer'] = implode(',', $filters['organizer']);
    }

    // Featured filter
    if (!empty($filters['featured'])) {
        $url_params['featured'] = $filters['featured'];
    }
    
    // Virtual filter
    if (!empty($filters['virtual'])) {
        $url_params['virtual'] = $filters['virtual'];
    }
    
    return $url_params;
}

/**
* Helper to build SQL JOIN and WHERE clauses for event filters.
*
* @param array $event_filters Active filters from get_active_filters().
* @return array Associative array with 'joins', 'where', and 'params' (for wpdb->prepare).
*/
function _get_sql_for_event_filters($event_filters) {
    global $wpdb;

    $dynamic_joins = [];
    $dynamic_where_conditions = [];
    $query_params = []; // Collects parameters for wpdb->prepare

    // --- Taxonomy Filters ---
    // Extract pure taxonomy filters, ignoring other filter types for this SQL build
    $pure_taxonomy_filters = array_filter($event_filters, function($key) {
        return !in_array($key,
            [
                'dayofweek', 'timeperiod', 'cost_range',
                'venue_country', 'venue_state', 'venue_city', 'venue_address',
                '_taxonomy_archive_locked',
            ]
        );
    }, ARRAY_FILTER_USE_KEY);

    $join_counter = 0; // To make aliases unique for multiple taxonomy joins
    if (!empty($pure_taxonomy_filters)) {
        foreach ($pure_taxonomy_filters as $taxonomy => $term_ids) {
            if (is_array($term_ids) && !empty($term_ids)) {
                $join_alias_tr = "tr_tax{$join_counter}"; // term_relationships alias
                $join_alias_tt = "tt_tax{$join_counter}"; // term_taxonomy alias

                $dynamic_joins[] = "
                    JOIN {$wpdb->term_relationships} {$join_alias_tr} ON {$join_alias_tr}.object_id = p.ID
                    JOIN {$wpdb->term_taxonomy} {$join_alias_tt} ON {$join_alias_tt}.term_taxonomy_id = {$join_alias_tr}.term_taxonomy_id";

                $term_placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
                $dynamic_where_conditions[] = "({$join_alias_tt}.taxonomy = %s AND {$join_alias_tt}.term_id IN ({$term_placeholders}))";
                $query_params[] = $taxonomy;
                $query_params = array_merge($query_params, array_map('intval', $term_ids));
                $join_counter++;
            }
        }
    }

    // --- Day of Week Filtering ---
    if (!empty($event_filters['dayofweek'])) {
        $day_conditions = [];
        foreach ((array) $event_filters['dayofweek'] as $day) {
            $day_conditions[] = "DAYOFWEEK(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) = %d";
            $query_params[] = (int)$day;
        }
        if (!empty($day_conditions)) {
            $dynamic_where_conditions[] = '(' . implode(' OR ', $day_conditions) . ')';
        }
    }

    
    // --- Time Period Filtering ---
    if (!empty($event_filters['timeperiod'])) {
        $time_conditions = [];
        foreach ((array) $event_filters['timeperiod'] as $period) {
            switch ($period) {
                case 'morning':
                    $time_conditions[] = "(HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 6 AND HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 12)";
                    break;
                case 'afternoon':
                    $time_conditions[] = "(HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 12 AND HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 17)";
                    break;
                case 'evening':
                    $time_conditions[] = "(HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 17 AND HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 21)";
                    break;
                case 'night':
                    $time_conditions[] = "((HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 21) OR (HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 6))";
                    break;
            }
        }
        
        if (!empty($time_conditions)) {
            // Just this simple condition is sufficient and correct
            $dynamic_where_conditions[] = '(' . implode(' OR ', $time_conditions) . ')';
        }
    }

    // --- Cost Filtering ---
    $cost_filters = isset($event_filters['cost_range']) ? $event_filters['cost_range'] : null;
    if ($cost_filters !== null) {
        $min_cost = 0;
        $max_cost = PHP_INT_MAX;
        $cost_meta_key = '_EventCost';

        if (is_array($cost_filters)) {
            $min_cost = isset($cost_filters['min']) ? floatval($cost_filters['min']) : 0;
            $max_cost = isset($cost_filters['max']) ? floatval($cost_filters['max']) : PHP_INT_MAX;
        } elseif (is_string($cost_filters) && strpos($cost_filters, ',') !== false) {
            list($min_cost, $max_cost) = array_map('floatval', explode(',', $cost_filters));
        } elseif (is_numeric($cost_filters)) {
            $min_cost = floatval($cost_filters);
        }

        if ($cost_filters !== null) {
            $cost_meta_alias = 'pm_cost';
            $dynamic_joins[] = "JOIN {$wpdb->postmeta} {$cost_meta_alias} ON {$cost_meta_alias}.post_id = p.ID";

            // Note: The REGEXP_REPLACE for _EventCost might need careful testing on different MySQL versions.
            // A more robust way might be to add a custom meta field for numeric cost if it's complex.
            $dynamic_where_conditions[] = "
                {$cost_meta_alias}.meta_key = %s AND
                CAST(REGEXP_REPLACE({$cost_meta_alias}.meta_value, '[^0-9.]', '') AS DECIMAL(10,2)) BETWEEN %f AND %f
            ";
            $query_params[] = $cost_meta_key;
            $query_params[] = $min_cost;
            $query_params[] = $max_cost;
        }
    }

    // --- Venue Filtering (Country, State, City, Address) ---
    $venue_filters_map = array(
        'venue_country' => '_VenueCountry',
        'venue_state'   => '_VenueState',
        'venue_city'    => '_VenueCity',
        'venue_address' => '_VenueAddress',
    );

    $venue_join_counter = 0;
    foreach ($venue_filters_map as $filter_key => $venue_meta_key) {
        $current_venue_values = [];
        if (!empty($event_filters[$filter_key])) {
            $current_venue_values = (array) $event_filters[$filter_key];
        }

        if (!empty($current_venue_values)) {
            $pm_venue_alias = "pmv{$venue_join_counter}"; // Postmeta for Event Venue ID
            $pm_location_detail_alias = "pmld{$venue_join_counter}"; // Postmeta for Venue details

            $dynamic_joins[] = "
                JOIN {$wpdb->postmeta} {$pm_venue_alias} ON {$pm_venue_alias}.post_id = p.ID AND {$pm_venue_alias}.meta_key = '_EventVenueID'
                JOIN {$wpdb->postmeta} {$pm_location_detail_alias} ON {$pm_venue_alias}.meta_value = {$pm_location_detail_alias}.post_id";

            $venue_placeholders = implode(',', array_fill(0, count($current_venue_values), '%s'));
            $dynamic_where_conditions[] = "
                {$pm_location_detail_alias}.meta_key = %s AND
                {$pm_location_detail_alias}.meta_value IN ({$venue_placeholders})
            ";
            $query_params[] = $venue_meta_key;
            $query_params = array_merge($query_params, $current_venue_values);

            $venue_join_counter++;
        }
    }

    $final_joins = implode("\n", array_unique($dynamic_joins)); // Use array_unique to prevent duplicate joins
    $final_where = implode(' AND ', $dynamic_where_conditions);

    return [
        'joins' => $final_joins,
        'where' => $final_where,
        'params' => $query_params,
    ];
}

function generate_year_grid_view() {
    $current_year = date('Y');
    $start_year = $current_year - 5;
    $end_year = $current_year + 6;
    
    // Get active filters to preserve in URLs
    $active_filters = get_active_filters_universal();
    $filter_params = convert_all_filters_to_url_params($active_filters);
    $filter_query = !empty($filter_params) ? '?' . http_build_query($filter_params) : '';
    
    ob_start();
    ?>
    <div class="container my-3">
        <h5 class="text-center mb-4">Select Year</h5>
        <div id="years-grid" class="row">
            <?php for ($year = $start_year; $year <= $end_year; $year++): 
                $year_url = "/events/{$year}/{$filter_query}";
            ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="year-card <?php echo ($year === $current_year) ? 'current-year-card' : ''; ?>" 
                         data-year="<?php echo $year; ?>" 
                         data-navigation-type="year">
                        <a href="<?php echo esc_url($year_url); ?>" 
                           data-year="<?php echo $year; ?>"
                           class="year-navigation-link">
                            <?php echo $year; ?>
                        </a>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function generate_month_grid_view($year) {
    global $wp_locale;
    
    $current_month = date('m');
    $current_year = date('Y');
    
    // Get active filters to preserve in URLs
    $active_filters = get_active_filters_universal();
    $filter_params = convert_all_filters_to_url_params($active_filters);
    $filter_query = !empty($filter_params) ? '?' . http_build_query($filter_params) : '';
    
    ob_start();
    ?>
    <div class="container my-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="/events/<?php echo $filter_query; ?>" 
               class="btn btn-sm btn-outline-secondary back-to-years-link"
               data-navigation-type="back-to-years">
                <i class="fas fa-chevron-left"></i> Back to Years
            </a>
            <h5 class="mb-0">Select Month - <?php echo $year; ?></h5>
            <span></span>
        </div>
        
        <div id="months-grid" class="row">
            <?php for ($month = 1; $month <= 12; $month++): 
                $month_padded = str_pad($month, 2, '0', STR_PAD_LEFT);
                $month_name = $wp_locale->get_month_abbrev($wp_locale->get_month($month));
                $is_current = ($year == $current_year && $month_padded == $current_month);
                $month_url = "/events/{$year}/{$month_padded}/{$filter_query}";
            ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="month-card <?php echo $is_current ? 'current-month-card' : ''; ?>"
                         data-year="<?php echo $year; ?>" 
                         data-month="<?php echo $month; ?>"
                         data-navigation-type="month">
                        <a href="<?php echo esc_url($month_url); ?>" 
                           data-year="<?php echo $year; ?>" 
                           data-month="<?php echo $month; ?>"
                           class="month-navigation-link">
                            <?php echo $month_name; ?>
                        </a>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/*
 * UPDATED generate_events_calendar_html() - CLEAN VERSION
 * Generates complete events calendar HTML with CLEAN URLs (no filter parameters)
 * JavaScript handles ALL filter parameter addition to prevent duplicates
 */
function generate_events_calendar_html($args) {
    global $wpdb, $wp_locale;

    // === ADAPTIVE CONTEXT DETECTION ===
    $current_url = $_SERVER['REQUEST_URI'];
    $active_filters = get_active_filters_universal();
    $has_filters = !empty($active_filters);
    
    // Detect date archive context
    $date_archive = is_custom_post_type_date_archive_by_url();
    
    // Decision tree for which view to show:
    
    // 1. /events/ with filters → Year Grid
    if (!$date_archive && $has_filters) {
        return generate_year_grid_view();
    }
    
    // 2. /events/2024/ (year archive) → Month Grid  
    if ($date_archive && $date_archive['type'] === 'yearly') {
        return generate_month_grid_view($date_archive['year']);
    }

    // --- 1. Determine Current Month/Year Context ---
    $thisyear = date('Y');
    $thismonth = date('m');

    if ($date_archive) {
        $thisyear = $date_archive['year'];
        $thismonth = $date_archive['month'] ?: date('m');
    }

    // Prioritize archive info for month/year
    if (function_exists('is_on_custom_post_type_date_archive') && is_on_custom_post_type_date_archive()) {
        $archive_info = is_custom_post_type_date_archive_by_url();
        if ($archive_info) {
            $thisyear = $archive_info['year'];
            $thismonth = $archive_info['month'] ?: date('m'); // Use current month if only year is in archive
        }
    }

    // Fallback to WordPress query vars if no archive context found
    if (get_query_var('year')) {
        $thisyear = get_query_var('year');
    }
    if (get_query_var('monthnum')) {
        $thismonth = get_query_var('monthnum');
    }

    $thismonth = str_pad($thismonth, 2, '0', STR_PAD_LEFT);
    $last_day = date('t', mktime(0, 0, 0, $thismonth, 1, $thisyear));
    $week_begins = (int) get_option('start_of_week');
    $unixmonth = mktime(0, 0, 0, $thismonth, 1, $thisyear);

    //error_log("Generating calendar for: {$thisyear}-{$thismonth}");

    // --- 2. Get Active Filters (using your existing function) ---
    $event_filters = get_active_filters();

    // Explicitly add day-of-week filter from URL
    if (isset($_GET['dayofweek'])) {
        $dayofweek = explode(',', $_GET['dayofweek']);
        $event_filters['dayofweek'] = array_map('intval', $dayofweek);
        // Force this to be logged for debugging
        error_log("CALENDAR: Explicitly adding day-of-week filter from URL: " . print_r($event_filters['dayofweek'], true));
    }

    // Add this to the beginning of generate_events_calendar_html
    error_log('CALENDAR: Starting calendar generation with URL: ' . $_SERVER['REQUEST_URI']);
    if (isset($_GET['dayofweek'])) {
        error_log('CALENDAR: Day of week parameter found in URL: ' . $_GET['dayofweek']);
    }

    // --- 3. Build WP_Query args using your centralized function ---
    // This call sets up all the posts_where filters (dayofweek, timeperiod, cost, venue)
    // and tax_query (for categories, tags, etc.) based on active filters.
    $query_args = build_filtered_query_args(
        'tribe_events', // Post type for events
        $event_filters,
        get_current_page_url() // Pass current URL if needed for archive context
    );

    // Ensure the day-of-week filter gets applied directly to the query
    if (!empty($event_filters['dayofweek'])) {
        $query_args['_custom_dayofweek_filter'] = array_map('intval', (array) $event_filters['dayofweek']);
        // Debug
        error_log("CALENDAR: Set _custom_dayofweek_filter directly: " . print_r($query_args['_custom_dayofweek_filter'], true));
    }

    // Organizer handling - following the same pattern as dayofweek/timeperiod
    if (!empty($event_filters['organizer']) && $post_type === 'tribe_events') {
        // Set directly in query_vars, not nested
        $args['_custom_organizer_filter'] = $event_filters['organizer'];
        
        // Debug
        error_log('BUILD ARGS: Setting _custom_organizer_filter to: ' . print_r($event_filters['organizer'], true));
    }

    // --- 4. Add Calendar-Specific Date Filtering to WP_Query Args ---
    // We need events for the *specific month* of the calendar.
    // This is a direct meta_query for the start date.
    // It will combine with any other meta_query parts set by build_filtered_query_args
    // if that function uses meta_query for other purposes (though it primarily uses posts_where).

    // Ensure meta_query exists and is an array
    if (!isset($query_args['meta_query'])) {
        $query_args['meta_query'] = array();
    }
    // Ensure it's a relation 'AND' if there are multiple meta_query clauses
    if (count($query_args['meta_query']) > 0) {
        array_unshift($query_args['meta_query'], array('relation' => 'AND'));
    }

    /*
    // Add the specific date range for the calendar month
    $query_args['meta_query'][] = array(
        'key'     => '_EventStartDate',
        'value'   => $thisyear . '-' . $thismonth . '-01 00:00:00', // Start of the month
        'compare' => '>=',
        'type'    => 'DATETIME',
    );
    $query_args['meta_query'][] = array(
        'key'     => '_EventStartDate',
        'value'   => $thisyear . '-' . $thismonth . '-' . $last_day . ' 23:59:59', // End of the month
        'compare' => '<=',
        'type'    => 'DATETIME',
    );
    */

    $query_args['meta_query'] = array(
    'event_start_date' => array(
        'key' => '_EventStartDate',
        'value' => array(
            $thisyear . '-' . $thismonth . '-01 00:00:00',
            $thisyear . '-' . $thismonth . '-' . $last_day . ' 23:59:59'
        ),
        'compare' => 'BETWEEN',
        'type' => 'DATETIME'
        )
    );
    
    // Make sure we're using the same ordering as the event query
    $query_args['orderby'] = 'meta_value';
    $query_args['meta_key'] = '_EventStartDate';
    $query_args['order'] = 'ASC';

    // --- 5. Adjust Paging and Ordering for Calendar Display ---
    $query_args['posts_per_page'] = -1; // Get all events for the month
    //$query_args['paged'] = 1; // Not relevant when posts_per_page is -1

    // Ensure future events are also included if desired, as your initial $args allows 'future' status
    $query_args['post_status'] = array('publish', 'future');

    // Make sure filters are applied (though build_filtered_query_args sets this)
    //$query_args['suppress_filters'] = false;

    //error_log('Final calendar WP_Query args: ' . print_r($query_args, true));

    // --- 6. Run the WP_Query ---
    $events_query = new WP_Query($query_args);
    if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
        $debug_output .= '<div><strong>Calendar Query SQL:</strong> ' . $events_query->request . '</div>';
        // Add this to your generate_events_calendar_html function after creating the WP_Query
        $calendar_query_sql = $events_query->request;
        $debug_output .= '<div style="background:#f8f8f8; border:1px solid #ddd; padding:10px; margin:10px 0;">';
        $debug_output .= '<h4>Calendar Query SQL:</h4>';
        $debug_output .= '<pre>' . esc_html($calendar_query_sql) . '</pre>';

        // Check if day-of-week filter is in the SQL
        $has_dayofweek = (strpos($calendar_query_sql, 'DAYOFWEEK') !== false);
        $debug_output .= '<p><strong>Has Day-of-Week Filter:</strong> ' . ($has_dayofweek ? 'YES' : 'NO') . '</p>';

        // If we can extract the days being filtered
        if ($has_dayofweek && preg_match('/DAYOFWEEK.*IN\s*\((.*?)\)/i', $calendar_query_sql, $matches)) {
            $debug_output .= '<p><strong>Days being filtered:</strong> ' . $matches[1] . '</p>';
        }

        $debug_output .= '</div>';
    }
    $daywithpost = array();
    $ak_titles_for_day = array();

    if ($events_query->have_posts()) {
        while ($events_query->have_posts()) {
            $events_query->the_post();
            $event_id = get_the_ID();

            // Get the raw event start date
            $event_start_date_str = get_post_meta($event_id, '_EventStartDate', true);

            // Convert to DateTime object for easier day extraction
            try {
                $start_datetime = new DateTime($event_start_date_str);
                $day = (int) $start_datetime->format('j'); // Day of the month (1-31)

                // The meta_query already filters to this month, but an extra check for safety.
                if ($start_datetime->format('Y') == $thisyear && $start_datetime->format('m') === $thismonth) {
                    $daywithpost[] = $day;

                    if (!isset($ak_titles_for_day[$day])) {
                        $ak_titles_for_day[$day] = array();
                    }

                    $ak_titles_for_day[$day][] = array(
                        'title' => get_the_title(),
                        'url' => get_permalink($event_id)
                    );
                }
            } catch (Exception $e) {
                //error_log('Error parsing event start date for event ID ' . $event_id . ': ' . $e->getMessage());
                // Skip this event if date is malformed
            }
        }
        wp_reset_postdata(); // Important: Reset post data after a custom loop
    }

    $daywithpost = array_unique($daywithpost);
    //error_log('Days with events (filtered): ' . implode(', ', $daywithpost));

     // Build days debug information
    /*$days_debug = '<div class="days-debug" style="margin-top: 10px;">
        <strong>Days with posts:</strong> ' . (empty($daywithpost) ? 'None' : implode(', ', $daywithpost)) . '
    </div>';
    */

    // --- 7. Build Calendar HTML ---
    $calendar_caption = _x('%1$s %2$s', 'calendar caption');
    $calendar_output = '<table id="wp-calendar" class="wp-calendar-table">
    <caption>' . sprintf($calendar_caption, $wp_locale->get_month($thismonth), $thisyear) . '</caption>
    <thead>
    <tr>';

    // Week days header
    $myweek = array();
    for ($wdcount = 0; $wdcount <= 6; $wdcount++) {
        $myweek[] = $wp_locale->get_weekday(($wdcount + $week_begins) % 7);
    }

    $initial = isset($args['initial']) ? $args['initial'] : true;
    foreach ($myweek as $wd) {
        $day_name = $initial ? $wp_locale->get_weekday_initial($wd) : $wp_locale->get_weekday_abbrev($wd);
        $wd = esc_attr($wd);
        $calendar_output .= "\n\t\t<th scope=\"col\" title=\"$wd\">$day_name</th>";
    }

    $calendar_output .= '
    </tr>
    </thead>
    <tbody>
    <tr>';

    // Calculate padding for start of month
    $pad = calendar_week_mod(date('w', $unixmonth) - $week_begins);
    if (0 != $pad) {
        $calendar_output .= "\n\t\t" . '<td colspan="' . esc_attr($pad) . '" class="pad">&nbsp;</td>';
    }

    $daysinmonth = (int) date('t', $unixmonth);
    $newrow = false;

    // Generate calendar days
    for ($day = 1; $day <= $daysinmonth; ++$day) {
        if (isset($newrow) && $newrow) {
            $calendar_output .= "\n\t</tr>\n\t<tr>\n\t\t";
        }
        $newrow = false;

        // Check if this is today
        if ($day == gmdate('j', current_time('timestamp')) && 
            $thismonth == gmdate('m', current_time('timestamp')) && 
            $thisyear == gmdate('Y', current_time('timestamp'))) {
            $calendar_output .= '<td id="today">';
        } else {
            $calendar_output .= '<td>';
        }

        // Create clickable links for days with events
        if (in_array($day, $daywithpost)) {
            $day_link = get_events_day_link($thisyear, $thismonth, $day);
            
            // Create tooltip with event titles
            $tooltip_titles = array();
            if (isset($ak_titles_for_day[$day])) {
                foreach ($ak_titles_for_day[$day] as $event) {
                    $tooltip_titles[] = $event['title'];
                }
            }
            $tooltip = implode(', ', $tooltip_titles);
            
            $calendar_output .= '<a href="' . $day_link . '" title="' . esc_attr($tooltip) . '" style="font-weight: bold; color: #0073aa;">' . $day . '</a>';
        } else {
            $calendar_output .= $day;
        }

        $calendar_output .= '</td>';

        // Check if we need a new row (end of week)
        if (6 == calendar_week_mod(date('w', mktime(0, 0, 0, $thismonth, $day, $thisyear)) - $week_begins)) {
            $newrow = true;
        }
    }

    // Pad end of month
    $pad = 7 - calendar_week_mod(date('w', mktime(0, 0, 0, $thismonth, $day, $thisyear)) - $week_begins);
    if ($pad != 0 && $pad != 7) {
        $calendar_output .= "\n\t\t" . '<td class="pad" colspan="' . esc_attr($pad) . '">&nbsp;</td>';
    }

    $calendar_output .= "\n\t</tr>\n\t</tbody>";

    // --- 8. Build Calendar Navigation ---
    // Get SQL clauses for active filters
    $filter_sql = _get_sql_for_event_filters($event_filters);
    $custom_filter_join_clause = $filter_sql['joins'];
    $custom_filter_where_clause = $filter_sql['where'];
    $custom_filter_params = $filter_sql['params']; // Parameters for the filter WHERE clauses

    // Get previous month with events (respecting ALL filters)
    $prev_query = "
        SELECT DISTINCT
            MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) AS month,
            YEAR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) AS year
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON pm.post_id = p.ID" . ($custom_filter_join_clause ? "\n" . $custom_filter_join_clause : "") . "
        WHERE pm.meta_key = '_EventStartDate'
        AND STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') < %s
        AND p.post_type = 'tribe_events'
        AND p.post_status IN ('publish', 'future')";

    if (!empty($custom_filter_where_clause)) {
        $prev_query .= ' AND (' . $custom_filter_where_clause . ')';
    }
    $prev_query .= "
        ORDER BY STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') DESC
        LIMIT 1";

    // Combine parameters for wpdb->prepare: custom filter params first, then the date parameter
    $prev_params_final = array_merge($custom_filter_params, [$thisyear . '-' . $thismonth . '-01 00:00:00']);
    $previous = $wpdb->get_row($wpdb->prepare($prev_query, ...$prev_params_final));


    // Get next month with events (respecting ALL filters)
    $next_query = "
        SELECT DISTINCT
            MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) AS month,
            YEAR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) AS year
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON pm.post_id = p.ID" . ($custom_filter_join_clause ? "\n" . $custom_filter_join_clause : "") . "
        WHERE pm.meta_key = '_EventStartDate'
        AND STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') > %s
        AND p.post_type = 'tribe_events'
        AND p.post_status IN ('publish', 'future')";

    if (!empty($custom_filter_where_clause)) {
        $next_query .= ' AND (' . $custom_filter_where_clause . ')';
    }
    $next_query .= "
        ORDER BY STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') ASC
        LIMIT 1";

    // Combine parameters for wpdb->prepare: custom filter params first, then the date parameter
    $next_params_final = array_merge($custom_filter_params, [$thisyear . '-' . $thismonth . '-' . $last_day . ' 23:59:59']);
    $next = $wpdb->get_row($wpdb->prepare($next_query, ...$next_params_final));


    $calendar_output .= '<tfoot><tr>';

    // Previous month link - CLEAN URL (no filter parameters)
    if ($previous) {
        $prev_link = get_events_month_link($previous->year, $previous->month); // ← NOW GENERATES CLEAN URLs
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="prev"><a href="' . $prev_link . '" title="' . 
            esc_attr(sprintf(__('View events for %1$s %2$s'), $wp_locale->get_month($previous->month), 
            $previous->year)) . '">&laquo; ' . 
            $wp_locale->get_month_abbrev($wp_locale->get_month($previous->month)) . '</a></td>';
    } else {
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="prev" class="pad">&nbsp;</td>';
    }

    $calendar_output .= "\n\t\t" . '<td class="pad">&nbsp;</td>';

    // UPDATED: Next month link - CLEAN URL (no filter parameters)
    if ($next) {
        $next_link = get_events_month_link($next->year, $next->month); // ← NOW GENERATES CLEAN URLs
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="next"><a href="' . $next_link . '" title="' . 
            esc_attr(sprintf(__('View events for %1$s %2$s'), $wp_locale->get_month($next->month), 
            $next->year)) . '">' . 
            $wp_locale->get_month_abbrev($wp_locale->get_month($next->month)) . ' &raquo;</a></td>';
    } else {
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="next" class="pad">&nbsp;</td>';
    }

    $calendar_output .= '</tr></tfoot>';
    $calendar_output .= "\n\t</table>";


    // Add .wp-calendar-nav wrapper for AJAX navigation - CLEAN URLs
    $nav_output = '';
    if ($previous || $next) {
        $nav_output = '<nav class="wp-calendar-nav" role="navigation" aria-label="' . esc_attr__('Previous and next months') . '">';

        if ($previous) {
            $prev_link = get_events_month_link($previous->year, $previous->month); // ← CLEAN URL
            $nav_output .= '<span class="wp-calendar-nav-prev"><a href="' . $prev_link . '">&laquo; ' . 
                $wp_locale->get_month($previous->month) . ' ' . $previous->year . '</a></span>';
        }

        if ($next) {
            $next_link = get_events_month_link($next->year, $next->month); // ← CLEAN URL
            $nav_output .= '<span class="wp-calendar-nav-next"><a href="' . $next_link . '">' . 
                $wp_locale->get_month($next->month) . ' ' . $next->year . ' &raquo;</a></span>';
        }
        $nav_output .= '</nav>';
    }

    //error_log('Generated calendar HTML with ' . count($daywithpost) . ' clickable days - ALL LINKS ARE CLEAN (no parameters)');

    return $debug_output . $calendar_output . $nav_output;
}

/**
 * Replace calendar output entirely for events
 */
function replace_events_calendar_output($calendar_output, $args) {
    global $calendar_using_events;

    // Only replace for events
    if (!$calendar_using_events) {
        return $calendar_output;
    }

    // Generate our custom events calendar
    return generate_events_calendar_html($args);
}

/**
 * Build calendar navigation URL that properly handles taxonomy context
 */
function build_calendar_navigation_url($target_date_url, $preserve_taxonomy = true) {
    $archive_context = get_current_archive_context();

    // If we're on a taxonomy archive and want to preserve context
    if ($preserve_taxonomy && $archive_context['context'] === 'taxonomy') {
        $url = new URL($target_date_url, site_url());

        // Add taxonomy as query parameter
        if ($archive_context['taxonomy'] === 'tribe_events_cat') {
            $url->searchParams.set('category', $archive_context['term_id']);
        } elseif ($archive_context['taxonomy'] === 'post_tag') {
            $url->searchParams.set('keyword', $archive_context['term_id']);
        }

        return $url->toString();
    }

    return $target_date_url;
}

// Call this function during calendar generation for debugging
// debug_clean_calendar_urls();

/**
 * Modified custom_calendar_shortcode - uses output replacement instead of SQL interception
 */
function custom_calendar_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_type' => 'tribe_events',
        'initial' => true,
        'show_dropdowns' => true,
        'show_today_button' => true,
        'ajax_target' => '#events-container',
        'adaptive' => false // New parameter to enable adaptive behavior
    ), $atts, 'custom_calendar');

    // Convert string 'true'/'false' to boolean
    $atts['initial'] = filter_var($atts['initial'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_dropdowns'] = filter_var($atts['show_dropdowns'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_today_button'] = filter_var($atts['show_today_button'], FILTER_VALIDATE_BOOLEAN);
    $atts['adaptive'] = filter_var($atts['adaptive'], FILTER_VALIDATE_BOOLEAN);
    $atts['display'] = false; // Always return for shortcode

    // Set global flags for our filters
    global $calendar_post_type, $show_calendar_dropdowns, $show_calendar_today, $calendar_ajax_target, $calendar_using_events;
    $calendar_post_type = $atts['post_type'];
    $show_calendar_dropdowns = $atts['show_dropdowns'];
    $show_calendar_today = $atts['show_today_button'];  
    $calendar_ajax_target = $atts['ajax_target'];

    // For events with adaptive behavior
    if ($atts['post_type'] === 'tribe_events' && $atts['adaptive']) {
        $calendar_using_events = true;
        
        // Disable dropdowns and today button for adaptive mode
        $show_calendar_dropdowns = false;
        $show_calendar_today = false;
        
        // Add the replace filter
        add_filter('get_calendar', 'replace_events_calendar_output', 10, 2);
        
        // Get the adaptive calendar content
        $calendar = get_calendar($atts);
        
        // Clean up
        $calendar_using_events = false;
        remove_filter('get_calendar', 'replace_events_calendar_output', 10);
        
        return $calendar;
    } else {
        // Original behavior for non-adaptive calendars
        if ($atts['post_type'] === 'tribe_events') {
            $calendar_using_events = true;
            add_filter('get_calendar', 'replace_events_calendar_output', 10, 2);
        }

        // Add dropdown filter if enabled
        if ($atts['show_dropdowns']) {
            add_filter('get_calendar', 'add_calendar_dropdowns', 15, 2);
        }

        // Get the calendar
        $calendar = get_calendar($atts);

        // Clean up
        if ($atts['post_type'] === 'tribe_events') {
            $calendar_using_events = false;
            remove_filter('get_calendar', 'replace_events_calendar_output', 10);
        }

        if ($atts['show_dropdowns']) {
            remove_filter('get_calendar', 'add_calendar_dropdowns', 15);
        }

        return $calendar;
    }
}

// Keep your existing shortcode registration
add_shortcode('custom_calendar', 'custom_calendar_shortcode');

// Expand the debug function to show more details about the query
function debug_events_calendar_query_display($query_args, $events_query) {
    $output = '<div class="calendar-debug" style="background: #f8f8f8; border: 1px solid #ddd; padding: 15px; margin: 20px 0; font-family: monospace;">';
    $output .= '<h3>Calendar Debug Information</h3>';
    
    // Number of posts found
    $output .= '<p><strong>Posts Found:</strong> ' . $events_query->found_posts . '</p>';
    
    // Show query arguments
    $output .= '<details open>';
    $output .= '<summary><strong>Query Arguments</strong></summary>';
    $output .= '<pre>' . htmlspecialchars(print_r($query_args, true)) . '</pre>';
    $output .= '</details>';
    
    // Show SQL query
    $output .= '<details open>';
    $output .= '<summary><strong>SQL Query</strong></summary>';
    $output .= '<pre>' . htmlspecialchars($events_query->request) . '</pre>';
    $output .= '</details>';
    
    // Debug all events in the system (regardless of month)
    $all_events_query = new WP_Query([
        'post_type' => 'tribe_events',
        'post_status' => array('publish', 'future'),
        'posts_per_page' => 5,
        'orderby' => 'meta_value',
        'meta_key' => '_EventStartDate',
        'order' => 'ASC'
    ]);
    
    $output .= '<details open>';
    $output .= '<summary><strong>Recent Events Check (Ignoring Month Filter)</strong></summary>';
    if ($all_events_query->have_posts()) {
        $output .= '<p>Found ' . $all_events_query->found_posts . ' total events in the system</p>';
        $output .= '<table style="border-collapse: collapse; width: 100%;">';
        $output .= '<tr><th>ID</th><th>Title</th><th>Date</th><th>Status</th></tr>';
        
        while ($all_events_query->have_posts()) {
            $all_events_query->the_post();
            $event_id = get_the_ID();
            $event_title = get_the_title();
            $event_date = get_post_meta($event_id, '_EventStartDate', true);
            $event_status = get_post_status();
            
            $output .= '<tr>';
            $output .= '<td>' . $event_id . '</td>';
            $output .= '<td>' . $event_title . '</td>';
            $output .= '<td>' . $event_date . '</td>';
            $output .= '<td>' . $event_status . '</td>';
            $output .= '</tr>';
        }
        $output .= '</table>';
        wp_reset_postdata();
    } else {
        $output .= '<p style="color: red;"><strong>No events found at all in the system!</strong></p>';
    }
    $output .= '</details>';
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Debug function to trace where the "AND 0 = 1" condition is coming from
 */
function trace_impossible_condition($where, $query) {
    // Only run for tribe_events queries with timeperiod filter
    if (isset($query->query_vars['post_type']) && 
        $query->query_vars['post_type'] === 'tribe_events' &&
        isset($query->query_vars['_custom_timeperiod_filter']) &&
        strpos($where, '0 = 1') !== false) {
        
        // Get stack trace
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $trace_output = array();
        
        foreach ($trace as $i => $call) {
            $file = isset($call['file']) ? basename($call['file']) : 'unknown';
            $line = isset($call['line']) ? $call['line'] : 'unknown';
            $function = isset($call['function']) ? $call['function'] : 'unknown';
            $class = isset($call['class']) ? $call['class'] : '';
            
            $trace_output[] = "#$i $class::$function in $file:$line";
        }
        
        error_log('Impossible condition trace: ' . implode("\n", $trace_output));
    }
    
    return $where;
}

// Add before our fix but with lower priority
add_filter('posts_where', 'trace_impossible_condition', 99998, 2);

/**
 * Fix impossible SQL condition in WordPress queries
 * This removes "AND 0 = 1" conditions that prevent results from appearing
 */
function fix_impossible_sql_condition($where, $query) {
    // Only apply to relevant queries to avoid affecting other parts of the site
    if (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'tribe_events') {
        
        // Check if the impossible condition exists in the WHERE clause
        if (strpos($where, '0 = 1') !== false) {
            // Remove pattern: tt1.term_taxonomy_id IN (5520) AND 0 = 1
            $where = preg_replace('/AND\s+\(\s+tt\d+\.term_taxonomy_id\s+IN\s+\([0-9,]+\)\s+AND\s+0\s+=\s+1\s+\)/', '', $where);
            
            // If pattern didn't match but "0 = 1" still exists, use simpler replacement
            if (strpos($where, '0 = 1') !== false) {
                $where = str_replace('AND 0 = 1', '', $where);
            }
            
            // Clean up any empty conditions that might be left behind
            $where = preg_replace('/AND\s+\(\s+\)/', '', $where);
            $where = preg_replace('/AND\s+\(\s*\)/', '', $where);
            
            // Log the fixed SQL if debugging is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Fixed SQL WHERE clause: ' . $where);
            }
        }
    }
    
    return $where;
}

// Add the filter with a very high priority to ensure it runs after all other filters
add_filter('posts_where', 'fix_impossible_sql_condition', 99999, 2);

/**
 * Additional fixes for impossible SQL conditions
 * Targets specific patterns seen in your query logs
 */
function fix_additional_impossible_conditions($where, $query) {
    // Only apply to tribe_events queries
    if (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'tribe_events') {
        $original_where = $where;
        
        // Check if any impossible condition patterns exist
        if (strpos($where, '0 = 1') !== false) {
            // Pattern 1: wp_term_relationships.term_taxonomy_id IN (5151) AND 0 = 1
            $where = preg_replace('/\(\s*wp_term_relationships\.term_taxonomy_id\s+IN\s+\([0-9,]+\)\s+AND\s+0\s+=\s+1\s*\)/', 'wp_term_relationships.term_taxonomy_id IN ($1)', $where);
            
            // Pattern 2: Simple standalone ( 0 = 1 )
            $where = preg_replace('/AND\s+\(\s+0\s+=\s+1\s+\)/', '', $where);
            
            // Clean up any resulting empty conditions
            $where = preg_replace('/AND\s+\(\s+\)/', '', $where);
            $where = preg_replace('/AND\s+\(\s*\)/', '', $where);
            
            // Log changes if we modified anything
            if ($where !== $original_where) {
                error_log('FIXED ADDITIONAL SQL CONDITIONS:');
                error_log('BEFORE: ' . $original_where);
                error_log('AFTER: ' . $where);
            }
        }
    }
    
    return $where;
}

// Add with very high priority to run after all other filters
add_filter('posts_where', 'fix_additional_impossible_conditions', 99999, 2);

// Add this function to your code
function debug_events_filter_process($message, $data = null) {
    // Check if we're in staging (you may need to adjust this condition)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // Create a debug log entry
        error_log('EVENTS FILTER DEBUG: ' . $message . ($data !== null ? ' - Data: ' . print_r($data, true) : ''));
        
        // Output to screen if it's an admin or if a debug flag is set
        if (current_user_can('manage_options') || isset($_GET['debug_events'])) {
            echo '<div class="events-debug" style="background:#f8f8f8; border:1px solid #ddd; padding:10px; margin:10px 0; font-family:monospace;">';
            echo '<strong>DEBUG:</strong> ' . esc_html($message) . '<br>';
            if ($data !== null) {
                echo '<pre>' . esc_html(print_r($data, true)) . '</pre>';
            }
            echo '</div>';
        }
    }
}

add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'dayofweek') !== false) {
        error_log('EARLY URL CHECK: Request contains dayofweek parameter');
        error_log('EARLY URL CHECK: Raw URL: ' . $_SERVER['REQUEST_URI']);
        error_log('EARLY URL CHECK: Raw $_GET: ' . print_r($_GET, true));
    }
}, 1); // Priority 1 to run very early

function inject_dayofweek_filter_early($query) {
        
        // Check for dayofweek in URL
        if (isset($_GET['dayofweek'])) {
            $days = explode(',', sanitize_text_field($_GET['dayofweek']));
            $selected_days = array_map('intval', $days);
            
            // Directly set the filter parameter
            $query->set('_custom_dayofweek_filter', $selected_days);
            
            error_log('EARLY INJECTION: Set _custom_dayofweek_filter to: ' . implode(',', $selected_days));
        }
    
}
add_action('pre_get_posts', 'inject_dayofweek_filter_early', 50); // Very early priority

function debug_filter_chain($where, $query) {
    // Only for our specific request
    if (isset($_GET['dayofweek'])) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $chain = [];
        
        foreach ($trace as $step) {
            $chain[] = ($step['class'] ?? '') . 
                      ($step['type'] ?? '') . 
                      ($step['function'] ?? 'unknown');
        }
        
        error_log('FILTER CHAIN: ' . implode(' → ', $chain));
    }
    
    return $where;
}
// Add at multiple priority points to see when it's called
add_filter('posts_where', 'debug_filter_chain', 1, 2);   // Very early
add_filter('posts_where', 'debug_filter_chain', 100, 2); // Middle
add_filter('posts_where', 'debug_filter_chain', 999, 2); // Late

function inspect_filter_priorities() {
    global $wp_filter;
    
    if (isset($wp_filter['posts_where'])) {
        $registered = [];
        
        foreach ($wp_filter['posts_where']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $id => $callback) {
                $function_name = is_string($callback['function']) 
                    ? $callback['function'] 
                    : (is_array($callback['function']) 
                        ? (is_object($callback['function'][0]) 
                            ? get_class($callback['function'][0]) . '->' . $callback['function'][1]
                            : $callback['function'][0] . '::' . $callback['function'][1])
                        : 'Closure');
                
                $registered[] = "$priority: $function_name";
            }
        }
        
        error_log('REGISTERED FILTERS (posts_where): ' . implode(', ', $registered));
    }
}

add_action('wp_loaded', 'inspect_filter_priorities');

function trace_all_hooks($tag, ...$args) {
    static $seen = [];
    
    // Only track posts_where and related filters
    if (strpos($tag, 'posts_where') !== false && !isset($seen[$tag])) {
        $seen[$tag] = true;
        error_log("HOOK FIRED: $tag with " . count($args) . " arguments");
        
        // If it's a specific hook we're interested in, get more details
        if ($tag === 'posts_where' && isset($args[1]) && is_object($args[1])) {
            $query = $args[1];
            error_log("QUERY TYPE: " . ($query->query_vars['post_type'] ?? 'unknown'));
            
            if (isset($_GET['dayofweek'])) {
                error_log("URL has dayofweek=" . $_GET['dayofweek'] . 
                        ", Query has it: " . (isset($query->query_vars['dayofweek']) ? 'YES' : 'NO'));
            }
        }
    }
}

add_action('all', 'trace_all_hooks', 1, 99);

/**
 * FINAL SQL VERIFICATION
 * Let's see the complete final SQL query
 */
/*
function debug_final_sql_with_dayofweek($query) {
    if ($query->is_main_query() && isset($_GET['dayofweek'])) {
        add_filter('posts_request', function($sql) {
            error_log("🔍 FINAL SQL QUERY:");
            error_log($sql);
            
            // Check specifically for our DAYOFWEEK condition
            if (preg_match('/DAYOFWEEK.*?IN\s*\(([^)]+)\)/', $sql, $matches)) {
                error_log("✅ DAYOFWEEK filter FOUND in SQL: " . $matches[0]);
                error_log("   Filtering for days: " . $matches[1]);
            } else {
                error_log("❌ DAYOFWEEK filter NOT FOUND in SQL");
            }
            
            return $sql;
        }, 999);
    }
}
add_action('pre_get_posts', 'debug_final_sql_with_dayofweek', 999);
*/

/**
 * TEST DIRECT SQL
 * Let's test if the DAYOFWEEK logic works at all
 */
/*
function test_dayofweek_sql_directly() {
    if (isset($_GET['test_sql']) && $_GET['test_sql'] === '1') {
        global $wpdb;
        
        // Test query for Monday (2) and Wednesday (4) events in October 2024
        $test_sql = "
            SELECT p.ID, p.post_title, pm.meta_value as event_date,
                   DAYOFWEEK(STR_TO_DATE(pm.meta_value, '%Y-%m-%d %H:%i:%s')) as day_of_week
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'tribe_events'
            AND p.post_status IN ('publish', 'future')
            AND pm.meta_key = '_EventStartDate'
            AND pm.meta_value LIKE '2024-10%'
            AND DAYOFWEEK(STR_TO_DATE(pm.meta_value, '%Y-%m-%d %H:%i:%s')) IN (2,4)
            ORDER BY pm.meta_value ASC
            LIMIT 10
        ";
        
        $results = $wpdb->get_results($test_sql);
        
        error_log("🧪 DIRECT SQL TEST RESULTS:");
        foreach ($results as $result) {
            error_log("   ID: {$result->ID}, Title: {$result->post_title}, Date: {$result->event_date}, Day: {$result->day_of_week}");
        }
        
        if (empty($results)) {
            error_log("   ⚠️ No events found for Monday/Wednesday in October 2024");
        }
        
        wp_die('Direct SQL test complete. Check error logs for results.');
    }
}
add_action('init', 'test_dayofweek_sql_directly');
*/

/**
 * Debug function to check filters at each step
 */
/*
function debug_all_cost_filters() {
    if (!current_user_can('manage_options')) return;
    
    error_log("=== COMPLETE COST FILTER DEBUG ===");
    
    // 1. Check URL parameter
    error_log("URL cost parameter: " . ($_GET['cost'] ?? 'NOT SET'));
    
    // 2. Check active filters
    $active_filters = get_active_filters();
    if (isset($active_filters['cost_range'])) {
        error_log("Active cost_range filter: " . print_r($active_filters['cost_range'], true));
    } else {
        error_log("NO cost_range in active filters");
        error_log("All active filters: " . print_r(array_keys($active_filters), true));
    }
    
    // 3. Check current query
    global $wp_query;
    if (isset($wp_query->query_vars['_custom_cost_filter'])) {
        error_log("Query has _custom_cost_filter: " . print_r($wp_query->query_vars['_custom_cosst_filter'], true));
    } else {
        error_log("NO _custom_cost_filter in current query");
    }
}
add_action('wp', 'debug_all_cost_filters');
*/

function debug_venue_filters() {
    if (!current_user_can('manage_options')) return;
    
    error_log("=== COMPLETE VENUE FILTER DEBUG ===");
    
    // Check URL parameter
    error_log("URL venue parameter: " . ($_GET['venue'] ?? 'NOT SET'));
    
    // Check active filters
    $active_filters = get_active_filters();
    if (isset($active_filters['venue'])) {
        error_log("Active venue filter: " . print_r($active_filters['venue'], true));
    } else {
        error_log("NO venue in active filters");
    }
    
    // Check current query
    global $wp_query;
    if (isset($wp_query->query_vars['_custom_venue_filter'])) {
        error_log("Query has _custom_venue_filter: " . print_r($wp_query->query_vars['_custom_venue_filter'], true));
    } else {
        error_log("NO _custom_venue_filter in current query");
    }
}
add_action('wp', 'debug_venue_filters');

/**
 * Diagnostic function to show all organizer data in the system
 * This will help identify the exact format of organizer data in your database
 */
function diagnose_organizers_in_database() {
    global $wpdb;
    
    // Only run on admin pages to avoid affecting frontend performance
    if (!is_admin()) return;
    
    error_log("=== COMPLETE ORGANIZER DIAGNOSTICS ===");
    
    // 1. Check for tribe_organizer posts
    $organizer_posts = $wpdb->get_results("
        SELECT ID, post_title, post_name 
        FROM {$wpdb->posts} 
        WHERE post_type = 'tribe_organizer' 
        AND post_status = 'publish'
        ORDER BY post_title
        LIMIT 20
    ");
    
    error_log("Found " . count($organizer_posts) . " organizer posts:");
    foreach ($organizer_posts as $post) {
        error_log("ID: {$post->ID}, Title: {$post->post_title}, Slug: {$post->post_name}");
    }
    
    // 2. Check all organizer meta keys in the system
    $meta_keys = $wpdb->get_col("
        SELECT DISTINCT meta_key 
        FROM {$wpdb->postmeta} 
        WHERE meta_key LIKE '%organizer%' OR meta_key LIKE '%Organizer%'
    ");
    
    error_log("Found " . count($meta_keys) . " organizer-related meta keys:");
    error_log(print_r($meta_keys, true));
    
    // 3. Check how events reference organizers
    $event_organizer_refs = $wpdb->get_results("
        SELECT e.ID, e.post_title, pm.meta_key, pm.meta_value
        FROM {$wpdb->posts} e
        JOIN {$wpdb->postmeta} pm ON e.ID = pm.post_id
        WHERE e.post_type = 'tribe_events'
        AND (
            pm.meta_key = '_EventOrganizerID' OR 
            pm.meta_key = '_OrganizerID' OR 
            pm.meta_key = '_EventOrganizer'
        )
        AND pm.meta_value != ''
        LIMIT 30
    ");
    
    error_log("Found " . count($event_organizer_refs) . " event-organizer references:");
    foreach ($event_organizer_refs as $ref) {
        error_log("Event ID: {$ref->ID}, Title: {$ref->post_title}, Meta Key: {$ref->meta_key}, Value: {$ref->meta_value}");
        
        // If it's a numeric reference, show the actual organizer name
        if (is_numeric($ref->meta_value)) {
            $org_name = $wpdb->get_var($wpdb->prepare(
                "SELECT post_title FROM {$wpdb->posts} WHERE ID = %d",
                $ref->meta_value
            ));
            error_log("  → References organizer: " . ($org_name ?: "Unknown"));
        }
    }
    
    // 4. Look specifically for "Professional Training Group" in any format
    $professional_training = $wpdb->get_results($wpdb->prepare("
        SELECT e.ID, e.post_title, pm.meta_key, pm.meta_value
        FROM {$wpdb->posts} e
        JOIN {$wpdb->postmeta} pm ON e.ID = pm.post_id
        WHERE e.post_type = 'tribe_events'
        AND (
            pm.meta_value LIKE %s OR
            pm.meta_value LIKE %s OR
            pm.meta_value LIKE %s
        )
        LIMIT 10
    ", 
    '%Professional Training Group%',
    '%professional training group%',
    '%professional-training-group%'));
    
    error_log("Searching for 'Professional Training Group' (any format) found " . count($professional_training) . " matches:");
    foreach ($professional_training as $item) {
        error_log("Event ID: {$item->ID}, Title: {$item->post_title}, Meta Key: {$item->meta_key}, Value: {$item->meta_value}");
    }
    
    // 5. Check if we have any events in October 2024
    $october_events = $wpdb->get_results("
        SELECT e.ID, e.post_title, pm.meta_value as start_date
        FROM {$wpdb->posts} e
        JOIN {$wpdb->postmeta} pm ON e.ID = pm.post_id
        WHERE e.post_type = 'tribe_events'
        AND pm.meta_key = '_EventStartDate'
        AND pm.meta_value LIKE '2024-10%'
        LIMIT 20
    ");
    
    error_log("Found " . count($october_events) . " events in October 2024:");
    foreach ($october_events as $event) {
        error_log("Event ID: {$event->ID}, Title: {$event->post_title}, Start Date: {$event->start_date}");
    }
}

// Run on init - you can temporarily add this to diagnose your database
add_action('init', 'diagnose_organizers_in_database');
