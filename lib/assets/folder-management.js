jQuery(document).ready(function($) {
    'use strict';

    const MetahotelsFolder = {
        init: function() {
            this.addBodyClass();
            this.initFolderToggle();
            this.initFolderActions();
            this.initCreateFolder();
            this.initRenameFolder();
            this.initDeleteFolder();
            this.initMoveToFolder();
            this.initContextMenu();
            this.initFolderFilter();
            this.handleAdminMenuToggle();
            this.initDragAndDrop();
        },

        addBodyClass: function() {
            if ($('#metahotels-folder-sidebar').length) {
                $('body').addClass('metahotels-has-folder-sidebar');
            }
        },

        handleAdminMenuToggle: function() {
            // Watch for WordPress admin menu toggle
            $(document).on('wp-collapse-menu', function() {
                // WordPress triggers this event when menu is toggled
                // The CSS will handle the positioning automatically
            });
        },

        initFolderToggle: function() {
            $(document).on('click', '.metahotels-folder-toggle.has-children', function(e) {
                e.stopPropagation();
                const $toggle = $(this);
                const $item = $toggle.closest('.metahotels-folder-item');
                const $children = $item.find('.metahotels-folder-children').first();
                
                $toggle.toggleClass('expanded');
                $children.slideToggle(200);
            });
        },

        initFolderActions: function() {
            $(document).on('click', '.metahotels-folder-content', function(e) {
                if ($(e.target).closest('.metahotels-folder-actions').length) {
                    return;
                }
                
                const $content = $(this);
                const $item = $content.closest('.metahotels-folder-item');
                const folderId = $item.data('folder-id');
                
                // Update active state
                $('.metahotels-folder-content').removeClass('active');
                $content.addClass('active');
                
                // Filter posts by folder
                const postType = metahotelsFolder.post_type;
                const url = new URL(window.location.href);
                
                if (folderId === 0 || $content.hasClass('metahotels-show-all')) {
                    // Show all posts
                    url.searchParams.delete('folder');
                } else {
                    url.searchParams.set('folder', folderId);
                }
                url.searchParams.delete('paged');
                window.location.href = url.toString();
            });
        },

        initCreateFolder: function() {
            $(document).on('click', '.metahotels-add-folder', function() {
                const postType = $(this).data('post-type');
                MetahotelsFolder.showCreateFolderModal(postType);
            });
        },

        showCreateFolderModal: function(postType, parentId = 0) {
            const modal = `
                <div class="metahotels-folder-modal" id="metahotels-create-folder-modal">
                    <div class="metahotels-folder-modal-content">
                        <div class="metahotels-folder-modal-header">
                            <h2>${metahotelsFolder.strings.create_folder}</h2>
                        </div>
                        <div class="metahotels-folder-modal-body">
                            <input type="text" id="metahotels-new-folder-name" placeholder="${metahotelsFolder.strings.folder_name}" autofocus>
                        </div>
                        <div class="metahotels-folder-modal-footer">
                            <button type="button" class="button metahotels-cancel-modal">Cancel</button>
                            <button type="button" class="button button-primary metahotels-save-folder">Create</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            $('#metahotels-create-folder-modal').addClass('active');
            $('#metahotels-new-folder-name').focus();

            // Save folder
            $(document).off('click', '.metahotels-save-folder').on('click', '.metahotels-save-folder', function() {
                const name = $('#metahotels-new-folder-name').val().trim();
                if (!name) {
                    alert('Please enter a folder name');
                    return;
                }

                MetahotelsFolder.createFolder(name, postType, parentId);
            });

            // Cancel
            $(document).off('click', '.metahotels-cancel-modal').on('click', '.metahotels-cancel-modal', function() {
                $('#metahotels-create-folder-modal').remove();
            });

            // Close on outside click
            $('#metahotels-create-folder-modal').on('click', function(e) {
                if ($(e.target).hasClass('metahotels-folder-modal')) {
                    $(this).remove();
                }
            });

            // Enter key
            $('#metahotels-new-folder-name').on('keypress', function(e) {
                if (e.which === 13) {
                    $('.metahotels-save-folder').click();
                }
            });
        },

        createFolder: function(name, postType, parentId = 0) {
            $.ajax({
                url: metahotelsFolder.ajax_url,
                type: 'POST',
                data: {
                    action: 'metahotels_create_folder',
                    nonce: metahotelsFolder.nonce,
                    name: name,
                    parent: parentId,
                    post_type: postType
                },
                success: function(response) {
                    if (response.success) {
                        MetahotelsFolder.refreshFolderTree(postType);
                        $('#metahotels-create-folder-modal').remove();
                    } else {
                        alert(response.data.message || 'Error creating folder');
                    }
                },
                error: function() {
                    alert('Error creating folder');
                }
            });
        },

        initRenameFolder: function() {
            $(document).on('click', '.metahotels-folder-rename', function(e) {
                e.stopPropagation();
                const $item = $(this).closest('.metahotels-folder-item');
                const folderId = $item.data('folder-id');
                const currentName = $item.data('folder-name');
                const postType = metahotelsFolder.post_type;

                MetahotelsFolder.showRenameFolderModal(folderId, currentName, postType);
            });
        },

        showRenameFolderModal: function(folderId, currentName, postType) {
            const modal = `
                <div class="metahotels-folder-modal" id="metahotels-rename-folder-modal">
                    <div class="metahotels-folder-modal-content">
                        <div class="metahotels-folder-modal-header">
                            <h2>${metahotelsFolder.strings.rename_folder}</h2>
                        </div>
                        <div class="metahotels-folder-modal-body">
                            <input type="text" id="metahotels-rename-folder-name" value="${currentName}" autofocus>
                        </div>
                        <div class="metahotels-folder-modal-footer">
                            <button type="button" class="button metahotels-cancel-modal">Cancel</button>
                            <button type="button" class="button button-primary metahotels-save-rename">Save</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            $('#metahotels-rename-folder-modal').addClass('active');
            $('#metahotels-rename-folder-name').focus().select();

            // Save rename
            $(document).off('click', '.metahotels-save-rename').on('click', '.metahotels-save-rename', function() {
                const newName = $('#metahotels-rename-folder-name').val().trim();
                if (!newName) {
                    alert('Please enter a folder name');
                    return;
                }

                MetahotelsFolder.renameFolder(folderId, newName, postType);
            });

            // Cancel
            $(document).off('click', '.metahotels-cancel-modal').on('click', '.metahotels-cancel-modal', function() {
                $('#metahotels-rename-folder-modal').remove();
            });

            // Close on outside click
            $('#metahotels-rename-folder-modal').on('click', function(e) {
                if ($(e.target).hasClass('metahotels-folder-modal')) {
                    $(this).remove();
                }
            });

            // Enter key
            $('#metahotels-rename-folder-name').on('keypress', function(e) {
                if (e.which === 13) {
                    $('.metahotels-save-rename').click();
                }
            });
        },

        renameFolder: function(folderId, newName, postType) {
            $.ajax({
                url: metahotelsFolder.ajax_url,
                type: 'POST',
                data: {
                    action: 'metahotels_rename_folder',
                    nonce: metahotelsFolder.nonce,
                    folder_id: folderId,
                    name: newName,
                    post_type: postType
                },
                success: function(response) {
                    if (response.success) {
                        MetahotelsFolder.refreshFolderTree(postType);
                        $('#metahotels-rename-folder-modal').remove();
                    } else {
                        alert(response.data.message || 'Error renaming folder');
                    }
                },
                error: function() {
                    alert('Error renaming folder');
                }
            });
        },

        initDeleteFolder: function() {
            $(document).on('click', '.metahotels-folder-delete', function(e) {
                e.stopPropagation();
                const $item = $(this).closest('.metahotels-folder-item');
                const folderId = $item.data('folder-id');
                const folderName = $item.data('folder-name');
                const postType = metahotelsFolder.post_type;

                if (confirm(metahotelsFolder.strings.delete_confirm)) {
                    MetahotelsFolder.deleteFolder(folderId, postType);
                }
            });
        },

        deleteFolder: function(folderId, postType) {
            $.ajax({
                url: metahotelsFolder.ajax_url,
                type: 'POST',
                data: {
                    action: 'metahotels_delete_folder',
                    nonce: metahotelsFolder.nonce,
                    folder_id: folderId,
                    post_type: postType
                },
                success: function(response) {
                    if (response.success) {
                        MetahotelsFolder.refreshFolderTree(postType);
                        // If we're viewing this folder, redirect to all posts
                        const urlParams = new URLSearchParams(window.location.search);
                        if (urlParams.get('folder') == folderId) {
                            window.location.href = window.location.pathname;
                        }
                    } else {
                        alert(response.data.message || 'Error deleting folder');
                    }
                },
                error: function() {
                    alert('Error deleting folder');
                }
            });
        },

        initMoveToFolder: function() {
            // Handle bulk action form submission
            $(document).on('submit', '#posts-filter', function(e) {
                const action = $('#doaction').val() || $('#doaction2').val();
                
                if (action === 'metahotels_move_to_folder') {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const selectedPosts = [];
                    $('tbody input[type="checkbox"]:checked').each(function() {
                        const postId = $(this).val();
                        if (postId) {
                            selectedPosts.push(postId);
                        }
                    });

                    if (selectedPosts.length === 0) {
                        alert('Please select at least one post to move');
                        $('#doaction, #doaction2').val('-1');
                        return false;
                    }

                    console.log('Showing move to folder modal for posts:', selectedPosts);
                    MetahotelsFolder.showMoveToFolderModal(selectedPosts);
                    $('#doaction, #doaction2').val('-1');
                    return false;
                } else if (action === 'metahotels_remove_from_folder') {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const selectedPosts = [];
                    $('tbody input[type="checkbox"]:checked').each(function() {
                        const postId = $(this).val();
                        if (postId) {
                            selectedPosts.push(postId);
                        }
                    });

                    if (selectedPosts.length === 0) {
                        alert('Please select at least one post to remove from folder');
                        $('#doaction, #doaction2').val('-1');
                        return false;
                    }

                    if (confirm('Are you sure you want to remove the selected posts from their folders?')) {
                        const postType = metahotelsFolder.post_type;
                        MetahotelsFolder.movePostsToFolder(selectedPosts, 0, postType); // 0 = remove from folder
                    }
                    $('#doaction, #doaction2').val('-1');
                    return false;
                }
            });

            // Also handle when bulk action dropdown changes (for immediate feedback)
            $(document).on('change', '#doaction, #doaction2', function() {
                const action = $(this).val();
                if (action === 'metahotels_move_to_folder') {
                    // Don't prevent default, let the form submit handler deal with it
                    // This ensures the Apply button works correctly
                }
            });

            // Handle individual post move to folder
            $(document).on('click', '.metahotels-move-post-to-folder', function(e) {
                e.preventDefault();
                const postId = $(this).data('post-id');
                const postIds = [postId];
                MetahotelsFolder.showMoveToFolderModal(postIds);
            });
        },

        showMoveToFolderModal: function(postIds) {
            console.log('showMoveToFolderModal called with postIds:', postIds);
            
            const postType = metahotelsFolder.post_type;
            if (!postType) {
                console.error('Post type not available');
                alert('Error: Post type not available');
                return;
            }
            
            // Remove any existing modal first
            $('#metahotels-move-folder-modal').remove();
            
            // Show loading state immediately
            const loadingModal = `
                <div class="metahotels-folder-modal active" id="metahotels-move-folder-modal">
                    <div class="metahotels-folder-modal-content" style="max-width: 500px;">
                        <div class="metahotels-folder-modal-header">
                            <h2>${metahotelsFolder.strings.move_to_folder}</h2>
                        </div>
                        <div class="metahotels-folder-modal-body">
                            <p>Loading folders...</p>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(loadingModal);
            console.log('Loading modal added to DOM');
            
            // Get folder tree HTML
            $.ajax({
                url: metahotelsFolder.ajax_url,
                type: 'POST',
                data: {
                    action: 'metahotels_get_folder_tree',
                    nonce: metahotelsFolder.nonce,
                    post_type: postType
                },
                success: function(response) {
                    if (response.success) {
                        const modal = `
                            <div class="metahotels-folder-modal active" id="metahotels-move-folder-modal">
                                <div class="metahotels-folder-modal-content" style="max-width: 500px;">
                                    <div class="metahotels-folder-modal-header">
                                        <h2>${metahotelsFolder.strings.move_to_folder}</h2>
                                    </div>
                                    <div class="metahotels-folder-modal-body">
                                        <div class="metahotels-folder-tree" style="max-height: 400px; overflow-y: auto;">
                                            <div class="metahotels-folder-item" data-folder-id="0">
                                                <div class="metahotels-folder-content" style="cursor: pointer;">
                                                    <span class="metahotels-folder-icon dashicons dashicons-category"></span>
                                                    <span class="metahotels-folder-name">${metahotelsFolder.strings.remove_from_folder}</span>
                                                </div>
                                            </div>
                                            ${response.data.html || '<p>No folders available. Create a folder first.</p>'}
                                        </div>
                                    </div>
                                    <div class="metahotels-folder-modal-footer">
                                        <button type="button" class="button metahotels-cancel-modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        `;

                        $('#metahotels-move-folder-modal').replaceWith(modal);

                        // Handle folder selection - use event delegation
                        $(document).off('click', '#metahotels-move-folder-modal .metahotels-folder-content').on('click', '#metahotels-move-folder-modal .metahotels-folder-content', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            // Don't trigger if clicking on action buttons
                            if ($(e.target).closest('.metahotels-folder-actions').length) {
                                return;
                            }
                            
                            const $item = $(this).closest('.metahotels-folder-item');
                            let folderId = $item.data('folder-id');
                            
                            // Handle undefined or null
                            if (folderId === undefined || folderId === null) {
                                folderId = 0;
                            }
                            
                            MetahotelsFolder.movePostsToFolder(postIds, folderId, postType);
                        });

                        // Cancel
                        $(document).off('click', '#metahotels-move-folder-modal .metahotels-cancel-modal').on('click', '#metahotels-move-folder-modal .metahotels-cancel-modal', function() {
                            $('#metahotels-move-folder-modal').remove();
                        });

                        // Close on outside click
                        $(document).off('click', '#metahotels-move-folder-modal').on('click', '#metahotels-move-folder-modal', function(e) {
                            if ($(e.target).hasClass('metahotels-folder-modal')) {
                                $(this).remove();
                            }
                        });
                    } else {
                        // Show error
                        $('#metahotels-move-folder-modal .metahotels-folder-modal-body').html('<p style="color: red;">Error loading folders: ' + (response.data.message || 'Unknown error') + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error loading folders:', status, error, xhr.responseText);
                    $('#metahotels-move-folder-modal .metahotels-folder-modal-body').html('<p style="color: red;">Error loading folders. Please check the browser console for details.</p>');
                }
            });
        },

        movePostsToFolder: function(postIds, folderId, postType) {
            // Show loading state
            $('#metahotels-move-folder-modal').addClass('metahotels-folder-loading');
            
            $.ajax({
                url: metahotelsFolder.ajax_url,
                type: 'POST',
                data: {
                    action: 'metahotels_move_posts_to_folder',
                    nonce: metahotelsFolder.nonce,
                    post_ids: postIds,
                    folder_id: folderId,
                    post_type: postType
                },
                success: function(response) {
                    $('#metahotels-move-folder-modal').removeClass('metahotels-folder-loading');
                    
                    if (response.success) {
                        $('#metahotels-move-folder-modal').remove();
                        
                        // Refresh folder tree to update counts
                        MetahotelsFolder.refreshFolderTree(postType);
                        
                        // Show success message briefly before reload
                        const message = $('<div class="notice notice-success is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 100000; padding: 10px 15px;"><p>' + (response.data.message || 'Posts moved successfully') + '</p></div>');
                        $('body').append(message);
                        
                        setTimeout(function() {
                            message.fadeOut(function() {
                                $(this).remove();
                            });
                            // Reload without folder parameter to avoid redirecting to folder
                            const url = new URL(window.location.href);
                            url.searchParams.delete('folder');
                            url.searchParams.delete('paged');
                            window.location.href = url.toString();
                        }, 1000);
                    } else {
                        alert(response.data.message || 'Error moving posts');
                        $('#metahotels-move-folder-modal').removeClass('metahotels-folder-loading');
                    }
                },
                error: function(xhr, status, error) {
                    $('#metahotels-move-folder-modal').removeClass('metahotels-folder-loading');
                    console.error('AJAX Error:', status, error, xhr.responseText);
                    alert('Error moving posts: ' + error + '. Please check the browser console for details.');
                }
            });
        },

        refreshFolderTree: function(postType) {
            $.ajax({
                url: metahotelsFolder.ajax_url,
                type: 'POST',
                data: {
                    action: 'metahotels_get_folder_tree',
                    nonce: metahotelsFolder.nonce,
                    post_type: postType
                },
                success: function(response) {
                    if (response.success) {
                        $('.metahotels-folder-tree').html(response.data.html);
                    }
                }
            });
        },

        initContextMenu: function() {
            // Right-click context menu (optional enhancement)
            $(document).on('contextmenu', '.metahotels-folder-content', function(e) {
                e.preventDefault();
                // Could add context menu here if needed
            });
        },

        initFolderFilter: function() {
            // Highlight active folder
            const urlParams = new URLSearchParams(window.location.search);
            const activeFolder = urlParams.get('folder');
            if (activeFolder) {
                $(`.metahotels-folder-item[data-folder-id="${activeFolder}"] .metahotels-folder-content`).addClass('active');
            }

            // Populate folder dropdown in Quick Edit
            this.initQuickEditFolder();
        },

        initQuickEditFolder: function() {
            // When Quick Edit opens, populate the folder dropdown with current folder
            $(document).on('click', '.editinline', function() {
                const $row = $(this).closest('tr');
                const postId = $row.find('input[type="checkbox"]').val();
                const $folderCell = $row.find('.column-folder');
                
                // Get current folder ID from the cell
                let currentFolderId = 0;
                const $folderLink = $folderCell.find('a');
                if ($folderLink.length) {
                    const href = $folderLink.attr('href');
                    const urlParams = new URLSearchParams(href.split('?')[1]);
                    currentFolderId = urlParams.get('folder') || 0;
                }

                // Set the dropdown value after a short delay (when Quick Edit form is rendered)
                setTimeout(function() {
                    $('.metahotels-quick-edit-folder').val(currentFolderId);
                }, 100);
            });
        },

        initDragAndDrop: function() {
            // Make post rows draggable
            $(document).on('mouseenter', '#the-list tr:not(.no-items)', function() {
                const $row = $(this);
                if (!$row.data('draggable-initialized')) {
                    $row.attr('draggable', 'true');
                    $row.data('draggable-initialized', true);
                    
                    // Add drag handle visual indicator under the checkbox
                    if (!$row.find('.metahotels-drag-handle').length) {
                        const $checkboxCell = $row.find('td.check-column');
                        if ($checkboxCell.length) {
                            $checkboxCell.append('<span class="metahotels-drag-handle dashicons dashicons-move" title="Drag to move to folder"></span>');
                        }
                    }
                }
            });

            // Handle drag start
            $(document).on('dragstart', '#the-list tr[draggable="true"]', function(e) {
                const $row = $(this);
                const postId = $row.find('input[type="checkbox"]').val();
                
                if (!postId) {
                    e.preventDefault();
                    return false;
                }

                // Get all selected posts (checked checkboxes)
                const selectedPosts = [];
                $('tbody input[type="checkbox"]:checked').each(function() {
                    const id = $(this).val();
                    if (id) {
                        selectedPosts.push(id);
                    }
                });

                // If no posts are selected via checkbox, use the dragged post
                const postIds = selectedPosts.length > 0 ? selectedPosts : [postId];
                
                // Mark all dragged rows
                if (selectedPosts.length > 0) {
                    selectedPosts.forEach(function(id) {
                        $('#the-list').find('input[type="checkbox"][value="' + id + '"]').closest('tr').addClass('metahotels-dragging');
                    });
                } else {
                    $row.addClass('metahotels-dragging');
                }

                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/plain', postIds.join(','));
                e.originalEvent.dataTransfer.setData('post-ids', JSON.stringify(postIds));
                e.originalEvent.dataTransfer.setData('post-id', postId); // Keep for backward compatibility
                
                // Create drag image - show count if multiple posts selected
                let dragImage = $row.clone();
                if (postIds.length > 1) {
                    // Create a custom drag image showing multiple posts
                    const dragCount = $('<div style="background: #2271b1; color: #fff; padding: 8px 12px; border-radius: 4px; font-weight: bold; font-size: 14px;">' + postIds.length + ' posts</div>');
                    $('body').append(dragCount);
                    dragCount.css({
                        'position': 'absolute',
                        'top': '-1000px',
                        'left': '-1000px'
                    });
                    e.originalEvent.dataTransfer.setDragImage(dragCount[0], 0, 0);
                    setTimeout(function() {
                        dragCount.remove();
                    }, 0);
                } else {
                    // Single post drag image
                    dragImage.css({
                        'width': $row.width(),
                        'opacity': '0.8',
                        'background': '#fff',
                        'border': '1px solid #2271b1'
                    });
                    $('body').append(dragImage);
                    dragImage.css({
                        'position': 'absolute',
                        'top': '-1000px',
                        'left': '-1000px'
                    });
                    e.originalEvent.dataTransfer.setDragImage(dragImage[0], 0, 0);
                    setTimeout(function() {
                        dragImage.remove();
                    }, 0);
                }
            });

            // Handle drag end
            $(document).on('dragend', '#the-list tr[draggable="true"]', function(e) {
                $('#the-list tr').removeClass('metahotels-dragging');
                $('.metahotels-folder-content').removeClass('metahotels-drag-over');
            });

            // Make folders droppable
            $(document).on('dragover', '.metahotels-folder-content', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.originalEvent.dataTransfer.dropEffect = 'move';
                $(this).addClass('metahotels-drag-over');
            });

            $(document).on('dragleave', '.metahotels-folder-content', function(e) {
                $(this).removeClass('metahotels-drag-over');
            });

            // Handle drop on folder
            $(document).on('drop', '.metahotels-folder-content', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                $(this).removeClass('metahotels-drag-over');
                
                const $item = $(this).closest('.metahotels-folder-item');
                let folderId = $item.data('folder-id');
                
                if (folderId === undefined || folderId === null) {
                    folderId = 0;
                }

                // Get post IDs from dataTransfer
                let postIds = [];
                try {
                    const postIdsJson = e.originalEvent.dataTransfer.getData('post-ids');
                    if (postIdsJson) {
                        postIds = JSON.parse(postIdsJson);
                    }
                } catch (err) {
                    // Fallback to single post ID
                    const postId = e.originalEvent.dataTransfer.getData('post-id') || 
                                  e.originalEvent.dataTransfer.getData('text/plain');
                    if (postId) {
                        // Handle comma-separated IDs
                        postIds = postId.includes(',') ? postId.split(',') : [postId];
                    }
                }

                if (postIds.length > 0) {
                    const postType = metahotelsFolder.post_type;
                    
                    // Convert to integers
                    postIds = postIds.map(function(id) {
                        return parseInt(id);
                    }).filter(function(id) {
                        return id > 0;
                    });
                    
                    if (postIds.length > 0) {
                        // Show feedback
                        const folderName = $(this).find('.metahotels-folder-name').text();
                        const postCount = postIds.length;
                        const postText = postCount === 1 ? 'post' : 'posts';
                        const message = $('<div class="notice notice-info is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 100000; padding: 10px 15px;"><p>Moving ' + postCount + ' ' + postText + ' to "' + folderName + '"...</p></div>');
                        $('body').append(message);
                        
                        MetahotelsFolder.movePostsToFolder(postIds, folderId, postType);
                        
                        setTimeout(function() {
                            message.fadeOut(function() {
                                $(this).remove();
                            });
                        }, 2000);
                    }
                }
            });

            // Also allow dropping on "All Items"
            $(document).on('dragover', '.metahotels-show-all', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.originalEvent.dataTransfer.dropEffect = 'move';
                $(this).addClass('metahotels-drag-over');
            });

            $(document).on('dragleave', '.metahotels-show-all', function(e) {
                $(this).removeClass('metahotels-drag-over');
            });

            $(document).on('drop', '.metahotels-show-all', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                $(this).removeClass('metahotels-drag-over');
                
                // Get post IDs from dataTransfer
                let postIds = [];
                try {
                    const postIdsJson = e.originalEvent.dataTransfer.getData('post-ids');
                    if (postIdsJson) {
                        postIds = JSON.parse(postIdsJson);
                    }
                } catch (err) {
                    // Fallback to single post ID
                    const postId = e.originalEvent.dataTransfer.getData('post-id') || 
                                  e.originalEvent.dataTransfer.getData('text/plain');
                    if (postId) {
                        // Handle comma-separated IDs
                        postIds = postId.includes(',') ? postId.split(',') : [postId];
                    }
                }

                if (postIds.length > 0) {
                    const postType = metahotelsFolder.post_type;
                    
                    // Convert to integers
                    postIds = postIds.map(function(id) {
                        return parseInt(id);
                    }).filter(function(id) {
                        return id > 0;
                    });
                    
                    if (postIds.length > 0) {
                        MetahotelsFolder.movePostsToFolder(postIds, 0, postType); // 0 = remove from folder
                    }
                }
            });
        }
    };

    // Initialize
    MetahotelsFolder.init();
});

