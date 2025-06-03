/**
 * ORGANIZED MODULAR JAVASCRIPT SOLUTION
 * Clear separation between Calendar-driven and Filter-driven interactions
 * 
 * STRUCTURE:
 * 1. Calendar interactions → update AJAX target, browser URL, taxonomy filters
 * 2. Filter interactions → update AJAX target, browser URL, calendar  
 * 3. URL initialization → set up everything from URL params
 */

jQuery(document).ready(function($) {
    console.log('🚀 Organized Calendar & Filter System Loaded');
    
    // ========================================
    // CORE HELPER FUNCTIONS (shared by both calendar and filters)
    // ========================================
    
    /**
     * Get current filter values from DOM elements
     */
    function getCurrentFilters() {
        const filters = {};
        $('.taxonomy-filter-select').each(function() {
            const taxonomyName = $(this).data('taxonomy');
            const selectedValue = $(this).val();
            
            if (selectedValue && selectedValue.length > 0) {
                filters[taxonomyName] = Array.isArray(selectedValue) ? selectedValue : [selectedValue];
            }
        });
        return filters;
    }
    
    /**
     * Get current calendar context (month/year/day)
     */
    function getCurrentCalendarContext() {
        // Extract day from current URL if on daily archive
        const currentPath = window.location.pathname;
        const dayMatch = currentPath.match(/\/(\d{4})\/(\d{2})\/(\d{2})\//);
        
        return {
            month: $('#calendar_month').val() || '',
            year: $('#calendar_year').val() || '',
            day: dayMatch ? dayMatch[3] : '',
            hasDropdowns: $('#calendar_month, #calendar_year').length > 0,
            isDaily: !!dayMatch,
            isMonthly: !dayMatch && currentPath.match(/\/(\d{4})\/(\d{2})\//),
            isYearly: !dayMatch && currentPath.match(/\/(\d{4})\//) && !currentPath.match(/\/(\d{4})\/(\d{2})\//)
        };
    }
    
    /**
     * Build clean URL without duplicates
     */
    function buildCleanURL(basePath, filters) {
        // Handle URLs that already have parameters
        let cleanBasePath = basePath;
        if (basePath.includes('?')) {
            cleanBasePath = basePath.split('?')[0];
            console.log('🧹 Stripped parameters from base path:', basePath, '→', cleanBasePath);
        }
        
        const url = new URL(cleanBasePath, window.location.origin);
        
        // Clear ALL existing filter parameters
        url.searchParams.delete('category');
        url.searchParams.delete('keyword'); 
        url.searchParams.delete('tribe_events_cat');
        url.searchParams.delete('post_tag');
        
        // Add new filter parameters (pretty format)
        Object.keys(filters).forEach(taxonomyName => {
            const termIds = filters[taxonomyName];
            
            if (termIds.length > 0) {
                let prettyParam = '';
                
                if (taxonomyName === 'tribe_events_cat' || taxonomyName === 'category') {
                    prettyParam = 'category';
                } else if (taxonomyName === 'post_tag') {
                    prettyParam = 'keyword';
                }
                
                if (prettyParam) {
                    url.searchParams.set(prettyParam, termIds.join(','));
                    console.log('📎 Added clean parameter:', prettyParam, '=', termIds.join(','));
                }
            }
        });
        
        const finalURL = url.toString();
        console.log('🎯 Built clean URL:', basePath, '→', finalURL);
        return finalURL;
    }
    
    /**
     * NEW HELPER FUNCTION: Update the calendar HTML display from fetched content
     * This was the missing piece - calendar HTML wasn't being updated during navigation!
     * Assumes calendar elements are outside the main AJAX target
     */
    function updateCalendarDisplayFromFetchedContent($data) {
        console.log('🔄 Updating calendar display from fetched HTML...');

        const $newCalendar = $data.find('#wp-calendar');
        const $newCalendarNav = $data.find('.wp-calendar-nav');

        if ($newCalendar.length) {
            $('#wp-calendar').replaceWith($newCalendar);
            console.log('✅ Calendar table updated from fetched data.');
            $('#wp-calendar').addClass('table table-striped'); // Re-apply classes if needed
        } else {
            console.warn('⚠️ Could not find #wp-calendar in the fetched data for update.');
        }

        if ($newCalendarNav.length) {
            $('.wp-calendar-nav').replaceWith($newCalendarNav);
            console.log('✅ Calendar navigation updated from fetched data.');
        } else {
            console.warn('⚠️ Could not find .wp-calendar-nav in the fetched data for update.');
        }
    }
    
    /**
     * Update browser URL without page reload
     */
    function updateBrowserURL(newURL) {
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', newURL);
            console.log('🔗 Browser URL updated:', newURL);
        }
    }
    
    /**
     * Update calendar dropdowns based on URL
     */
    function updateCalendarDropdownsFromURL(url) {
        const match = url.match(/\/(\d{4})\/(\d{2})/);
        if (match) {
            const year = match[1];
            const month = match[2];
            
            if ($('#calendar_year').length) {
                $('#calendar_year').val(year);
            }
            if ($('#calendar_month').length) {
                $('#calendar_month').val(month);
            }
            
            console.log('📅 Calendar dropdowns updated:', year, month);
        }
    }
    
    /**
     * Update filter dropdown options from new HTML content
     */
    function updateFilterDropdownOptions($newContent, maintainSelections = true) {
        console.log('🔧 Updating filter dropdown options from new content...');
        
        const currentSelections = maintainSelections ? getCurrentFilters() : {};
        
        $('.taxonomy-filter-select').each(function() {
            const $currentSelect = $(this);
            const taxonomyName = $currentSelect.data('taxonomy');
            const selectId = $currentSelect.attr('id');
            
            const $newSelect = $newContent.find(`[data-taxonomy="${taxonomyName}"], #${selectId}`).first();
            
            if ($newSelect.length) {
                const newOptions = $newSelect.html();
                
                if (newOptions && newOptions !== $currentSelect.html()) {
                    console.log(`📋 Updating ${taxonomyName} dropdown options`);
                    
                    $currentSelect.html(newOptions);
                    
                    if (maintainSelections && currentSelections[taxonomyName]) {
                        const availableValues = [];
                        $currentSelect.find('option').each(function() {
                            const val = $(this).val();
                            if (val) availableValues.push(val);
                        });
                        
                        const validSelections = currentSelections[taxonomyName].filter(val => 
                            availableValues.includes(val.toString())
                        );
                        
                        if (validSelections.length > 0) {
                            $currentSelect.val(validSelections);
                            console.log(`✅ Restored ${taxonomyName} selections:`, validSelections);
                        } else {
                            $currentSelect.val('');
                            console.log(`⚠️ No valid ${taxonomyName} selections for this date`);
                        }
                    }
                }
            }
        });
        
        console.log('✅ Filter dropdown options update complete');
    }
    
    // ========================================
    // CALENDAR-DRIVEN INTERACTIONS
    // Calendar changes → update AJAX target, browser URL, taxonomy filters
    // ========================================
    
    /**
     * MASTER FUNCTION: Handle calendar changes
     * Called when month/year dropdowns, monthly navigation, or day links are used
     */
    function handleCalendarChange(newURL, triggerSource = 'unknown') {
        console.log(`=== CALENDAR CHANGE (${triggerSource}) ===`);
        console.log('Navigating to:', newURL);
        
        const currentFilters = getCurrentFilters();
        const ajaxTarget = $('.calendar-controls').data('ajax-target') || '#primary';
        
        // Build final URL with current filters
        const finalURL = buildCleanURL(newURL, currentFilters);
        
        console.log('📅 Calendar navigation:', {
            triggerSource: triggerSource,
            newURL: newURL,
            currentFilters: currentFilters,
            finalURL: finalURL
        });
        
        // Load new content
        $(ajaxTarget).addClass('calendar-loading');
        
        $.get(finalURL)
            .done(function(data) {
                const $data = $(data);
                const newContent = $data.find(ajaxTarget).html();
                
                // 1. Update AJAX target
                $(ajaxTarget).html(newContent);

                // 2. CRITICAL FIX: Update the calendar itself (using the new helper function)
                // This was the missing piece - calendar HTML wasn't being updated!
                updateCalendarDisplayFromFetchedContent($data);
                
                // 3. Update browser URL
                updateBrowserURL(finalURL);
                
                // 4. Update calendar dropdowns to reflect new date
                updateCalendarDropdownsFromURL(finalURL);
                
                // 5. Update taxonomy filter OPTIONS (but keep current selections)
                updateFilterDropdownOptions($data, true);
                
                console.log(`✅ Calendar change complete (${triggerSource}) - Calendar HTML updated!`);
            })
            .fail(function(xhr, status, error) {
                console.log(`❌ Calendar change failed (${triggerSource}):`, status, error);
                window.location.href = finalURL;
            })
            .always(function() {
                $(ajaxTarget).removeClass('calendar-loading');
            });
    }
    
    /**
     * Calendar month/year dropdown changes
     */
    function initCalendarDropdownHandlers() {
        $(document).on('change', '#calendar_month, #calendar_year', function(e) {
            const calendarContext = getCurrentCalendarContext();
            
            // Build new URL with calendar date
            let newURL = window.location.pathname;
            
            const pathParts = newURL.split('/').filter(part => part);
            if (pathParts.length >= 1 && pathParts[0] === 'events') {
                newURL = '/events/' + calendarContext.year + '/';
                if (calendarContext.month) {
                    newURL += calendarContext.month.padStart(2, '0') + '/';
                }
            }
            
            handleCalendarChange(newURL, 'dropdown');
        });
    }
    
    /**
     * Calendar day/month navigation link clicks
     */
    function initCalendarLinkHandlers() {
        $(document).on('click', '#wp-calendar a, #calendar-wrapper a, .wp-calendar-nav a', function(e) {
            const href = $(this).attr('href');
            
            if (href && href.match(/\/(?:[a-z-]+\/)?(\d{4})\/(\d{2})(\/\d{2})?\/?$/)) {
                e.preventDefault();
                
                // Extract clean path without parameters
                let cleanPath = href;
                if (href.includes('?')) {
                    cleanPath = href.split('?')[0];
                }
                
                if (!cleanPath.endsWith('/')) {
                    cleanPath += '/';
                }
                
                handleCalendarChange(cleanPath, 'day/month link');
            }
        });
    }
    
    /**
     * Today button clicks
     */
    function initTodayButtonHandler() {
        $(document).on('click', '#calendar_today', function(e) {
            const today = new Date();
            const currentMonth = String(today.getMonth() + 1).padStart(2, '0');
            const currentYear = today.getFullYear();
            const currentDay = String(today.getDate()).padStart(2, '0');
            
            // Update calendar dropdowns first
            $('#calendar_month').val(currentMonth);
            $('#calendar_year').val(currentYear);
            
            // Build today URL
            const todayURL = `/events/${currentYear}/${currentMonth}/${currentDay}/`;
            
            handleCalendarChange(todayURL, 'today button');
        });
    }
    
    // ========================================
    // FILTER-DRIVEN INTERACTIONS  
    // Filter changes → update AJAX target, browser URL, calendar
    // ========================================
    
    /**
     * MASTER FUNCTION: Handle filter changes
     * Called when taxonomy filter dropdowns change
     */
    function handleFilterChange(newFilters, triggerSource = 'unknown') {
        console.log(`=== FILTER CHANGE (${triggerSource}) ===`);
        console.log('New filters:', newFilters);
        
        const container = $('.taxonomy-filter-container').first();
        const postType = container.data('post-type') || 'tribe_events';
        const ajaxTarget = container.data('ajax-target') || '#primary';
        
        // Show loading
        $('#filter-loading').show();
        $(ajaxTarget).addClass('loading');
        
        // Send AJAX request
        $.ajax({
            url: archiveEventsAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'filter_posts_by_taxonomy',
                current_page_url: window.location.pathname,
                post_type: postType,
                taxonomy_filters: newFilters,
                nonce: archiveEventsAjax.nonce
            },
            success: function(response) {
                console.log(`=== FILTER AJAX SUCCESS (${triggerSource}) ===`);
                
                if (response.success) {
                    // 1. Update AJAX target
                    $(ajaxTarget).html(response.data.html);
                    
                    // 2. Update browser URL
                    const cleanURL = buildCleanURL(window.location.pathname, newFilters);
                    updateBrowserURL(cleanURL);
                    
                    // 3. Update calendar with filtered results
                    updateCalendarWithFilters(newFilters);
                    
                    console.log(`✅ Filter change complete (${triggerSource})`);
                } else {
                    console.error('AJAX returned error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error(`=== FILTER AJAX ERROR (${triggerSource}) ===`);
                console.error('Status:', status, 'Error:', error);
            },
            complete: function() {
                $('#filter-loading').hide();
                $(ajaxTarget).removeClass('loading');
            }
        });
    }
    
    /**
     * Update calendar display with filtered results - IMPROVED VERSION
     * Now uses the same pattern as calendar navigation
     */
    function updateCalendarWithFilters(filters) {
        if (!$('#wp-calendar, .wp-calendar-nav').length) {
            console.log('📅 No calendar found to update');
            return;
        }
        
        console.log('📅 Updating calendar with filters:', filters);
        
        const calendarContext = getCurrentCalendarContext();
        
        // Build URL for calendar update
        let calendarURL = window.location.pathname;
        
        if (calendarContext.hasDropdowns && calendarContext.year) {
            const pathParts = window.location.pathname.split('/').filter(part => part);
            
            if (pathParts.length >= 1 && pathParts[0] === 'events') {
                calendarURL = '/events/' + calendarContext.year + '/';
                if (calendarContext.month) {
                    calendarURL += calendarContext.month.padStart(2, '0') + '/';
                }
            }
        }
        
        const cleanCalendarURL = buildCleanURL(calendarURL, filters);
        
        // Fetch updated calendar and update HTML display
        $.get(cleanCalendarURL)
            .done(function(data) {
                const $data = $(data);
                
                // UPDATED: Use the same calendar update method as navigation
                updateCalendarDisplayFromFetchedContent($data);
                console.log('✅ Calendar updated successfully with filtered results');
            })
            .fail(function(xhr, status, error) {
                console.log('❌ Calendar update failed:', status, error);
            });
    }
    
    /* OLD VERSION THAT DIDN'T UPDATE CALENDAR HTML PROPERLY - COMMENTED OUT
    function updateCalendarWithFilters(filters) {
        if (!$('#wp-calendar, .wp-calendar-nav').length) {
            console.log('📅 No calendar found to update');
            return;
        }
        
        console.log('📅 Updating calendar with filters:', filters);
        
        const calendarContext = getCurrentCalendarContext();
        
        // Build URL for calendar update
        let calendarURL = window.location.pathname;
        
        if (calendarContext.hasDropdowns && calendarContext.year) {
            const pathParts = window.location.pathname.split('/').filter(part => part);
            
            if (pathParts.length >= 1 && pathParts[0] === 'events') {
                calendarURL = '/events/' + calendarContext.year + '/';
                if (calendarContext.month) {
                    calendarURL += calendarContext.month.padStart(2, '0') + '/';
                }
            }
        }
        
        const cleanCalendarURL = buildCleanURL(calendarURL, filters);
        
        // Fetch updated calendar
        $.get(cleanCalendarURL)
            .done(function(data) {
                const $data = $(data);
                const newCalendar = $data.find('#wp-calendar, .wp-calendar-nav').first();
                
                if (newCalendar.length) {
                    $('#wp-calendar, .wp-calendar-nav').first().replaceWith(newCalendar);
                    console.log('✅ Calendar updated successfully with filtered results');
                    
                    $('#wp-calendar').addClass('table table-striped');
                } else {
                    console.log('⚠️ No calendar found in fetched data');
                }
            })
            .fail(function(xhr, status, error) {
                console.log('❌ Calendar update failed:', status, error);
            });
    }
    */
    
    /**
     * Taxonomy filter dropdown changes
     */
    function initFilterHandlers() {
        $(document).on('change', '.taxonomy-filter-select', function(e) {
            // Skip if this is a display update (prevents loops)
            if (e.namespace === 'display') {
                return;
            }
            
            const newFilters = getCurrentFilters();
            handleFilterChange(newFilters, 'filter dropdown');
        });
    }
    
    /**
     * Clear filters button
     */
    function initClearFiltersHandler() {
        $(document).on('click', '.clear-filters', function() {
            const container = $(this).closest('.taxonomy-filter-container');
            
            // Reset all dropdowns
            container.find('.taxonomy-filter-select').val('');
            
            // Trigger filter change with empty filters
            handleFilterChange({}, 'clear filters');
        });
    }
    
    // ========================================
    // URL INITIALIZATION
    // Direct URL paste → set up taxonomy filters, calendar, and AJAX target
    // ========================================
    
    /**
     * Initialize everything from URL parameters on page load
     */
    function initializeFromURL() {
        console.log('🔧 Initializing from URL...');
        
        const urlParams = new URLSearchParams(window.location.search);
        let hasFilters = false;
        const detectedFilters = {};
        
        // 1. Set up taxonomy filters from URL
        $('.taxonomy-filter-select').each(function() {
            const $select = $(this);
            const taxonomyName = $select.data('taxonomy');
            
            // Check for pretty parameter names first
            let paramValue = null;
            if (taxonomyName === 'tribe_events_cat' || taxonomyName === 'category') {
                paramValue = urlParams.get('category');
            } else if (taxonomyName === 'post_tag') {
                paramValue = urlParams.get('keyword');
            }
            
            // Fallback to direct parameter names
            if (!paramValue) {
                paramValue = urlParams.get(taxonomyName);
            }
            
            if (paramValue) {
                const termIds = paramValue.split(',');
                $select.val(termIds);
                detectedFilters[taxonomyName] = termIds.map(id => parseInt(id));
                hasFilters = true;
                
                console.log('🔧 Set filter from URL:', taxonomyName, '=', termIds);
            }
        });
        
        // 2. Set up calendar from URL
        updateCalendarDropdownsFromURL(window.location.href);
        
        // 3. If we have filters, update calendar to show filtered results
        if (hasFilters) {
            console.log('🔧 URL has filters, updating calendar with filtered results...');
            updateCalendarWithFilters(detectedFilters);
        }
        
        console.log('✅ URL initialization complete');
        return hasFilters;
    }
    
    // ========================================
    // SYSTEM INITIALIZATION
    // ========================================
    
    function initializeSystem() {
        console.log('🚀 Initializing organized filter & calendar system...');
        
        // Initialize calendar interactions
        initCalendarDropdownHandlers();
        initCalendarLinkHandlers(); 
        initTodayButtonHandler();
        
        // Initialize filter interactions
        initFilterHandlers();
        initClearFiltersHandler();
        
        // Initialize from URL
        initializeFromURL();
        
        // Add system status indicator
        if ($('.taxonomy-filter-container, .calendar-controls').length) {
            $('.taxonomy-filter-container, .calendar-controls').first().prepend(
                '<div class="system-status" style="background: #e8f5e8; padding: 5px; margin-bottom: 10px; font-size: 11px; border-radius: 3px;">' +
                '✅ <strong>Organized System Active</strong> - Clear separation: Calendar ↔ Filters ↔ URL' +
                '</div>'
            );
        }
        
        console.log('✅ Organized system initialization complete');
        
        // Expose helper functions for debugging
        window.FilterCalendarSystem = {
            // Core helpers
            getCurrentFilters: getCurrentFilters,
            getCurrentCalendarContext: getCurrentCalendarContext,
            buildCleanURL: buildCleanURL,
            
            // Calendar-driven functions
            handleCalendarChange: handleCalendarChange,
            updateCalendarDisplayFromFetchedContent: updateCalendarDisplayFromFetchedContent,
            
            // Filter-driven functions  
            handleFilterChange: handleFilterChange,
            updateCalendarWithFilters: updateCalendarWithFilters,
            
            // URL initialization
            initializeFromURL: initializeFromURL
        };
    }
    
    // Start the organized system
    initializeSystem();
    
    /* ========================================
     * OLD NON-ORGANIZED CODE - COMMENTED OUT
     * ========================================
     
    // Old mixed responsibility functions - replaced by organized structure above
    
    // function handleTaxonomyFilterChange() {
    //     $(document).on('change', '.taxonomy-filter-select', function(e) {
    //         // Skip if this is a display update (prevents loops)
    //         if (e.namespace === 'display') {
    //             return;
    //         }
    //         
    //         console.log('=== FILTER CHANGE DETECTED ===');
    //         
    //         const container = $(this).closest('.taxonomy-filter-container');
    //         const postType = container.data('post-type');
    //         const ajaxTarget = container.data('ajax-target');
    //         
    //         // Get current filter state
    //         const selectedFilters = getCurrentFilters();
    //         console.log('Current filters:', selectedFilters);
    //         
    //         // Show loading
    //         $('#filter-loading').show();
    //         if ($(ajaxTarget).length) {
    //             $(ajaxTarget).addClass('loading');
    //         }
    //         
    //         // Send AJAX request
    //         $.ajax({
    //             url: archiveEventsAjax.ajax_url,
    //             type: 'POST',
    //             data: {
    //                 action: 'filter_posts_by_taxonomy',
    //                 current_page_url: window.location.pathname,
    //                 post_type: postType,
    //                 taxonomy_filters: selectedFilters,
    //                 nonce: archiveEventsAjax.nonce
    //             },
    //             success: function(response) {
    //                 console.log('=== AJAX SUCCESS ===');
    //                 
    //                 if (response.success) {
    //                     // Update target content
    //                     if ($(ajaxTarget).length) {
    //                         $(ajaxTarget).html(response.data.html);
    //                     }
    //                     
    //                     // CRITICAL: Synchronize all components
    //                     synchronizeAllComponents(selectedFilters, true);
    //                     
    //                 } else {
    //                     console.error('AJAX returned error:', response.data);
    //                 }
    //             },
    //             error: function(xhr, status, error) {
    //                 console.error('=== AJAX ERROR ===');
    //                 console.error('Status:', status, 'Error:', error);
    //             },
    //             complete: function() {
    //                 $('#filter-loading').hide();
    //                 if ($(ajaxTarget).length) {
    //                     $(ajaxTarget).removeClass('loading');
    //                 }
    //             }
    //         });
    //     });
    // }
    
    // function handleCalendarDropdownChange() {
    //     $(document).on('change', '#calendar_month, #calendar_year', function(e) {
    //         console.log('=== CALENDAR DROPDOWN CHANGE ===');
    //         
    //         const calendarContext = getCurrentCalendarContext();
    //         const currentFilters = getCurrentFilters();
    //         const ajaxTarget = $('.calendar-controls').data('ajax-target') || '#primary';
    //         
    //         // Build new URL with calendar date
    //         let newURL = window.location.pathname;
    //         
    //         // Update path with new month/year
    //         const pathParts = newURL.split('/').filter(part => part);
    //         if (pathParts.length >= 1 && pathParts[0] === 'events') {
    //             newURL = '/events/' + calendarContext.year + '/';
    //             if (calendarContext.month) {
    //                 newURL += calendarContext.month.padStart(2, '0') + '/';
    //             }
    //         }
    //         
    //         // Add current filters
    //         const finalURL = buildCleanURL(newURL, currentFilters);
    //         
    //         console.log('Calendar navigation to:', finalURL);
    //         
    //         // Load new content
    //         $(ajaxTarget).addClass('calendar-loading');
    //         
    //         $.get(finalURL)
    //             .done(function(data) {
    //                 const $data = $(data);
    //                 const newContent = $data.find(ajaxTarget).html();
    //                 
    //                 // Update target content
    //                 $(ajaxTarget).html(newContent);
    //                 
    //                 // Update browser URL
    //                 updateBrowserURL(finalURL);
    //                 
    //                 // CRITICAL FIX: Pass the full $data to synchronization  
    //                 // This allows filter dropdown options to be updated for the new month
    //                 synchronizeAllComponents(currentFilters, false, $data);
    //                 
    //                 console.log('✅ Calendar navigation complete with filter options updated');
    //             })
    //             .fail(function(xhr, status, error) {
    //                 console.log('❌ Calendar navigation failed:', status, error);
    //                 window.location.href = finalURL; // Fallback to page reload
    //             })
    //             .always(function() {
    //                 $(ajaxTarget).removeClass('calendar-loading');
    //             });
    //     });
    // }
    
    // function handleCalendarLinkClicks() {
    //     $(document).on('click', '#wp-calendar a, #calendar-wrapper a, .wp-calendar-nav a', function(e) {
    //         const href = $(this).attr('href');
    //         
    //         // Check if it's a date archive URL
    //         if (href && href.match(/\/(?:[a-z-]+\/)?(\d{4})\/(\d{2})(\/\d{2})?\/?$/)) {
    //             e.preventDefault();
    //             
    //             console.log('=== CALENDAR LINK CLICKED ===', href);
    //             
    //             const currentFilters = getCurrentFilters();
    //             const ajaxTarget = $('.calendar-controls').data('ajax-target') || '#primary';
    //             
    //             // CRITICAL FIX: Extract clean path without ANY parameters
    //             // This prevents the duplicate parameter issue
    //             let cleanPath = href;
    //             if (href.includes('?')) {
    //                 cleanPath = href.split('?')[0];
    //                 console.log('🧹 Cleaned calendar link path:', href, '→', cleanPath);
    //             }
    //             
    //             // Ensure path ends with slash for consistency
    //             if (!cleanPath.endsWith('/')) {
    //                 cleanPath += '/';
    //             }
    //             
    //             // Build final URL with current filters (guaranteed no duplicates)
    //             const finalURL = buildCleanURL(cleanPath, currentFilters);
    //             
    //             console.log('📍 Calendar link navigation:', {
    //                 originalHref: href,
    //                 cleanPath: cleanPath,
    //                 currentFilters: currentFilters,
    //                 finalURL: finalURL
    //             });
    //             
    //             // Load new content
    //             $(ajaxTarget).addClass('calendar-loading');
    //             
    //             $.get(finalURL)
    //                 .done(function(data) {
    //                     const $data = $(data);
    //                     const newContent = $data.find(ajaxTarget).html();
    //                     
    //                     // Update target content
    //                     $(ajaxTarget).html(newContent);
    //                     
    //                     // Update browser URL
    //                     updateBrowserURL(finalURL);
    //                     
    //                     // Update calendar dropdowns to reflect new date
    //                     updateCalendarDropdownsFromURL(finalURL);
    //                     
    //                     // CRITICAL FIX: Pass the full $data to synchronization
    //                     // This allows filter dropdown options to be updated
    //                     synchronizeAllComponents(currentFilters, false, $data);
    //                     
    //                     console.log('✅ Calendar link navigation complete with filter options updated');
    //                 })
    //                 .fail(function(xhr, status, error) {
    //                     console.log('❌ Calendar link navigation failed:', status, error);
    //                     window.location.href = finalURL;
    //                 })
    //                 .always(function() {
    //                     $(ajaxTarget).removeClass('calendar-loading');
    //                 });
    //         }
    //     });
    // }
    
    // function handleTodayButtonClick() {
    //     $(document).on('click', '#calendar_today', function(e) {
    //         console.log('=== TODAY BUTTON CLICKED ===');
    //         
    //         const today = new Date();
    //         const currentMonth = String(today.getMonth() + 1).padStart(2, '0');
    //         const currentYear = today.getFullYear();
    //         const currentDay = String(today.getDate()).padStart(2, '0');
    //         
    //         // Update calendar dropdowns
    //         $('#calendar_month').val(currentMonth);
    //         $('#calendar_year').val(currentYear);
    //         
    //         // Trigger dropdown change handler
    //         $('#calendar_month').trigger('change');
    //     });
    // }
    
    // function handleClearFilters() {
    //     $(document).on('click', '.clear-filters', function() {
    //         console.log('=== CLEARING FILTERS ===');
    //         
    //         const container = $(this).closest('.taxonomy-filter-container');
    //         
    //         // Reset all dropdowns
    //         container.find('.taxonomy-filter-select').val('');
    //         
    //         // Trigger change on first dropdown to update everything
    //         container.find('.taxonomy-filter-select').first().trigger('change');
    //     });
    // }
    
    // function synchronizeAllComponents(filters, updateURL = true, $newContent = null) {
    //     console.log('🔄 Synchronizing all components...');
    //     
    //     // Update calendar with filtered results
    //     updateCalendarWithFilters(filters);
    //     
    //     // UPDATED: If we have new content, update filter dropdown options first
    //     if ($newContent && $newContent.length) {
    //         updateFilterDropdownOptions($newContent, true);
    //     } else {
    //         // Fallback: just update display state
    //         updateFilterDisplayState(filters);
    //     }
    //     
    //     // Update browser URL if requested
    //     if (updateURL) {
    //         const cleanURL = buildCleanURL(window.location.pathname, filters);
    //         updateBrowserURL(cleanURL);
    //     }
    //     
    //     console.log('✅ Component synchronization complete');
    // }
    
    */
});

/**
 * ORGANIZED STRUCTURE EXPLANATION:
 * 
 * 📋 CALENDAR-DRIVEN INTERACTIONS:
 * When user interacts with calendar (month/year dropdowns, monthly navigation, day links):
 * 1. handleCalendarChange() - Master function for all calendar interactions
 * 2. Updates AJAX target with new content for that date
 * 3. Updates browser URL with new date + current filters  
 * 4. Updates taxonomy filter OPTIONS to show only categories available for that date
 * 
 * 📋 FILTER-DRIVEN INTERACTIONS:
 * When user interacts with taxonomy filters:
 * 1. handleFilterChange() - Master function for all filter interactions
 * 2. Updates AJAX target with filtered content
 * 3. Updates browser URL with new filters + current date
 * 4. Updates calendar to highlight only days with filtered events
 * 
 * 📋 URL INITIALIZATION:
 * When user pastes URL directly in browser:
 * 1. initializeFromURL() - Parses URL parameters
 * 2. Sets up taxonomy filter selections from URL params
 * 3. Sets up calendar month/year from URL path
 * 4. Updates calendar to show filtered results if filters detected
 * 
 * ✅ BENEFITS OF THIS ORGANIZATION:
 * - Clear separation of concerns
 * - No duplicate logic between calendar and filter interactions
 * - Single master functions handle each interaction type
 * - Easy to debug and maintain
 * - URL initialization works perfectly with all components
 * - Clean flow: interaction → master function → updates 3 areas
 * 
 * 🔄 INTERACTION FLOW:
 * Calendar Change → handleCalendarChange() → Update: AJAX target, URL, filters
 * Filter Change → handleFilterChange() → Update: AJAX target, URL, calendar  
 * URL Paste → initializeFromURL() → Setup: filters, calendar, sync everything
 */
