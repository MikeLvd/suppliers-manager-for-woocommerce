# Suppliers Manager for WooCommerce v3.0.0

**Version:** 3.0.0  
**Architecture:** Custom Post Type (CPT)  
**Requires PHP:** 8.0+  
**Requires WordPress:** 5.8+  
**Requires WooCommerce:** 5.0+  
**License:** GPL v2 or later  
**Author:** Mike Lvd

## 🚀 What's New in v3.0.0

### Major Architectural Change

Version 3.0.0 represents a complete architectural overhaul, moving from a **Taxonomy-based system** to a **Custom Post Type (CPT) architecture**. This provides significantly enhanced capabilities while maintaining backward compatibility through automatic migration.

### Key Features

#### ✅ Custom Post Type Architecture
- **Professional Supplier Management** - Full-featured supplier profiles
- **Rich Content Editor** - Detailed supplier descriptions with WYSIWYG
- **Featured Images** - Supplier logos and branding support
- **Post Status Control** - Active/Inactive supplier management
- **Revisions Support** - Track all changes to supplier data

#### ✅ Enhanced Data Management
- **Custom Relationship Table** - Efficient many-to-many product-supplier links
- **Primary Supplier** - Designate primary supplier per product
- **Advanced Meta Fields** - Website, internal notes, and more
- **Better Performance** - Optimized queries and indexing

#### ✅ Improved Admin Interface
- **Professional Admin UI** - Custom columns, filtering, and sorting
- **Custom Meta Boxes** - Rich supplier and product management
- **Dashboard Integration** - "At a Glance" widget
- **Bulk Actions Support** - Efficient mass operations

#### ✅ All v2.1.1 Features Maintained
- ✅ Email notifications to suppliers
- ✅ Email history tracking
- ✅ BCC to admin
- ✅ Configurable order status triggers
- ✅ Settings page
- ✅ Translation ready

### Migration from v2.x

**Automatic Migration Included!**

When you upgrade from v2.x to v3.0.0:
1. All supplier taxonomy data is automatically migrated to Custom Post Types
2. All product assignments are preserved in the new relationship table
3. All term meta is converted to post meta
4. Original taxonomy data remains intact (for safety)
5. Migration statistics are logged and displayed

**Migration Process:**
- Detects existing v2.x installation automatically
- Creates new suppliers as posts
- Migrates all meta data (email, phone, address, contact)
- Links all products to new suppliers
- Logs detailed migration report
- Shows admin notice with results

## Installation

### Fresh Installation

1. Upload the plugin ZIP to WordPress
2. Activate the plugin
3. Go to WooCommerce > Suppliers to add suppliers
4. Edit products to assign suppliers
5. Configure settings at WooCommerce > Suppliers Manager

### Upgrading from v2.x

**IMPORTANT: Backup your database before upgrading!**

1. **Backup your database**
2. Deactivate v2.x plugin (optional but recommended)
3. Upload and activate v3.0.0
4. Automatic migration runs on activation
5. Check migration notice for statistics
6. Verify suppliers at WooCommerce > edit.php?post_type=supplier
7. Test email notifications

**What Gets Migrated:**
✅ All supplier names
✅ All supplier emails
✅ All supplier contact information
✅ All product assignments
✅ Email history (if v2.1.x)
✅ Settings and configuration

**What's New After Migration:**
- Suppliers appear as regular posts
- Can add supplier logos/images
- Can write detailed supplier descriptions
- Can set active/inactive status
- Better admin interface

## New Features Guide

### 1. Supplier Logos

Add professional branding to your suppliers:

1. Edit a supplier
2. Click "Set supplier logo" in the Featured Image box
3. Upload or select an image
4. Logo appears in supplier list and can be used in emails

### 2. Rich Supplier Profiles

Create detailed supplier information:

1. Edit a supplier
2. Use the WordPress editor for full descriptions
3. Add formatting, images, and content
4. Information stored and searchable

### 3. Primary Supplier

Designate the main supplier for each product:

1. Edit a product
2. Check suppliers in the Suppliers meta box
3. Select "Set as primary" for the main supplier
4. Primary supplier used for notifications

### 4. Supplier Status Management

Control active suppliers:

1. Edit a supplier
2. Change post status to Draft/Published
3. Draft suppliers won't receive notifications
4. Easily reactivate when needed

### 5. Internal Notes

Keep private notes about suppliers:

1. Edit a supplier
2. Find "Internal Notes" field
3. Add private notes for your team
4. Not visible to suppliers

### 6. Website Links

Store supplier websites:

1. Edit a supplier
2. Add website URL
3. Appears in supplier list
4. Quick reference for your team

## Database Structure

### New Tables

**wp_smfw_product_suppliers** (Relationships)
- `id` - Relationship ID
- `product_id` - WooCommerce product ID
- `supplier_id` - Supplier post ID
- `is_primary` - Primary supplier flag (0/1)
- `created_at` - Creation timestamp

**wp_smfw_email_history** (Email Logs - from v2.1.x)
- Complete email audit trail
- Retained from v2.1.x

### Post Meta (Supplier CPT)

- `_supplier_email` - Email address (required)
- `_supplier_telephone` - Phone number
- `_supplier_address` - Physical address
- `_supplier_contact` - Primary contact person
- `_supplier_website` - Website URL (new in v3.0)
- `_supplier_notes` - Internal notes (new in v3.0)
- `_migrated_from_term_id` - Original taxonomy term ID (migration)

## API Reference

### Functions

```php
// Get all suppliers for a product
$relationships = new \Suppliers_Manager_For_WooCommerce\Supplier_Relationships();
$supplier_ids = $relationships->get_product_suppliers($product_id);

// Get primary supplier
$primary_id = $relationships->get_primary_supplier($product_id);

// Get all products for a supplier
$product_ids = $relationships->get_supplier_products($supplier_id);

// Add relationship
$relationships->add_relationship($product_id, $supplier_id, $is_primary);

// Update all suppliers for a product
$relationships->update_product_suppliers($product_id, $supplier_ids, $primary_id);
```

### Hooks & Filters

All v2.x hooks maintained plus new ones:

```php
// CPT-specific hooks
do_action('smfw_supplier_created', $supplier_id);
do_action('smfw_supplier_updated', $supplier_id);
do_action('smfw_relationship_added', $product_id, $supplier_id);

// Email hooks (unchanged)
do_action('smfw_notify_supplier', $order_id, $order);
```

## Migration Troubleshooting

### Check Migration Status

```php
// Get migration stats
$stats = get_option('smfw_migration_stats');
print_r($stats);
```

### Manual Migration Verification

1. Go to WordPress > edit.php?post_type=supplier
2. Count suppliers (should match v2.x taxonomy terms)
3. Check a few suppliers have correct data
4. Edit a product and verify suppliers are assigned

### Re-run Migration (if needed)

```php
// In wp-admin/plugins.php
// Deactivate and reactivate plugin
// Migration runs again if taxonomy data exists
```

### Check Logs

```php
// WooCommerce > Status > Logs
// Look for 'suppliers-manager-migration' log
```

## Performance

### Optimization

- Indexed relationship table for fast queries
- Efficient product-supplier lookups
- Cached supplier counts
- Optimized admin queries

### Scalability

Tested with:
- ✅ 1,000+ suppliers
- ✅ 10,000+ products
- ✅ 100,000+ relationships
- ✅ Large email history tables

## FAQ

### Is my v2.x data safe?

Yes! The migration:
- **Does not delete** any taxonomy data
- **Creates new** CPT posts alongside existing data
- **Can be reversed** if needed
- **Fully logged** for audit

### Can I go back to v2.x?

Yes, but not recommended:
1. Deactivate v3.0.0
2. Reactivate v2.1.1
3. Original taxonomy data still exists
4. Manual cleanup may be needed

### Will my emails still work?

Yes! Email system is fully compatible:
- Same templates
- Same settings
- Same email history
- Updated to use CPT supplier data

### Do I need to reassign products?

No! All product assignments are automatically migrated to the new relationship system.

### Can I delete the old taxonomy?

After verifying v3.0.0 works:
1. Wait 30 days (safety period)
2. Backup database
3. Manually delete 'supplier' taxonomy terms
4. Not required - can leave for safety

## Changelog

See CHANGELOG.md for complete version history.

### [3.0.0] - 2025-01-XX

**Major Release - CPT Architecture**

- ✨ Custom Post Type architecture
- ✨ Supplier logos/featured images
- ✨ Rich content editor for suppliers
- ✨ Primary supplier designation
- ✨ Supplier status management (active/inactive)
- ✨ Custom relationship table
- ✨ Enhanced admin interface
- ✨ Automatic migration from v2.x
- ✨ Website and notes fields
- ✨ Dashboard "At a Glance" widget
- ✅ All v2.1.1 features maintained
- 🔄 Updated email system for CPT
- 📊 Enhanced admin columns
- 🎨 Improved UI/UX

## Support

- **Documentation**: README.md (this file)
- **Changelog**: CHANGELOG.md
- **Email**: info@goldenbath.gr
- **Website**: https://goldenbath.gr/

## Credits

**Developed by:** Mike Lvd  
**License:** GPL v2 or later  
**Architecture:** Custom Post Type (v3.0.0+)

---

**Upgrade to v3.0.0 and unlock the full power of supplier management!** 🚀
