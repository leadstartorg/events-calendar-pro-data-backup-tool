/**
 * COMPLETE ORGANIZED CALENDAR & FILTER SYSTEM WITH DAY-OF-WEEK AND TIME PERIOD FILTERING
 * Includes all original functions PLUS new advanced filtering capabilities
 */

jQuery(document).ready(function($) {
    console.log('🚀 Complete Advanced Organized Calendar & Filter System Loaded');
    
    // ========================================
    // CORE HELPER FUNCTIONS (shared by both calendar and filters)
    // ========================================
    
    /**
     * UPDATED: Get current filter values including new filter types
     */
    function getCurrentFilters() {
        const filters = {};
        
        // Existing taxonomy filters
        $('.taxonomy-filter-select').each(function() {
            const taxonomyName = $(this).data('taxonomy');
            const selectedValue = $(this).val();
            
            if (selectedValue && selectedValue.length > 0) {
                filters[taxonomyName] = Array.isArray(selectedValue) ? selectedValue : [selectedValue];
            }
        });
        
        // NEW: Day-of-week filters
        const selectedDays = [];
        $('.dayofweek-filter-checkbox:checked:not(:disabled)').each(function() {
            selectedDays.push(parseInt($(this).val()));
        });
        if (selectedDays.length > 0) {
            filters['dayofweek'] = selectedDays;
        }
        
        // NEW: Time period filters
        const selectedPeriods = [];
        $('.timeperiod-filter-checkbox:checked').each(function() {
            selectedPeriods.push($(this).val());
        });
        if (selectedPeriods.length > 0) {
            filters['timeperiod'] = selectedPeriods;
        }
        
        return filters;
    }

    /**
     * Check if current URL is a specific daily archive (YYYY/MM/DD)
     */
    function isSpecificDailyArchive() {
        const currentPath = window.location.pathname;
        // Match pattern: /events/YYYY/MM/DD/ (specific day)
        const dailyPattern = /\/events\/(\d{4})\/(\d{2})\/(\d{2})\/?$/;
        return dailyPattern.test(currentPath);
    }
    
    /**
     * Update day-of-week checkbox states based on URL context
     */
    function updateDayOfWeekCheckboxStates() {
        const isDaily = isSpecificDailyArchive();
        
        $('.dayofweek-filter-checkbox').each(function() {
            const $checkbox = $(this);
            const $label = $checkbox.closest('label, .dayofweek-checkbox');
            
            if (isDaily) {
                // Disable checkboxes for specific daily archives
                $checkbox.prop('disabled', true);
                $checkbox.prop('checked', false); // Clear any selections
                
                // Add visual styling to indicate disabled state
                $label.css({
                    'opacity': '0.5',
                    'cursor': 'not-allowed',
                    'color': '#999'
                });
                
                // Add tooltip
                $label.attr('title', 'Day of week filtering is not applicable when viewing a specific date');
                
            } else {
                // Enable checkboxes for other archive types
                $checkbox.prop('disabled', false);
                
                // Restore normal styling
                $label.css({
                    'opacity': '1',
                    'cursor': 'pointer',
                    'color': ''
                });
                
                // Remove tooltip
                $label.removeAttr('title');
            }
        });
        
        // Add or remove notice
        if (isDaily) {
            if ($('.dayofweek-disabled-notice').length === 0) {
                $('.dayofweek-filter-checkboxes').after(
                    '<div class="dayofweek-disabled-notice" style="font-size: 12px; color: #666; font-style: italic; margin-top: 5px;">' +
                    'Day of week filtering is not applicable when viewing a specific date.' +
                    '</div>'
                );
            }
        } else {
            $('.dayofweek-disabled-notice').remove();
        }
    }
    
    // Run on page load
    updateDayOfWeekCheckboxStates();
    
    // MODIFY YOUR EXISTING updateBrowserURL function to include this:
    // Add this line after window.history.replaceState():
    // updateDayOfWeekCheckboxStates();
    
    // MODIFY YOUR EXISTING getCurrentFilters function:
    // Change this line:
    // $('.dayofweek-filter-checkbox:checked').each(function() {
    // To this:
    // $('.dayofweek-filter-checkbox:checked:not(:disabled)').each(function() {
    
    // MODIFY YOUR EXISTING buildCleanURL function:
    // In the dayofweek section, add this condition:
    // if (filters[taxonomyName].length > 0 && !isSpecificDailyArchive()) {
    
    // Make these functions available globally for existing code
    window.updateDayOfWeekCheckboxStates = updateDayOfWeekCheckboxStates;
    window.isSpecificDailyArchive = isSpecificDailyArchive;
    
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
     * UPDATED: Build clean URL with new filter types
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
        url.searchParams.delete('dayofweek');
        url.searchParams.delete('timeperiod');
        
        // Add new filter parameters (pretty format)
        Object.keys(filters).forEach(taxonomyName => {
            if (taxonomyName === 'dayofweek') {
                // ADD this condition:
                if (filters[taxonomyName].length > 0 && !isSpecificDailyArchive()) {
                    url.searchParams.set('dayofweek', filters[taxonomyName].join(','));
                    console.log('📅 Added day-of-week parameter:', filters[taxonomyName].join(','));
                }
            } else if (taxonomyName === 'timeperiod') {
                // NEW: Add time period parameters
                if (filters[taxonomyName].length > 0) {
                    url.searchParams.set('timeperiod', filters[taxonomyName].join(','));
                    console.log('🕐 Added time period parameter:', filters[taxonomyName].join(','));
                }
            } else {
                // Existing taxonomy filters
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
            }
        });
        
        const finalURL = url.toString();
        console.log('🎯 Built clean URL with advanced filters:', basePath, '→', finalURL);
        return finalURL;
    }
    
    /**
     * NEW HELPER FUNCTION: Update the calendar HTML display from fetched content
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
            updateDayOfWeekCheckboxStates();
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
    
    /**
     * UPDATED: Update filter display state including new filter types
     */
    function updateFilterDisplayState(filters) {
        console.log('🔧 Updating filter display state with advanced filters:', filters);
        
        // Update taxonomy dropdowns
        $('.taxonomy-filter-select').each(function() {
            const $select = $(this);
            const taxonomyName = $select.data('taxonomy');
            
            if (filters[taxonomyName]) {
                $select.val(filters[taxonomyName]);
            } else {
                $select.val('');
            }
        });
        
        // NEW: Update day-of-week checkboxes
        $('.dayofweek-filter-checkbox').prop('checked', false);
        if (filters['dayofweek']) {
            filters['dayofweek'].forEach(function(day) {
                $('.dayofweek-filter-checkbox[value="' + day + '"]').prop('checked', true);
            });
            console.log('📅 Updated day-of-week checkboxes:', filters['dayofweek']);
        }
        
        // NEW: Update time period checkboxes
        $('.timeperiod-filter-checkbox').prop('checked', false);
        if (filters['timeperiod']) {
            filters['timeperiod'].forEach(function(period) {
                $('.timeperiod-filter-checkbox[value="' + period + '"]').prop('checked', true);
            });
            console.log('🕐 Updated time period checkboxes:', filters['timeperiod']);
        }
        
        console.log('✅ Advanced filter display state update complete');
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

                // 2. CRITICAL FIX: Update the calendar itself
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
    // UPDATED FILTER-DRIVEN INTERACTIONS  
    // Filter changes → update AJAX target, browser URL, calendar
    // ========================================
    
    /**
     * UPDATED: Handle filter changes including new filter types
     */
    function handleFilterChange(newFilters, triggerSource = 'unknown') {
        // Critical safety check
        if (typeof archiveEventsAjax === 'undefined') {
            console.error('❌ FATAL: archiveEventsAjax not defined - AJAX filtering will not work');
            return;
        }

        console.log(`=== ADVANCED FILTER CHANGE (${triggerSource}) ===`);
        console.log('New advanced filters:', newFilters);
        
        const container = $('.taxonomy-filter-container').first();
        const postType = container.data('post-type') || 'tribe_events';
        const ajaxTarget = container.data('ajax-target') || '#primary';
        
        // Show loading
        $('#filter-loading').show();
        $(ajaxTarget).addClass('loading');
        
        // NEW: Prepare advanced AJAX data
        const ajaxData = {
            action: 'filter_posts_by_taxonomy',
            current_page_url: window.location.pathname,
            post_type: postType,
            taxonomy_filters: {},
            nonce: archiveEventsAjax.nonce
        };
        
        // Separate different filter types
        Object.keys(newFilters).forEach(filterKey => {
            if (filterKey === 'dayofweek') {
                ajaxData.dayofweek_filters = newFilters[filterKey];
            } else if (filterKey === 'timeperiod') {
                ajaxData.timeperiod_filters = newFilters[filterKey];
            } else {
                // Regular taxonomy filters
                ajaxData.taxonomy_filters[filterKey] = newFilters[filterKey];
            }
        });
        
        console.log('📤 Sending advanced AJAX data:', ajaxData);
        
        // Send AJAX request
        $.ajax({
            url: archiveEventsAjax.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                console.log(`=== ADVANCED FILTER AJAX SUCCESS (${triggerSource}) ===`);
                
                if (response.success) {
                    // 1. Update AJAX target
                    $(ajaxTarget).html(response.data.html);
                    
                    // 2. Update browser URL
                    const cleanURL = buildCleanURL(window.location.pathname, newFilters);
                    updateBrowserURL(cleanURL);
                    
                    // 3. Update calendar with filtered results
                    updateCalendarWithFilters(newFilters);
                    
                    console.log(`✅ Advanced filter change complete (${triggerSource})`);
                } else {
                    console.error('AJAX returned error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error(`=== ADVANCED FILTER AJAX ERROR (${triggerSource}) ===`);
                console.error('Status:', status, 'Error:', error);
            },
            complete: function() {
                $('#filter-loading').hide();
                $(ajaxTarget).removeClass('loading');
            }
        });
    }
    
    /**
     * UPDATED: Update calendar with advanced filters
     */
    function updateCalendarWithFilters(filters) {
        if (!$('#wp-calendar, .wp-calendar-nav').length) {
            console.log('📅 No calendar found to update');
            return;
        }
        
        console.log('📅 Updating calendar with advanced filters:', filters);
        
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
                
                // Use the same calendar update method as navigation
                updateCalendarDisplayFromFetchedContent($data);
                console.log('✅ Calendar updated successfully with advanced filtered results');
            })
            .fail(function(xhr, status, error) {
                console.log('❌ Advanced calendar update failed:', status, error);
            });
    }
    
    /**
     * UPDATED: Initialize filter handlers including new filter types
     */
    function initFilterHandlers() {
        // Existing taxonomy filter dropdown changes
        $(document).on('change', '.taxonomy-filter-select', function(e) {
            // Skip if this is a display update (prevents loops)
            if (e.namespace === 'display') {
                return;
            }
            
            const newFilters = getCurrentFilters();
            handleFilterChange(newFilters, 'filter dropdown');
        });
        
        // NEW: Day-of-week checkbox changes
        $(document).on('change', '.dayofweek-filter-checkbox', function(e) {
            console.log('📅 Day-of-week checkbox changed');
            const newFilters = getCurrentFilters();
            handleFilterChange(newFilters, 'day-of-week checkbox');
        });
        
        // NEW: Time period checkbox changes
        $(document).on('change', '.timeperiod-filter-checkbox', function(e) {
            console.log('🕐 Time period checkbox changed');
            const newFilters = getCurrentFilters();
            handleFilterChange(newFilters, 'time period checkbox');
        });
    }
    
    /**
     * UPDATED: Clear filters handler including new filter types
     */
    function initClearFiltersHandler() {
        $(document).on('click', '.clear-filters', function() {
            const container = $(this).closest('.taxonomy-filter-container');
            
            // Reset all dropdowns
            container.find('.taxonomy-filter-select').val('');
            
            // NEW: Reset day-of-week checkboxes
            container.find('.dayofweek-filter-checkbox').prop('checked', false);
            
            // NEW: Reset time period checkboxes
            container.find('.timeperiod-filter-checkbox').prop('checked', false);
            
            // Trigger filter change with empty filters
            handleFilterChange({}, 'clear filters');
        });
    }
    
    // ========================================
    // UPDATED URL INITIALIZATION
    // Direct URL paste → set up taxonomy filters, calendar, and AJAX target
    // ========================================
    
    /**
     * UPDATED: Initialize everything from URL parameters including new filter types
     */
    function initializeFromURL() {
        console.log('🔧 Initializing advanced filters from URL...');
        
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
        
        // 2. NEW: Set up day-of-week filters from URL
        const dayofweekParam = urlParams.get('dayofweek');
        if (dayofweekParam) {
            const days = dayofweekParam.split(',').map(d => parseInt(d));
            days.forEach(function(day) {
                $('.dayofweek-filter-checkbox[value="' + day + '"]').prop('checked', true);
            });
            detectedFilters['dayofweek'] = days;
            hasFilters = true;
            console.log('🔧 Set day-of-week filter from URL:', days);
        }
        
        // 3. NEW: Set up time period filters from URL
        const timeperiodParam = urlParams.get('timeperiod');
        if (timeperiodParam) {
            const periods = timeperiodParam.split(',');
            periods.forEach(function(period) {
                $('.timeperiod-filter-checkbox[value="' + period + '"]').prop('checked', true);
            });
            detectedFilters['timeperiod'] = periods;
            hasFilters = true;
            console.log('🔧 Set time period filter from URL:', periods);
        }
        
        // 4. Set up calendar from URL
        updateCalendarDropdownsFromURL(window.location.href);
        
        // 5. If we have filters, update calendar to show filtered results
        if (hasFilters) {
            console.log('🔧 URL has advanced filters, updating calendar with filtered results...');
            updateCalendarWithFilters(detectedFilters);
        }
        
        console.log('✅ Advanced URL initialization complete');
        return hasFilters;
    }

    
    // ========================================
    // SYSTEM INITIALIZATION
    // ========================================
    
    function initializeSystem() {
        console.log('🚀 Initializing complete advanced filter & calendar system...');
        
        // Initialize calendar interactions
        initCalendarDropdownHandlers();
        initCalendarLinkHandlers(); 
        initTodayButtonHandler();
        
        // Initialize advanced filter interactions
        initFilterHandlers();
        initClearFiltersHandler();
        
        // Initialize from URL with advanced filters
        initializeFromURL();
        
        // Add advanced system status indicator
        if ($('.taxonomy-filter-container, .calendar-controls').length) {
            $('.taxonomy-filter-container, .calendar-controls').first().prepend(
                '<div class="advanced-system-status" style="background: #e8f5e8; padding: 5px; margin-bottom: 10px; font-size: 11px; border-radius: 3px;">' +
                '✅ <strong>Complete Advanced System Active</strong> - Calendar ↔ Taxonomy ↔ Day-of-Week ↔ Time Period ↔ URL' +
                '</div>'
            );
        }
        
        console.log('✅ Complete advanced system initialization complete');
        
        // Expose helper functions for debugging
        window.CompleteFilterCalendarSystem = {
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
            updateFilterDisplayState: updateFilterDisplayState,
            
            // URL initialization
            initializeFromURL: initializeFromURL
        };
    }
    
    // Start the complete system
    initializeSystem();
    
    /**
     * DEBUGGING HELPERS
     */
    
    // Debug current filter state
    $(document).on('click', '.debug-filters', function() {
        const currentFilters = getCurrentFilters();
        console.log('🔍 Current Complete Advanced Filters:', currentFilters);
        
        const debugInfo = {
            'Current Filters': currentFilters,
            'URL Parameters': Object.fromEntries(new URLSearchParams(window.location.search)),
            'Archive Context': window.location.pathname
        };
        
        alert('Complete Advanced Filter Debug Info (check console for details):\n' + JSON.stringify(debugInfo, null, 2));
    });
    
    // Add debug button if needed
    if (window.location.search.includes('debug=1')) {
        $('body').append('<button class="debug-filters" style="position:fixed;top:10px;right:10px;z-index:9999;background:#007cba;color:white;border:none;padding:10px;cursor:pointer;">Debug Complete Advanced Filters</button>');
    }
});

/**
 * COMPLETE INTERACTION FLOW DOCUMENTATION:
 * 
 * 📋 CALENDAR-DRIVEN INTERACTIONS:
 * Calendar Change → handleCalendarChange() → Update: AJAX target, URL, filters
 * 
 * 📋 ADVANCED FILTER-DRIVEN INTERACTIONS:
 * Filter Change → handleFilterChange() → Process: taxonomy + day-of-week + time period → Update: AJAX target, URL, calendar
 * 
 * 📋 ADVANCED URL INITIALIZATION:
 * URL Paste → initializeFromURL() → Parse: taxonomy + day-of-week + time period → Setup: filters, calendar, sync
 * 
 * ✅ COMPLETE BENEFITS:
 * - All original calendar functionality preserved
 * - Day-of-week filtering with checkboxes (Sunday-Saturday)
 * - Time period filtering (All Day, Morning, Afternoon, Evening, Night)
 * - Only available on date archives for events (not taxonomy archives)
 * - Clean URL parameter handling (dayofweek=1,2,3 & timeperiod=morning,evening)
 * - Seamless integration with existing calendar and taxonomy systems
 * - Proper AJAX communication with all filter types
 * - URL initialization handles all filter types consistently
 * - Complete function library with no missing dependencies
 */
