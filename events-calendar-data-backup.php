// Temporarily show hidden custom fields
add_filter('is_protected_meta', '__return_false', 999);

// Hook into WordPress init action with high priority to run before the plugin
add_action('init', 'register_tribe_post_types_and_taxonomies', 0);

function register_tribe_post_types_and_taxonomies() {
    // Register Events post type
    if (!post_type_exists('tribe_events')) {
        register_post_type('tribe_events', array(
            'labels' => array(
                'name' => 'Events',
                'singular_name' => 'Event',
                'menu_name' => 'Events',
                'all_items' => 'All Events',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Event',
                'edit_item' => 'Edit Event',
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'events'),
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'comments'),
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-calendar',
        ));
    }

    // Register Event Series post type
    if (!post_type_exists('tribe_event_series')) {
        register_post_type('tribe_event_series', array(
            'labels' => array(
                'name' => 'Event Series',
                'singular_name' => 'Event Series',
                'menu_name' => 'Event Series',
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'event-series'),
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
            'show_in_rest' => true,
        ));
    }

    // Register Venue post type
    if (!post_type_exists('tribe_venue')) {
        register_post_type('tribe_venue', array(
            'labels' => array(
                'name' => 'Venues',
                'singular_name' => 'Venue',
                'menu_name' => 'Venues',
                'all_items' => 'All Venues',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Venue',
                'edit_item' => 'Edit Venue',
            ),
            'public' => true,
            'rewrite' => array('slug' => 'venue'),
            'supports' => array('title', 'editor', 'thumbnail'),
            'show_in_rest' => true,
        ));
    }

    // Register Organizer post type
    if (!post_type_exists('tribe_organizer')) {
        register_post_type('tribe_organizer', array(
            'labels' => array(
                'name' => 'Organizers',
                'singular_name' => 'Organizer',
                'menu_name' => 'Organizers',
                'all_items' => 'All Organizers',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Organizer',
                'edit_item' => 'Edit Organizer',
            ),
            'public' => true,
            'rewrite' => array('slug' => 'organizer'),
            'supports' => array('title', 'editor', 'thumbnail'),
            'show_in_rest' => true,
        ));
    }

    // Register Taxonomies
    // 1. Post Tags for Events
    if (!taxonomy_exists('post_tag')) {
        // post_tag already exists in WordPress, we just need to associate it with tribe_events
        register_taxonomy_for_object_type('post_tag', 'tribe_events');
    }

    // 2. Event Categories
    if (!taxonomy_exists('tribe_events_cat')) {
        register_taxonomy('tribe_events_cat', 'tribe_events', array(
            'labels' => array(
                'name' => 'Event Categories',
                'singular_name' => 'Event Category',
                'menu_name' => 'Event Categories',
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'event-category'),
            'show_in_rest' => true,
        ));
    }

    // 3. Venue Categories - now associated with both tribe_venue and tribe_events
    if (!taxonomy_exists('tec_venue_category')) {
        register_taxonomy('tec_venue_category', array('tribe_venue', 'tribe_events'), array(
            'labels' => array(
                'name' => 'Venue Categories',
                'singular_name' => 'Venue Category',
                'menu_name' => 'Venue Categories',
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'venue-category'),
            'show_in_rest' => true,
        ));
    }

    // 4. Organizer Categories - now associated with both tribe_organizer and tribe_events
    if (!taxonomy_exists('tec_organizer_category')) {
        register_taxonomy('tec_organizer_category', array('tribe_organizer', 'tribe_events'), array(
            'labels' => array(
                'name' => 'Organizer Categories',
                'singular_name' => 'Organizer Category',
                'menu_name' => 'Organizer Categories',
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'organizer-category'),
            'show_in_rest' => true,
        ));
    }
}

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
                <div style="height: 20px; width: 100%; background-color: #f0f0f0; margin-top: 10px;">
                    <div style="height: 100%; width: <?php echo $percent; ?>%; background-color: #0073aa;"></div>
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
    $batch_size = 50; // Process 50 events at a time - adjust based on your server capacity
    
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
            error_log('Error backing up event #' . $event_id . ': ' . $e->getMessage());
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
