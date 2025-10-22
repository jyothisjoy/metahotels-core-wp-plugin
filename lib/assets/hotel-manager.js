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
        $('#show-trash').off('click').on('click', handleTrashFilter);
        
        // Bulk edit handlers
        $('#bulk-edit').off('click').on('click', showBulkEditModal);
        $('#bulk-delete').off('click').on('click', handleBulkDelete);
        $('#bulk-restore').off('click').on('click', handleBulkRestore);
        $('#bulk-cancel').off('click').on('click', cancelBulkMode);
        $('#bulk-select-toggle').off('click').on('click', toggleBulkMode);
        
        // Modal handlers
        $('#mh-rename-modal .mh-cancel, #mh-add-modal .mh-cancel, #mh-bulk-edit-modal .mh-cancel, #mh-quick-edit-modal .mh-cancel').on('click', hideAllModals);
        $('#slug-mode').on('change', toggleCustomSlugField);
        
        // Form submissions (use off() to prevent duplicate bindings)
        $('#mh-rename-form').off('submit').on('submit', handleRenameSubmit);
        $('#mh-add-form').off('submit').on('submit', handleAddSubmit);
        $('#mh-bulk-edit-form').off('submit').on('submit', handleBulkEditSubmit);
        $('#mh-quick-edit-form').off('submit').on('submit', handleQuickEditSubmit);
        
        // Pin/unpin handlers
        $(document).on('click', '.mh-unpin', handleUnpinHotel);
        $(document).on('click', '#pin-hotel-btn', handlePinHotel);
        
        // Tree event handlers - use off() to prevent duplicate bindings
        $(document).off('click', '.mh-expand-toggle').on('click', '.mh-expand-toggle', toggleNodeExpansion);
        $(document).off('click', '.mh-action').on('click', '.mh-action', handleTreeAction);
        $(document).off('click', '.mh-tree-node').on('click', '.mh-tree-node', selectNode);
        $(document).off('change', '.mh-bulk-select').on('change', '.mh-bulk-select', updateBulkCount);
        
        // Actions dropdown handlers - use more specific delegation
        $(document).off('click', '.mh-actions-toggle').on('click', '.mh-actions-toggle', function(e) {
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
    
    function loadHotelTree(hotelId, includeTrash = false) {
        showLoading();
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_get_tree',
                hotel_id: hotelId,
                include_trash: includeTrash ? 1 : 0,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#mh-tree').html(response.data.html);
                    
                    // Events are handled by document-level delegation, no need to rebind
                    
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
            case 'restore':
                restorePage(postId);
                break;
            case 'delete-permanent':
                deletePermanent(postId);
                break;
            case 'quick-edit':
                showQuickEditModal(postId);
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
    
    function restorePage(postId) {
        if (!confirm('Are you sure you want to restore this page from trash?')) {
            return;
        }
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_restore_post',
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
    
    function deletePermanent(postId) {
        if (!confirm('Are you sure you want to permanently delete this page? This action cannot be undone.')) {
            return;
        }
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_delete_permanent',
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
    
    function handleTrashFilter() {
        const button = $('#show-trash');
        const isActive = button.data('active') === 'true';
        const showTrash = !isActive; // Toggle the state
        
        // Update button state and appearance
        button.data('active', showTrash.toString());
        if (showTrash) {
            button.addClass('button-primary');
            button.find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-yes');
            button.contents().filter(function() {
                return this.nodeType === 3; // Text nodes
            }).remove();
            button.append(' Hide Trashed Items');
        } else {
            button.removeClass('button-primary');
            button.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-trash');
            button.contents().filter(function() {
                return this.nodeType === 3; // Text nodes
            }).remove();
            button.append(' Show Trashed Items');
        }
        
        // Update hotel selector to include trashed hotels
        updateHotelSelector(showTrash);
        
        if (currentHotelId) {
            loadHotelTree(currentHotelId, showTrash);
        }
    }
    
    function updateHotelSelector(includeTrash) {
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_get_hotels_with_trash',
                include_trash: includeTrash ? 1 : 0,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const select = $('#hotel-select');
                    const currentValue = select.val();
                    
                    // Clear existing options
                    select.empty();
                    select.append('<option value="">Choose a hotel...</option>');
                    
                    // Add hotels from response
                    response.data.forEach(function(hotel) {
                        const option = $('<option></option>')
                            .attr('value', hotel.id)
                            .text(hotel.title + (hotel.status === 'trash' ? ' (Trashed)' : ''));
                        
                        if (hotel.id == currentValue) {
                            option.attr('selected', 'selected');
                        }
                        
                        select.append(option);
                    });
                    
                    // Trigger change event to update the display
                    select.trigger('change');
                }
            },
            error: function() {
                showError('Failed to update hotel list');
            }
        });
    }
    
    function showQuickEditModal(postId) {
        const node = $(`.mh-tree-node[data-post-id="${postId}"]`);
        const title = node.find('.mh-node-title').text().replace(' (Hotel Landing)', '');
        
        // Get post data via AJAX
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_get_post_data',
                post_id: postId,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const post = response.data;
                    
                    $('#quick-edit-post-id').val(postId);
                    $('#quick-edit-title').val(post.title);
                    $('#quick-edit-slug').val(post.slug);
                    $('#quick-edit-status').val(post.status);
                    $('#quick-edit-author').val(post.author);
                    
                    // Load parent options
                    loadParentOptions(postId, post.parent);
                    
                    $('#mh-quick-edit-modal').show();
                } else {
                    showError('Failed to load post data.');
                }
            },
            error: function() {
                showError('Failed to load post data.');
            }
        });
    }
    
    function loadParentOptions(excludeId, currentParent) {
        if (!currentHotelId) return;
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_get_parent_options',
                hotel_id: currentHotelId,
                exclude_id: excludeId,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const select = $('#quick-edit-parent');
                    select.empty();
                    select.append('<option value="">— No Change —</option>');
                    select.append('<option value="0">— No Parent —</option>');
                    
                    response.data.forEach(function(option) {
                        select.append(`<option value="${option.value}">${option.text}</option>`);
                    });
                    
                    // Set current parent as selected, or "No Change" if no current parent
                    if (currentParent && currentParent !== '0') {
                        select.val(currentParent);
                    } else {
                        select.val(''); // Default to "No Change"
                    }
                }
            },
            error: function() {
                showError('Failed to load parent options.');
            }
        });
    }
    
    // Bulk edit functionality
    function showBulkEditModal() {
        const selectedIds = getSelectedPostIds();
        if (selectedIds.length === 0) {
            showError('Please select items to edit.');
            return;
        }
        
        $('#bulk-post-ids').val(selectedIds.join(','));
        
        // Load parent options for bulk edit
        loadBulkParentOptions();
        
        $('#mh-bulk-edit-modal').show();
    }
    
    function loadBulkParentOptions() {
        if (!currentHotelId) return;
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_get_parent_options',
                hotel_id: currentHotelId,
                exclude_id: 0, // Don't exclude any items for bulk edit
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const select = $('#bulk-parent');
                    // Keep the "No Change" option and add new options
                    const noChangeOption = select.find('option[value=""]');
                    const noParentOption = select.find('option[value="0"]');
                    select.empty();
                    select.append(noChangeOption);
                    select.append(noParentOption);
                    
                    response.data.forEach(function(option) {
                        select.append(`<option value="${option.value}">${option.text}</option>`);
                    });
                }
            },
            error: function() {
                showError('Failed to load parent options.');
            }
        });
    }
    
    function handleBulkDelete() {
        const selectedIds = getSelectedPostIds();
        if (selectedIds.length === 0) {
            showError('Please select items to delete.');
            return;
        }
        
        if (!confirm(`Are you sure you want to delete ${selectedIds.length} item(s)?`)) {
            return;
        }
        
        // Delete each selected item
        let completed = 0;
        const total = selectedIds.length;
        
        selectedIds.forEach(function(postId) {
            $.ajax({
                url: mh_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mh_delete_post',
                    post_id: postId,
                    nonce: mh_ajax.nonce
                },
                success: function(response) {
                    completed++;
                    if (completed === total) {
                        cancelBulkMode();
                        refreshTree();
                        showSuccess(`${total} item(s) deleted successfully.`);
                    }
                },
                error: function() {
                    completed++;
                    if (completed === total) {
                        cancelBulkMode();
                        refreshTree();
                        showError('Some items could not be deleted.');
                    }
                }
            });
        });
    }
    
    function handleBulkRestore() {
        const selectedIds = getSelectedPostIds();
        if (selectedIds.length === 0) {
            showError('Please select items to restore.');
            return;
        }
        
        if (!confirm(`Are you sure you want to restore ${selectedIds.length} item(s)?`)) {
            return;
        }
        
        // Restore each selected item
        let completed = 0;
        const total = selectedIds.length;
        
        selectedIds.forEach(function(postId) {
            $.ajax({
                url: mh_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mh_restore_post',
                    post_id: postId,
                    nonce: mh_ajax.nonce
                },
                success: function(response) {
                    completed++;
                    if (completed === total) {
                        cancelBulkMode();
                        refreshTree();
                        showSuccess(`${total} item(s) restored successfully.`);
                    }
                },
                error: function() {
                    completed++;
                    if (completed === total) {
                        cancelBulkMode();
                        refreshTree();
                        showError('Some items could not be restored.');
                    }
                }
            });
        });
    }
    
    function toggleBulkMode() {
        const isBulkMode = $('.mh-tree-node').hasClass('bulk-mode');
        
        if (isBulkMode) {
            // Exit bulk mode
            $('.mh-tree-node').removeClass('bulk-mode');
            $('.mh-bulk-select').hide();
            $('.mh-bulk-actions').hide();
            $('.mh-actions').show();
            $('#bulk-select-toggle').removeClass('button-primary');
        } else {
            // Enter bulk mode
            $('.mh-tree-node').addClass('bulk-mode');
            $('.mh-bulk-select').show();
            $('#bulk-select-toggle').addClass('button-primary');
        }
    }
    
    function cancelBulkMode() {
        $('.mh-tree-node').removeClass('bulk-mode');
        $('.mh-bulk-select').prop('checked', false).hide();
        $('.mh-bulk-actions').hide();
        $('.mh-actions').show();
        $('#bulk-select-toggle').removeClass('button-primary');
    }
    
    function getSelectedPostIds() {
        return $('.mh-bulk-select:checked').map(function() {
            return $(this).data('post-id');
        }).get();
    }
    
    function updateBulkCount() {
        const count = getSelectedPostIds().length;
        $('.mh-bulk-count').text(`${count} item${count !== 1 ? 's' : ''} selected`);
        
        if (count > 0) {
            $('.mh-bulk-actions').show();
            $('.mh-actions').hide();
        } else {
            $('.mh-bulk-actions').hide();
            $('.mh-actions').show();
        }
    }
    
    function handleBulkEditSubmit(e) {
        e.preventDefault();
        
        const postIds = $('#bulk-post-ids').val().split(',');
        const status = $('#bulk-status').val();
        const author = $('#bulk-author').val();
        const parent = $('#bulk-parent').val();
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_bulk_edit',
                post_ids: postIds,
                status: status,
                author: author,
                parent: parent,
                nonce: mh_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    hideAllModals();
                    cancelBulkMode();
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
    
    function handleQuickEditSubmit(e) {
        e.preventDefault();
        
        const postId = $('#quick-edit-post-id').val();
        const title = $('#quick-edit-title').val();
        const slug = $('#quick-edit-slug').val();
        const status = $('#quick-edit-status').val();
        const author = $('#quick-edit-author').val();
        const parent = $('#quick-edit-parent').val();
        
        $.ajax({
            url: mh_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mh_quick_edit',
                post_id: postId,
                title: title,
                slug: slug,
                status: status,
                author: author,
                parent: parent,
                nonce: mh_ajax.nonce
            },
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
