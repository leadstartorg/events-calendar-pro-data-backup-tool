<?php
/**
 * Fixed archive template for tribe_events with proper date handling
 */

get_header();

// Get unique venue data
// First, let's debug what's actually in the database
global $wpdb;

// ADD after line 10
$archive_context = get_current_archive_context();
$is_taxonomy_archive = ($archive_context['context'] === 'taxonomy');
$taxonomy_filters = $is_taxonomy_archive ? $archive_context['filter_params'] : array();

// Get date from query vars (they're working correctly)
$year = get_query_var('year');
$monthnum = get_query_var('monthnum'); 
$day = get_query_var('day');

// Only apply date filtering if we have date parameters
if ($year) {
    // Build date range for more precise filtering using full datetime
    if ($day) {
        // Day archive: specific day with full time range
        $query_start_date = sprintf('%04d-%02d-%02d 00:00:00', $year, $monthnum, $day);
        $query_end_date = sprintf('%04d-%02d-%02d 23:59:59', $year, $monthnum, $day);
        $compare = 'BETWEEN';
        $meta_value = array($query_start_date, $query_end_date);
    } elseif ($monthnum) {
        // Month archive: use LIKE for simpler matching
        $date_pattern = sprintf('%04d-%02d', $year, $monthnum);
        $compare = 'LIKE';
        $meta_value = $date_pattern;
    } else {
        // Year archive: use LIKE for simpler matching
        $date_pattern = sprintf('%04d', $year);
        $compare = 'LIKE';
        $meta_value = $date_pattern;
    }

    // Query events by event date
    $events_args = array(
        'post_type' => 'tribe_events',
        'post_status' => array('publish', 'future'),
        'posts_per_page' => get_option('posts_per_page'),
        'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
        'meta_query' => array(
            array(
                'key' => '_EventStartDate',
                'value' => $meta_value,
                'compare' => $compare
            )
        ),
        'meta_key' => '_EventStartDate',
        'orderby' => 'meta_value',
        'order' => 'ASC'
    );

    $events_args = build_filtered_query_args('tribe_events', array(), $debug_output);
    $events_query = new WP_Query($events_args);

} else {

    // No date filtering - show all events
    $events_args = array(
        'post_type' => 'tribe_events',
        'post_status' => array('publish', 'future'),
        'posts_per_page' => get_option('posts_per_page'), 
        'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
        //'meta_key' => '_EventStartDate',
        //'orderby' => 'meta_value',
        'order' => 'ASC'
    );

    $events_args['tax_query'] = array(
        array(
            'taxonomy' => $archive_context['taxonomy'],  // tribe_events_cat
            'field' => 'term_id',
            'terms' => $archive_context['term_id'],      // 5151
        )
    );

    if (!empty($_GET['category'])) {
        $tax_query[] = array(
            'taxonomy' => 'tribe_events_cat',
            'field' => 'term_id', 
            'terms' => array_map('intval', $category_ids),
        );
    }
    
    if (!empty($_GET['keyword'])) {
        $tag_ids = is_array($_GET['keyword']) ? $_GET['keyword'] : explode(',', $_GET['keyword']);
        $tax_query[] = array(
            'taxonomy' => 'post_tag',
            'field' => 'term_id',
            'terms' => array_map('intval', $tag_ids),
        );
    }

    // Check for legacy parameter names as well
    if (!empty($_GET['tribe_events_cat'])) {
        $category_ids = is_array($_GET['tribe_events_cat']) ? $_GET['tribe_events_cat'] : explode(',', $_GET['tribe_events_cat']);
        $tax_query[] = array(
            'taxonomy' => 'tribe_events_cat',
            'field' => 'term_id',
            'terms' => array_map('intval', $category_ids),
        );
    }
    
    if (!empty($_GET['post_tag'])) {
        $tag_ids = is_array($_GET['post_tag']) ? $_GET['post_tag'] : explode(',', $_GET['post_tag']);
        $tax_query[] = array(
            'taxonomy' => 'post_tag',
            'field' => 'term_id',
            'terms' => array_map('intval', $tag_ids),
        );
    }

    $events_query = new WP_Query($events_args);
    //echo '<!-- No Date Filtering Found Posts (all): ' . $events_query->found_posts . ' -->';

}

    // Debug the actual SQL query being generated
    echo '<!-- URL Params: year=' . $year . ', month=' . $monthnum . ', day=' . $day . ' -->';
    echo '<!-- SQL Query: ' . $events_query->request . ' -->';
    echo '<!-- Found Posts (all): ' . $events_query->found_posts . ' -->';

?>
<div id="primary">
    <?php
            if (is_custom_post_type_date_archive_by_url()) {
        $info = is_custom_post_type_date_archive_by_url();
        echo "Detected: " . $info['post_type'] . " archive for " . $info['year'];
        if ($info['month']) echo "/" . $info['month'];
        if ($info['day']) echo "/" . $info['day'];
    } else {
        echo "No custom post type date archive detected";
    } ?>
    <?php echo do_shortcode('[debug_wp_state]'); ?>
<?php //echo do_shortcode('[debug_events_db]'); ?>
    <div class="post-type-archive-tribe_events">
        <header class="page-header mb-5 p-md-5" role="heading">
            <h1 class="page-title text-center"><?php echo $page_title; ?></h1>
            <?php the_archive_description( '<div class="archive-description text-center">', '</div>' ); ?>
        </header>
        <div class="container-fluid">
            <!-- Navigation and Controls -->
            <div class="container mt-3">
                <div class="row align-items-center mb-3">
                    <div class="col-md-6">
                        <div id="custom-filter-bar">
                            <?php echo 'view tabs (list, calendar, grid)'; ?>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="date-nav-container justify-content-md-end">
                            <?php 
                                echo do_shortcode('[custom_calendar post_type="tribe_events" ajax_target="#events-container"]'); 
                            ?>
                            <?php
                            // Display a calendar
                           //get_calendar( array( 'post_type' => 'tribe_events' ) );
                            ?>
                            <?php //echo do_shortcode('[custom_calendar post_type="tribe_events" ajax_target="#events-container"]') ; ?>
                            <?php //echo do_shortcode('[debug_calendar_taxonomy]') ; ?>
                            <?php //echo do_shortcode('[debug_calendar_sql year="2024" month="12"]') ; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="container">
                <div class="row">
                <div class="col-md-3">
                    <div class="collapse d-md-block" id="filterSidebar">
                        <div class="filter-sidebar">
                            <div class="mb-3">
                                <h5 class="mb-0">Filters</h5>
                            </div>
                            <?php 
                                echo do_shortcode('[taxonomy_filter post_type="tribe_events" ajax_target="#events-container"]'); 
                            ?>
                            <?php //echo do_shortcode('[taxonomy_filter post_type="tribe_events" ajax_target="#events-container"]'); ?>
                        </div> 
                    </div> 
                </div>
            
                    <div class="col-md-9 scroll-content" id="events-container">
                        <?php
                        // Debug: Show the query being used (visible on page)
                        if (isset($date_pattern)) {
                            //echo '<div class="alert alert-info">META QUERY DEBUG: Looking for _EventStartDate LIKE "' . $meta_value . '"</div>';
                        }
                        
                        if ($events_query->have_posts()):
                            echo '<div style="background: #f3e5f5; padding: 10px; margin: 10px 0;">';
                            echo '<strong>📋 Found ' . $events_query->found_posts . ' events</strong>';
                            echo '</div>';
                            //echo '<div class="alert alert-success">FOUND ' . $events_query->found_posts . ' EVENTS</div>';
                            $current_month = '';
                            while ($events_query->have_posts()) : $events_query->the_post();
                                $event_id = get_the_ID();
                                
                                // Get event start date from meta and define for template
                                $start_date_meta = get_post_meta($event_id, '_EventStartDate', true);
                                $start_date = $start_date_meta; // Define for the template part
                                /*
                                // Debug: Show actual meta value and what date() produces (visible on page)
                                echo '<div class="alert alert-warning">';
                                echo '<strong>Event ' . $event_id . ':</strong><br>';
                                echo '_EventStartDate = "' . $start_date_meta . '"<br>';
                                echo 'Date formatting test: "' . date('F Y', strtotime($start_date_meta)) . '"<br>';
                                
                                // Also check if there are other date fields causing confusion
                                $post_date = get_the_date('Y-m-d H:i:s');
                                echo 'Post publish date: "' . $post_date . '"';
                                echo '</div>';
                                */
                                if ($start_date_meta) {
                                    $event_month = date('F Y', strtotime($start_date_meta));
                                    
                                    // Show month separator if different month
                                    if ($current_month !== $event_month) {
                                        if ($current_month !== '') {
                                            echo '<hr class="month-separator">';
                                        }
                                        echo '<h3 class="month-header">' . $event_month . '</h3>';
                                        $current_month = $event_month;
                                    }
                                }
                            
                                // Make sure variables are available to the template
                                set_query_var('event_id', $event_id);
                                set_query_var('start_date', $start_date);
                                
                                get_template_part('template-parts/content', 'events'); 

                            endwhile; 
                            wp_reset_postdata(); 
                            // Pagination 
                            get_template_part('template-parts/pagination', 'notabs');
                        else:
                            get_template_part('template-parts/content', 'none'); ?>
                                                        <div class="no-events-found text-center py-5">
                                <h3>No events found</h3>
                                <p>There are no events matching your search criteria.</p>
                                <a href="<?php echo home_url('/events'); ?>" class="btn btn-primary">View All Events</a>
                            </div>
                      <?php  endif; ?>

                        <!-- Subscribe to Calendar -->
                        <div class="text-center mt-4">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-calendar-plus"></i> Subscribe to calendar
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-google"></i> Google Calendar</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-calendar"></i> iCalendar</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-microsoft"></i> Outlook 365</a></li>
                                    <li><a class="dropdown-item" href="#" download><i class="bi bi-download"></i> Export .ics file</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
?>
<?php get_footer(); ?>
