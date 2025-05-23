=== Custom Fields CSV Importer ===
Contributors: pieroaiello  
Tags: custom fields, csv, importer, acf, post meta  
Requires at least: 5.6  
Tested up to: 6.5  
Requires PHP: 7.4  
Stable tag: 1.0.0  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Import custom fields (meta fields) from a CSV file for one or more existing Custom Post Types (CPTs).

== Description ==

**Custom Fields CSV Importer** allows you to quickly update WordPress custom fields (`postmeta`) for posts, pages, or any custom post type using a CSV file.

**Key Features:**
- Compatible with standard WordPress custom fields (`postmeta`)
- Optimized batch import (100 rows at a time)
- Select one or more post types
- Preview mode showing the first 10 rows with a “View All” option
- Export import results as CSV (updated / not found)
- Clean and native WordPress admin UI

**Current Limitations (Beta version):**
- Currently supports only plain string values. Complex values like ACF serialized arrays are not supported yet.

== Installation ==

1. Download the `.zip` plugin file
2. Go to *Plugins > Add New > Upload Plugin* and select the `.zip` file
3. Activate the plugin
4. You will find a new **Custom Fields CSV Importer** item in the WordPress admin menu

== Frequently Asked Questions ==

= What columns must the CSV include? =

The CSV file must include **at least three columns**:  
- `ID` of the existing post  
- `meta_key` (the custom field name)  
- `meta_value` (the value to be saved)

= Does it work with ACF fields? =

Yes, if the value is a plain string. Support for ACF arrays and serialized data will be added in future versions.

= Where are the import result CSV files stored? =

They are saved in the `wp-content/uploads/cfci/` folder.

== Screenshots ==

1. Start screen with CPT selection and CSV upload
2. Preview mode showing the first rows of the CSV
3. Import confirmation with download links for result CSVs

== Changelog ==

= 1.0.0 =
* Initial release: custom fields import via CSV, preview mode, result export

== Upgrade Notice ==

= 1.0.0 =
First stable release.
