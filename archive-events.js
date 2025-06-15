/**
 * UPDATED UNIVERSAL ARCHIVE EVENTS JAVASCRIPT
 * Single $.get approach - updates BOTH events and filters from same response
 * Eliminates redundant AJAX calls
 */

jQuery(document).ready(function($) {
    console.log('🚀 Updated Universal Archive Events System Loaded');
    
    // ========================================
    // CORE HELPER FUNCTIONS
    // ========================================
    
    /**
     * Get current filter values including ALL filter types
     */
    function getCurrentFilters() {
        const filters = {};
        
        // Extract primary date range from URL parameters if present
        const urlParams = new URLSearchParams(window.location.search);
        const startDate = urlParams.get('start_date');
        const endDate = urlParams.get('end_date');
        if (startDate && endDate) {
            filters['primary_date_range'] = {
                start: startDate,
                end: endDate
            };
        }

        // Search filter
        const searchInput = $('#event-search-input');
        if (searchInput.length && searchInput.val().trim()) {
            filters['search'] = searchInput.val().trim();
        }
        
        // Taxonomy filters
        $('.taxonomy-filter-select').each(function() {
            const taxonomyName = $(this).data('taxonomy');
            const selectedValue = $(this).val();
            
            if (selectedValue && selectedValue.length > 0) {
                filters[taxonomyName] = Array.isArray(selectedValue) ? selectedValue : [selectedValue];
            }
        });
        
        // Day-of-week filters
        const selectedDays = [];
        $('.dayofweek-filter-checkbox:checked').each(function() {
            selectedDays.push(parseInt($(this).val()));
        });
        if (selectedDays.length > 0) {
            filters['dayofweek'] = selectedDays;
        }
        
        // Time period filters
        const selectedPeriods = [];
        $('.timeperiod-filter-checkbox:checked').each(function() {
            selectedPeriods.push($(this).val());
        });
        if (selectedPeriods.length > 0) {
            filters['timeperiod'] = selectedPeriods;
        }

        // Cost Range filter
        const minSlider = $('.range-input .min');
        const maxSlider = $('.range-input .max');
        
        if (minSlider.length && maxSlider.length) {
            const minVal = parseFloat(minSlider.val()) || 0;
            const maxVal = parseFloat(maxSlider.val()) || 5000;
            const maxPossible = parseFloat(maxSlider.attr('max')) || 5000;
            
            if (minVal > 0 || maxVal < maxPossible) {
                filters['cost_range'] = {
                    min: minVal,
                    max: maxVal
                };
            }
        }

        // Venue filters
        const selectedCountry = $('#venue-country-select').val();
        if (selectedCountry) {
            filters['venue_country'] = [selectedCountry];
        }

        const selectedStates = $('#venue-state-select').val();
        if (selectedStates && selectedStates.length > 0) {
            filters['venue_state'] = Array.isArray(selectedStates) ? selectedStates : [selectedStates];
        }
        
        const selectedCities = $('#venue-city-select').val();
        if (selectedCities && selectedCities.length > 0) {
            filters['venue_city'] = Array.isArray(selectedCities) ? selectedCities : [selectedCities];
        }

        const selectedAddresses = $('#venue-address-select').val();
        if (selectedAddresses && selectedAddresses.length > 0) {
            filters['venue_address'] = Array.isArray(selectedAddresses) ? selectedAddresses : [selectedAddresses];
        }

        // Organizer filter
        const selectedOrganizers = $('#organizer-select').val();
        if (selectedOrganizers && selectedOrganizers.length > 0 && selectedOrganizers[0] !== '') {
            filters['organizer'] = Array.isArray(selectedOrganizers) ? selectedOrganizers : [selectedOrganizers];
        }

        // Featured events filter - ADD THIS BLOCK
        if ($('#featured-filter-checkbox:checked').length > 0) {
            filters['featured'] = '1';
        }
        
        // Virtual events filter - ADD THIS BLOCK
        if ($('#virtual-filter-checkbox:checked').length > 0) {
            filters['virtual'] = '1';
        }
        
        return filters;
    }

    /**
     * Get current calendar context
     */
    function getCurrentCalendarContext() {
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
     * Build clean URL with ALL filter types
     */
    function buildCleanURL(basePath, filters) {
        let cleanBasePath = basePath;

        // Remove query parameters
        if (basePath.includes('?')) {
            cleanBasePath = basePath.split('?')[0];
        }
        
        // Remove pagination from path
        cleanBasePath = cleanBasePath.replace(/\/page\/\d+\/?$/, '/');

        // Ensure trailing slash
        if (!cleanBasePath.endsWith('/')) {
            cleanBasePath += '/';
        }
        
        const url = new URL(cleanBasePath, window.location.origin);
        
        // Clear existing filter parameters
        //const filterParams = ['category', 'keyword', 'tribe_events_cat', 'post_tag', 'dayofweek', 'timeperiod', 'cost', 'country', 'state', 'city', 'address', 'organizer', 'start_date', 'end_date'];
        const filterParams = [
        'category', 'keyword', 'tribe_events_cat', 'post_tag', 'dayofweek', 
        'timeperiod', 'cost', 'country', 'state', 'city', 'address', 
        'organizer', 'start_date', 'end_date', 'featured', 'virtual' // ← ADD featured, virtual
        ];

        filterParams.forEach(param => url.searchParams.delete(param));

        // Add primary date range if exists
        if (filters['primary_date_range']) {
            url.searchParams.set('start_date', filters['primary_date_range'].start);
            url.searchParams.set('end_date', filters['primary_date_range'].end);
        }

        // Search filter
        if (filters['search']) {
            url.searchParams.set('search', filters['search']);
        }
        
        // Add new filter parameters
        Object.keys(filters).forEach(filterKey => {
            if (filterKey === 'dayofweek') {
                if (filters[filterKey].length > 0) {
                    url.searchParams.set('dayofweek', filters[filterKey].join(','));
                }
            } else if (filterKey === 'timeperiod') {
                if (filters[filterKey].length > 0) {
                    url.searchParams.set('timeperiod', filters[filterKey].join(','));
                }
            } else if (filterKey === 'featured') {
                // NEW: Featured filter
                if (filters[filterKey] === '1') {
                    url.searchParams.set('featured', '1');
                }
            } else if (filterKey === 'virtual') {
                // NEW: Virtual filter
                if (filters[filterKey] === '1') {
                    url.searchParams.set('virtual', '1');
                }
            } else if (filterKey === 'cost_range') {
                if (filters[filterKey]) {
                    const min = filters[filterKey].min || 0;
                    const max = filters[filterKey].max;
                    
                    if (min > 0) {
                        url.searchParams.set('cost', `${min},${max}`);
                    } else {
                        url.searchParams.set('cost', max.toString());
                    }
                }
            } else if (filterKey === 'venue_country') {
                if (filters[filterKey] && filters[filterKey].length > 0) {
                    url.searchParams.set('country', filters[filterKey][0]);
                }
            } else if (filterKey === 'venue_state') {
                if (filters[filterKey] && filters[filterKey].length > 0) {
                    url.searchParams.set('state', filters[filterKey].join(','));
                }
            } else if (filterKey === 'venue_city') {
                if (filters[filterKey] && filters[filterKey].length > 0) {
                    url.searchParams.set('city', filters[filterKey].join(','));
                }
            } else if (filterKey === 'venue_address') {
                if (filters[filterKey] && filters[filterKey].length > 0) {
                    url.searchParams.set('address', filters[filterKey].join(','));
                }
            } else if (filterKey === 'organizer') {
                if (filters[filterKey] && filters[filterKey].length > 0) {
                    url.searchParams.set('organizer', filters[filterKey].join(','));
                }
            } else if (filterKey !== 'primary_date_range') {
                const termIds = filters[filterKey];
                
                if (termIds && termIds.length > 0) {
                    let prettyParam = '';
                    
                    if (filterKey === 'tribe_events_cat' || filterKey === 'category') {
                        prettyParam = 'category';
                    } else if (filterKey === 'post_tag') {
                        prettyParam = 'keyword';
                    }
                    
                    if (prettyParam) {
                        url.searchParams.set(prettyParam, termIds.join(','));
                    }
                }
            }
        });
        
        return url.toString();
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

    // ========================================
    // LOADING STATE MANAGEMENT
    // ========================================
    
    /**
     * Show loading state for AJAX operations
     */
    function showLoadingState(targetSelector, loadingText = 'Loading...') {
        const $target = $(targetSelector);
        
        $target.addClass('ajax-loading');
        
        if ($target.find('.ajax-loading-overlay').length === 0) {
            const isFilterContainer = $target.hasClass('event-filter-container') || 
                                    $target.hasClass('filter-sidebar') || 
                                    targetSelector.includes('filter');
            
            const loadingIcon = isFilterContainer ? 
                '<i class="fa fa-filter" style="font-size: 24px; color: #3498db; margin-bottom: 10px;"></i>' :
                '<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem; border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; animation: spin 2s linear infinite;"></div>';
            
            const loadingHTML = `
                <div class="ajax-loading-overlay" style="
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255, 255, 255, 0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                    min-height: 200px;
                    backdrop-filter: blur(2px);
                ">
                    <div style="text-align: center; padding: 20px;">
                        ${loadingIcon}
                        <div style="margin-top: 15px; font-weight: 500; color: #666; font-size: 14px;">
                            ${loadingText}
                        </div>
                        ${isFilterContainer ? '<div style="font-size: 12px; color: #999; margin-top: 5px;">Updating filter options and counts...</div>' : ''}
                    </div>
                </div>
                <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                </style>
            `;
            
            if ($target.css('position') === 'static') {
                $target.css('position', 'relative');
            }
            
            $target.append(loadingHTML);
        }
        
        console.log('🔄 Loading state shown for:', targetSelector);
    }

    /**
     * Hide loading state
     */
    function hideLoadingState(targetSelector) {
        const $target = $(targetSelector);
        
        $target.removeClass('ajax-loading');
        $target.find('.ajax-loading-overlay').remove();
        $('#filter-loading').hide();
        
        console.log('✅ Loading state hidden for:', targetSelector);
    }

    /**
     * Show error message to user
     */
    function showErrorMessage(message, isTemporary = true) {
        $('.filter-error-message').remove();
        
        const errorHTML = `
            <div class="filter-error-message alert alert-danger" style="
                background-color: #f8d7da;
                color: #721c24;
                padding: 15px;
                margin: 15px 0;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                position: relative;
            ">
                <strong>Filter Error:</strong> ${message}
                <button type="button" class="close" style="
                    position: absolute;
                    top: 10px;
                    right: 15px;
                    background: none;
                    border: none;
                    font-size: 20px;
                    cursor: pointer;
                ">&times;</button>
            </div>
        `;
        
        $('.event-filter-container, .filter-sidebar').first().after(errorHTML);
        
        $('.filter-error-message .close').on('click', function() {
            $(this).closest('.filter-error-message').fadeOut();
        });
        
        if (isTemporary) {
            setTimeout(() => {
                $('.filter-error-message').fadeOut();
            }, 5000);
        }
    }

    /**
     * Show success message to user
     */
    function showSuccessMessage(message) {
        $('.filter-success-message').remove();
        
        const successHTML = `
            <div class="filter-success-message alert alert-success" style="
                background-color: #d4edda;
                color: #155724;
                padding: 10px 15px;
                margin: 10px 0;
                border: 1px solid #c3e6cb;
                border-radius: 4px;
                font-size: 12px;
            ">
                ${message}
            </div>
        `;
        
        $('.event-filter-container, .filter-sidebar').first().prepend(successHTML);
        
        setTimeout(() => {
            $('.filter-success-message').fadeOut();
        }, 2000);
    }

    // ========================================
    // CONTENT EXTRACTION FROM RESPONSE
    // ========================================
    
    /**
     * Extract content from full page response
     */
    function extractContentFromResponse(responseHTML, targetSelector) {
        try {
            const $response = $(responseHTML);
            const $targetContent = $response.find(targetSelector);
            
            if ($targetContent.length > 0) {
                console.log('✅ Successfully extracted content for:', targetSelector);
                return $targetContent.html();
            } else {
                console.warn('⚠️ Target selector not found in response:', targetSelector);
                return null;
            }
        } catch (error) {
            console.error('❌ Error extracting content:', error);
            return null;
        }
    }

    /**
     * Extract and update calendar from response
     */
    function extractAndUpdateCalendar(responseHTML) {
        try {
            const $response = $(responseHTML);
            
            // Extract calendar table
            const $newCalendar = $response.find('#wp-calendar');
            if ($newCalendar.length > 0) {
                $('#wp-calendar').replaceWith($newCalendar);
                console.log('✅ Calendar table updated from response');
            }
            
            // Extract calendar navigation
            const $newCalendarNav = $response.find('.wp-calendar-nav');
            if ($newCalendarNav.length > 0) {
                $('.wp-calendar-nav').replaceWith($newCalendarNav);
                console.log('✅ Calendar navigation updated from response');
            }
            
            // Update calendar dropdowns from URL if present
            const currentURL = window.location.pathname;
            updateCalendarDropdownsFromURL(currentURL);
            
            return true;
        } catch (error) {
            console.error('❌ Error updating calendar from response:', error);
            return false;
        }
    }

    // ========================================
    // FILTER VALUE PRESERVATION
    // ========================================

    /**
     * Store current filter values before replacing HTML
     */
    function storeCurrentFilterValues() {
        const values = {};
        
        // Store search value
        const searchInput = $('#event-search-input');
        if (searchInput.length && searchInput.val().trim()) {
            values['search'] = searchInput.val().trim();
        }

        // Store taxonomy filter values
        $('.taxonomy-filter-select').each(function() {
            const $select = $(this);
            const taxonomyName = $select.data('taxonomy');
            const selectedValue = $select.val();
            
            if (selectedValue && selectedValue.length > 0) {
                values[taxonomyName] = selectedValue;
            }
        });
        
        // Store day-of-week values
        const selectedDays = [];
        $('.dayofweek-filter-checkbox:checked').each(function() {
            selectedDays.push($(this).val());
        });
        if (selectedDays.length > 0) {
            values['dayofweek'] = selectedDays;
        }
        
        // Store time period values
        const selectedPeriods = [];
        $('.timeperiod-filter-checkbox:checked').each(function() {
            selectedPeriods.push($(this).val());
        });
        if (selectedPeriods.length > 0) {
            values['timeperiod'] = selectedPeriods;
        }

        if ($('#featured-filter-checkbox:checked').length > 0) {
            values['featured'] = '1';
        }
    
        if ($('#virtual-filter-checkbox:checked').length > 0) {
            values['virtual'] = '1';
        }
        
        // Store cost range values
        const minSlider = $('.range-input .min');
        const maxSlider = $('.range-input .max');
        if (minSlider.length && maxSlider.length) {
            values['cost_range'] = {
                min: minSlider.val(),
                max: maxSlider.val()
            };
        }
        
        // Store venue values
        values['venue_country'] = $('#venue-country-select').val();
        values['venue_state'] = $('#venue-state-select').val();
        values['venue_city'] = $('#venue-city-select').val();
        values['venue_address'] = $('#venue-address-select').val();
        
        // Store organizer values
        values['organizer'] = $('#organizer-select').val();
        
        return values;
    }

    /**
     * Restore filter values after replacing HTML
     */
    function restoreFilterValues(values) {
        if (!values) return;

        // Restore search input
        if (values['search']) {
            $('#event-search-input').val(values['search']);
        }
        
        // Restore taxonomy filters
        $('.taxonomy-filter-select').each(function() {
            const $select = $(this);
            const taxonomyName = $select.data('taxonomy');
            
            if (values[taxonomyName]) {
                $select.val(values[taxonomyName]);
            }
        });
        
        // Restore day-of-week checkboxes
        if (values['dayofweek']) {
            values['dayofweek'].forEach(function(day) {
                $('.dayofweek-filter-checkbox[value="' + day + '"]').prop('checked', true);
            });
        }
        
        // Restore time period checkboxes
        if (values['timeperiod']) {
            values['timeperiod'].forEach(function(period) {
                $('.timeperiod-filter-checkbox[value="' + period + '"]').prop('checked', true);
            });
        }

        if (values['featured'] === '1') {
            $('#featured-filter-checkbox').prop('checked', true);
        }
    
        if (values['virtual'] === '1') {
            $('#virtual-filter-checkbox').prop('checked', true);
        }
        
        // Restore cost range
        if (values['cost_range']) {
            $('.range-input .min').val(values['cost_range'].min);
            $('.range-input .max').val(values['cost_range'].max);
            $('#min-price-input').val(values['cost_range'].min);
            $('#max-price-input').val(values['cost_range'].max);
            
            // Update visual slider
            const rangeSelected = $('.range-selected');
            if (rangeSelected.length) {
                const minVal = parseFloat(values['cost_range'].min);
                const maxVal = parseFloat(values['cost_range'].max);
                const maxRange = parseFloat($('.range-input .max').attr('max')) || 5000;
                rangeSelected.css('left', ((minVal / maxRange) * 100) + '%');
                rangeSelected.css('right', (100 - (maxVal / maxRange) * 100) + '%');
            }
        }
        
        // Restore venue filters
        if (values['venue_country']) {
            $('#venue-country-select').val(values['venue_country']);
            $('.state-filter-group, .address-filter-group').show();
        }
        
        if (values['venue_state']) {
            $('#venue-state-select').val(values['venue_state']);
            $('.city-filter-group').show();
        }
        
        if (values['venue_city']) {
            $('#venue-city-select').val(values['venue_city']);
        }
        
        if (values['venue_address']) {
            $('#venue-address-select').val(values['venue_address']);
        }
        
        // Restore organizer filter
        if (values['organizer']) {
            $('#organizer-select').val(values['organizer']);
        }
    }

    // ========================================
    // MAIN FILTER UPDATE FUNCTION
    // ========================================

    /**
     * UPDATED: Handle filter changes - Updates BOTH events AND filters from single $.get
     */
    function handleFilterChange(newFilters, triggerSource = 'unknown') {
        console.log(`=== UNIVERSAL FILTER CHANGE (${triggerSource}) ===`);
        console.log('New filters:', newFilters);
        
        // Get target containers
        const eventsContainer = $('.event-filter-container').first();
        const eventsTarget = eventsContainer.data('ajax-target') || '#events-container';
        const filtersTarget = '.filter-sidebar, .event-filter-container'; // Update filters too
        
        // Build clean URL with filters
        const currentURL = window.location.pathname;
        const filteredURL = buildCleanURL(currentURL, newFilters);
        
        console.log('🔗 Fetching filtered content from:', filteredURL);
        
        // Store current filter values before updating
        const currentFilterValues = storeCurrentFilterValues();
        
        // Show loading state for BOTH events and filters
        showLoadingState(eventsTarget, 'Updating events...');
        showLoadingState(filtersTarget, 'Updating filters...');
        
        // Fetch content using $.get
        $.get(filteredURL)
            .done(function(responseHTML) {
                console.log('✅ Successfully fetched filtered page');
                
                // ========================================
                // EXTRACT AND UPDATE EVENTS CONTENT
                // ========================================
                const newEventsContent = extractContentFromResponse(responseHTML, eventsTarget);
                
                if (newEventsContent) {
                    $(eventsTarget).html(newEventsContent);
                    console.log('✅ Events content updated');
                } else {
                    throw new Error('Could not extract events content from response');
                }
                
                // ========================================
                // EXTRACT AND UPDATE FILTER CONTENT
                // ========================================
                
                // Try to extract updated filter content from the response
                const $response = $(responseHTML);
                
                // Update individual filter components with their updated counts
                // 1. Update category dropdown
                const $newCategoryFilter = $response.find('#category-filter');
                if ($newCategoryFilter.length > 0) {
                    const currentCategoryValue = $('#category-filter').val();
                    $('#category-filter').replaceWith($newCategoryFilter);
                    // Restore selection if still valid
                    if (currentCategoryValue && $('#category-filter option[value="' + currentCategoryValue + '"]').length > 0) {
                        $('#category-filter').val(currentCategoryValue);
                    }
                    console.log('✅ Category filter updated with new counts');
                }
                
                // 2. Update tag dropdown
                const $newTagFilter = $response.find('#tag-filter');
                if ($newTagFilter.length > 0) {
                    const currentTagValue = $('#tag-filter').val();
                    $('#tag-filter').replaceWith($newTagFilter);
                    if (currentTagValue && $('#tag-filter option[value="' + currentTagValue + '"]').length > 0) {
                        $('#tag-filter').val(currentTagValue);
                    }
                    console.log('✅ Tag filter updated with new counts');
                }
                
                // 3. Update organizer filter
                const $newOrganizerSelect = $response.find('#organizer-select');
                if ($newOrganizerSelect.length > 0) {
                    const currentOrganizerValue = $('#organizer-select').val();
                    $('#organizer-select').replaceWith($newOrganizerSelect);
                    if (currentOrganizerValue) {
                        $('#organizer-select').val(currentOrganizerValue);
                    }
                    console.log('✅ Organizer filter updated with new counts');
                }
                
                // 4. Update venue filters
                const $newVenueCountry = $response.find('#venue-country-select');
                if ($newVenueCountry.length > 0) {
                    const currentCountryValue = $('#venue-country-select').val();
                    $('#venue-country-select').replaceWith($newVenueCountry);
                    if (currentCountryValue) {
                        $('#venue-country-select').val(currentCountryValue);
                    }
                    console.log('✅ Venue country filter updated with new counts');
                }
                
                const $newVenueState = $response.find('#venue-state-select');
                if ($newVenueState.length > 0) {
                    const currentStateValue = $('#venue-state-select').val();
                    $('#venue-state-select').replaceWith($newVenueState);
                    if (currentStateValue) {
                        $('#venue-state-select').val(currentStateValue);
                    }
                    console.log('✅ Venue state filter updated with new counts');
                }
                
                const $newVenueCity = $response.find('#venue-city-select');
                if ($newVenueCity.length > 0) {
                    const currentCityValue = $('#venue-city-select').val();
                    $('#venue-city-select').replaceWith($newVenueCity);
                    if (currentCityValue) {
                        $('#venue-city-select').val(currentCityValue);
                    }
                    console.log('✅ Venue city filter updated with new counts');
                }
                
                const $newVenueAddress = $response.find('#venue-address-select');
                if ($newVenueAddress.length > 0) {
                    const currentAddressValue = $('#venue-address-select').val();
                    $('#venue-address-select').replaceWith($newVenueAddress);
                    if (currentAddressValue) {
                        $('#venue-address-select').val(currentAddressValue);
                    }
                    console.log('✅ Venue address filter updated with new counts');
                }
                
                // ========================================
                // RESTORE FILTER SELECTIONS
                // ========================================
                
                // Restore the user's current filter selections after updating HTML
                restoreFilterValues(currentFilterValues);
                
                // ========================================
                // UPDATE CALENDAR AND URL
                // ========================================
                
                // Update calendar with filtered results
                extractAndUpdateCalendar(responseHTML);
                
                // Update browser URL
                updateBrowserURL(filteredURL);
                
                // Show success message
                showSuccessMessage('Filters updated successfully');
                
                console.log(`✅ Filter change complete - BOTH events and filters updated from single $.get (${triggerSource})`);
                
            })
            .fail(function(xhr, status, error) {
                console.error(`❌ Filter change failed (${triggerSource}):`, status, error);
                
                // Show error to user
                showErrorMessage('Unable to update filters. Reloading page...');
                
                // Fallback to page reload after a short delay
                setTimeout(() => {
                    window.location.href = filteredURL;
                }, 1500);
            })
            .always(function() {
                // Always hide loading state for both targets
                hideLoadingState(eventsTarget);
                hideLoadingState(filtersTarget);
            });
    }

    /**
     * Handle calendar changes using $.get approach - FIXED to preserve ALL filters
     */
    function handleCalendarChange(newURL, triggerSource = 'unknown') {
        console.log(`=== CALENDAR CHANGE VIA $.get (${triggerSource}) ===`);
        console.log('Navigating to:', newURL);
        
        // Get ALL current filters (not just taxonomy)
        const currentFilters = getCurrentFilters();
        const ajaxTarget = $('.calendar-controls').data('ajax-target') || '#events-container';
        const filtersTarget = '.filter-sidebar, .event-filter-container';
        
        // Build final URL with ALL current filters preserved
        const finalURL = buildCleanURL(newURL, currentFilters);
        
        console.log('📅 Calendar navigation with ALL filters preserved:', {
            triggerSource: triggerSource,
            newURL: newURL,
            currentFilters: currentFilters,
            finalURL: finalURL
        });
        
        // Store current filter values before updating
        const currentFilterValues = storeCurrentFilterValues();
        
        // Show loading state for BOTH events and filters
        showLoadingState(ajaxTarget, 'Loading calendar...');
        showLoadingState(filtersTarget, 'Updating filters...');
        
        // Fetch new content
        $.get(finalURL)
            .done(function(responseHTML) {
                console.log('✅ Successfully fetched calendar page');
                
                // Extract and update main content
                const newContent = extractContentFromResponse(responseHTML, ajaxTarget);
                
                if (newContent) {
                    // Update main content
                    $(ajaxTarget).html(newContent);
                    
                    // FIXED: Also update filter counts/options from calendar navigation
                    const $response = $(responseHTML);
                    
                    // Update category filter
                    const $newCategoryFilter = $response.find('#category-filter');
                    if ($newCategoryFilter.length > 0) {
                        const currentCategoryValue = $('#category-filter').val();
                        $('#category-filter').replaceWith($newCategoryFilter);
                        if (currentCategoryValue) {
                            $('#category-filter').val(currentCategoryValue);
                        }
                    }
                    
                    // Update tag filter
                    const $newTagFilter = $response.find('#tag-filter');
                    if ($newTagFilter.length > 0) {
                        const currentTagValue = $('#tag-filter').val();
                        $('#tag-filter').replaceWith($newTagFilter);
                        if (currentTagValue) {
                            $('#tag-filter').val(currentTagValue);
                        }
                    }
                    
                    // Update organizer filter
                    const $newOrganizerSelect = $response.find('#organizer-select');
                    if ($newOrganizerSelect.length > 0) {
                        const currentOrganizerValue = $('#organizer-select').val();
                        $('#organizer-select').replaceWith($newOrganizerSelect);
                        if (currentOrganizerValue) {
                            $('#organizer-select').val(currentOrganizerValue);
                        }
                    }
                    
                    // Update venue filters
                    const venueSelectors = ['#venue-country-select', '#venue-state-select', '#venue-city-select', '#venue-address-select'];
                    venueSelectors.forEach(selector => {
                        const $newVenueFilter = $response.find(selector);
                        if ($newVenueFilter.length > 0) {
                            const currentValue = $(selector).val();
                            $(selector).replaceWith($newVenueFilter);
                            if (currentValue) {
                                $(selector).val(currentValue);
                            }
                        }
                    });
                    
                    // Restore ALL filter values
                    restoreFilterValues(currentFilterValues);
                    
                    // Update calendar display
                    extractAndUpdateCalendar(responseHTML);
                    
                    // Update browser URL
                    updateBrowserURL(finalURL);
                    
                    // Update calendar dropdowns to reflect new date
                    updateCalendarDropdownsFromURL(finalURL);
                    
                    console.log(`✅ Calendar change complete with ALL filters preserved (${triggerSource})`);
                } else {
                    throw new Error('Could not extract content from response');
                }
            })
            .fail(function(xhr, status, error) {
                console.error(`❌ Calendar change failed (${triggerSource}):`, status, error);
                
                // Show error to user
                showErrorMessage('Unable to load calendar. Reloading page...');
                
                // Fallback to page reload
                setTimeout(() => {
                    window.location.href = finalURL;
                }, 1500);
            })
            .always(function() {
                // Always hide loading state for BOTH targets
                hideLoadingState(ajaxTarget);
                hideLoadingState(filtersTarget);
            });
    }

    // ========================================
    // VENUE DEPENDENCY MANAGEMENT 
    // ========================================

    /**
     * Update dependent venue options - Keep using AJAX for this
     */
    function updateDependentVenueOptions(filterType, selectedCountry, selectedState) {
        if (typeof archiveEventsAjax === 'undefined') {
            console.error('❌ archiveEventsAjax not defined - cannot update venue options');
            return;
        }

        console.log(`🔄 Updating ${filterType} options via AJAX...`);
        
        $.ajax({
            url: archiveEventsAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_dependent_venue_options',
                nonce: archiveEventsAjax.nonce,
                selected_country: selectedCountry,
                selected_state: selectedState,
                filter_type: filterType
            },
            success: function(response) {
                if (response.success && response.data[filterType]) {
                    const selectId = `#venue-${filterType.slice(0, -1)}-select`;
                    const $select = $(selectId);
                    
                    // Clear existing options except first
                    $select.find('option:not(:first)').remove();
                    
                    // Add new options
                    Object.entries(response.data[filterType]).forEach(([value, count]) => {
                        const countDisplay = count > 0 ? ` (${count})` : '';
                        $select.append(`<option value="${value}">${value}${countDisplay}</option>`);
                    });
                    
                    console.log(`✅ Updated ${filterType} options successfully`);
                }
            },
            error: function(xhr, status, error) {
                console.error(`❌ Error updating ${filterType}:`, error);
            }
        });
    }

    /**
     * Initialize venue dependency handlers - MOVED FROM PHP
     */
    function initVenueDependencyHandlers() {
        // Add CSS for venue filters (moved from PHP)
        if (!$('#venue-filter-styles').length) {
            $('head').append(`
                <style id="venue-filter-styles">
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
                </style>
            `);
        }
        
        // Country change handler
        $(document).on('change', '#venue-country-select', function() {
            const selectedCountry = $(this).val();
            const $stateSelect = $('#venue-state-select');
            const $citySelect = $('#venue-city-select');
            const $addressSelect = $('#venue-address-select');
            
            if (selectedCountry) {
                // Show dependent filter groups
                $('.state-filter-group').show();
                $('.address-filter-group').show();
                
                // Clear dependent selections and show loading
                $stateSelect.html('<option value="">Loading states...</option>');
                $citySelect.html('');
                $addressSelect.html('<option value="">Loading venues...</option>');
                $('.city-filter-group').hide();
                
                // Update states and addresses via AJAX
                updateDependentVenueOptions('states', selectedCountry, '');
                updateDependentVenueOptions('addresses', selectedCountry, '');
            } else {
                // Hide dependent filters
                $('.state-filter-group').hide();
                $('.city-filter-group').hide();
                $('.address-filter-group').hide();
            }
            
            // Trigger main filter change (with small delay for AJAX)
            setTimeout(() => {
                const newFilters = getCurrentFilters();
                handleFilterChange(newFilters, 'venue country');
            }, 100);
        });
        
        // State change handler
        $(document).on('change', '#venue-state-select', function() {
            const selectedCountry = $('#venue-country-select').val();
            const selectedStates = $(this).val() || [];
            const $citySelect = $('#venue-city-select');
            const $addressSelect = $('#venue-address-select');
            
            if (selectedStates.length > 0) {
                $('.city-filter-group').show();
                
                // Update cities and addresses
                $citySelect.html('<option value="">Loading cities...</option>');
                const primaryState = Array.isArray(selectedStates) ? selectedStates[0] : selectedStates;
                updateDependentVenueOptions('cities', selectedCountry, primaryState);
                updateDependentVenueOptions('addresses', selectedCountry, primaryState);
            } else {
                $('.city-filter-group').hide();
                updateDependentVenueOptions('addresses', selectedCountry, '');
            }
            
            // Clear city selection
            $citySelect.val('');
            
            // Trigger main filter change
            setTimeout(() => {
                const newFilters = getCurrentFilters();
                handleFilterChange(newFilters, 'venue state');
            }, 100);
        });
        
        // City change handler
        $(document).on('change', '#venue-city-select', function() {
            const newFilters = getCurrentFilters();
            handleFilterChange(newFilters, 'venue city');
        });
        
        // Address change handler  
        $(document).on('change', '#venue-address-select', function() {
            const newFilters = getCurrentFilters();
            handleFilterChange(newFilters, 'venue address');
        });
        
        console.log('✅ Venue dependency handlers initialized');
    }

    // ========================================
    // EVENT HANDLERS
    // ========================================

    /**
     * Initialize filter handlers with debouncing
     */
    function initFilterHandlersUniversal() {
        let filterTimeout;
        let isUpdating = false;
        
        // Enhanced debounced filter change function
        function debouncedFilterChange(triggerSource) {
            if (isUpdating) {
                console.log('⏳ Filter update in progress, ignoring rapid change');
                return;
            }
            
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(() => {
                isUpdating = true;
                const newFilters = getCurrentFilters();
                
                handleFilterChange(newFilters, triggerSource);
                
                // Reset updating flag after completion
                setTimeout(() => {
                    isUpdating = false;
                }, 500);
                
            }, 500);
        }
        
        // Apply to all filter handlers
        $(document).on('change', '.taxonomy-filter-select', function(e) {
            if (e.namespace === 'display') return;
            console.log('🏷️ Taxonomy filter changed');
            debouncedFilterChange('taxonomy dropdown');
        });
        
        $(document).on('change', '.dayofweek-filter-checkbox', function(e) {
            console.log('📅 Day of week filter changed');
            debouncedFilterChange('day-of-week checkbox');
        });
        
        $(document).on('change', '.timeperiod-filter-checkbox', function(e) {
            console.log('⏰ Time period filter changed');
            debouncedFilterChange('time period checkbox');
        });

        $(document).on('change', '.range-input input', function(e) {
            console.log('💰 Cost filter changed');
            debouncedFilterChange('cost range slider');
        });

        $(document).on('change', '#organizer-select', function(e) {
            console.log('👤 Organizer filter changed');
            debouncedFilterChange('organizer filter');
        });
        
        // NOTE: Venue filter handlers moved to initVenueDependencyHandlers()
    }

    /**
     * Initialize calendar handlers
     */
    function initCalendarHandlersUniversal() {
        // Calendar dropdown changes
        $(document).on('change', '#calendar_month, #calendar_year', function(e) {
            const calendarContext = getCurrentCalendarContext();
            
            let newURL = '/events/' + calendarContext.year + '/';
            if (calendarContext.month) {
                newURL += calendarContext.month.padStart(2, '0') + '/';
            }
            
            handleCalendarChange(newURL, 'calendar dropdown');
        });

        // Calendar link clicks
        $(document).on('click', '#wp-calendar a, .wp-calendar-nav a', function(e) {
            const href = $(this).attr('href');
            
            if (href && href.match(/\/events\/(\d{4})\/(\d{2})(\/\d{2})?\/?$/)) {
                e.preventDefault();
                
                let cleanPath = href;
                if (href.includes('?')) {
                    cleanPath = href.split('?')[0];
                }
                
                if (!cleanPath.endsWith('/')) {
                    cleanPath += '/';
                }
                
                handleCalendarChange(cleanPath, 'calendar link');
            }
        });

        // Today button
        $(document).on('click', '#calendar_today', function(e) {
            const today = new Date();
            const currentMonth = String(today.getMonth() + 1).padStart(2, '0');
            const currentYear = today.getFullYear();
            const currentDay = String(today.getDate()).padStart(2, '0');
            
            $('#calendar_month').val(currentMonth);
            $('#calendar_year').val(currentYear);
            
            const todayURL = `/events/${currentYear}/${currentMonth}/${currentDay}/`;
            handleCalendarChange(todayURL, 'today button');
        });
    }

    /**
     * Clear filters handler
     */
    function initClearFiltersHandler() {
        $(document).on('click', '.clear-filters', function() {
            const container = $(this).closest('.event-filter-container, .filter-sidebar');

            // Reset search input
            $('#event-search-input').val('').trigger('change');
            
            // Reset all filter elements
            container.find('.taxonomy-filter-select').val('');
            container.find('.dayofweek-filter-checkbox').prop('checked', false);
            container.find('.timeperiod-filter-checkbox').prop('checked', false);

            $('#featured-filter-checkbox').prop('checked', false);
            $('#virtual-filter-checkbox').prop('checked', false);
            
            // Reset cost sliders
            const minSlider = container.find('.range-input .min');
            const maxSlider = container.find('.range-input .max');
            if (minSlider.length && maxSlider.length) {
                const minDefault = minSlider.attr('min') || '0';
                const maxDefault = maxSlider.attr('max') || '5000';
                minSlider.val(minDefault);
                maxSlider.val(maxDefault);
                container.find('#min-price-input').val(minDefault);
                container.find('#max-price-input').val(maxDefault);
                
                // Update visual slider
                const rangeSelected = container.find('.range-selected');
                if (rangeSelected.length) {
                    rangeSelected.css('left', '0%');
                    rangeSelected.css('right', '0%');
                }
            }
            
            // Reset venue filters
            $('#venue-country-select').val('').trigger('change');
            $('#venue-state-select').val('').trigger('change');
            $('#venue-city-select').val('').trigger('change');
            $('#venue-address-select').val('').trigger('change');
            
            // Reset organizer filter
            $('#organizer-select').val('').trigger('change');
            
            // Hide dependent filters
            $('.state-filter-group, .city-filter-group, .address-filter-group').hide();
            
            const clearedFilters = getCurrentFilters();
            handleFilterChange(clearedFilters, 'clear filters');
        });
    }

    /**
     * Initialize from URL parameters
     */
    function initializeFromURL() {
        console.log('🔧 Initializing from URL...');
        
        const urlParams = new URLSearchParams(window.location.search);
        let hasFilters = false;

        // Set up search filter from URL
        const searchParam = urlParams.get('search') || urlParams.get('s');
        if (searchParam) {
            $('#event-search-input').val(searchParam);
            hasFilters = true;
        }
        
        // Set up taxonomy filters from URL
        $('.taxonomy-filter-select').each(function() {
            const $select = $(this);
            const taxonomyName = $select.data('taxonomy');
            
            let paramValue = null;
            if (taxonomyName === 'tribe_events_cat' || taxonomyName === 'category') {
                paramValue = urlParams.get('category');
            } else if (taxonomyName === 'post_tag') {
                paramValue = urlParams.get('keyword');
            }
            
            if (!paramValue) {
                paramValue = urlParams.get(taxonomyName);
            }
            
            if (paramValue) {
                const termIds = paramValue.split(',');
                $select.val(termIds);
                hasFilters = true;
            }
        });
        
        // Set up day-of-week filters from URL
        const dayofweekParam = urlParams.get('dayofweek');
        if (dayofweekParam) {
            const days = dayofweekParam.split(',').map(d => parseInt(d));
            days.forEach(function(day) {
                $('.dayofweek-filter-checkbox[value="' + day + '"]').prop('checked', true);
            });
            hasFilters = true;
        }
        
        // Set up time period filters from URL
        const timeperiodParam = urlParams.get('timeperiod');
        if (timeperiodParam) {
            const periods = timeperiodParam.split(',');
            periods.forEach(function(period) {
                $('.timeperiod-filter-checkbox[value="' + period + '"]').prop('checked', true);
            });
            hasFilters = true;
        }
        const featuredParam = urlParams.get('featured');
        if (featuredParam === '1') {
            $('#featured-filter-checkbox').prop('checked', true);
            hasFilters = true;
        }
        
        // NEW: Set up virtual filter from URL - ADD THIS BLOCK
        const virtualParam = urlParams.get('virtual');
        if (virtualParam === '1') {
            $('#virtual-filter-checkbox').prop('checked', true);
            hasFilters = true;
        }

        // Set up cost filter from URL
        const costParam = urlParams.get('cost');
        if (costParam) {
            const minSlider = $('.range-input .min');
            const maxSlider = $('.range-input .max');
            
            if (costParam.includes(',')) {
                const [min, max] = costParam.split(',').map(v => parseFloat(v));
                if (minSlider.length && maxSlider.length) {
                    minSlider.val(min);
                    maxSlider.val(max);
                    $('#min-price-input').val(min);
                    $('#max-price-input').val(max);
                    
                    // Update visual slider
                    const rangeSelected = $('.range-selected');
                    if (rangeSelected.length) {
                        const maxRange = parseFloat(maxSlider.attr('max')) || 5000;
                        rangeSelected.css('left', ((min / maxRange) * 100) + '%');
                        rangeSelected.css('right', (100 - (max / maxRange) * 100) + '%');
                    }
                }
            } else {
                const maxVal = parseFloat(costParam);
                if (minSlider.length && maxSlider.length) {
                    minSlider.val(0);
                    maxSlider.val(maxVal);
                    $('#min-price-input').val(0);
                    $('#max-price-input').val(maxVal);
                    
                    // Update visual slider
                    const rangeSelected = $('.range-selected');
                    if (rangeSelected.length) {
                        const maxRange = parseFloat(maxSlider.attr('max')) || 5000;
                        rangeSelected.css('left', '0%');
                        rangeSelected.css('right', (100 - (maxVal / maxRange) * 100) + '%');
                    }
                }
            }
            hasFilters = true;
        }

        // Set up venue filters from URL
        const countryParam = urlParams.get('country');
        if (countryParam) {
            $('#venue-country-select').val(countryParam);
            $('.state-filter-group, .address-filter-group').show();
            hasFilters = true;
        }
        
        const stateParam = urlParams.get('state');
        if (stateParam) {
            const states = stateParam.split(',');
            $('#venue-state-select').val(states);
            $('.city-filter-group').show();
            hasFilters = true;
        }
        
        const cityParam = urlParams.get('city');
        if (cityParam) {
            const cities = cityParam.split(',');
            $('#venue-city-select').val(cities);
            hasFilters = true;
        }

        const addressParam = urlParams.get('address');
        if (addressParam) {
            const addresses = addressParam.split(',');
            $('#venue-address-select').val(addresses);
            hasFilters = true;
        }

        // Set up organizer filter from URL
        const organizerParam = urlParams.get('organizer');
        if (organizerParam) {
            const organizers = organizerParam.split(',');
            $('#organizer-select').val(organizers);
            hasFilters = true;
        }
        
        // Set up calendar from URL
        updateCalendarDropdownsFromURL(window.location.href);
        
        console.log('✅ URL initialization complete, has filters:', hasFilters);
        return hasFilters;
    }

    // ========================================
    // COST SLIDER FUNCTIONALITY
    // ========================================

    // Initialize cost slider functionality
    function initializeCostSlider() {
        const rangeSelected = document.querySelector(".range-selected");
        const rangeInput = document.querySelectorAll(".range-input input");
        const rangePrice = document.querySelectorAll(".range-price input");

        if (!rangeSelected || !rangeInput.length || !rangePrice.length) {
            return;
        }

        // Function to update slider from range inputs
        function updateRangeSliderFromInput(e) {
            let minRange = parseInt(rangeInput[0].value);
            let maxRange = parseInt(rangeInput[1].value);

            if (e.target.classList.contains('min')) {
                if (minRange > maxRange) {
                    rangeInput[0].value = maxRange;
                    minRange = maxRange;
                }
            } else {
                if (maxRange < minRange) {
                    rangeInput[1].value = minRange;
                    maxRange = minRange;
                }
            }

            rangePrice[0].value = minRange;
            rangePrice[1].value = maxRange;

            rangeSelected.style.left = ((minRange / parseInt(rangeInput[0].max)) * 100) + "%";
            rangeSelected.style.right = (100 - (maxRange / parseInt(rangeInput[1].max)) * 100) + "%";
        }

        // Function to update slider from price inputs
        function updateRangeSliderFromPrice(e) {
            let minPrice = parseInt(rangePrice[0].value);
            let maxPrice = parseInt(rangePrice[1].value);

            const sliderMin = parseInt(rangeInput[0].min);
            const sliderMax = parseInt(rangeInput[1].max);

            if (minPrice < sliderMin) { minPrice = sliderMin; rangePrice[0].value = minPrice; }
            if (maxPrice > sliderMax) { maxPrice = sliderMax; rangePrice[1].value = maxPrice; }

            if (e.target.classList.contains('min-price-input')) {
                if (minPrice > maxPrice) {
                    maxPrice = minPrice;
                    rangePrice[1].value = maxPrice;
                }
            } else {
                if (maxPrice < minPrice) {
                    minPrice = maxPrice;
                    rangePrice[0].value = minPrice;
                }
            }

            rangeInput[0].value = minPrice;
            rangeInput[1].value = maxPrice;

            rangeSelected.style.left = ((minPrice / sliderMax) * 100) + "%";
            rangeSelected.style.right = (100 - (maxPrice / sliderMax) * 100) + "%";
        }

        // Attach event listeners
        rangeInput.forEach((input) => {
            input.addEventListener("input", updateRangeSliderFromInput);
            input.addEventListener('change', function() {
                const newFilters = getCurrentFilters();
                handleFilterChange(newFilters, 'cost range slider');
            });
        });

        rangePrice.forEach((input) => {
            if (input === rangePrice[0]) {
                input.classList.add('min-price-input');
                input.id = 'min-price-input';
            } else {
                input.classList.add('max-price-input');
                input.id = 'max-price-input';
            }
            input.addEventListener("input", updateRangeSliderFromPrice);
        });

        // Initial setup
        updateRangeSliderFromInput({ target: rangeInput[0] });
    }

    // ========================================
    // SEARCH SLIDER FUNCTIONALITY
    // ========================================
    /**
     * Search handler
     */
    function initSearchHandler() {
        console.log('🔍 Initializing simple search handler');
        
        // Search on Enter key
        $(document).on('keypress', '#event-search-input', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                console.log('🔍 Search submitted via Enter key');
                
                const newFilters = getCurrentFilters();
                handleFilterChange(newFilters, 'search');
            }
        });
        
        // Search on blur (when user clicks away) - optional
        $(document).on('blur', '#event-search-input', function() {
            const currentValue = $(this).val().trim();
            const urlParam = new URLSearchParams(window.location.search).get('search') || '';
            
            // Only trigger if value changed
            if (currentValue !== urlParam) {
                console.log('🔍 Search changed on blur');
                const newFilters = getCurrentFilters();
                handleFilterChange(newFilters, 'search blur');
            }
        });
        
        console.log('✅ Simple search handler initialized');
    }

    // ========================================
    // FEATURED FUNCTIONALITY
    // ========================================

    function initFeaturedHandler() {
    console.log('✨ Initializing featured events handler');
    
    $(document).on('change', '#featured-filter-checkbox', function(e) {
        console.log('✨ Featured events filter changed:', $(this).is(':checked'));
        
        const newFilters = getCurrentFilters();
        handleFilterChange(newFilters, 'featured events checkbox');
    });
    
        console.log('✅ Featured events handler initialized');
    }

    // ========================================
    // VIRTUAL FUNCTIONALITY
    // ========================================

    function initVirtualHandler() {
        console.log('💻 Initializing virtual events handler');
        
        $(document).on('change', '#virtual-filter-checkbox', function(e) {
            console.log('💻 Virtual events filter changed:', $(this).is(':checked'));
            
            const newFilters = getCurrentFilters();
            handleFilterChange(newFilters, 'virtual events checkbox');
        });
        
        console.log('✅ Virtual events handler initialized');
    }

    // ========================================
    // SYSTEM INITIALIZATION
    // ========================================
    
    /**
     * Initialize the universal AJAX system
     */
    function initializeUniversalAjaxSystem() {
        console.log('🚀 Initializing Updated Universal AJAX system...');
        
        // Initialize filter handlers
        initFilterHandlersUniversal();
        
        // Initialize calendar handlers
        initCalendarHandlersUniversal();
        
        // Initialize clear filters handler
        initClearFiltersHandler();
        
        // Initialize venue dependency handlers (MOVED FROM PHP)
        initVenueDependencyHandlers();

        // Initialize search slider
        initSearchHandler();

        // Initialize featured and virtual event handlers
        initFeaturedHandler();
        initVirtualHandler();
        
        // Initialize cost slider
        initializeCostSlider();
        
        // Initialize from URL
        initializeFromURL();
        
        // Expose functions for debugging
        window.UniversalAjaxSystem = {
            handleFilterChange: handleFilterChange,
            handleCalendarChange: handleCalendarChange,
            showLoadingState: showLoadingState,
            hideLoadingState: hideLoadingState,
            extractContentFromResponse: extractContentFromResponse,
            getCurrentFilters: getCurrentFilters,
            buildCleanURL: buildCleanURL,
            storeCurrentFilterValues: storeCurrentFilterValues,
            restoreFilterValues: restoreFilterValues,
            updateDependentVenueOptions: updateDependentVenueOptions,
            initSearchHandler:initSearchHandler,
            initFeaturedHandler: initFeaturedHandler,
            initVirtualHandler: initVirtualHandler
        };
        
        console.log('✅ Updated Universal AJAX system initialized');
        console.log('📍 Single $.get updates BOTH events and filters - no redundant AJAX calls');
        console.log('📍 Venue dependencies handled centrally - no embedded scripts');
    }

    // Start the universal system
    initializeUniversalAjaxSystem();
});

// Debug helpers (can be removed in production)
$(document).on('click', '.debug-ajax', function() {
    if (typeof window.UniversalAjaxSystem !== 'undefined') {
        const currentFilters = window.UniversalAjaxSystem.getCurrentFilters();
        console.log('🔍 Current Universal Filters:', currentFilters);
        alert('Universal AJAX Debug Info (check console):\n' + JSON.stringify(currentFilters, null, 2));
    } else {
        alert('Universal AJAX system not initialized');
    }
});

// Add debug button if needed (remove in production)
if (window.location.search.includes('debug=1')) {
    $('body').append('<button class="debug-ajax" style="position:fixed;top:50px;right:10px;z-index:9999;background:#28a745;color:white;border:none;padding:10px;cursor:pointer;">Debug Universal AJAX</button>');
}

/**
 * JavaScript for TRUE drilldown behavior - updates both main page AND collapse content
 * Add this to your existing Universal Archive Events JavaScript
 */

/**
 * Navigate to year view (from year grid) - TRUE DRILLDOWN
 */
function navigateToYear(year) {
    const newURL = `/events/${year}/`;
    
    console.log(`📅 Drilldown to year ${year} - updating main page AND collapse content`);
    
    // 1. Update main page content using existing AJAX system
    if (typeof window.UniversalAjaxSystem !== 'undefined' && 
        typeof window.UniversalAjaxSystem.handleCalendarChange === 'function') {
        
        console.log(`📄 Updating main page to ${newURL}`);
        window.UniversalAjaxSystem.handleCalendarChange(newURL, 'year grid navigation');
        
    } else if (typeof handleCalendarChange === 'function') {
        console.log(`📄 Using global handleCalendarChange for main page`);
        handleCalendarChange(newURL, 'year grid navigation');
        
    } else {
        // Fallback - just reload the page
        console.log(`📄 Fallback: Page reload for year ${year}`);
        const currentFilters = typeof getCurrentFilters === 'function' ? getCurrentFilters() : {};
        const finalURL = typeof buildCleanURL === 'function' ? 
            buildCleanURL(newURL, currentFilters) : newURL;
        window.location.href = finalURL;
        return; // Don't update collapse if we're reloading
    }
    
    // 2. Update collapse content to show months grid for this year
    updateCollapseToDrilldownView('month', { year: year });
}

/**
 * Navigate to month view (from month grid) - TRUE DRILLDOWN  
 */
function navigateToMonth(year, month) {
    const monthPadded = month.toString().padStart(2, '0');
    const newURL = `/events/${year}/${monthPadded}/`;
    
    console.log(`📅 Drilldown to ${year}/${monthPadded} - updating main page AND collapse content`);
    
    // 1. Update main page content using existing AJAX system
    if (typeof window.UniversalAjaxSystem !== 'undefined' && 
        typeof window.UniversalAjaxSystem.handleCalendarChange === 'function') {
        
        console.log(`📄 Updating main page to ${newURL}`);
        window.UniversalAjaxSystem.handleCalendarChange(newURL, 'month grid navigation');
        
    } else if (typeof handleCalendarChange === 'function') {
        console.log(`📄 Using global handleCalendarChange for main page`);
        handleCalendarChange(newURL, 'month grid navigation');
        
    } else {
        // Fallback - just reload the page
        console.log(`📄 Fallback: Page reload for ${year}/${monthPadded}`);
        const currentFilters = typeof getCurrentFilters === 'function' ? getCurrentFilters() : {};
        const finalURL = typeof buildCleanURL === 'function' ? 
            buildCleanURL(newURL, currentFilters) : newURL;
        window.location.href = finalURL;
        return; // Don't update collapse if we're reloading
    }
    
    // 2. Update collapse content to show calendar for this month
    // FIXED: Add small delay to ensure main page update completes first
    setTimeout(() => {
        console.log(`🔄 Updating collapse to calendar view for ${year}/${monthPadded}`);
        updateCollapseToDrilldownView('calendar', { year: year, month: monthPadded });
    }, 500);
}

/**
 * Navigate back to years view - TRUE DRILLDOWN
 */
function navigateBackToYears() {
    const newURL = '/events/';
    
    console.log('📅 Drilldown back to years - updating main page AND collapse content');
    
    // 1. Update main page content using existing AJAX system
    if (typeof window.UniversalAjaxSystem !== 'undefined' && 
        typeof window.UniversalAjaxSystem.handleCalendarChange === 'function') {
        
        console.log('📄 Updating main page back to /events/');
        window.UniversalAjaxSystem.handleCalendarChange(newURL, 'back to years');
        
    } else if (typeof handleCalendarChange === 'function') {
        console.log('📄 Using global handleCalendarChange to go back');
        handleCalendarChange(newURL, 'back to years');
        
    } else {
        // Fallback - just reload the page
        console.log('📄 Fallback: Page reload back to years');
        const currentFilters = typeof getCurrentFilters === 'function' ? getCurrentFilters() : {};
        const finalURL = typeof buildCleanURL === 'function' ? 
            buildCleanURL(newURL, currentFilters) : newURL;
        window.location.href = finalURL;
        return; // Don't update collapse if we're reloading
    }
    
    // 2. Update collapse content to show years grid
    updateCollapseToDrilldownView('year');
}

/**
 * ALTERNATIVE: Simplified collapse update that generates content directly
 */
function updateCollapseToDrilldownViewSimple(viewType, params = {}) {
    console.log(`🔄 SIMPLE update collapse to ${viewType} view:`, params);
    
    const $dateSelector = $('#dateSelector .card-body');
    if ($dateSelector.length === 0) {
        console.log('⚠️ Date selector not found');
        return;
    }
    
    // Show loading state
    $dateSelector.html(`
        <div class="d-flex justify-content-center align-items-center" style="min-height: 200px;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);
    
    // SIMPLIFIED: Generate content based on viewType without complex extraction
    if (viewType === 'year') {
        // Generate years grid directly
        generateYearsGridInCollapse();
        
    } else if (viewType === 'month') {
        // Generate months grid directly
        generateMonthsGridInCollapse(params.year);
        
    } else if (viewType === 'calendar') {
        // Fetch calendar using a simpler approach
        fetchCalendarForCollapse(params.year, params.month);
    }
}

/**
 * Generate years grid directly in collapse
 */
function generateYearsGridInCollapse() {
    const currentYear = new Date().getFullYear();
    const startYear = currentYear - 5;
    const endYear = currentYear + 6;
    
    let html = `
        <div class="container p-0 my-3">
            <h5 class="text-center mb-4">Select Year</h5>
            <div id="years-grid" class="row">
    `;
    
    for (let year = startYear; year <= endYear; year++) {
        const isCurrentYear = (year === currentYear);
        const currentClass = isCurrentYear ? 'current-year-card' : '';
        
        html += `
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="year-card ${currentClass}" data-year="${year}" data-navigation-type="year">
                    <a href="/events/${year}/" data-year="${year}" class="year-navigation-link">
                        ${year}
                    </a>
                </div>
            </div>
        `;
    }
    
    html += `
            </div>
        </div>
    `;
    
    $('#dateSelector .card-body').html(html);
    console.log('✅ Years grid generated in collapse');
}

/**
 * Generate months grid directly in collapse
 */
function generateMonthsGridInCollapse(year) {
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1; // 1-based
    
    const monthNames = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    
    let html = `
        <div class="container p-0 my-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="/events/" class="btn btn-sm btn-outline-secondary back-to-years-link" data-navigation-type="back-to-years">
                    <i class="fas fa-chevron-left"></i> Back to Years
                </a>
                <h5 class="mb-0">Select Month - ${year}</h5>
                <span></span>
            </div>
            <div id="months-grid" class="row">
    `;
    
    for (let month = 1; month <= 12; month++) {
        const monthPadded = month.toString().padStart(2, '0');
        const monthName = monthNames[month - 1];
        const isCurrentMonth = (year == currentYear && month == currentMonth);
        const currentClass = isCurrentMonth ? 'current-month-card' : '';
        
        html += `
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="month-card ${currentClass}" data-year="${year}" data-month="${month}" data-navigation-type="month">
                    <a href="/events/${year}/${monthPadded}/" data-year="${year}" data-month="${month}" class="month-navigation-link">
                        ${monthName}
                    </a>
                </div>
            </div>
        `;
    }
    
    html += `
            </div>
        </div>
    `;
    
    $('#dateSelector .card-body').html(html);
    console.log(`✅ Months grid generated in collapse for ${year}`);
}

/**
 * Fetch calendar for collapse using direct shortcode approach
 */
function fetchCalendarForCollapse(year, month) {
    console.log(`📅 Fetching calendar for ${year}/${month} using direct approach`);
    
    // Get current filters
    const currentFilters = typeof getCurrentFilters === 'function' ? getCurrentFilters() : {};
    const filterQuery = Object.keys(currentFilters).length > 0 ? 
        '?' + $.param(currentFilters) : '';
    
    const calendarURL = `/events/${year}/${month}/${filterQuery}`;
    
    console.log(`📡 Fetching calendar from: ${calendarURL}`);
    
    $.get(calendarURL)
        .done(function(responseHTML) {
            console.log('✅ Calendar response received');
            
            // Try multiple methods to extract calendar
            const $response = $(responseHTML);
            let calendarHTML = '';
            
            // Method 1: Look for the shortcode output area
            const $shortcodeArea = $response.find('[data-shortcode="custom_calendar"], .custom-calendar-output');
            if ($shortcodeArea.length > 0) {
                calendarHTML = $shortcodeArea.html();
                console.log('📅 Found calendar via shortcode area');
            }
            
            // Method 2: Look for wp-calendar and its surrounding content
            if (!calendarHTML) {
                const $calendar = $response.find('#wp-calendar');
                if ($calendar.length > 0) {
                    // Get the calendar plus any navigation
                    const $calendarContainer = $calendar.closest('div');
                    const $navigation = $response.find('.wp-calendar-nav');
                    
                    calendarHTML = '<div>' + $calendar.prop('outerHTML');
                    if ($navigation.length > 0) {
                        calendarHTML += $navigation.prop('outerHTML');
                    }
                    calendarHTML += '</div>';
                    console.log('📅 Found calendar via wp-calendar');
                }
            }
            
            // Method 3: Fallback - create a simple calendar view
            if (!calendarHTML) {
                console.log('⚠️ Could not extract calendar, creating fallback');
                calendarHTML = `
                    <div class="container p-0 my-3">
                        <h5 class="text-center mb-4">Calendar - ${year}/${month}</h5>
                        <div class="alert alert-info">
                            <p><strong>Calendar for ${year}/${month}</strong></p>
                            <p>Calendar content could not be extracted.</p>
                            <a href="${calendarURL}" class="btn btn-primary btn-sm">View Full Calendar</a>
                        </div>
                    </div>
                `;
            }
            
            $('#dateSelector .card-body').html(calendarHTML);
            console.log('✅ Calendar updated in collapse');
        })
        .fail(function(xhr, status, error) {
            console.error(`❌ Failed to fetch calendar: ${status} ${error}`);
            
            $('#dateSelector .card-body').html(`
                <div class="container p-0 my-3">
                    <div class="alert alert-danger">
                        <h6>Failed to Load Calendar</h6>
                        <p>Could not load calendar for ${year}/${month}</p>
                        <a href="/events/${year}/${month}/" class="btn btn-primary btn-sm">View Full Calendar</a>
                    </div>
                </div>
            `);
        });
}

/**
 * FIXED: Update collapse content to show the appropriate drilldown view
 */
function updateCollapseToDrilldownView(viewType, params = {}) {
    console.log(`🔄 Updating collapse to ${viewType} view:`, params);
    
    // Try the simple approach first
    if (viewType === 'year' || viewType === 'month') {
        updateCollapseToDrilldownViewSimple(viewType, params);
        return;
    }
    
    // For calendar, try both approaches
    const $dateSelector = $('#dateSelector .card-body');
    if ($dateSelector.length === 0) {
        console.log('⚠️ Date selector not found');
        return;
    }
    
    // Show loading state
    $dateSelector.html(`
        <div class="d-flex justify-content-center align-items-center" style="min-height: 200px;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);
    
    // Generate the appropriate view content via AJAX
    let ajaxURL;
    
    switch(viewType) {
        case 'year':
            // Get years grid by fetching /events/ with filters
            ajaxURL = '/events/';
            break;
        case 'month':
            // Get months grid by fetching /events/YEAR/ with filters  
            ajaxURL = `/events/${params.year}/`;
            break;
        case 'calendar':
            // Get calendar by fetching /events/YEAR/MONTH/ with filters
            ajaxURL = `/events/${params.year}/${params.month}/`;
            break;
        default:
            console.error('Unknown view type:', viewType);
            return;
    }
    
    // Add current filters to the URL
    const currentFilters = typeof getCurrentFilters === 'function' ? getCurrentFilters() : {};
    const finalURL = typeof buildCleanURL === 'function' ? 
        buildCleanURL(ajaxURL, currentFilters) : ajaxURL;
    
    console.log(`📡 Fetching collapse content from: ${finalURL}`);
    
    // Fetch the new content
    $.get(finalURL)
        .done(function(responseHTML) {
            console.log('✅ Successfully fetched collapse content');
            
            try {
                const $response = $(responseHTML);
                let $newContent;
                
                // IMPROVED: Better content extraction logic
                if (viewType === 'year') {
                    // Look for years grid - try multiple selectors
                    $newContent = $response.find('#years-grid').closest('.container');
                    if ($newContent.length === 0) {
                        $newContent = $response.find('#years-grid').parent();
                    }
                    if ($newContent.length === 0) {
                        $newContent = $response.find('#years-grid');
                    }
                    
                } else if (viewType === 'month') {
                    // Look for months grid - try multiple selectors
                    $newContent = $response.find('#months-grid').closest('.container');
                    if ($newContent.length === 0) {
                        $newContent = $response.find('#months-grid').parent();
                    }
                    if ($newContent.length === 0) {
                        $newContent = $response.find('#months-grid');
                    }
                    
                } else if (viewType === 'calendar') {
                    // FIXED: Look for calendar content more thoroughly
                    console.log('🔍 Looking for calendar content...');
                    
                    // Method 1: Look for the complete calendar table with navigation
                    $newContent = $response.find('#wp-calendar').closest('table').parent();
                    console.log('Method 1 - calendar table parent:', $newContent.length);
                    
                    // Method 2: Look for calendar wrapped in any container
                    if ($newContent.length === 0) {
                        $newContent = $response.find('#wp-calendar').closest('div');
                        console.log('Method 2 - calendar closest div:', $newContent.length);
                    }
                    
                    // Method 3: Look for the calendar table itself
                    if ($newContent.length === 0) {
                        const $calendarTable = $response.find('#wp-calendar');
                        const $calendarNav = $response.find('.wp-calendar-nav');
                        
                        if ($calendarTable.length > 0) {
                            // Create a wrapper with both calendar and navigation
                            $newContent = $('<div>').append($calendarTable.clone()).append($calendarNav.clone());
                            console.log('Method 3 - manual calendar construction');
                        }
                    }
                    
                    // Method 4: Look for any element that contains a calendar
                    if ($newContent.length === 0) {
                        $newContent = $response.find('*:has(#wp-calendar)').first();
                        console.log('Method 4 - parent containing calendar:', $newContent.length);
                    }
                    
                    // Method 5: Search for calendar class or similar
                    if ($newContent.length === 0) {
                        $newContent = $response.find('.wp-calendar-table, .calendar, [class*="calendar"]').first().parent();
                        console.log('Method 5 - calendar class search:', $newContent.length);
                    }
                    
                    // Method 6: Fallback to simple approach
                    if ($newContent.length === 0) {
                        console.log('🔄 Falling back to simple calendar approach');
                        fetchCalendarForCollapse(params.year, params.month);
                        return;
                    }
                }
                
                if ($newContent && $newContent.length > 0) {
                    // Replace the collapse content
                    $dateSelector.html($newContent.html());
                    console.log(`✅ Collapse updated to ${viewType} view successfully`);
                } else {
                    console.warn(`⚠️ Could not find ${viewType} content in response`);
                    console.log('Response HTML snippet:', responseHTML.substring(0, 1000));
                    
                    // For calendar, try the simple fallback
                    if (viewType === 'calendar') {
                        console.log('🔄 Trying simple calendar fallback');
                        fetchCalendarForCollapse(params.year, params.month);
                    } else {
                        // For other types, show error
                        $dateSelector.html(`
                            <div class="alert alert-info">
                                <h6>${viewType.charAt(0).toUpperCase() + viewType.slice(1)} View</h6>
                                <p>Unable to extract ${viewType} content from response.</p>
                                <a href="${finalURL}" class="btn btn-primary btn-sm">Navigate to ${viewType} view</a>
                            </div>
                        `);
                    }
                }
                
            } catch (error) {
                console.error('❌ Error processing collapse content:', error);
                
                // For calendar, try the simple fallback
                if (viewType === 'calendar') {
                    console.log('🔄 Error occurred, trying simple calendar fallback');
                    fetchCalendarForCollapse(params.year, params.month);
                } else {
                    $dateSelector.html(`
                        <div class="alert alert-warning">
                            <h6>Error Loading ${viewType.charAt(0).toUpperCase() + viewType.slice(1)}</h6>
                            <p>Unable to process ${viewType} view content.</p>
                            <a href="${finalURL}" class="btn btn-primary btn-sm">Navigate manually</a>
                        </div>
                    `);
                }
            }
        })
        .fail(function(xhr, status, error) {
            console.error(`❌ Failed to fetch collapse content: ${status} ${error}`);
            
            // For calendar, try the simple fallback
            if (viewType === 'calendar') {
                console.log('🔄 AJAX failed, trying simple calendar fallback');
                fetchCalendarForCollapse(params.year, params.month);
            } else {
                $dateSelector.html(`
                    <div class="alert alert-danger">
                        <h6>Failed to Load ${viewType.charAt(0).toUpperCase() + viewType.slice(1)}</h6>
                        <p>Network error: ${status}</p>
                        <a href="${finalURL}" class="btn btn-primary btn-sm">Navigate manually</a>
                    </div>
                `);
            }
        });
}

/**
 * Initialize date selector when page loads - ENHANCED with link interception
 */
$(document).ready(function() {
    console.log('🗓️ Date selector JavaScript initialized - TRUE DRILLDOWN MODE');
    
    // Handle collapse events
    $('#dateSelector').on('show.bs.collapse', function () {
        console.log('📅 Date selector opened');
    });
    
    $('#dateSelector').on('hide.bs.collapse', function () {
        console.log('📅 Date selector closed');
    });
    
    // === Intercept year navigation clicks ===
    $(document).on('click', '.year-navigation-link', function(e) {
        e.preventDefault();
        const year = $(this).data('year');
        console.log(`🔽 Year drilldown clicked: ${year}`);
        navigateToYear(year);
    });
    
    // === Intercept month navigation clicks ===
    $(document).on('click', '.month-navigation-link', function(e) {
        e.preventDefault();
        const year = $(this).data('year');
        const month = $(this).data('month');
        console.log(`🔽 Month drilldown clicked: ${year}/${month}`);
        navigateToMonth(year, month);
    });
    
    // === Intercept back to years clicks ===
    $(document).on('click', '.back-to-years-link', function(e) {
        e.preventDefault();
        console.log('🔙 Back to years drilldown clicked');
        navigateBackToYears();
    });
    
    // === Handle other calendar links within the date selector ===
    $(document).on('click', '#dateSelector a[href*="/events/"]', function(e) {
        const href = $(this).attr('href');
        
        // Skip if this is one of our drilldown navigation links (already handled above)
        if ($(this).hasClass('year-navigation-link') || 
            $(this).hasClass('month-navigation-link') || 
            $(this).hasClass('back-to-years-link')) {
            return; // Let the specific handlers above deal with it
        }
        
        // Handle regular calendar navigation (like clicking on specific days)
        if (href && href.match(/\/events\/(\d{4})\/(\d{2})(\/\d{2})?\/?$/)) {
            e.preventDefault();
            
            // Extract clean path without query parameters
            let cleanPath = href;
            if (href.includes('?')) {
                cleanPath = href.split('?')[0];
            }
            if (!cleanPath.endsWith('/')) {
                cleanPath += '/';
            }
            
            // For regular calendar links, just update the main page (not drilldown)
            if (typeof window.UniversalAjaxSystem !== 'undefined' && 
                typeof window.UniversalAjaxSystem.handleCalendarChange === 'function') {
                
                console.log('📅 Regular calendar navigation:', cleanPath);
                window.UniversalAjaxSystem.handleCalendarChange(cleanPath, 'date selector calendar link');
                
            } else if (typeof handleCalendarChange === 'function') {
                console.log('📅 Using global handleCalendarChange for calendar link');
                handleCalendarChange(cleanPath, 'date selector calendar link');
                
            } else {
                // Fallback to page navigation
                console.log('📅 Fallback: Direct navigation for calendar link');
                window.location.href = href;
            }
        }
    });
});

/**
 * Export functions for debugging
 */
if (typeof window.DateSelectorSystem === 'undefined') {
    window.DateSelectorSystem = {
        navigateToYear: navigateToYear,
        navigateToMonth: navigateToMonth,
        navigateBackToYears: navigateBackToYears,
        updateCollapseToDrilldownView: updateCollapseToDrilldownView
    };
}
