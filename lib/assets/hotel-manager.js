/**
 * Hotel Manager JavaScript
 * Handles all interactive functionality for the Hotel Manager interface
 */

(function($) {
    'use strict';
    
    let currentHotelId = null;
    let selectedNodeId = null;
    let isSubmitting = false;
    let isDuplicatingHotel = false;
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Check if we're on the hotel post type page
        if (window.location.href.indexOf('post_type=hotel') !== -1 && !window.location.href.indexOf('page=') !== -1) {
            initializeHotelManager();
        }
    });
    
    // Make initializeHotelManager globally available
    window.initializeHotelManager = initializeHotelManager;
    
    function initializeHotelManager() {
        // Initialize Select2 for hotel selector
        $('#hotel-select').select2({
            placeholder: 'Choose a hotel...',
            allowClear: true,
            width: '100%'
        });
        
        // Hotel selector change
        $('#hotel-select').on('change', function() {
            const hotelId = $(this).val();
            if (hotelId) {
                loadHotelTree(hotelId);
                currentHotelId = hotelId;
                updateButtonStates(true);
                updatePinButton(hotelId);
                updatePageTitle($(this).find('option:selected').text());
            } else {
                clearTree();
                currentHotelId = null;
                updateButtonStates(false);
                removePinButton();
                updatePageTitle();
            }
        });
        
        // Initialize from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const hotelId = urlParams.get('hotel_id');
        if (hotelId) {
            $('#hotel-select').val(hotelId).trigger('change');
        }
        
        // Update page title to show we're in Hotel Manager mode
        updatePageTitle('Hotel Manager');
        
        // Button event handlers (use off() to prevent duplicate bindings)
        $('#add-inner-page').off('click').on('click', showAddModal);
        $('#duplicate-page').off('click').on('click', duplicateSelectedPage);
        $('#duplicate-hotel').off('click').on('click', duplicateCurrentHotel);
        $('#seed-defaults').off('click').on('click', seedDefaultPages);
        $('#expand-all').off('click').on('click', expandAllNodes);
        $('#collapse-all').off('click').on('click', collapseAllNodes);
        $('#refresh-tree').off('click').on('click', refreshTree);
        
        // Modal handlers
        $('#mh-rename-modal .mh-cancel, #mh-add-modal .mh-cancel').on('click', hideAllModals);
        $('#slug-mode').on('change', toggleCustomSlugField);
        
        // Form submissions (use off() to prevent duplicate bindings)
        $('#mh-rename-form').off('submit').on('submit', handleRenameSubmit);
        $('#mh-add-form').off('submit').on('submit', handleAddSubmit);
        
        // Pin/unpin handlers
        $(document).on('click', '.mh-unpin', handleUnpinHotel);
        $(document).on('click', '#pin-hotel-btn', handlePinHotel);
        
        // Tree event handlers
        $(document).on('click', '.mh-expand-toggle', toggleNodeExpansion);
        $(document).on('click', '.mh-action', handleTreeAction);
        $(document).on('click', '.mh-tree-node', selectNode);
        
        // Actions dropdown handlers - use more specific delegation
        $(document).on('click', '.mh-actions-toggle', function(e) {
            handleActionsToggle.call(this, e);
        });
        
        // Close dropdown if clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.mh-actions-dropdown').length) {
                $('.mh-actions-menu').hide();
            }
        });
        
        
        // Initialize sortable
        initializeSortable();
    }
    
    function loadHotelTree(hotelId) {
        showLoading();
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_get_tree',
                hotel_id: hotelId,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#mh-tree').html(response.data.html);
                    
                    // Re-bind events for dynamically loaded content
                    rebindTreeEvents();
                    
                    initializeSortable();
                    hideLoading();
                } else {
                    showError(response.data || mh_ajax.strings.error);
                }
            },
            error: function() {
                showError(mh_ajax.strings.error);
            }
        });
    }
    
    function clearTree() {
        $('#mh-tree').html('<p class="mh-no-hotel">Please select a hotel to view its pages.</p>');
        selectedNodeId = null;
    }
    
    function rebindTreeEvents() {
        // Re-bind events for dynamically loaded content
        $('.mh-actions-toggle').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Close all other dropdowns
            $('.mh-actions-menu').not($(this).siblings('.mh-actions-menu')).hide();
            
            // Toggle current dropdown
            const menu = $(this).siblings('.mh-actions-menu');
            menu.toggle();
        });
    }
    
    function showLoading() {
        $('.mh-loading').show();
        $('#mh-tree').hide();
    }
    
    function hideLoading() {
        $('.mh-loading').hide();
        $('#mh-tree').show();
    }
    
    function updateButtonStates(hasHotel) {
        const buttons = ['#add-inner-page', '#seed-defaults', '#duplicate-hotel'];
        buttons.forEach(selector => {
            $(selector).prop('disabled', !hasHotel);
        });
        
        $('#duplicate-page').prop('disabled', !selectedNodeId);
    }
    
    function selectNode(e) {
        // Don't select if clicking on expand toggle, actions, or quick links
        if ($(e.target).hasClass('mh-expand-toggle') || 
            $(e.target).hasClass('mh-action') || 
            $(e.target).hasClass('mh-actions-toggle') ||
            $(e.target).closest('.mh-quick-links').length ||
            $(e.target).closest('.mh-actions-dropdown').length) {
            return;
        }
        
        e.stopPropagation();
        
        // Remove previous selection
        $('.mh-tree-node').removeClass('selected');
        
        // Add selection to clicked node
        $(this).addClass('selected');
        selectedNodeId = $(this).data('post-id');
        
        updateButtonStates(currentHotelId);
    }
    
    function showAddModal() {
        if (!currentHotelId) return;
        
        $('#add-parent-id').val(currentHotelId);
        $('#add-title').val('');
        $('#add-slug').val('');
        $('#add-status').val('draft');
        $('#mh-add-modal').show();
    }
    
    function hideAllModals() {
        $('.mh-modal').hide();
    }
    
    function toggleCustomSlugField() {
        const mode = $(this).val();
        const customField = $('#custom-slug-field');
        
        if (mode === 'custom') {
            customField.show();
        } else {
            customField.hide();
        }
    }
    
    function handleAddSubmit(e) {
        e.preventDefault();
        
        // Prevent double submission
        if (isSubmitting) {
            return;
        }
        
        isSubmitting = true;
        
        // Disable submit button
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Adding...');
        
        const formData = {
            action: 'mh_add_child',
            parent_id: $('#add-parent-id').val(),
            title: $('#add-title').val(),
            slug: $('#add-slug').val(),
            status: $('#add-status').val(),
            nonce: mh_ajax.nonce
        };
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    hideAllModals();
                    refreshTree();
                    showSuccess(response.data.message);
                } else {
                    showError(response.data || mh_ajax.strings.error);
                }
            },
            error: function() {
                showError(mh_ajax.strings.error);
            },
            complete: function() {
                // Re-enable submit button
                submitBtn.prop('disabled', false).text('Add Page');
                isSubmitting = false;
            }
        });
    }
    
    function handleRenameSubmit(e) {
        e.preventDefault();
        
        // Prevent double submission
        const submitBtn = $(this).find('button[type="submit"]');
        if (submitBtn.prop('disabled')) {
            return;
        }
        
        // Disable submit button
        submitBtn.prop('disabled', true).text('Saving...');
        
        const formData = {
            action: 'mh_rename_post',
            post_id: $('#rename-post-id').val(),
            new_title: $('#rename-title').val(),
            slug_mode: $('#slug-mode').val(),
            custom_slug: $('#custom-slug').val(),
            nonce: mh_ajax.nonce
        };
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    hideAllModals();
                    refreshTree();
                    showSuccess(response.data.message);
                } else {
                    showError(response.data || mh_ajax.strings.error);
                }
            },
            error: function() {
                showError(mh_ajax.strings.error);
            },
            complete: function() {
                // Re-enable submit button
                submitBtn.prop('disabled', false).text('Save');
            }
        });
    }
    
    function handleTreeAction(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const action = $(this).data('action');
        const postId = $(this).data('post-id');
        
        switch (action) {
            case 'rename':
                showRenameModal(postId);
                break;
            case 'duplicate':
                duplicatePage(postId);
                break;
            case 'move':
                // TODO: Implement move functionality
                showError('Move functionality coming soon!');
                break;
            case 'delete':
                deletePage(postId);
                break;
        }
    }
    
    function showRenameModal(postId) {
        const node = $(`.mh-tree-node[data-post-id="${postId}"]`);
        const title = node.find('.mh-node-title').text();
        
        $('#rename-post-id').val(postId);
        $('#rename-title').val(title);
        $('#slug-mode').val('keep');
        $('#custom-slug').val('');
        $('#custom-slug-field').hide();
        
        $('#mh-rename-modal').show();
    }
    
    function duplicatePage(postId) {
        if (!confirm(mh_ajax.strings.confirm_duplicate)) {
            return;
        }
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_duplicate_post',
                post_id: postId,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    refreshTree();
                    showSuccess(response.data.message);
                } else {
                    showError(response.data || mh_ajax.strings.error);
                }
            },
            error: function() {
                showError(mh_ajax.strings.error);
            }
        });
    }
    
    function duplicateSelectedPage() {
        if (selectedNodeId) {
            duplicatePage(selectedNodeId);
        }
    }
    
    function duplicateCurrentHotel() {
        if (!currentHotelId) {
            showError('No hotel selected.');
            return;
        }
        
        // Prevent multiple simultaneous duplications
        if (isDuplicatingHotel) {
            return;
        }
        
        if (!confirm('Are you sure you want to duplicate this entire hotel with all its pages? This may take a moment.')) {
            return;
        }
        
        isDuplicatingHotel = true;
        
        // Disable the button during duplication
        const btn = $('#duplicate-hotel');
        const originalText = btn.text();
        btn.prop('disabled', true).text('Duplicating...');
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_duplicate_hotel',
                hotel_id: currentHotelId,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    // Optionally refresh the hotel list or redirect to the new hotel
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showError(response.data || mh_ajax.strings.error);
                }
            },
            error: function() {
                showError(mh_ajax.strings.error);
            },
            complete: function() {
                // Re-enable the button
                btn.prop('disabled', false).text(originalText);
                isDuplicatingHotel = false;
            }
        });
    }
    
    function deletePage(postId) {
        if (!confirm(mh_ajax.strings.confirm_delete)) {
            return;
        }
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_delete_post',
                post_id: postId,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    refreshTree();
                    showSuccess(response.data.message);
                } else {
                    showError(response.data || mh_ajax.strings.error);
                }
            },
            error: function() {
                showError(mh_ajax.strings.error);
            }
        });
    }
    
    function seedDefaultPages() {
        if (!currentHotelId) return;
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_seed_defaults',
                hotel_id: currentHotelId,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    refreshTree();
                    showSuccess(response.data.message);
                } else {
                    showError(response.data || mh_ajax.strings.error);
                }
            },
            error: function() {
                showError(mh_ajax.strings.error);
            }
        });
    }
    
    function toggleNodeExpansion(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const node = button.closest('.mh-tree-node');
        const children = node.find('> .mh-tree-level');
        
        // Toggle the expanded state
        if (button.hasClass('expanded')) {
            // Currently expanded, so collapse
            button.removeClass('expanded');
            children.addClass('collapsed');
        } else {
            // Currently collapsed, so expand
            button.addClass('expanded');
            children.removeClass('collapsed');
        }
    }
    
    function handleActionsToggle(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close all other dropdowns
        $('.mh-actions-menu').not($(this).siblings('.mh-actions-menu')).hide();
        
        // Toggle current dropdown
        const menu = $(this).siblings('.mh-actions-menu');
        menu.toggle();
        
        // Position the dropdown if it's visible
        if (menu.is(':visible')) {
            const dropdown = $(this).closest('.mh-actions-dropdown');
            const rect = dropdown[0].getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            
            // Check if dropdown would go off screen
            if (rect.bottom + menu.outerHeight() > viewportHeight) {
                // Position above the button instead
                menu.css({
                    'top': 'auto',
                    'bottom': '100%',
                    'margin-top': '0',
                    'margin-bottom': '2px'
                });
            } else {
                // Reset to default positioning
                menu.css({
                    'top': '100%',
                    'bottom': 'auto',
                    'margin-top': '2px',
                    'margin-bottom': '0'
                });
            }
        }
    }
    
    function expandAllNodes() {
        $('.mh-expand-toggle').addClass('expanded');
        $('.mh-tree-level').removeClass('collapsed');
    }
    
    function collapseAllNodes() {
        $('.mh-expand-toggle').removeClass('expanded');
        $('.mh-tree-level').addClass('collapsed');
    }
    
    function refreshTree() {
        if (currentHotelId) {
            loadHotelTree(currentHotelId);
        }
    }
    
    function updatePinButton(hotelId) {
        // Remove existing pin button
        removePinButton();
        
        // Add new pin button
        const pinBtn = $(`
            <button type="button" id="pin-hotel-btn" class="button" data-hotel-id="${hotelId}">
                <span class="dashicons dashicons-star-empty"></span> Pin Hotel
            </button>
        `);
        $('.mh-hotel-selector-wrapper').append(pinBtn);
    }
    
    function removePinButton() {
        $('#pin-hotel-btn').remove();
    }
    
    function updatePageTitle(hotelName) {
        const baseTitle = 'Hotel Manager';
        if (hotelName) {
            document.title = `${hotelName} - ${baseTitle}`;
            $('.wrap h1').text(`${baseTitle}: ${hotelName}`);
        } else {
            document.title = baseTitle;
            $('.wrap h1').text(baseTitle);
        }
    }
    
    function handlePinHotel(e) {
        e.preventDefault();
        
        const hotelId = $(this).data('hotel-id');
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_pin_toggle',
                hotel_id: hotelId,
                op: 'add',
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Refresh to update pinned list
                } else {
                    showError(response.data || mh_ajax.strings.error);
                }
            },
            error: function() {
                showError(mh_ajax.strings.error);
            }
        });
    }
    
    function handleUnpinHotel(e) {
        e.preventDefault();
        
        const hotelId = $(this).data('hotel-id');
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_pin_toggle',
                hotel_id: hotelId,
                op: 'remove',
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Refresh to update pinned list
                } else {
                    showError(response.data || mh_ajax.strings.error);
                }
            },
            error: function() {
                showError(mh_ajax.strings.error);
            }
        });
    }
    
    function initializeSortable() {
        $('.mh-tree-level').sortable({
            items: '.mh-tree-node',
            handle: '.mh-drag-handle',
            placeholder: 'mh-tree-node ui-sortable-placeholder',
            helper: 'clone',
            tolerance: 'pointer',
            cursor: 'move',
            opacity: 0.8,
            update: function(event, ui) {
                const parentId = $(this).closest('.mh-tree-node').data('post-id') || currentHotelId;
                const orderedIds = $(this).sortable('toArray', {attribute: 'data-post-id'});
                
                $.ajax({
                    url: mh_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mh_reorder_siblings',
                        parent_id: parentId,
                        ordered_ids: orderedIds,
                        nonce: mh_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showSuccess(response.data.message);
                        } else {
                            showError(response.data || mh_ajax.strings.error);
                            refreshTree(); // Revert on error
                        }
                    },
                    error: function() {
                        showError(mh_ajax.strings.error);
                        refreshTree(); // Revert on error
                    }
                });
            }
        });
    }
    
    function showSuccess(message) {
        showNotice(message, 'success');
    }
    
    function showError(message) {
        showNotice(message, 'error');
    }
    
    function showNotice(message, type) {
        const notice = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
        
        // Manual dismiss
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut();
        });
    }
    
    
})(jQuery);
