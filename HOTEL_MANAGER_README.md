# Hotel Manager - MetaHotels Core Plugin

## Overview

The Hotel Manager is a comprehensive admin interface for managing hotel inner pages within the MetaHotels Core WordPress plugin. It provides a tree-based view of hotel pages with drag-and-drop reordering, inline editing, and bulk operations.

## Features

### Core Functionality
- **Hotel Selection**: Choose from a dropdown of all top-level hotels
- **Tree View**: Hierarchical display of hotel pages with unlimited nesting
- **Drag & Drop**: Reorder pages within the same parent using jQuery UI Sortable
- **Inline Rename**: Quick rename with slug control options
- **Duplicate Pages**: Create copies of existing pages
- **Delete/Trash**: Move pages to trash
- **Seed Defaults**: Create standard pages (About, Dining, Rooms, Offers, Gallery, Contact)

### User Experience
- **Pinned Hotels**: Pin frequently accessed hotels for quick access
- **Expand/Collapse**: Collapsible tree nodes for better organization
- **Status Badges**: Visual indicators for page status (Published, Draft, Private)
- **Quick Links**: Direct access to Edit, View, and Elementor edit links
- **Responsive Design**: Works on desktop and mobile devices

### Security & Compatibility
- **Nonce Protection**: All AJAX actions are protected with WordPress nonces
- **Capability Checks**: Proper permission validation for all operations
- **Elementor Integration**: Automatic detection and links to Elementor editor
- **Polylang/WPML Ready**: Compatible with multilingual plugins

## Usage

### Accessing Hotel Manager
1. Navigate to **Hotels → Hotel Manager** in the WordPress admin
2. Select a hotel from the dropdown
3. The tree view will load showing all child pages

### Managing Pages

#### Adding New Pages
1. Click **"Add Inner Page"** button
2. Fill in the title and optional slug
3. Choose status (Draft/Published)
4. Click **"Add Page"**

#### Renaming Pages
1. Click the actions menu (⋮) next to any page
2. Select **"Rename"**
3. Enter new title and choose slug mode:
   - **Keep current slug**: Title changes, slug stays the same
   - **Sync slug to title**: Slug automatically updates to match title
   - **Custom slug**: Manually specify the slug

#### Duplicating Pages
1. Select a page by clicking on it
2. Click **"Duplicate"** button, or
3. Use the actions menu → **"Duplicate"**

#### Reordering Pages
1. Drag pages using the drag handle (≡) on the left
2. Drop in the desired position
3. Changes are saved automatically

#### Deleting Pages
1. Use the actions menu (⋮) → **"Delete"**
2. Pages are moved to trash (not permanently deleted)

### Pinning Hotels
1. Select a hotel from the dropdown
2. Click **"Pin Hotel"** button
3. Pinned hotels appear in the toolbar for quick access
4. Click the × to unpin

### Seeding Default Pages
1. Select a hotel
2. Click **"Seed Defaults"**
3. Creates standard pages: About, Dining, Rooms, Offers, Gallery, Contact
4. Only creates pages that don't already exist

## Technical Details

### File Structure
```
lib/
├── functions/
│   └── hotel-manager.php          # Main functionality and AJAX handlers
└── assets/
    ├── hotel-manager.css          # Styling
    └── hotel-manager.js           # JavaScript interactions
```

### AJAX Endpoints
All endpoints use the `mh_` prefix and require proper nonce verification:

- `mh_get_hotels` - Retrieve list of top-level hotels
- `mh_get_tree` - Get hierarchical tree for selected hotel
- `mh_add_child` - Create new child page
- `mh_rename_post` - Rename page with slug options
- `mh_duplicate_post` - Duplicate existing page
- `mh_reorder_siblings` - Update page order
- `mh_move_post` - Move page to different parent (v1.1)
- `mh_seed_defaults` - Create default pages
- `mh_pin_toggle` - Add/remove pinned hotels
- `mh_delete_post` - Move page to trash

### Database Storage
- **Pinned Hotels**: Stored in `user_meta` with key `mh_pinned_hotels`
- **Page Hierarchy**: Uses WordPress native `post_parent` and `menu_order`
- **URLs**: Maintains native hierarchical CPT permalinks

### Browser Compatibility
- Modern browsers with JavaScript enabled
- Graceful degradation for no-JS environments
- Mobile-responsive design

## Customization

### Adding Custom Default Pages
Modify the `$default_pages` array in the `mh_ajax_seed_defaults()` function:

```php
$default_pages = array(
    'about' => 'About',
    'dining' => 'Dining',
    'rooms' => 'Rooms',
    'offers' => 'Offers',
    'gallery' => 'Gallery',
    'contact' => 'Contact',
    'spa' => 'Spa',           // Add custom page
    'events' => 'Events'      // Add custom page
);
```

### Styling Customization
Override CSS classes in your theme or child theme:
- `.mh-hotel-manager` - Main container
- `.mh-tree-node` - Individual page nodes
- `.mh-node-content` - Page content area
- `.mh-actions-menu` - Actions dropdown

### JavaScript Extensions
Hook into the existing JavaScript events:
```javascript
// Custom action after tree loads
$(document).on('mh:treeLoaded', function(event, hotelId) {
    // Your custom code here
});
```

## Troubleshooting

### Common Issues

**Tree not loading:**
- Check browser console for JavaScript errors
- Verify AJAX nonce is valid
- Ensure user has proper capabilities

**Drag & drop not working:**
- Verify jQuery UI is loaded
- Check for JavaScript conflicts
- Ensure proper CSS classes are present

**Pages not saving:**
- Check WordPress debug log
- Verify database permissions
- Ensure proper nonce verification

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Future Enhancements (v1.1)

- **Move Pages**: Drag pages between different parents
- **Bulk Operations**: Select multiple pages for batch actions
- **Search/Filter**: Find specific pages within large trees
- **Export/Import**: Backup and restore page structures
- **Templates**: Save and reuse page structures
- **Analytics**: Track page usage and performance

## Support

For issues or feature requests, please contact the plugin developer or create an issue in the project repository.


