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

function blog_sidebar_shortcode($atts) {
    ob_start();
    get_sidebar();
    return ob_get_clean();
}
add_shortcode('blog_sidebar', 'blog_sidebar_shortcode');

add_filter('tribe_events_rewrite_base_slugs', 'custom_events_rewrite_slugs');
function custom_events_rewrite_slugs($bases) {
    $bases['archive'] = ['us_events', 'us/events'];
    $bases['single'] = ['us_event', 'us/event'];
    $bases['tax'] = ['us_events_category', 'us/events/category'];
    $bases['tag'] = ['us_events_tag', 'us/events/tag'];
    return $bases;
}

add_action('init', 'custom_events_single_rewrite_rule', 11);
function custom_events_single_rewrite_rule() {
    add_rewrite_rule(
        '^us/event/([^/]+)/?$',
        'index.php?tribe_events=$matches[1]',
        'top'
    );
}

add_action('init', 'custom_events_category_tag_rewrite_rules', 11);
function custom_events_category_tag_rewrite_rules() {
    add_rewrite_rule(
        '^us_events/category/([^/]+)/?$',
        'index.php?tribe_events_cat=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^us_events/tag/([^/]+)/?$',
        'index.php?post_type=tribe_events&tag=$matches[1]',
        'top'
    );
}

add_filter('tribe_events_single_slug', 'custom_events_single_slug');
function custom_events_single_slug($slug) {
    return 'us/event';
}

add_action('template_redirect', 'custom_events_redirect');
function custom_events_redirect() {
    if (is_singular('tribe_events')) {
        $current_url = $_SERVER['REQUEST_URI'];
        $desired_url = str_replace('us_event/', 'us/event/', $current_url);

        if ($current_url !== $desired_url) {
            wp_redirect($desired_url, 301);
            exit;
        }
    }
}

add_filter('tribe_events_single_view_link', 'custom_events_single_view_link', 10, 2);
function custom_events_single_view_link($link, $post_id) {
    return str_replace('us_event/', 'us/event/', $link);
}

add_filter('post_type_link', 'custom_events_post_type_link', 10, 2);
function custom_events_post_type_link($post_link, $post) {
    if ($post->post_type === 'tribe_events') {
        $post_link = str_replace('us_event/', 'us/event/', $post_link);
    }
    return $post_link;
}

add_filter('term_link', 'custom_events_category_link', 10, 3);
function custom_events_category_link($termlink, $term, $taxonomy) {
    if ($taxonomy == 'tribe_events_cat') {
        $termlink = str_replace('/us_events/category/', '/us/events/category/', $termlink);
    }
    return $termlink;
}

add_filter('tribe_events_get_link', 'custom_events_get_link');
function custom_events_get_link($link) {
    $link = str_replace('us_events/', 'us/events/', $link);
    $link = str_replace('us_events_category/', 'us/events/category/', $link);
    $link = str_replace('us_events_tag/', 'us/events/tag/', $link);
    return $link;
}

add_action('tribe_events_pre_rewrite', 'custom_events_rewrite_rules');
function custom_events_rewrite_rules($rewrite) {
    // Archive pages
    $rewrite->archive(['us/events'], ['eventDisplay' => 'default']);
    $rewrite->archive(['us/events', '{{ page }}', '(\d+)'], ['eventDisplay' => 'list', 'paged' => '%1']);
    $rewrite->archive(['us/events', '{{ featured }}'], ['featured' => true]);
    $rewrite->archive(['us/events', '{{ month }}'], ['eventDisplay' => 'month']);
    $rewrite->archive(['us/events', '{{ list }}'], ['eventDisplay' => 'list']);
    $rewrite->archive(['us/events', '{{ today }}'], ['eventDisplay' => 'day']);
    $rewrite->archive(['us/events', '(\d{4}-\d{2})'], ['eventDisplay' => 'month', 'eventDate' => '%1']);
    $rewrite->archive(['us/events', '(\d{4}-\d{2}-\d{2})'], ['eventDisplay' => 'day', 'eventDate' => '%1']);

    // Single event pages
    $rewrite->single(['us/event', '([^/]+)'], ['eventDisplay' => 'single', 'name' => '%1']);

    // Taxonomy (category) pages
    $rewrite->tax(['us/events/category', '(?:[^/]+/)([^/]+)', '{{ page }}', '(\d+)'], ['eventDisplay' => 'list', 'tribe_events_cat' => '%1', 'paged' => '%2']);
    $rewrite->tax(['us/events/category', '(?:[^/]+/)([^/]+)', '{{ month }}'], ['eventDisplay' => 'month', 'tribe_events_cat' => '%1']);
    $rewrite->tax(['us/events/category', '(?:[^/]+/)([^/]+)', '{{ list }}'], ['eventDisplay' => 'list', 'tribe_events_cat' => '%1']);
    $rewrite->tax(['us/events/category', '(?:[^/]+/)([^/]+)', '{{ today }}'], ['eventDisplay' => 'day', 'tribe_events_cat' => '%1']);
    $rewrite->tax(['us/events/category', '(?:[^/]+/)([^/]+)', '(\d{4}-\d{2})'], ['eventDisplay' => 'month', 'tribe_events_cat' => '%1', 'eventDate' => '%2']);
    $rewrite->tax(['us/events/category', '(?:[^/]+/)([^/]+)', '(\d{4}-\d{2}-\d{2})'], ['eventDisplay' => 'day', 'tribe_events_cat' => '%1', 'eventDate' => '%2']);

    // Tag pages
    $rewrite->tag(['us/events/tag', '([^/]+)', '{{ page }}', '(\d+)'], ['eventDisplay' => 'list', 'tag' => '%1', 'paged' => '%2']);
    $rewrite->tag(['us/events/tag', '([^/]+)', '{{ month }}'], ['eventDisplay' => 'month', 'tag' => '%1']);
    $rewrite->tag(['us/events/tag', '([^/]+)', '{{ list }}'], ['eventDisplay' => 'list', 'tag' => '%1']);
    $rewrite->tag(['us/events/tag', '([^/]+)', '{{ today }}'], ['eventDisplay' => 'day', 'tag' => '%1']);
    $rewrite->tag(['us/events/tag', '([^/]+)', '(\d{4}-\d{2})'], ['eventDisplay' => 'month', 'tag' => '%1', 'eventDate' => '%2']);
    $rewrite->tag(['us/events/tag', '([^/]+)', '(\d{4}-\d{2}-\d{2})'], ['eventDisplay' => 'day', 'tag' => '%1', 'eventDate' => '%2']);
}

add_filter('tribe_events_rewrite_base_slugs', 'custom_events_rewrite_base_slugs');
function custom_events_rewrite_base_slugs($bases) {
    $bases['tax'] = ['us/events/category', 'category', tribe_get_option('categorySlug', 'category')];
    return $bases;
}

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
function add_events_taxonomy_query_vars($vars) {
    $vars[] = 'tribe_events_cat';
    $vars[] = 'category';  // Pretty param for tribe_events_cat/category
    $vars[] = 'keyword';   // Pretty param for post_tag
    $vars[] = 'dayofweek';
    $vars[] = 'timeperiod'; 
    return $vars;
}
add_filter('query_vars', 'add_events_taxonomy_query_vars');

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
    flush_rewrite_rules();
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
 * Event Taxonomy Filtering
 * ===============================================================================
 */

/**
 * Helper: Parses filter data from an AJAX request.
 *
 * @param array $ajax_data The AJAX data array.
 * @return array Processed taxonomy filters.
 */

/**
 * Helper: Parses taxonomy filters from URL query parameters.
 * Handles both pretty URL parameters ('category', 'keyword')
 * and legacy parameters ('tribe_events_cat', 'post_tag').
 *
 * @return array Processed taxonomy filters.
 */
function _get_filters_from_ajax_data($ajax_data) {
    $filters = [];
    if (!empty($ajax_data['taxonomy_filters'])) {
        foreach ($ajax_data['taxonomy_filters'] as $taxonomy => $term_ids) {
            if (!empty($term_ids)) {
                $filters[$taxonomy] = array_map('intval', (array)$term_ids);
            }
        }
    }

    // NEW: Handle day-of-week filters from AJAX
    if (!empty($ajax_data['dayofweek_filters'])) {
        $days = array_map('intval', (array)$ajax_data['dayofweek_filters']);
        $valid_days = array_filter($days, function($day) {
            return $day >= 1 && $day <= 7;
        });
        if (!empty($valid_days)) {
            $filters['dayofweek'] = $valid_days;
        }
    }

    // NEW: Handle time period filters from AJAX
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

    //error_log('🔄 Filters from AJAX data: ', $filters);
    return $filters;
}

function _get_filters_from_url_params() {
    $filters = [];

    // Check for 'category' parameter (can be pretty or legacy)
    if (!empty($_GET['category'])) {
        $category_values = is_array($_GET['category']) ? $_GET['category'] : explode(',', $_GET['category']);
        // By default, map 'category' to 'tribe_events_cat' for event-related contexts
        $filters['tribe_events_cat'] = array_map('intval', $category_values);
        //error_log('🔍 URL parameter "category" filter (mapped to tribe_events_cat): ', $filters['tribe_events_cat']);
    }

    // Check for 'keyword' parameter (can be pretty or legacy)
    if (!empty($_GET['keyword'])) {
        $keyword_values = is_array($_GET['keyword']) ? $_GET['keyword'] : explode(',', $_GET['keyword']);
        // Map 'keyword' to 'post_tag'
        $filters['post_tag'] = array_map('intval', $keyword_values);
        //error_log('🔍 URL parameter "keyword" filter (mapped to post_tag): ', $filters['post_tag']);
    }

    // Legacy parameter 'tribe_events_cat' (explicit event category)
    if (!empty($_GET['tribe_events_cat'])) {
        $tribe_cat_values = is_array($_GET['tribe_events_cat']) ? $_GET['tribe_events_cat'] : explode(',', $_GET['tribe_events_cat']);
        $filters['tribe_events_cat'] = array_map('intval', $tribe_cat_values);
        //error_log('🔍 URL parameter legacy "tribe_events_cat" filter: ', $filters['tribe_events_cat']);
    }

    // Legacy parameter 'post_tag' (explicit post tag)
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

/**
 * MASTER FUNCTION: Get all active taxonomy filters based on priority.
 * This function determines what categories/tags are currently being filtered.
 *
 * @param array $ajax_data Optional AJAX data containing filters.
 * @return array An array of active taxonomy filters, e.g., ['tribe_events_cat' => [1, 2], 'post_tag' => [10]].
 */
function get_active_taxonomy_filters($ajax_data = array()) {
    $filters = []; // Start with an empty list of filters

    // Priority 1: Check AJAX data if provided.
    // This is like someone directly telling us what filters to apply.
    $ajax_filters = _get_filters_from_ajax_data($ajax_data);
    if (!empty($ajax_filters)) {
        //error_log('✅ Filters determined from AJAX data.');
        return $ajax_filters; // If AJAX data is present, use it and stop.
    }

    // Priority 2: Get current archive context (Date, Taxonomy, or Base).
    // This checks what kind of page we're on based on its URL.
    $archive_context = get_current_archive_context();
    //error_log('📍 Current archive context: ' . print_r($archive_context, true));

    // CONTEXT-SPECIFIC FILTER DETECTION
    // If we're on a dedicated taxonomy page (like /events/category/business/)
    if ($archive_context['context'] === 'taxonomy') {
        // The filter IS the page itself. So, we get the term ID from the context.
        if ($archive_context['term_id']) {
            $filters[$archive_context['taxonomy']] = array($archive_context['term_id']);
            //error_log('🏷️ Taxonomy context: Filter is explicitly set by the URL as ' . $archive_context['taxonomy'] . ' = [' . $archive_context['term_id'] . ']');

            // ADD: Store this as "locked" filter for JavaScript
            $filters['_taxonomy_archive_locked'] = array(
                'taxonomy' => $archive_context['taxonomy'],
                'term_id' => $archive_context['term_id'],
                'term_slug' => $archive_context['term_slug']
            );
        }
        return $filters;
    }

    // If we're on a date archive page (like /events/2024/12/) OR a base events page (/events/)
    // In these cases, we look for additional filters specified in the URL's query parameters.
    if ($archive_context['context'] === 'date' || $archive_context['context'] === 'base') {
        //error_log('📅🏠 Date or Base context: Checking URL parameters for additional filters.');
        // Call our new helper function to get filters from the URL query string.
        $filters = _get_filters_from_url_params();

        // NEW: Add day-of-week and time period filters for date archives only
        if ($archive_context['context'] === 'date') {
            $filters = array_merge($filters, _get_dayofweek_filters_from_url());
            $filters = array_merge($filters, _get_timeperiod_filters_from_url());
        }

        //error_log('✅ Filters determined from URL parameters.');
        return $filters;
    }

    // Fallback: If no specific context or AJAX data, return an empty array.
    //error_log('🤷 No specific filters detected based on context or AJAX data.');
    return $filters;
}

// NEW: Helper function to get day-of-week filters from URL parameters
function _get_dayofweek_filters_from_url() {
    $filters = [];

    if (!empty($_GET['dayofweek'])) {
        $days = is_array($_GET['dayofweek']) ? $_GET['dayofweek'] : explode(',', $_GET['dayofweek']);
        // Validate day numbers (1-7, where 1=Sunday in WordPress)
        $valid_days = array_filter($days, function($day) {
            return is_numeric($day) && $day >= 1 && $day <= 7;
        });

        if (!empty($valid_days)) {
            $filters['dayofweek'] = array_map('intval', $valid_days);
            //error_log('📅 Day of week filter found: ', $valid_days);
        }
    }

    return $filters;
}

// NEW: Helper function to get time period filters from URL parameters  
function _get_timeperiod_filters_from_url() {
    $filters = [];

    if (!empty($_GET['timeperiod'])) {
        $periods = is_array($_GET['timeperiod']) ? $_GET['timeperiod'] : explode(',', $_GET['timeperiod']);
        // Validate time periods
        $valid_periods = array('allday', 'morning', 'afternoon', 'evening', 'night');
        $filtered_periods = array_filter($periods, function($period) use ($valid_periods) {
            return in_array($period, $valid_periods);
        });

        if (!empty($filtered_periods)) {
            $filters['timeperiod'] = $filtered_periods;
            //error_log('🕐 Time period filter found: ', $filtered_periods);
        }
    }

    return $filters;
}

// UPDATED get_current_taxonomy_filters() - DEPRECATED, use get_active_taxonomy_filters()
/**
 * @deprecated Use get_active_taxonomy_filters() instead
 * ⚠️ This function only checks $_GET and doesn't handle AJAX context properly
*/
function get_current_taxonomy_filters() {
    /*
    // OLD IMPLEMENTATION - COMMENTED OUT
    $filters = array();

    // Check URL parameters
    if (isset($_GET['tribe_events_cat']) && !empty($_GET['tribe_events_cat'])) {
        $filters['tribe_events_cat'] = array_map('intval', (array)$_GET['tribe_events_cat']);
    }

    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $filters['category'] = array_map('intval', (array)$_GET['category']);
    }

    if (isset($_GET['post_tag']) && !empty($_GET['post_tag'])) {
        $filters['post_tag'] = array_map('intval', (array)$_GET['post_tag']);
    }

    return $filters;
    */
    // NEW CENTRALIZED IMPLEMENTATION
    return get_active_taxonomy_filters();
}

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

            if ($debug_output !== '') {
                $debug_output .= '<div style="background: 
#e1f5fe; border: 1px solid 
#03a9f4; padding: 5px; margin: 2px 0; font-size: 10px;">';
                $debug_output .= 'Added filter: ' . $taxonomy . ' = [' . implode(', ', $term_ids) . ']';
                $debug_output .= '</div>';
            }
        }
    }

    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    return $args;
}

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
    if (empty($post_type) || $post_type === 'post') {
        $archive_context = get_current_archive_context();
        if ($archive_context['post_type']) {
            $post_type = $archive_context['post_type'];
        }
    }

    // Get date archive context
    $archive_info = is_custom_post_type_date_archive_by_url();

    // Build query args for posts in current date archive
    $post_query_args = array(
        'post_type' => $post_type,
        'post_status' => array('publish', 'future'),
        'posts_per_page' => -1,
        'fields' => 'ids' // Only get post IDs for efficiency
    );

    // Add date filtering if we're on a date archive
    if ($archive_info && ($archive_info['post_type'] === $post_type)) {
        if ($post_type === 'tribe_events') {
            // For events, filter by event start date
            $date_pattern = '';

            if ($archive_info['day'] && $archive_info['month']) {
                $date_pattern = sprintf('%04d-%02d-%02d', $archive_info['year'], $archive_info['month'], $archive_info['day']);
            } elseif ($archive_info['month']) {
                $date_pattern = sprintf('%04d-%02d', $archive_info['year'], $archive_info['month']);
            } else {
                $date_pattern = sprintf('%04d', $archive_info['year']);
            }

            $post_query_args['meta_query'] = array(
                array(
                    'key' => '_EventStartDate',
                    'value' => $date_pattern,
                    'compare' => 'LIKE'
                )
            );
        } else {
            // For regular posts, filter by post date
            if ($archive_info['year']) {
                $post_query_args['year'] = $archive_info['year'];
            }
            if ($archive_info['month']) {
                $post_query_args['monthnum'] = $archive_info['month'];
            }
            if ($archive_info['day']) {
                $post_query_args['day'] = $archive_info['day'];
            }
        }
    }

    // Get posts that match the date criteria
    $matching_posts = get_posts($post_query_args);

    // Get terms, but only include those that have posts in the current date archive
    $terms_args = array(
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC'
    );

    // If we have date filtering, only get terms that are used by posts in this date range
    if (!empty($matching_posts)) {
        $terms_args['object_ids'] = $matching_posts;
    }

    $terms = get_terms($terms_args);

    if (is_wp_error($terms) || empty($terms)) {
        return '<select disabled><option>No terms found for this date</option></select>';
    }

    $multiple_attr = $multiple ? 'multiple' : '';
    $name_attr = $multiple ? $taxonomy . '[]' : $taxonomy;

    // 
    $html = '<select class="taxonomy-filter-select" name="' . $name_attr . '" id="' . $id . '" data-taxonomy="' . $taxonomy . '" ' . $multiple_attr . '>';

    if (!$multiple) {
        $html .= '<option value="">All ' . ucfirst($taxonomy) . '</option>';
    }

    // ADD: Check for pre-selection based on archive context
    $archive_context = get_current_archive_context();
    $preselected_term_id = null;
    if ($archive_context['context'] === 'taxonomy' && $archive_context['taxonomy'] === $taxonomy) {
        $preselected_term_id = $archive_context['term_id'];
    }

    foreach ($terms as $term) {
        $term_post_count = count(array_intersect($matching_posts, get_objects_in_term($term->term_id, $taxonomy)));

        // ADD: Mark as selected if this is the archive's taxonomy term
        $selected = ($preselected_term_id == $term->term_id) ? ' selected' : '';

        $html .= '<option value="' . $term->term_id . '"' . $selected . '>' . $term->name . ' (' . $term_post_count . ')</option>';
    }

    $html .= '</select>';

    // Debug info
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $html .= '<div style="font-size: 11px; color: #666; margin-top: 5px;">';
        $html .= 'Debug: Found ' . count($matching_posts) . ' posts, ' . count($terms) . ' terms';
        if ($archive_info) {
            $html .= ' | Date: ' . $archive_info['year'];
            if ($archive_info['month']) $html .= '/' . $archive_info['month'];
            if ($archive_info['day']) $html .= '/' . $archive_info['day'];
        }
        $html .= '</div>';
    }

    return $html;
}

// AJAX Handler for filtering posts - callback function - handles both logged-in and non-logged-in users
/**
 * FIXED AJAX Handler for filtering posts - handles advanced filtering correctly
 * This bypasses the complex query building and uses a direct approach
 */
function wp_ajax_filter_posts_by_taxonomy() {
    ob_start();

    $debug_output = '';

    if (!wp_verify_nonce($_POST['nonce'], 'taxonomy_filter_nonce')) {
        $debug_output .= '<div style="background: 
#ffebee; border: 1px solid 
#f44336; padding: 10px; color: 
#c62828;">';
        $debug_output .= '❌ Security check failed';
        $debug_output .= '</div>';

        ob_end_clean();
        wp_send_json_error(array(
            'message' => 'Security check failed',
            'debug' => $debug_output
        ));
        return;
    }

    $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
    $current_page_url = sanitize_text_field($_POST['current_page_url'] ?? '');

    $debug_output .= '<div style="background: 
#e3f2fd; border: 1px solid 
#2196f3; padding: 10px; margin: 5px 0; font-size: 12px;">';
    $debug_output .= '<strong>🔧 FIXED AJAX HANDLER</strong><br>';
    $debug_output .= 'Post Type: ' . $post_type . '<br>';
    $debug_output .= 'Current URL: ' . $current_page_url . '<br>';
    $debug_output .= '</div>';

    // Extract filters from AJAX data
    $taxonomy_filters = array();
    if (!empty($_POST['taxonomy_filters'])) {
        foreach ($_POST['taxonomy_filters'] as $taxonomy => $term_ids) {
            if (!empty($term_ids)) {
                $taxonomy_filters[$taxonomy] = array_map('intval', (array)$term_ids);
            }
        }
    }

    $dayofweek_filters = array();
    if (!empty($_POST['dayofweek_filters'])) {
        $dayofweek_filters = array_map('intval', (array)$_POST['dayofweek_filters']);
    }

    $timeperiod_filters = array();
    if (!empty($_POST['timeperiod_filters'])) {
        $timeperiod_filters = (array)$_POST['timeperiod_filters'];
    }

    $debug_output .= '<div style="background: 
#f3e5f5; border: 1px solid 
#9c27b0; padding: 10px; margin: 5px 0; font-size: 12px;">';
    $debug_output .= '<strong>📝 EXTRACTED FILTERS</strong><br>';
    $debug_output .= 'Taxonomy Filters: <pre>' . print_r($taxonomy_filters, true) . '</pre>';
    $debug_output .= 'Day of Week Filters: <pre>' . print_r($dayofweek_filters, true) . '</pre>';
    $debug_output .= 'Time Period Filters: <pre>' . print_r($timeperiod_filters, true) . '</pre>';
    $debug_output .= '</div>';

    // SIMPLIFIED QUERY BUILDING - No complex date archive detection
    $args = array(
        'post_type' => $post_type,
        'post_status' => array('publish', 'future'),
        'posts_per_page' => -1, // Get all posts, we'll limit in results
        'meta_key' => '_EventStartDate',
        'orderby' => 'meta_value',
        'order' => 'ASC'
    );

    // Add taxonomy filters to query if present
    if (!empty($taxonomy_filters)) {
        $tax_query = array('relation' => 'AND');

        foreach ($taxonomy_filters as $taxonomy => $term_ids) {
            if (!empty($term_ids)) {
                $tax_query[] = array(
                    'taxonomy' => sanitize_text_field($taxonomy),
                    'field' => 'term_id',
                    'terms' => array_map('intval', $term_ids),
                    'operator' => 'IN'
                );

                $debug_output .= '<div style="background: 
#e1f5fe; border: 1px solid 
#03a9f4; padding: 5px; margin: 2px 0; font-size: 10px;">';
                $debug_output .= 'Added taxonomy filter: ' . $taxonomy . ' = [' . implode(', ', $term_ids) . ']';
                $debug_output .= '</div>';
            }
        }

        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }
    }

    // ADD DATE RESTRICTION ONLY if we can detect it from URL
    if (preg_match('#/events/(\d{4})/(\d{1,2})(?:/(\d{1,2}))?#', $current_page_url, $matches)) {
        $year = $matches[1];
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $day = isset($matches[3]) ? str_pad($matches[3], 2, '0', STR_PAD_LEFT) : null;

        if ($day) {
            // Daily archive
            $date_pattern = sprintf('%04d-%02d-%02d', $year, $month, $day);
        } elseif ($month) {
            // Monthly archive  
            $date_pattern = sprintf('%04d-%02d', $year, $month);
        } else {
            // Yearly archive
            $date_pattern = sprintf('%04d', $year);
        }

        $args['meta_query'] = array(
            array(
                'key' => '_EventStartDate',
                'value' => $date_pattern,
                'compare' => 'LIKE'
            )
        );

        $debug_output .= '<div style="background: 
#fff3cd; border: 1px solid 
#856404; padding: 5px; margin: 2px 0; font-size: 10px;">';
        $debug_output .= 'Added date restriction from URL: _EventStartDate LIKE "' . $date_pattern . '"';
        $debug_output .= '</div>';
    }

    // Execute the query
    $query = new WP_Query($args);

    $debug_output .= '<div style="background: 
#e8f5e8; border: 1px solid 
#4caf50; padding: 10px; margin: 5px 0; font-size: 12px;">';
    $debug_output .= '<strong>📊 INITIAL QUERY RESULTS</strong><br>';
    $debug_output .= 'Found: ' . $query->found_posts . ' posts BEFORE advanced filtering<br>';
    $debug_output .= 'Posts retrieved: ' . count($query->posts) . '<br>';
    $debug_output .= '</div>';

    // APPLY ADVANCED FILTERING (Day of Week + Time Period)
    $final_posts = $query->posts;

    if (!empty($dayofweek_filters) || !empty($timeperiod_filters)) {
        $debug_output .= '<div style="background: 
#fff3cd; border: 1px solid 
#856404; padding: 5px; margin: 5px 0; font-size: 12px;">';
        $debug_output .= '<strong>⏰ APPLYING ADVANCED FILTERS...</strong><br>';

        $filtered_posts = array();
        $filter_stats = array(
            'total_checked' => 0,
            'no_start_date' => 0,
            'bad_date' => 0,
            'day_pass' => 0,
            'day_fail' => 0,
            'time_pass' => 0,
            'time_fail' => 0,
            'both_pass' => 0
        );

        foreach ($query->posts as $post) {
            $filter_stats['total_checked']++;

            // Get event start date
            $start_date = get_post_meta($post->ID, '_EventStartDate', true);
            if (!$start_date) {
                $filter_stats['no_start_date']++;
                // IMPORTANT: Continue to next post, but log this issue
                $debug_output .= '<div style="background: 
#ffebee; color: 
#c62828; padding: 2px; margin: 1px 0; font-size: 9px;">';
                $debug_output .= '⚠️ Event "' . $post->post_title . '" (ID: ' . $post->ID . ') has no _EventStartDate';
                $debug_output .= '</div>';
                continue;
            }

            $start_timestamp = strtotime($start_date);
            if (!$start_timestamp) {
                $filter_stats['bad_date']++;
                $debug_output .= '<div style="background: 
#ffebee; color: 
#c62828; padding: 2px; margin: 1px 0; font-size: 9px;">';
                $debug_output .= '⚠️ Event "' . $post->post_title . '" has unparseable date: "' . $start_date . '"';
                $debug_output .= '</div>';
                continue;
            }

            $day_of_week = date('w', $start_timestamp) + 1; // Convert to 1-7 format
            $hour = (int) date('H', $start_timestamp);
            $allday_meta = get_post_meta($post->ID, '_EventAllDay', true);
            $is_all_day = in_array($allday_meta, array('yes', '1', 'true', 'on'));

            // Check day filter
            $passes_day_filter = true;
            if (!empty($dayofweek_filters)) {
                $passes_day_filter = in_array($day_of_week, $dayofweek_filters);
            }

            if ($passes_day_filter) {
                $filter_stats['day_pass']++;
            } else {
                $filter_stats['day_fail']++;
            }

            // Check time filter
            $passes_time_filter = true;
            if (!empty($timeperiod_filters)) {
                $passes_time_filter = false;

                foreach ($timeperiod_filters as $period) {
                    switch ($period) {
                        case 'allday':
                            if ($is_all_day) $passes_time_filter = true;
                            break;
                        case 'morning':
                            if (!$is_all_day && $hour >= 6 && $hour < 12) $passes_time_filter = true;
                            break;
                        case 'afternoon':
                            if (!$is_all_day && $hour >= 12 && $hour < 17) $passes_time_filter = true;
                            break;
                        case 'evening':
                            if (!$is_all_day && $hour >= 17 && $hour < 21) $passes_time_filter = true;
                            break;
                        case 'night':
                            if (!$is_all_day && ($hour >= 21 || $hour < 6)) $passes_time_filter = true;
                            break;
                    }
                    if ($passes_time_filter) break;
                }
            }

            if ($passes_time_filter) {
                $filter_stats['time_pass']++;
            } else {
                $filter_stats['time_fail']++;
            }

            // Must pass both filters
            if ($passes_day_filter && $passes_time_filter) {
                $filtered_posts[] = $post;
                $filter_stats['both_pass']++;
            }
        }

        $final_posts = $filtered_posts;

        $debug_output .= '<strong>📊 ADVANCED FILTER STATS:</strong><br>';
        $debug_output .= 'Total posts checked: ' . $filter_stats['total_checked'] . '<br>';
        $debug_output .= 'No start date: ' . $filter_stats['no_start_date'] . '<br>';
        $debug_output .= 'Bad date format: ' . $filter_stats['bad_date'] . '<br>';
        $debug_output .= 'Passed day filter: ' . $filter_stats['day_pass'] . '<br>';
        $debug_output .= 'Failed day filter: ' . $filter_stats['day_fail'] . '<br>';
        $debug_output .= 'Passed time filter: ' . $filter_stats['time_pass'] . '<br>';
        $debug_output .= 'Failed time filter: ' . $filter_stats['time_fail'] . '<br>';
        $debug_output .= '<strong>Final result: ' . count($final_posts) . ' posts</strong><br>';
        $debug_output .= '</div>';
    }

    // Generate results HTML
    $results_html = '';

    $results_html .= '<div style="border: 2px solid 
#4CAF50; padding: 15px; margin: 10px 0; background: 
#f9f9f9;">';
    $results_html .= '<h3>🎉 FIXED AJAX RESULTS</h3>';
    $results_html .= '<p><strong>Post Type:</strong> ' . esc_html($post_type) . '</p>';
    $results_html .= '<p><strong>Total Found:</strong> ' . count($final_posts) . ' posts</p>';

    // Show active filters
    if (!empty($taxonomy_filters) || !empty($dayofweek_filters) || !empty($timeperiod_filters)) {
        $results_html .= '<p><strong>Active Filters:</strong></p><ul>';

        foreach ($taxonomy_filters as $filter_key => $filter_values) {
            $results_html .= '<li>' . esc_html($filter_key) . ': ' . implode(', ', array_map('esc_html', $filter_values)) . '</li>';
        }

        if (!empty($dayofweek_filters)) {
            $day_names = array(1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday');
            $day_labels = array_map(function($day) use ($day_names) { return $day_names[$day] ?? $day; }, $dayofweek_filters);
            $results_html .= '<li>Day of Week: ' . implode(', ', $day_labels) . '</li>';
        }

        if (!empty($timeperiod_filters)) {
            $results_html .= '<li>Time Period: ' . implode(', ', $timeperiod_filters) . '</li>';
        }

        $results_html .= '</ul>';
    }

    $results_html .= '<hr style="margin: 15px 0;">';

    // Show sample results (first 10)
    if (!empty($final_posts)) {
        $results_html .= '<div style="max-height: 400px; overflow-y: auto;">';
        $count = 0;
        foreach ($final_posts as $post) {
            if ($count >= 10) break;

            $results_html .= '<div style="border: 1px solid #ddd; padding: 10px; margin: 5px 0; background: white;">';
            $results_html .= '<h4 style="margin: 0 0 5px 0;"><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></h4>';
            $results_html .= '<p style="margin: 0; font-size: 12px; color: #666;">Published: ' . get_the_date('', $post->ID) . '</p>';

            if ($post_type === 'tribe_events') {
                $event_start = get_post_meta($post->ID, '_EventStartDate', true);
                if ($event_start) {
                    $day_of_week = date('l', strtotime($event_start));
                    $time_of_day = date('g:i A', strtotime($event_start));
                    $is_all_day = get_post_meta($post->ID, '_EventAllDay', true);

                    $results_html .= '<p style="margin: 0; font-size: 12px; color: #666;">Event: ' . date('Y-m-d', strtotime($event_start)) . ' (' . $day_of_week . ')';

                    if (in_array($is_all_day, array('yes', '1', 'true', 'on'))) {
                        $results_html .= ' - All Day';
                    } else {
                        $results_html .= ' at ' . $time_of_day;
                    }

                    $results_html .= '</p>';
                }
            }

            $results_html .= '</div>';
            $count++;
        }

        if (count($final_posts) > 10) {
            $results_html .= '<p style="text-align: center; font-style: italic;">... and ' . (count($final_posts) - 10) . ' more posts</p>';
        }

        $results_html .= '</div>';
    } else {
        $results_html .= '<p style="color: 
#ff6b6b; font-weight: bold;">No posts found matching your criteria.</p>';
    }

    $results_html .= '</div>';

    ob_end_clean();

    wp_send_json_success(array(
        'html' => $debug_output . $results_html,
        'found_posts' => count($final_posts),
        'max_pages' => ceil(count($final_posts) / 20),
        'debug_only' => $debug_output,
        'results_only' => $results_html
    ));
}
add_action('wp_ajax_filter_posts_by_taxonomy', 'wp_ajax_filter_posts_by_taxonomy');
add_action('wp_ajax_nopriv_filter_posts_by_taxonomy', 'wp_ajax_filter_posts_by_taxonomy');

/* 
 * Helper functions for custom WHERE clause modifications
 */

// NEW: Apply day-of-week filtering to WHERE clause for events
function apply_dayofweek_filter_to_where_clause($where, $dayofweek_values) {
    global $wpdb;

    if (empty($dayofweek_values)) {
        return $where;
    }

    $day_conditions = array();
    foreach ($dayofweek_values as $day) {
        // WordPress DAYOFWEEK() returns 1=Sunday, 2=Monday, etc.
        $day_conditions[] = $wpdb->prepare("DAYOFWEEK(STR_TO_DATE(pm_event_date.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) = %d", $day);
    }

    if (!empty($day_conditions)) {
        $where .= " AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm_event_date 
            WHERE pm_event_date.post_id = {$wpdb->posts}.ID 
            AND pm_event_date.meta_key = '_EventStartDate'
            AND (" . implode(' OR ', $day_conditions) . ")
        )";
    }

    return $where;
}

function apply_timeperiod_filter_to_where_clause($where, $timeperiod_values) {
    global $wpdb;

    if (empty($timeperiod_values)) {
        return $where;
    }

    $time_conditions = array();

    foreach ($timeperiod_values as $period) {
        switch ($period) {
            case 'allday':
                // Check for all-day events (this might need adjustment based on how all-day is stored)
                $time_conditions[] = "pm_event_allday.meta_value = 'yes'";
                break;
            case 'morning':
                // 6 AM to 12 PM
                $time_conditions[] = "HOUR(STR_TO_DATE(pm_event_time.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 6 AND HOUR(STR_TO_DATE(pm_event_time.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 12";
                break;
            case 'afternoon':
                // 12 PM to 5 PM  
                $time_conditions[] = "HOUR(STR_TO_DATE(pm_event_time.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 12 AND HOUR(STR_TO_DATE(pm_event_time.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 17";
                break;
            case 'evening':
                // 5 PM to 9 PM
                $time_conditions[] = "HOUR(STR_TO_DATE(pm_event_time.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 17 AND HOUR(STR_TO_DATE(pm_event_time.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 21";
                break;
            case 'night':
                // 9 PM to 6 AM (next day)
                $time_conditions[] = "(HOUR(STR_TO_DATE(pm_event_time.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 21 OR HOUR(STR_TO_DATE(pm_event_time.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 6)";
                break;
        }
    }

    if (!empty($time_conditions)) {
        $joins_needed = array();

        // Add joins for all-day check if needed
        if (in_array('allday', $timeperiod_values)) {
            $joins_needed[] = "LEFT JOIN {$wpdb->postmeta} pm_event_allday ON pm_event_allday.post_id = {$wpdb->posts}.ID AND pm_event_allday.meta_key = '_EventAllDay'";
        }

        // Add join for time-based filtering if needed
        if (array_intersect($timeperiod_values, ['morning', 'afternoon', 'evening', 'night'])) {
            $joins_needed[] = "LEFT JOIN {$wpdb->postmeta} pm_event_time ON pm_event_time.post_id = {$wpdb->posts}.ID AND pm_event_time.meta_key = '_EventStartDate'";
        }

        // Note: This is a simplified approach. In a production environment, you'd want to 
        // handle the JOINs more carefully to avoid duplicates
        $where .= " AND (" . implode(' OR ', $time_conditions) . ")";
    }

    return $where;
}

// taxonomy filter shortcode
function taxonomy_filter_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_type' => 'post',
        'show_categories' => 'true',
        'show_tags' => 'true',
        'show_dayofweek' => 'true',
        'show_timeperiod' => 'true',
        'ajax_target' => '#primary'
    ), $atts, 'taxonomy_filter');

    // Auto-detect post type from context (existing logic)
    $date_info = is_custom_post_type_date_archive_by_url();
    if ($date_info) {
        $atts['post_type'] = $date_info['post_type'];
    } 

    $taxonomy_info = is_custom_post_type_taxonomy_archive_by_url();
    if ($taxonomy_info) {
        $atts['post_type'] = $taxonomy_info['post_type'];
    } 

    if ($atts['post_type'] === 'post') {
        $archive_context = get_current_archive_context();
        if ($archive_context['post_type'] === 'tribe_events') {
            $atts['post_type'] = 'tribe_events';
        } 
    }

    if ($atts['post_type'] === 'post') {
        $wp_post_type = get_query_var('post_type');
        if ($wp_post_type === 'tribe_events') {
            $atts['post_type'] = 'tribe_events'; 
        } 
    }

    if ($atts['post_type'] === 'post') {
        $current_url = $_SERVER['REQUEST_URI'];
        if (strpos($current_url, '/events/') !== false) {
            $atts['post_type'] = 'tribe_events';
        }
    }

    $post_type = $atts['post_type'];
    $archive_context = get_current_archive_context();

    // NEW: Only show day-of-week and time period filters on date archives for events
    $show_advanced_filters = ($post_type === 'tribe_events' && $archive_context['context'] === 'date');

    ob_start();
    ?>
    <div class="taxonomy-filter-container" data-post-type="<?php echo esc_attr($post_type); ?>" data-ajax-target="<?php echo esc_attr($atts['ajax_target']); ?>">

        <?php
        $taxonomies = get_post_type_taxonomies($post_type);
        ?>

        <?php if ($atts['show_categories'] === 'true' && !empty($taxonomies['category'])): ?>
            <div class="filter-group">
                <label for="category-filter"><?php echo $taxonomies['category']['label']; ?>:</label>
                <?php echo render_taxonomy_dropdown($taxonomies['category']['name'], $post_type, 'category-filter'); ?>
            </div>
        <?php endif; ?>

        <?php if ($atts['show_tags'] === 'true' && !empty($taxonomies['tag'])): ?>
            <div class="filter-group">
                <label for="tag-filter"><?php echo $taxonomies['tag']['label']; ?>:</label>
                <?php echo render_taxonomy_dropdown($taxonomies['tag']['name'], $post_type, 'tag-filter', 'multiple'); ?>
            </div>
        <?php endif; ?>

        <?php if ($show_advanced_filters && $atts['show_dayofweek'] === 'true'): ?>
            <div class="filter-group">
                <label>Day of Week:</label>
                <div class="dayofweek-filter-checkboxes">
                    <?php
                    $days = array(
                        1 => 'Sunday',
                        2 => 'Monday', 
                        3 => 'Tuesday',
                        4 => 'Wednesday',
                        5 => 'Thursday',
                        6 => 'Friday',
                        7 => 'Saturday'
                    );

                    $selected_days = array();
                    if (!empty($_GET['dayofweek'])) {
                        $selected_days = is_array($_GET['dayofweek']) ? $_GET['dayofweek'] : explode(',', $_GET['dayofweek']);
                    }

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
        <?php endif; ?>

        <?php if ($show_advanced_filters && $atts['show_timeperiod'] === 'true'): ?>
            <div class="filter-group">
                <label>Time Period:</label>
                <div class="timeperiod-filter-checkboxes">
                    <?php
                    $time_periods = array(
                        'allday' => 'All Day',
                        'morning' => 'Morning (6 AM - 12 PM)',
                        'afternoon' => 'Afternoon (12 PM - 5 PM)', 
                        'evening' => 'Evening (5 PM - 9 PM)',
                        'night' => 'Night (9 PM - 6 AM)'
                    );

                    $selected_periods = array();
                    if (!empty($_GET['timeperiod'])) {
                        $selected_periods = is_array($_GET['timeperiod']) ? $_GET['timeperiod'] : explode(',', $_GET['timeperiod']);
                    }

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
        <?php endif; ?>

        <button type="button" class="clear-filters">Clear Filters</button>

        <!-- Hidden fields to store current context -->
        <input type="hidden" class="current-year" value="<?php echo get_query_var('year'); ?>">
        <input type="hidden" class="current-month" value="<?php echo get_query_var('monthnum'); ?>">
        <input type="hidden" class="current-day" value="<?php echo get_query_var('day'); ?>">
    </div>

    <div id="filter-loading" style="display: none;">Loading...</div>

    <style>
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
    </style>
    <?php

    return ob_get_clean();
}
add_shortcode('taxonomy_filter', 'taxonomy_filter_shortcode');

/**
 * Debug taxonomy filtering in calendar
 */
function debug_calendar_taxonomy_shortcode($atts) {
    $atts = shortcode_atts(array(
        'year' => date('Y'),
        'month' => date('m')
    ), $atts);

    global $wpdb;

    $thismonth = str_pad($atts['month'], 2, '0', STR_PAD_LEFT);
    $thisyear = $atts['year'];

    // Get current taxonomy filters (same logic as calendar generation)
    $taxonomy_filters = function_exists('get_current_taxonomy_filters') ? get_current_taxonomy_filters() : array();

    // CRITICAL: If no taxonomy filters passed, check URL parameters (same as calendar generation)
    if (empty($taxonomy_filters)) {
        if (!empty($_GET['category'])) {
            $category_values = is_array($_GET['category']) ? $_GET['category'] : explode(',', $_GET['category']);
            $taxonomy_filters['tribe_events_cat'] = array_map('intval', $category_values);
        }

        if (!empty($_GET['keyword'])) {
            $keyword_values = is_array($_GET['keyword']) ? $_GET['keyword'] : explode(',', $_GET['keyword']);
            $taxonomy_filters['post_tag'] = array_map('intval', $keyword_values);
        }

        if (!empty($_GET['tribe_events_cat'])) {
            $tribe_cat_values = is_array($_GET['tribe_events_cat']) ? $_GET['tribe_events_cat'] : explode(',', $_GET['tribe_events_cat']);
            $taxonomy_filters['tribe_events_cat'] = array_map('intval', $tribe_cat_values);
        }

        if (!empty($_GET['post_tag'])) {
            $tag_values = is_array($_GET['post_tag']) ? $_GET['post_tag'] : explode(',', $_GET['post_tag']);
            $taxonomy_filters['post_tag'] = array_map('intval', $tag_values);
        }
    }

    ob_start();
    ?>
    <div style="background: 
#f0f8ff; border: 2px solid 
#4CAF50; padding: 15px; margin: 10px 0;">
        <h3>🔍 Calendar Taxonomy Debug for <?php echo $thisyear; ?>-<?php echo $thismonth; ?></h3>

        <h4>Active Taxonomy Filters:</h4>
        <?php if (!empty($taxonomy_filters)): ?>
            <ul>
                <?php foreach ($taxonomy_filters as $taxonomy => $term_ids): ?>
                    <li><strong><?php echo esc_html($taxonomy); ?>:</strong> <?php echo implode(', ', $term_ids); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><em>No active taxonomy filters</em></p>
        <?php endif; ?>

        <h4>URL Parameters:</h4>
        <ul>
            <li><strong>category:</strong> <?php echo isset($_GET['category']) ? esc_html($_GET['category']) : 'none'; ?></li>
            <li><strong>keyword:</strong> <?php echo isset($_GET['keyword']) ? esc_html($_GET['keyword']) : 'none'; ?></li>
            <li><strong>tribe_events_cat:</strong> <?php echo isset($_GET['tribe_events_cat']) ? esc_html($_GET['tribe_events_cat']) : 'none'; ?></li>
            <li><strong>post_tag:</strong> <?php echo isset($_GET['post_tag']) ? esc_html($_GET['post_tag']) : 'none'; ?></li>
        </ul>

        <?php
        // Test the filtered query (same logic as calendar generation)
        $base_query = "
            SELECT DISTINCT 
                DAYOFMONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) as day,
                p.post_title,
                GROUP_CONCAT(t.name) as taxonomies
            FROM {$wpdb->postmeta} pm 
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id";

        $taxonomy_joins = '';
        $taxonomy_where = '';
        $query_params = array($thismonth, $thisyear);

        if (!empty($taxonomy_filters)) {
            $tax_conditions = array();
            $join_counter = 0;

            foreach ($taxonomy_filters as $taxonomy => $term_ids) {
                if (!empty($term_ids)) {
                    $join_alias = "tr_filter{$join_counter}";
                    $tt_alias = "tt_filter{$join_counter}";

                    $taxonomy_joins .= " 
                        JOIN {$wpdb->term_relationships} {$join_alias} ON {$join_alias}.object_id = p.ID
                        JOIN {$wpdb->term_taxonomy} {$tt_alias} ON {$tt_alias}.term_taxonomy_id = {$join_alias}.term_taxonomy_id";

                    $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
                    $tax_conditions[] = "({$tt_alias}.taxonomy = %s AND {$tt_alias}.term_id IN ({$placeholders}))";

                    $query_params[] = $taxonomy;
                    foreach ($term_ids as $term_id) {
                        $query_params[] = intval($term_id);
                    }

                    $join_counter++;
                }
            }

            if (!empty($tax_conditions)) {
                $taxonomy_where = ' AND (' . implode(' OR ', $tax_conditions) . ')';
            }
        }

        $complete_query = $base_query . $taxonomy_joins . "
            WHERE pm.meta_key = '_EventStartDate'
            AND MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) = %s
            AND YEAR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) = %s
            AND p.post_type = 'tribe_events' 
            AND p.post_status IN ('publish', 'future')" . $taxonomy_where . "
            GROUP BY p.ID
            ORDER BY STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') ASC";

        $filtered_events = $wpdb->get_results($wpdb->prepare($complete_query, ...$query_params));
        ?>

        <h4>Filtered Events Found:</h4>
        <?php if (!empty($filtered_events)): ?>
            <p><strong>Total:</strong> <?php echo count($filtered_events); ?> events</p>
            <p><strong>Days with events:</strong> 
                <?php 
                $days = array_unique(wp_list_pluck($filtered_events, 'day'));
                sort($days);
                echo implode(', ', $days); 
                ?>
            </p>

            <details>
                <summary>Show all filtered events</summary>
                <ul style="max-height: 200px; overflow-y: auto; margin-top: 10px;">
                    <?php foreach ($filtered_events as $event): ?>
                        <li>
                            <strong>Day <?php echo $event->day; ?>:</strong> 
                            <?php echo esc_html($event->post_title); ?>
                            <?php if ($event->taxonomies): ?>
                                <small>(<?php echo esc_html($event->taxonomies); ?>)</small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php else: ?>
            <p style="color: 
#ff6b6b;">❌ No events found matching current filters</p>
        <?php endif; ?>

        <h4>Generated Query:</h4>
        <details>
            <summary>Show SQL query</summary>
            <code style="background: 
#f5f5f5; padding: 10px; display: block; white-space: pre-wrap; font-size: 11px; margin-top: 10px;">
                <?php echo str_replace('%%', '%', $complete_query); ?>
            </code>
            <p><strong>Parameters:</strong> <?php echo implode(', ', $query_params); ?></p>
        </details>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('debug_calendar_taxonomy', 'debug_calendar_taxonomy_shortcode');

/**
 * ===============================================================================
 * Filtered Query Argumentss
 * ===============================================================================
 */
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

/**
 * Context-aware query building
 * Applies different logic based on archive context to construct WP_Query arguments.
 *
 * @param string $post_type The post type for the query (e.g., 'post', 'tribe_events').
 * @param array $taxonomy_filters An optional associative array of taxonomy filters
 * (e.g., ['tribe_events_cat' => [1, 2], 'post_tag' => [10]]).
 * @param string $current_page_url Optional: The URL of the current page, used for specific URL parsing.
 * @return array An array of WP_Query arguments.
 */
function build_filtered_query_args($post_type, $taxonomy_filters = array(), $current_page_url = '') {
    // Start with basic query args
    $args = array(
        'post_type' => $post_type,
        'post_status' => array('publish', 'future'),
        'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
        'posts_per_page' => 20,
        'orderby' => 'date',
        'order' => 'ASC'
    );

    $archive_context = get_current_archive_context();
    $date_info = _get_date_archive_info($current_page_url);
    $is_date_archive = (bool) $date_info;

    // Apply date filtering if we're on a date archive
    if ($is_date_archive) {
        if ($post_type === 'tribe_events') {
            // Events: filter by _EventStartDate (meta_query)
            $date_pattern = $date_info['year'];
            if ($date_info['month']) {
                $date_pattern .= '-' . $date_info['month'];
                if ($date_info['day']) {
                    $date_pattern .= '-' . $date_info['day'];
                }
            }

            $args['meta_query'] = isset($args['meta_query']) ? (array) $args['meta_query'] : [];
            $args['meta_query'][] = array(
                'key' => '_EventStartDate',
                'value' => $date_pattern,
                'compare' => 'LIKE'
            );

            $args['meta_key'] = '_EventStartDate';
            $args['orderby'] = 'meta_value';
            $args['order'] = 'ASC';
        } else {
            // Regular posts: filter by post date
            if ($date_info['year']) {
                $args['year'] = intval($date_info['year']);
            }
            if ($date_info['month']) {
                $args['monthnum'] = intval($date_info['month']);
            }
            if ($date_info['day']) {
                $args['day'] = intval($date_info['day']);
            }
        }
    }

    // Get all active filters (including new filter types)
    if (empty($taxonomy_filters)) {
        $taxonomy_filters = get_active_taxonomy_filters();
    }

    // NEW: Handle Day of Week filtering using WP_Query's date_query
    if (!empty($taxonomy_filters['dayofweek']) && $post_type === 'tribe_events') {
        // For events, we need to use a custom approach since WP_Query's date_query 
        // works on post_date but we need _EventStartDate

        // We'll add this to meta_query as a complex query
        $dayofweek_meta_query = array();
        foreach ($taxonomy_filters['dayofweek'] as $day) {
            $dayofweek_meta_query[] = array(
                'key' => '_EventStartDate',
                'value' => '',
                'compare' => 'EXISTS'
            );
        }

        // Store day-of-week filter for later processing in calendar query
        $args['_custom_dayofweek_filter'] = $taxonomy_filters['dayofweek'];

        //error_log('📅 Day of week filter applied: ', $taxonomy_filters['dayofweek']);
    }

    // NEW: Handle Time Period filtering  
    if (!empty($taxonomy_filters['timeperiod']) && $post_type === 'tribe_events') {
        // Store time period filter for later processing
        $args['_custom_timeperiod_filter'] = $taxonomy_filters['timeperiod'];

        //error_log('🕐 Time period filter applied: ', $taxonomy_filters['timeperiod']);
    }

    // Apply taxonomy filters (existing logic)
    if ($archive_context['context'] === 'taxonomy' && $archive_context['term_id']) {
        $current_taxonomy = $archive_context['taxonomy'];
        $current_term_id = $archive_context['term_id'];

        if (!isset($taxonomy_filters[$current_taxonomy]) || !in_array($current_term_id, $taxonomy_filters[$current_taxonomy])) {
            $taxonomy_filters[$current_taxonomy][] = $current_term_id;
        }
    }

    // Filter out non-taxonomy filters before applying tax_query
    $pure_taxonomy_filters = array_filter($taxonomy_filters, function($key) {
        return !in_array($key, ['dayofweek', 'timeperiod', '_taxonomy_archive_locked']);
    }, ARRAY_FILTER_USE_KEY);

    $args = apply_taxonomy_filters_to_query_args($args, $pure_taxonomy_filters);

    return $args;
}

/*
 * ===============================================================================
 * Calendar Replacement
 * ===============================================================================
 */


 // Force calendar to include future posts
 /*
function include_future_posts_in_calendar($query) {
    if (!is_admin() && $query->is_date() && $query->is_main_query()) {
        $query->set('post_status', array('publish', 'future'));
    }
}
add_action('pre_get_posts', 'include_future_posts_in_calendar');
*/

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

/*
 * Generate complete events calendar HTML with taxonomy filtering
 */
/*
 * UPDATED generate_events_calendar_html() - CLEAN VERSION
 * Generates complete events calendar HTML with CLEAN URLs (no filter parameters)
 * JavaScript handles ALL filter parameter addition to prevent duplicates
 */
function generate_events_calendar_html($args) {
    global $wpdb, $wp_locale;

    // Get current month/year context
    $thisyear = date('Y');
    $thismonth = date('m');

    // Check if we're on a date archive
    if (function_exists('is_on_custom_post_type_date_archive') && is_on_custom_post_type_date_archive()) {
        $archive_info = is_custom_post_type_date_archive_by_url();
        if ($archive_info) {
            $thisyear = $archive_info['year'];
            $thismonth = $archive_info['month'] ?: date('m');
        }
    }

    // Or check WordPress query vars
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

    // UPDATED: Get current taxonomy filters (use centralized function)
    $taxonomy_filters = get_active_taxonomy_filters();

    $dayofweek_filters = !empty($taxonomy_filters['dayofweek']) ? $taxonomy_filters['dayofweek'] : array();
    $timeperiod_filters = !empty($taxonomy_filters['timeperiod']) ? $taxonomy_filters['timeperiod'] : array();

        // Remove non-taxonomy filters before building calendar query
    $pure_taxonomy_filters = array_filter($taxonomy_filters, function($key) {
        return !in_array($key, ['dayofweek', 'timeperiod', '_taxonomy_archive_locked']);
    }, ARRAY_FILTER_USE_KEY);

    // Use pure taxonomy filters for calendar query building
    $taxonomy_filters = $pure_taxonomy_filters;

    // ADD day-of-week filtering to calendar query
    if (!empty($dayofweek_filters)) {
        $day_conditions = array();
        foreach ($dayofweek_filters as $day) {
            $day_conditions[] = $wpdb->prepare("DAYOFWEEK(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) = %d", $day);
        }
        if (!empty($day_conditions)) {
            $taxonomy_where .= ' AND (' . implode(' OR ', $day_conditions) . ')';
        }
    }

    // ADD time period filtering to calendar query  
    if (!empty($timeperiod_filters)) {
        $time_conditions = array();
        foreach ($timeperiod_filters as $period) {
            switch ($period) {
                case 'morning':
                    $time_conditions[] = "HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 6 AND HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 12";
                    break;
                case 'afternoon':
                    $time_conditions[] = "HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 12 AND HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 17";
                    break;
                case 'evening':
                    $time_conditions[] = "HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 17 AND HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 21";
                    break;
                case 'night':
                    $time_conditions[] = "(HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) >= 21 OR HOUR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < 6)";
                    break;
                case 'allday':
                    // This would need a separate query to check _EventAllDay meta
                    break;
            }
        }
        if (!empty($time_conditions)) {
            $taxonomy_where .= ' AND (' . implode(' OR ', $time_conditions) . ')';
        }
    }

    //error_log('Active taxonomy filters: ' . print_r($taxonomy_filters, true));

    // Build base query for events
    $base_query = "
        SELECT DISTINCT 
            DAYOFMONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) as day,
            pm.post_id, 
            p.post_title 
        FROM {$wpdb->postmeta} pm 
        JOIN {$wpdb->posts} p ON pm.post_id = p.ID";

    // Add taxonomy joins if filters are active
    $taxonomy_joins = '';
    $taxonomy_where = '';
    $query_params = array($thismonth, $thisyear);

    if (!empty($taxonomy_filters)) {
        $tax_conditions = array();
        $join_counter = 0;

        foreach ($taxonomy_filters as $taxonomy => $term_ids) {
            if (!empty($term_ids)) {
                $join_alias = "tr{$join_counter}";
                $tt_alias = "tt{$join_counter}";

                // Add taxonomy relationship joins
                $taxonomy_joins .= " 
                    JOIN {$wpdb->term_relationships} {$join_alias} ON {$join_alias}.object_id = p.ID
                    JOIN {$wpdb->term_taxonomy} {$tt_alias} ON {$tt_alias}.term_taxonomy_id = {$join_alias}.term_taxonomy_id";

                // Add conditions for this taxonomy
                $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
                $tax_conditions[] = "({$tt_alias}.taxonomy = %s AND {$tt_alias}.term_id IN ({$placeholders}))";

                // Add parameters
                $query_params[] = $taxonomy;
                foreach ($term_ids as $term_id) {
                    $query_params[] = intval($term_id);
                }

                $join_counter++;
            }
        }

        if (!empty($tax_conditions)) {
            $taxonomy_where = ' AND (' . implode(' OR ', $tax_conditions) . ')';
        }
    }

    // Complete query with taxonomy filtering
    $complete_query = $base_query . $taxonomy_joins . "
        WHERE pm.meta_key = '_EventStartDate'
        AND MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) = %s
        AND YEAR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) = %s
        AND p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')" . $taxonomy_where . "
        ORDER BY STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') ASC";

    //error_log('Calendar query with taxonomy filters: ' . $complete_query);
    //error_log('Query params: ' . print_r($query_params, true));

    // Execute the query
    $dayswithevents = $wpdb->get_results($wpdb->prepare($complete_query, ...$query_params));

    // Process results
    $daywithpost = array();
    $ak_titles_for_day = array();

    if ($dayswithevents) {
        foreach ($dayswithevents as $event) {
            $day = (int) $event->day;
            $daywithpost[] = $day;

            if (!isset($ak_titles_for_day[$day])) {
                $ak_titles_for_day[$day] = array();
            }

            $ak_titles_for_day[$day][] = array(
                'title' => $event->post_title,
                'url' => get_permalink($event->post_id)
            );
        }
    }

    $daywithpost = array_unique($daywithpost);

    //error_log('Days with events (filtered): ' . implode(', ', $daywithpost));

    // Get previous month with events (respecting taxonomy filters)
    $prev_query = "
        SELECT DISTINCT 
            MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) AS month, 
            YEAR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) AS year
        FROM {$wpdb->postmeta} pm 
        JOIN {$wpdb->posts} p ON pm.post_id = p.ID" . $taxonomy_joins . "
        WHERE pm.meta_key = '_EventStartDate'
        AND STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') < %s
        AND p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')" . $taxonomy_where . "
        ORDER BY STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') DESC 
        LIMIT 1";

    $prev_params = array("$thisyear-$thismonth-01");
    if (!empty($taxonomy_filters)) {
        foreach ($taxonomy_filters as $taxonomy => $term_ids) {
            if (!empty($term_ids)) {
                $prev_params[] = $taxonomy;
                foreach ($term_ids as $term_id) {
                    $prev_params[] = intval($term_id);
                }
            }
        }
    }

    $previous = $wpdb->get_row($wpdb->prepare($prev_query, ...$prev_params));

    // Get next month with events (respecting taxonomy filters)
    $next_query = "
        SELECT DISTINCT 
            MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) AS month, 
            YEAR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) AS year
        FROM {$wpdb->postmeta} pm 
        JOIN {$wpdb->posts} p ON pm.post_id = p.ID" . $taxonomy_joins . "
        WHERE pm.meta_key = '_EventStartDate'
        AND STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') > %s
        AND MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s')) != %s
        AND p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')" . $taxonomy_where . "
        ORDER BY STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d %%H:%%i:%%s') ASC 
        LIMIT 1";

    $next_params = array("$thisyear-$thismonth-01", $thismonth);
    if (!empty($taxonomy_filters)) {
        foreach ($taxonomy_filters as $taxonomy => $term_ids) {
            if (!empty($term_ids)) {
                $next_params[] = $taxonomy;
                foreach ($term_ids as $term_id) {
                    $next_params[] = intval($term_id);
                }
            }
        }
    }

    $next = $wpdb->get_row($wpdb->prepare($next_query, ...$next_params));

    // Build calendar HTML
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

    <tfoot>
    <tr>';

    // UPDATED: Previous month link - CLEAN URL (no filter parameters)
    if ($previous) {
        $prev_link = get_events_month_link($previous->year, $previous->month); // ← NOW GENERATES CLEAN URLs
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="prev"><a href="' . $prev_link . '" title="' . 
            esc_attr(sprintf(__('View events for %1$s %2$s'), $wp_locale->get_month($previous->month), 
            $previous->year)) . '">&laquo; ' . 
            $wp_locale->get_month_abbrev($wp_locale->get_month($previous->month)) . '</a></td>';
    } else {
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="prev" class="pad">&nbsp;</td>';
    }

    /* OLD CODE THAT ADDED FILTER PARAMETERS - COMMENTED OUT
    // Previous month link
    if ($previous) {
        $prev_link = get_events_month_link($previous->year, $previous->month);
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="prev"><a href="' . $prev_link . '" title="' . 
            esc_attr(sprintf(__('View events for %1$s %2$s'), $wp_locale->get_month($previous->month), 
            $previous->year)) . '">&laquo; ' . 
            $wp_locale->get_month_abbrev($wp_locale->get_month($previous->month)) . '</a></td>';
    } else {
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="prev" class="pad">&nbsp;</td>';
    }
    */

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

    /* OLD CODE THAT ADDED FILTER PARAMETERS - COMMENTED OUT
    // Next month link  
    if ($next) {
        $next_link = get_events_month_link($next->year, $next->month);
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="next"><a href="' . $next_link . '" title="' . 
            esc_attr(sprintf(__('View events for %1$s %2$s'), $wp_locale->get_month($next->month), 
            $next->year)) . '">' . 
            $wp_locale->get_month_abbrev($wp_locale->get_month($next->month)) . ' &raquo;</a></td>';
    } else {
        $calendar_output .= "\n\t\t" . '<td colspan="3" id="next" class="pad">&nbsp;</td>';
    }
    */

    $calendar_output .= '
    </tr>
    </tfoot>

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

        // UPDATED: Create clickable links for days with events - CLEAN URLs (no filter parameters)
        if (in_array($day, $daywithpost)) {
            $day_link = get_events_day_link($thisyear, $thismonth, $day); // ← NOW GENERATES CLEAN URLs

            // Create tooltip with event titles
            $tooltip_titles = array();
            if (isset($ak_titles_for_day[$day])) {
                foreach ($ak_titles_for_day[$day] as $event) {
                    $tooltip_titles[] = $event['title'];
                }
            }
            $tooltip = implode(', ', $tooltip_titles);

            $calendar_output .= '<a href="' . $day_link . '" title="' . esc_attr($tooltip) . '" style="font-weight: bold; color: 
#0073aa;">' . $day . '</a>';
        } else {
            $calendar_output .= $day;
        }

        /* OLD CODE THAT ADDED FILTER PARAMETERS - COMMENTED OUT
        // THE KEY PART - Create clickable links for days with events
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

            $calendar_output .= '<a href="' . $day_link . '" title="' . esc_attr($tooltip) . '" style="font-weight: bold; color: 
#0073aa;">' . $day . '</a>';
        } else {
            $calendar_output .= $day;
        }
        */

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

    $calendar_output .= "\n\t</tr>\n\t</tbody>\n\t</table>";

    // UPDATED: Add .wp-calendar-nav wrapper for AJAX navigation - CLEAN URLs
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

    /* OLD NAV CODE THAT ADDED FILTER PARAMETERS - COMMENTED OUT
    // CRITICAL FIX: Add .wp-calendar-nav wrapper for AJAX navigation
    $nav_output = '';
    if ($previous || $next) {
        $nav_output = '<nav class="wp-calendar-nav" role="navigation" aria-label="' . esc_attr__('Previous and next months') . '">';

        if ($previous) {
            $prev_link = get_events_month_link($previous->year, $previous->month);
            $nav_output .= '<span class="wp-calendar-nav-prev"><a href="' . $prev_link . '">&laquo; ' . 
                $wp_locale->get_month($previous->month) . ' ' . $previous->year . '</a></span>';
        }

        if ($next) {
            $next_link = get_events_month_link($next->year, $next->month);
            $nav_output .= '<span class="wp-calendar-nav-next"><a href="' . $next_link . '">' . 
                $wp_locale->get_month($next->month) . ' ' . $next->year . ' &raquo;</a></span>';
        }

        $nav_output .= '</nav>';
    }
    */

    //error_log('Generated calendar HTML with ' . count($daywithpost) . ' clickable days - ALL LINKS ARE CLEAN (no parameters)');

    return $calendar_output . $nav_output;
}

/**
 * UPDATED get_events_month_link() - CLEAN VERSION
 * Generates clean URLs without any filter parameters
 * JavaScript will add current filters when links are clicked
 */
function get_events_month_link($year, $month) {
    global $wp_rewrite;

    $archive_context = get_current_archive_context();
    //error_log('📅 Generating month link - Context: ' . $archive_context['context']);

    if ($archive_context['context'] === 'taxonomy') {
        // TAXONOMY CONTEXT: Stay in taxonomy archive, don't add date to URL
        // Return the base taxonomy URL without date segments
        //error_log('🏷️ Taxonomy context - returning base taxonomy URL (no date mixing)');

        if ($archive_context['taxonomy_type'] && $archive_context['term_slug']) {
            return home_url("/events/{$archive_context['taxonomy_type']}/{$archive_context['term_slug']}/");
        }

        // Fallback
        return home_url('/events/');
    }

    // DATE CONTEXT: Generate date archive URL
    //error_log('📅 Date context - generating date archive URL');

    if (!$wp_rewrite->using_permalinks()) {
        return home_url("?post_type=tribe_events&m=$year" . zeroise($month, 2));
    } else {
        return home_url("/events/$year/" . zeroise($month, 2) . "/");
    }

    // NO filter processing - JavaScript handles all parameters
    // This prevents duplicate parameter issues completely

    /* OLD CODE THAT CAUSED DUPLICATES - COMMENTED OUT
    // UPDATED: Use centralized filter detection and pretty parameter conversion
    $current_filters = get_active_taxonomy_filters();

    if (!empty($current_filters)) {
        // NEW: Use pretty parameter conversion
        $filter_params = taxonomy_filters_to_pretty_params($current_filters);

        if (!empty($filter_params)) {
            $separator = ($wp_rewrite->using_permalinks()) ? '?' : '&';
            $base_url .= $separator . http_build_query($filter_params);
        }
    }

    return $base_url;
    */
}

/**
 * UPDATED get_events_day_link() - CLEAN VERSION  
 * Generates clean URLs without any filter parameters
 * JavaScript will add current filters when links are clicked
 */

function get_events_day_link($year, $month, $day) {
    global $wp_rewrite;

    $archive_context = get_current_archive_context();
    //error_log('📅 Generating day link - Context: ' . $archive_context['context']);

    if ($archive_context['context'] === 'taxonomy') {
        // TAXONOMY CONTEXT: Stay in taxonomy archive, don't add date to URL
        // Return the base taxonomy URL without date segments
        //error_log('🏷️ Taxonomy context - returning base taxonomy URL (no date mixing)');

        if ($archive_context['taxonomy_type'] && $archive_context['term_slug']) {
            return home_url("/events/{$archive_context['taxonomy_type']}/{$archive_context['term_slug']}/");
        }

        // Fallback
        return home_url('/events/');
    }

    // Generate CLEAN base URL only
    if (!$wp_rewrite->using_permalinks()) {
        return home_url("?post_type=tribe_events&m=$year" . zeroise($month, 2) . zeroise($day, 2));
    } else {
        return home_url("/events/$year/" . zeroise($month, 2) . "/" . zeroise($day, 2) . "/");
    }

    // NO filter processing - JavaScript handles all parameters
    // This prevents duplicate parameter issues completely

    /* OLD CODE THAT CAUSED DUPLICATES - COMMENTED OUT
    // UPDATED: Use centralized filter detection and pretty parameter conversion
    $current_filters = get_active_taxonomy_filters();

    if (!empty($current_filters)) {
        // NEW: Use pretty parameter conversion
        $filter_params = taxonomy_filters_to_pretty_params($current_filters);

        if (!empty($filter_params)) {
            $separator = ($wp_rewrite->using_permalinks()) ? '?' : '&';
            $base_url .= $separator . http_build_query($filter_params);
        }
    }

    return $base_url;
    */
}

/**
 * UPDATED modify_calendar_links_with_filters() - CLEAN VERSION
 * Only handles post type URL structure, NO filter parameters
 * JavaScript handles all filter parameter addition
 */
function modify_calendar_links_with_filters($calendar_output, $args) {
    global $calendar_post_type;

    // STEP 1: Modify links for custom post types (if not regular posts)
    if ($calendar_post_type && $calendar_post_type !== 'post') {
        $post_type_obj = get_post_type_object($calendar_post_type);

        if ($post_type_obj && $post_type_obj->has_archive) {
            // Get the archive slug
            $archive_slug = $post_type_obj->has_archive;
            if ($archive_slug === true) {
                $archive_slug = isset($post_type_obj->rewrite['slug']) ? $post_type_obj->rewrite['slug'] : $post_type_obj->name;
            }

            // Replace day links: from /2024/11/15/ to /events/2024/11/15/
            $pattern = '/href="([^"]*?)\/(\d{4})\/(\d{2})\/(\d{2})\//';
            $replacement = 'href="' . home_url('/') . $archive_slug . '/$2/$3/$4/';
            $calendar_output = preg_replace($pattern, $replacement, $calendar_output);

            // Replace month navigation links: from /2024/11/ to /events/2024/11/
            $pattern = '/href="([^"]*?)\/(\d{4})\/(\d{2})\//';
            $replacement = 'href="' . home_url('/') . $archive_slug . '/$2/$3/';
            $calendar_output = preg_replace($pattern, $replacement, $calendar_output);
        }
    }

    // DO NOT add any filter parameters
    // Let JavaScript handle ALL parameter addition to prevent duplicates

    /* OLD CODE THAT CAUSED DUPLICATES - COMMENTED OUT
    // Add taxonomy filter parameters to ALL links
    // UPDATED: Use centralized filter detection
    $current_filters = get_active_taxonomy_filters();

    if (!empty($current_filters)) {
        // NEW: Use pretty parameter conversion for ALL filters
        $filter_params = taxonomy_filters_to_pretty_params($current_filters);

        if (!empty($filter_params)) {
            $query_string = http_build_query($filter_params);

            // Add filters to calendar links
            $calendar_output = preg_replace(
                '/href="([^"]+)"/',
                'href="$1?' . $query_string . '"',
                $calendar_output
            );
        }
    }
    */

    return $calendar_output;
}

/* OLD SEPARATE FUNCTIONS - COMMENTED OUT

// Modify calendar links for custom post types
function modify_calendar_links_for_post_type($calendar_output, $args) {
    global $calendar_post_type;

    if (!$calendar_post_type || $calendar_post_type === 'post') {
        return $calendar_output;
    }

    $post_type_obj = get_post_type_object($calendar_post_type);
    if (!$post_type_obj || !$post_type_obj->has_archive) {
        return $calendar_output;
    }

    // Get the archive slug
    $archive_slug = $post_type_obj->has_archive;
    if ($archive_slug === true) {
        $archive_slug = isset($post_type_obj->rewrite['slug']) ? $post_type_obj->rewrite['slug'] : $post_type_obj->name;
    }

    // Replace day links: from /2024/11/15/ to /events/2024/11/15/
    $pattern = '/href="([^"]*?)\/(\d{4})\/(\d{2})\/(\d{2})\//';
    $replacement = 'href="' . home_url('/') . $archive_slug . '/$2/$3/$4/';
    $calendar_output = preg_replace($pattern, $replacement, $calendar_output);

    // Replace month navigation links: from /2024/11/ to /events/2024/11/
    $pattern = '/href="([^"]*?)\/(\d{4})\/(\d{2})\//';
    $replacement = 'href="' . home_url('/') . $archive_slug . '/$2/$3/';
    $calendar_output = preg_replace($pattern, $replacement, $calendar_output);

    return $calendar_output;
}

function preserve_taxonomy_in_calendar_links($calendar_output) {
    // UPDATED: Use centralized filter detection
    $current_filters = get_active_taxonomy_filters();

    // OLD CODE - COMMENTED OUT
    // $current_filters = get_current_taxonomy_filters();

    if (empty($current_filters)) {
        return $calendar_output;
    }

    // NEW: Use pretty parameter conversion for ALL filters
    $filter_params = taxonomy_filters_to_pretty_params($current_filters);

    // OLD CODE - COMMENTED OUT (only preserved some filters)
    // Build filter query string using pretty parameters
    // $filter_params = array();
    // foreach ($current_filters as $taxonomy => $term_ids) {
    //     if (!empty($term_ids)) {
    //         if ($taxonomy === 'tribe_events_cat' || $taxonomy === 'category') {
    //             $filter_params['category'] = implode(',', $term_ids);
    //         } elseif ($taxonomy === 'post_tag') {
    //             $filter_params['keyword'] = implode(',', $term_ids);
    //         }
    //     }
    // }

    if (!empty($filter_params)) {
        $query_string = http_build_query($filter_params);

        // Add filters to calendar links
        $calendar_output = preg_replace(
            '/href="([^"]+)"/',
            'href="$1?' . $query_string . '"',
            $calendar_output
        );
    }

    return $calendar_output;
}
*/

// calendar modification for events
function modify_calendar_for_events($query) {
    // Only modify if this is the calendar query for events
    if (!is_admin() && isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'tribe_events') {

        // UPDATED: Use centralized taxonomy filter detection
        $taxonomy_filters = get_active_taxonomy_filters();

        /* OLD CODE - COMMENTED OUT
        // Get current taxonomy filters from URL parameters
        $taxonomy_filters = get_current_taxonomy_filters();
        */

        // Get the date parameters from URL detection if on date archive
        if (is_on_custom_post_type_date_archive()) {
            $archive_info = is_custom_post_type_date_archive_by_url();

            // Set query vars based on URL-detected dates
            $query->set('year', $archive_info['year']);
            if ($archive_info['month']) {
                $query->set('monthnum', $archive_info['month']);
            }
            if ($archive_info['day']) {
                $query->set('day', $archive_info['day']);
            }

            // Build date pattern for meta query based on archive type
            if ($archive_info['day'] && $archive_info['month']) {
                // Daily archive: exact date match
                $date_pattern = sprintf('%04d-%02d-%02d', $archive_info['year'], $archive_info['month'], $archive_info['day']);
            } elseif ($archive_info['month']) {
                // Monthly archive: year-month pattern
                $date_pattern = sprintf('%04d-%02d', $archive_info['year'], $archive_info['month']);
            } else {
                // Yearly archive: year pattern
                $date_pattern = sprintf('%04d', $archive_info['year']);
            }

            // Set meta query to use event start date
            $meta_query = array(
                array(
                    'key' => '_EventStartDate',
                    'value' => $date_pattern,
                    'compare' => 'LIKE'
                )
            );

            $query->set('meta_query', $meta_query);
            $query->set('meta_key', '_EventStartDate');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'DESC');

        } else {
            // Fallback: original calendar query modification for non-archive pages
            $year = $query->get('year');
            $monthnum = $query->get('monthnum');
            $day = $query->get('day');

            if ($year || $monthnum || $day) {
                // Remove default date query
                $query->set('year', '');
                $query->set('monthnum', '');
                $query->set('day', '');

                // Build date pattern for meta query
                if ($day && $monthnum) {
                    $date_pattern = sprintf('%04d-%02d-%02d', $year, $monthnum, $day);
                } elseif ($monthnum) {
                    $date_pattern = sprintf('%04d-%02d', $year, $monthnum);
                } else {
                    $date_pattern = sprintf('%04d', $year);
                }

                // Set meta query to use event start date
                $meta_query = array(
                    array(
                        'key' => '_EventStartDate',
                        'value' => $date_pattern,
                        'compare' => 'LIKE'
                    )
                );

                $query->set('meta_query', $meta_query);
                $query->set('meta_key', '_EventStartDate');
                $query->set('orderby', 'meta_value');
                $query->set('order', 'DESC');
            }
        }

        // UPDATED: Add taxonomy filters using centralized approach
        if (!empty($taxonomy_filters)) {
            $dummy_debug = ''; // Calendar doesn't need debug output
            $temp_args = array();

            // Use centralized tax_query builder
            $temp_args = apply_taxonomy_filters_to_query_args($temp_args, $taxonomy_filters, $dummy_debug);

            if (!empty($temp_args['tax_query'])) {
                $existing_tax_query = $query->get('tax_query') ?: array();

                if (!empty($existing_tax_query)) {
                    // Merge with existing tax_query
                    $combined_tax_query = array('relation' => 'AND');
                    $combined_tax_query[] = $existing_tax_query;
                    $combined_tax_query[] = $temp_args['tax_query'];
                    $query->set('tax_query', $combined_tax_query);
                } else {
                    $query->set('tax_query', $temp_args['tax_query']);
                }
            }
        }

        /* OLD DUPLICATE CODE - COMMENTED OUT
        // Add taxonomy filters if present
        if (!empty($taxonomy_filters)) {
            $existing_tax_query = $query->get('tax_query') ?: array();
            $tax_query = array('relation' => 'AND');

            // Add existing tax query if present
            if (!empty($existing_tax_query)) {
                $tax_query[] = $existing_tax_query;
            }

            foreach ($taxonomy_filters as $taxonomy => $term_ids) {
                if (!empty($term_ids)) {
                    $tax_query[] = array(
                        'taxonomy' => sanitize_text_field($taxonomy),
                        'field' => 'term_id',
                        'terms' => array_map('intval', (array)$term_ids),
                        'operator' => 'IN'
                    );
                }
            }

            if (count($tax_query) > 1) {
                $query->set('tax_query', $tax_query);
            }
        }
        */

        // Handle existing meta query properly - merge with date query
        $existing_meta_query = $query->get('meta_query');
        if (!empty($existing_meta_query) && isset($meta_query)) {
            // Merge existing meta query with our date meta query
            if (is_array($existing_meta_query)) {
                $combined_meta_query = array_merge($existing_meta_query, $meta_query);
                $query->set('meta_query', $combined_meta_query);
            }
        }
    }
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

/**
 * DEBUG: Add logging to verify clean URL generation
 */
function debug_clean_calendar_urls() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        //error_log('=== CLEAN CALENDAR URL GENERATION ===');
        //error_log('Month link example: ' . get_events_month_link(2024, 12));
        //error_log('Day link example: ' . get_events_day_link(2024, 12, 9));
        //error_log('Should have NO parameters - JavaScript will add them');
    }
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
        'ajax_target' => '#primary'
    ), $atts, 'custom_calendar');

    // Convert string 'true'/'false' to boolean
    $atts['initial'] = filter_var($atts['initial'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_dropdowns'] = filter_var($atts['show_dropdowns'], FILTER_VALIDATE_BOOLEAN);
    $atts['display'] = false; // Always return for shortcode

    // Set global flags for our filters
    global $calendar_post_type, $show_calendar_dropdowns, $show_calendar_today, $calendar_ajax_target, $calendar_using_events;
    $calendar_post_type = $atts['post_type'];
    $show_calendar_dropdowns = $atts['show_dropdowns'];
    $show_calendar_today = $atts['show_today_button'];  
    $calendar_ajax_target = $atts['ajax_target'];

    // NEW APPROACH: Replace calendar output instead of SQL queries
    if ($atts['post_type'] === 'tribe_events') {
        $calendar_using_events = true;
        add_action('pre_get_posts', 'modify_calendar_for_events');
        add_filter('get_calendar', 'replace_events_calendar_output', 10, 2);
        add_filter('get_calendar', 'modify_calendar_links_with_filters', 15, 2);
        //add_filter('get_calendar', 'modify_calendar_links_for_post_type', 10, 2);
        //add_filter('get_calendar', 'preserve_taxonomy_in_calendar_links', 20, 1); 
    }

    // Add dropdown filter if enabled
    if ($atts['show_dropdowns']) {
        add_filter('get_calendar', 'add_calendar_dropdowns', 15, 2);
    }

    // Get the calendar - our output replacement will generate events calendar
    $calendar = get_calendar($atts);

    // Clean up
    if ($atts['post_type'] === 'tribe_events') {
        $calendar_using_events = false;
        remove_action('pre_get_posts', 'modify_calendar_for_events');
        remove_filter('get_calendar', 'replace_events_calendar_output', 10);
        remove_filter('get_calendar', 'modify_calendar_links_with_filters', 15, 2);
        //remove_filter('get_calendar', 'modify_calendar_links_for_post_type', 10);
        //remove_filter('get_calendar', 'preserve_taxonomy_in_calendar_links', 20);
    }

    if ($atts['show_dropdowns']) {
        remove_filter('get_calendar', 'add_calendar_dropdowns', 15);
    }

    return $calendar;
}
// Keep your existing shortcode registration
add_shortcode('custom_calendar', 'custom_calendar_shortcode');

/* 
Usage examples:
[custom_calendar] - Default post type (posts)
[custom_calendar post_type="movie"] - Movies with URLs like /movies/2024/11/15/
[custom_calendar post_type="event" initial="false"] - Events with full day names
*/

/* ===================================
   FIXING ARCHIVE TEMPLATES
   =================================== */

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

    if (is_tax('tribe_events_cat') || is_post_type_archive('tribe_events') || (is_tag() && is_post_type_archive('tribe_events'))) {
        $new_template = locate_template(array('archive-tribe_events.php'));
        if (!empty($new_template)) {
            return $new_template;
        }
    }

    // ADD: Check for events date archives by URL pattern
    $request_uri = $_SERVER['REQUEST_URI'];
    if (preg_match('#^/events/\d{4}(?:/\d{1,2}(?:/\d{1,2})?)?/?#', $request_uri)) {
        $new_template = locate_template(array('archive-tribe_events.php'));
        if (!empty($new_template)) {
            return $new_template;
        }
    }

    // Check if this is an event taxonomy archive
    if (is_event_taxonomy_archive()) {
        // Look for archive-tribe_events.php in theme
        $event_archive_template = locate_template(array('archive-tribe_events.php'));

        if ($event_archive_template) {
            //error_log('🎯 Forcing archive-tribe_events.php for event taxonomy archive');
            return $event_archive_template;
        }
    }

    return $template;

}
add_filter('template_include', 'force_tribe_events_archive_template');

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

// Add this temporary function to check what meta keys actually exist
function debug_event_meta_keys() {
    global $wpdb;

    $meta_keys = $wpdb->get_results("
        SELECT DISTINCT meta_key, COUNT(*) as count 
        FROM {$wpdb->postmeta} pm 
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
        WHERE p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')
        GROUP BY meta_key 
        ORDER BY meta_key
    ");

    //error_log('=== EVENT META KEYS IN DATABASE ===');
    foreach ($meta_keys as $key) {
        //error_log($key->meta_key . ' (count: ' . $key->count . ')');
    }
}

// Call this once to see what meta keys you have
debug_event_meta_keys();

// Add this temporary function to your functions.php to diagnose the database issues
function debug_events_database() {    
    global $wpdb;

    echo '<div style="padding: 20px; font-family: monospace; background: 
#f1f1f1; margin: 20px;">';
    echo '<h2>Events Database Diagnostic</h2>';

    // 1. Check total events
    $total_events = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type = 'tribe_events' 
        AND post_status IN ('publish', 'future')
    ");
    echo '<h3>1. Total Events: ' . $total_events . '</h3>';

    // 2. Check meta keys used by events
    echo '<h3>2. Event Meta Keys:</h3>';
    $meta_keys = $wpdb->get_results("
        SELECT DISTINCT meta_key, COUNT(*) as count 
        FROM {$wpdb->postmeta} pm 
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
        WHERE p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')
        GROUP BY meta_key 
        ORDER BY meta_key
    ");

    echo '<ul>';
    foreach ($meta_keys as $key) {
        echo '<li><strong>' . $key->meta_key . '</strong> (count: ' . $key->count . ')</li>';
    }
    echo '</ul>';

    // 3. Check cost data specifically
    echo '<h3>3. Cost Data Analysis:</h3>';
    $cost_data = $wpdb->get_results("
        SELECT 
            pm.meta_value as cost_serialized,
            COUNT(*) as count,
            p.post_title
        FROM {$wpdb->postmeta} pm 
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
        WHERE p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')
        AND pm.meta_key = '_custom_event_cost'
        AND pm.meta_value != ''
        GROUP BY pm.meta_value 
        LIMIT 10
    ");

    // ADD this to your debug function after the cost analysis section:

    // Advanced cost analysis
    echo '<h3>3b. Advanced Cost Analysis:</h3>';

    // Check for any events with actual cost values
    $events_with_costs = $wpdb->get_results("
        SELECT 
            p.post_title,
            pm.meta_value as cost_data
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')
        AND pm.meta_key = '_custom_event_cost'
        AND pm.meta_value LIKE '%cost%'
        AND pm.meta_value NOT LIKE '%s:0:\"\"%'
        LIMIT 20
    ");

    $min_cost = null;
    $max_cost = null;
    $cost_count = 0;

    echo '<h4>Events with Cost Data:</h4>';
    foreach ($events_with_costs as $event) {
        $cost_array = maybe_unserialize($event->cost_data);
        if (is_array($cost_array) && isset($cost_array['cost']) && !empty($cost_array['cost'])) {
            $cost_value = floatval($cost_array['cost']);
            if ($cost_value > 0) {
                $cost_count++;
                if ($min_cost === null || $cost_value < $min_cost) {
                    $min_cost = $cost_value;
                }
                if ($max_cost === null || $cost_value > $max_cost) {
                    $max_cost = $cost_value;
                }

                echo '<div style="border: 1px solid #ddd; padding: 5px; margin: 2px;">';
                echo '<strong>Event:</strong> ' . htmlspecialchars($event->post_title) . '<br>';
                echo '<strong>Cost:</strong> $' . number_format($cost_value, 2) . '<br>';
                echo '</div>';
            }
        }
    }

    echo '<h4>Cost Summary:</h4>';
    echo '<p><strong>Events with actual costs:</strong> ' . $cost_count . '</p>';
    if ($min_cost !== null && $max_cost !== null) {
        echo '<p><strong>Minimum Cost:</strong> $' . number_format($min_cost, 2) . '</p>';
        echo '<p><strong>Maximum Cost:</strong> $' . number_format($max_cost, 2) . '</p>';
    } else {
        echo '<p><strong>No events found with actual cost values</strong></p>';
    }

    // Tags analysis
    echo '<h3>8. Tags Usage Analysis:</h3>';

    // Get tag usage for events
    $event_tags = $wpdb->get_results("
        SELECT 
            t.name,
            t.slug,
            tt.count,
            tt.term_id
        FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
        WHERE tt.taxonomy = 'post_tag'
        AND p.post_type = 'tribe_events'
        AND p.post_status IN ('publish', 'future')
        GROUP BY t.term_id
        ORDER BY tt.count DESC
        LIMIT 20
    ");

    echo '<h4>Most Used Tags on Events:</h4>';
    echo '<p><strong>Total unique tags used:</strong> ' . count($event_tags) . '</p>';

    if (!empty($event_tags)) {
        echo '<ul>';
        foreach ($event_tags as $tag) {
            echo '<li><strong>' . htmlspecialchars($tag->name) . '</strong> (' . $tag->count . ' events)</li>';
        }
        echo '</ul>';
    } else {
        echo '<p><strong>No tags found on events</strong></p>';
    }

    echo '<h4>Cost Data Sample:</h4>';
    foreach ($cost_data as $cost_row) {
        echo '<div style="border: 1px solid #ccc; padding: 10px; margin: 5px;">';
        echo '<strong>Raw Value:</strong> ' . htmlspecialchars($cost_row->cost_serialized) . '<br>';
        echo '<strong>Count:</strong> ' . $cost_row->count . '<br>';

        $unserialized = maybe_unserialize($cost_row->cost_serialized);
        if (is_array($unserialized)) {
            echo '<strong>Unserialized:</strong> <pre>' . print_r($unserialized, true) . '</pre>';
            if (isset($unserialized['cost']) && !empty($unserialized['cost'])) {
                echo '<strong>Extracted Cost:</strong> $' . $unserialized['cost'] . '<br>';
            }
        } else {
            echo '<strong>Could not unserialize</strong><br>';
        }
        echo '</div>';
    }

    // 4. Check venue data
    echo '<h3>4. Venue Data Analysis:</h3>';

    // Check if venues exist
    $venue_count = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type = 'tribe_venue' 
        AND post_status IN ('publish', 'future')
    ");
    echo '<p><strong>Total Venues:</strong> ' . $venue_count . '</p>';

    // Check venue addresses
    $venue_addresses = $wpdb->get_results("
        SELECT DISTINCT pm.meta_value, COUNT(*) as count
        FROM {$wpdb->postmeta} pm 
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.post_type = 'tribe_venue'
        AND pm.meta_key = '_VenueAddress' 
        AND pm.meta_value != '' 
        GROUP BY pm.meta_value
        ORDER BY pm.meta_value
        LIMIT 10
    ");

    echo '<h4>Venue Addresses Sample:</h4><ul>';
    foreach ($venue_addresses as $addr) {
        echo '<li>' . htmlspecialchars($addr->meta_value) . ' (' . $addr->count . ' venues)</li>';
    }
    echo '</ul>';

    // Check venue states and cities
    $venue_states = $wpdb->get_results("
        SELECT DISTINCT pm.meta_value, COUNT(*) as count
        FROM {$wpdb->postmeta} pm 
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.post_type = 'tribe_venue'
        AND pm.meta_key = '_VenueState' 
        AND pm.meta_value != '' 
        GROUP BY pm.meta_value
        ORDER BY pm.meta_value
        LIMIT 20
    ");

    echo '<h4>Venue States Sample:</h4><ul>';
    foreach ($venue_states as $state) {
        echo '<li>' . htmlspecialchars($state->meta_value) . ' (' . $state->count . ' venues)</li>';
    }
    echo '</ul>';

    $venue_cities = $wpdb->get_results("
        SELECT DISTINCT pm.meta_value, COUNT(*) as count
        FROM {$wpdb->postmeta} pm 
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.post_type = 'tribe_venue'
        AND pm.meta_key = '_VenueCity' 
        AND pm.meta_value != '' 
        GROUP BY pm.meta_value
        ORDER BY pm.meta_value
        LIMIT 20
    ");

    echo '<h4>Venue Cities Sample:</h4><ul>';
    foreach ($venue_cities as $city) {
        echo '<li>' . htmlspecialchars($city->meta_value) . ' (' . $city->count . ' venues)</li>';
    }
    echo '</ul>';

    // Check event-venue relationships
    $event_venue_relationships = $wpdb->get_results("
        SELECT 
            e.post_title as event_title,
            pm_venue.meta_value as venue_id,
            v.post_title as venue_title
        FROM {$wpdb->posts} e
        INNER JOIN {$wpdb->postmeta} pm_venue ON e.ID = pm_venue.post_id
        LEFT JOIN {$wpdb->posts} v ON pm_venue.meta_value = v.ID
        WHERE e.post_type = 'tribe_events'
        AND e.post_status IN ('publish', 'future')
        AND pm_venue.meta_key = '_EventVenueID'
        LIMIT 5
    ");

    echo '<h4>Event-Venue Relationships Sample:</h4>';
    foreach ($event_venue_relationships as $rel) {
        echo '<div style="border: 1px solid #ddd; padding: 5px; margin: 2px;">';
        echo '<strong>Event:</strong> ' . htmlspecialchars($rel->event_title) . '<br>';
        echo '<strong>Venue ID:</strong> ' . $rel->venue_id . '<br>';
        echo '<strong>Venue Title:</strong> ' . htmlspecialchars($rel->venue_title) . '<br>';
        echo '</div>';
    }

    // 5. Check event start dates
    echo '<h3>5. Event Date Analysis:</h3>';
    $date_sample = $wpdb->get_results("
        SELECT 
            p.post_title,
            pm.meta_value as start_date,
            DAYOFWEEK(STR_TO_DATE(pm.meta_value, '%Y-%m-%d %H:%i:%s')) as day_of_week,
            HOUR(STR_TO_DATE(pm.meta_value, '%Y-%m-%d %H:%i:%s')) as hour_of_day
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'tribe_events'
        AND p.post_status IN ('publish', 'future')
        AND pm.meta_key = '_EventStartDate'
        AND pm.meta_value != ''
        ORDER BY pm.meta_value
        LIMIT 10
    ");

    echo '<h4>Event Date Sample:</h4>';
    foreach ($date_sample as $date) {
        echo '<div style="border: 1px solid #ddd; padding: 5px; margin: 2px;">';
        echo '<strong>Event:</strong> ' . htmlspecialchars($date->post_title) . '<br>';
        echo '<strong>Start Date:</strong> ' . $date->start_date . '<br>';
        echo '<strong>Day of Week:</strong> ' . $date->day_of_week . ' (1=Sunday, 7=Saturday)<br>';
        echo '<strong>Hour:</strong> ' . $date->hour_of_day . '<br>';
        echo '</div>';
    }

    // 6. Test a sample filter query
    echo '<h3>6. Sample Filter Query Test:</h3>';

    // Test cost filter
    echo '<h4>Testing Cost Filter ($0-$100):</h4>';
    $cost_test = $wpdb->get_results("
        SELECT DISTINCT p.ID, p.post_title, pm.meta_value
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')
        AND pm.meta_key = '_custom_event_cost'
        AND pm.meta_value != ''
        AND pm.meta_value LIKE '%s:4:\"cost\";s:%'
        LIMIT 5
    ");

    foreach ($cost_test as $test) {
        echo '<div style="border: 1px solid #ddd; padding: 5px; margin: 2px;">';
        echo '<strong>Event:</strong> ' . htmlspecialchars($test->post_title) . '<br>';
        echo '<strong>Raw Cost Data:</strong> ' . htmlspecialchars($test->meta_value) . '<br>';

        // Try to extract cost
        $cost_data = maybe_unserialize($test->meta_value);
        if (is_array($cost_data) && isset($cost_data['cost'])) {
            echo '<strong>Extracted Cost:</strong> $' . $cost_data['cost'] . '<br>';
        }
        echo '</div>';
    }

    // Test day filter  
    echo '<h4>Testing Day Filter (Monday events):</h4>';
    $day_test = $wpdb->get_results("
        SELECT p.post_title, pm.meta_value as start_date
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')
        AND pm.meta_key = '_EventStartDate'
        AND DAYOFWEEK(STR_TO_DATE(pm.meta_value, '%Y-%m-%d %H:%i:%s')) = 2
        LIMIT 5
    ");

    foreach ($day_test as $test) {
        echo '<div style="border: 1px solid #ddd; padding: 5px; margin: 2px;">';
        echo '<strong>Event:</strong> ' . htmlspecialchars($test->post_title) . '<br>';
        echo '<strong>Start Date:</strong> ' . $test->start_date . ' (Monday)<br>';
        echo '</div>';
    }

    // Test time filter
    echo '<h4>Testing Time Filter (Morning events 6AM-12PM):</h4>';
    $time_test = $wpdb->get_results("
        SELECT p.post_title, pm.meta_value as start_date
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')
        AND pm.meta_key = '_EventStartDate'
        AND HOUR(STR_TO_DATE(pm.meta_value, '%Y-%m-%d %H:%i:%s')) BETWEEN 6 AND 11
        LIMIT 5
    ");

    foreach ($time_test as $test) {
        echo '<div style="border: 1px solid #ddd; padding: 5px; margin: 2px;">';
        echo '<strong>Event:</strong> ' . htmlspecialchars($test->post_title) . '<br>';
        echo '<strong>Start Date:</strong> ' . $test->start_date . ' (Morning)<br>';
        echo '</div>';
    }

    // 7. Test venue data structure for smart filtering
    echo '<h3>7. Venue Data Structure for Smart Filtering:</h3>';
    $venue_structure = $wpdb->get_results("
        SELECT 
            v.ID,
            v.post_title as venue_name,
            COALESCE(pm_state.meta_value, '') as state,
            COALESCE(pm_city.meta_value, '') as city,
            COALESCE(pm_addr.meta_value, '') as address
        FROM {$wpdb->posts} v
        LEFT JOIN {$wpdb->postmeta} pm_state ON v.ID = pm_state.post_id AND pm_state.meta_key = '_VenueState'
        LEFT JOIN {$wpdb->postmeta} pm_city ON v.ID = pm_city.post_id AND pm_city.meta_key = '_VenueCity'
        LEFT JOIN {$wpdb->postmeta} pm_addr ON v.ID = pm_addr.post_id AND pm_addr.meta_key = '_VenueAddress'
        WHERE v.post_type = 'tribe_venue'
        AND v.post_status IN ('publish', 'future')
        LIMIT 10
    ");

    echo '<h4>Venue Structure Sample (for smart filtering):</h4>';
    foreach ($venue_structure as $venue) {
        echo '<div style="border: 1px solid #ddd; padding: 5px; margin: 2px; background: 
#f9f9f9;">';
        echo '<strong>Venue:</strong> ' . htmlspecialchars($venue->venue_name) . '<br>';
        echo '<strong>State:</strong> ' . htmlspecialchars($venue->state) . '<br>';
        echo '<strong>City:</strong> ' . htmlspecialchars($venue->city) . '<br>';
        echo '<strong>Address:</strong> ' . htmlspecialchars($venue->address) . '<br>';
        echo '</div>';
    }

    echo '</div>';
}
add_shortcode('debug_events_db', 'debug_events_database');

function debug_wp_state_shortcode() {
    global $wp_query;

    $html = '<div style="background: 
#f8f9fa; border: 2px solid 
#6c757d; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 12px;">';
    $html .= '<strong>🔬 WORDPRESS STATE DEBUG</strong><br><br>';

    $html .= '<strong>URL & Context:</strong><br>';
    $html .= 'Current URL: ' . $_SERVER['REQUEST_URI'] . '<br>';
    $html .= 'Template: ' . basename(get_page_template()) . '<br><br>';

    $html .= '<strong>WordPress Query:</strong><br>';
    $html .= 'is_post_type_archive("tribe_events"): ' . var_export(is_post_type_archive('tribe_events'), true) . '<br>';
    $html .= 'is_category(): ' . var_export(is_category(), true) . '<br>';
    $html .= 'is_tag(): ' . var_export(is_tag(), true) . '<br>';
    $html .= 'is_tax(): ' . var_export(is_tax(), true) . '<br><br>';

    $html .= '<strong>Query Vars:</strong><br>';
    $html .= 'post_type: ' . var_export(get_query_var('post_type'), true) . '<br>';
    $html .= 'tribe_events_cat: ' . var_export(get_query_var('tribe_events_cat'), true) . '<br>';
    $html .= 'post_tag: ' . var_export(get_query_var('post_tag'), true) . '<br>';
    $html .= 'category_name: ' . var_export(get_query_var('category_name'), true) . '<br><br>';

    $html .= '<strong>Our Custom Detection:</strong><br>';
    $archive_context = get_current_archive_context();
    $html .= 'Archive Context: <pre>' . print_r($archive_context, true) . '</pre>';

    $html .= '</div>';

    return $html;
}
add_shortcode('debug_wp_state', 'debug_wp_state_shortcode');

function quick_time_debug_shortcode() {
    global $wpdb;

    ob_start();
    echo '<div style="background: 
#fff3cd; border: 2px solid 
#856404; padding: 20px; margin: 20px 0; font-family: monospace; font-size: 12px;">';
    echo '<h3>🔍 QUICK TIME FILTERING DEBUG</h3>';

    // Step 1: Check if events exist at all
    $total_events = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type = 'tribe_events' 
        AND post_status IN ('publish', 'future')
    ");

    echo '<p><strong>Total Events:</strong> ' . $total_events . '</p>';

    if ($total_events == 0) {
        echo '<p style="color: red;">❌ No events found! This is the problem.</p>';
        echo '</div>';
        return ob_get_clean();
    }

    // Step 2: Check events with start dates
    $events_with_dates = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'tribe_events' 
        AND p.post_status IN ('publish', 'future')
        AND pm.meta_key = '_EventStartDate'
        AND pm.meta_value != ''
    ");

    echo '<p><strong>Events with Start Dates:</strong> ' . $events_with_dates . '</p>';

    if ($events_with_dates == 0) {
        echo '<p style="color: red;">❌ No events have _EventStartDate meta! This is the problem.</p>';
        echo '</div>';
        return ob_get_clean();
    }

    // Step 3: Test the exact filtering that's failing
    echo '<h4>🧪 Testing Your Exact Filter Combination:</h4>';

    // Get events for category 5151 (from your console log)
    $test_events = get_posts(array(
        'post_type' => 'tribe_events',
        'post_status' => array('publish', 'future'),
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'tribe_events_cat',
                'field' => 'term_id',
                'terms' => array(5151)
            )
        )
    ));

    echo '<p><strong>Events in Category 5151:</strong> ' . count($test_events) . '</p>';

    if (count($test_events) == 0) {
        echo '<p style="color: red;">❌ No events in category 5151! This is the problem.</p>';
        echo '</div>';
        return ob_get_clean();
    }

    // Step 4: Analyze these specific events
    echo '<h4>📋 Analyzing Category 5151 Events:</h4>';
    echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background: white;">';

    $allday_count = 0;
    $morning_count = 0;
    $afternoon_count = 0;
    $evening_count = 0;
    $night_count = 0;
    $unparseable_dates = 0;

    foreach ($test_events as $event) {
        $start_date = get_post_meta($event->ID, '_EventStartDate', true);
        $allday_meta = get_post_meta($event->ID, '_EventAllDay', true);

        echo '<div style="border-bottom: 1px dotted #ccc; padding: 3px 0;">';
        echo '<strong>' . esc_html($event->post_title) . '</strong><br>';
        echo 'Start Date: "' . esc_html($start_date) . '"<br>';
        echo 'All Day Meta: "' . esc_html($allday_meta) . '"<br>';

        if ($start_date) {
            $timestamp = strtotime($start_date);
            if ($timestamp) {
                $hour = date('H', $timestamp);
                $formatted_time = date('Y-m-d H:i:s', $timestamp);
                echo 'Parsed: ' . $formatted_time . ' (Hour: ' . $hour . ')<br>';

                // Check all-day status
                $is_all_day = in_array($allday_meta, array('yes', '1', 'true', 'on'));
                if ($is_all_day) {
                    echo '<span style="background: 
#d4edda; padding: 1px 3px;">ALL DAY</span><br>';
                    $allday_count++;
                } else {
                    // Classify time period
                    if ($hour >= 6 && $hour < 12) {
                        echo '<span style="background: 
#fff3cd; padding: 1px 3px;">MORNING</span><br>';
                        $morning_count++;
                    } elseif ($hour >= 12 && $hour < 17) {
                        echo '<span style="background: 
#cce5ff; padding: 1px 3px;">AFTERNOON</span><br>';
                        $afternoon_count++;
                    } elseif ($hour >= 17 && $hour < 21) {
                        echo '<span style="background: 
#f8d7da; padding: 1px 3px;">EVENING</span><br>';
                        $evening_count++;
                    } elseif ($hour >= 21 || $hour < 6) {
                        echo '<span style="background: 
#d1ecf1; padding: 1px 3px;">NIGHT</span><br>';
                        $night_count++;
                    } else {
                        echo '<span style="background: 
#f5f5f5; padding: 1px 3px;">UNKNOWN PERIOD</span><br>';
                    }
                }
            } else {
                echo '<span style="color: red;">❌ UNPARSEABLE DATE</span><br>';
                $unparseable_dates++;
            }
        } else {
            echo '<span style="color: red;">❌ NO START DATE</span><br>';
        }
        echo '</div>';
    }

    echo '</div>';

    // Step 5: Summary of what should match your filters
    echo '<h4>📊 Time Period Breakdown:</h4>';
    echo '<ul>';
    echo '<li>All Day: ' . $allday_count . '</li>';
    echo '<li>Morning (6AM-12PM): ' . $morning_count . '</li>';  
    echo '<li>Afternoon (12PM-5PM): ' . $afternoon_count . '</li>';
    echo '<li>Evening (5PM-9PM): ' . $evening_count . '</li>';
    echo '<li>Night (9PM-6AM): ' . $night_count . '</li>';
    echo '<li>Unparseable Dates: ' . $unparseable_dates . '</li>';
    echo '</ul>';

    // Step 6: Expected results
    echo '<h4>🎯 Expected Results for Your Filter:</h4>';
    echo '<p><strong>Your time period filter:</strong> allday,morning,afternoon,evening,night</p>';
    echo '<p><strong>Your day filter:</strong> Sunday,Monday,Tuesday,Wednesday (1,2,3,4)</p>';

    $expected_total = $allday_count + $morning_count + $afternoon_count + $evening_count + $night_count;
    echo '<p><strong>Expected matches (before day filtering):</strong> ' . $expected_total . '</p>';

    if ($expected_total == 0) {
        echo '<div style="background: 
#f8d7da; padding: 10px; margin: 10px 0; border: 1px solid 
#f5c6cb;">';
        echo '<strong>🚨 PROBLEM IDENTIFIED:</strong><br>';
        if ($unparseable_dates > 0) {
            echo '• ' . $unparseable_dates . ' events have unparseable dates<br>';
        }
        if ($allday_count == 0 && ($morning_count + $afternoon_count + $evening_count + $night_count) == 0) {
            echo '• No events found in any time period - check date format or time zone issues<br>';
        }
        echo '</div>';
    }

    // Step 7: Day of week analysis
    echo '<h4>📅 Day of Week Analysis:</h4>';
    $day_counts = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0);
    $day_names = array(1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday');

    foreach ($test_events as $event) {
        $start_date = get_post_meta($event->ID, '_EventStartDate', true);
        if ($start_date) {
            $timestamp = strtotime($start_date);
            if ($timestamp) {
                $day_of_week = date('w', $timestamp) + 1; // Convert to 1-7 format
                $day_counts[$day_of_week]++;
            }
        }
    }

    echo '<ul>';
    foreach ($day_counts as $day_num => $count) {
        $selected = in_array($day_num, array(1, 2, 3, 4)) ? ' ✅' : '';
        echo '<li>' . $day_names[$day_num] . ': ' . $count . ' events' . $selected . '</li>';
    }
    echo '</ul>';

    $selected_day_total = $day_counts[1] + $day_counts[2] + $day_counts[3] + $day_counts[4];
    echo '<p><strong>Events on selected days (Sun-Wed):</strong> ' . $selected_day_total . '</p>';

    if ($selected_day_total == 0) {
        echo '<div style="background: 
#f8d7da; padding: 10px; margin: 10px 0; border: 1px solid 
#f5c6cb;">';
        echo '<strong>🚨 DAY FILTER PROBLEM:</strong> No events found on Sunday-Wednesday!<br>';
        echo '</div>';
    }

    echo '</div>';
    return ob_get_clean();
}
add_shortcode('quick_time_debug', 'quick_time_debug_shortcode');
