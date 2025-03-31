# WordPress Events Calendar Data Backup Tool

This plugin provides a solution for backing up event data from The Events Calendar (and Events Calendar Pro) into custom post meta fields. It's designed to ensure data persistence even if the original plugins are deactivated. This tool helps WordPress site owners free their event data from The Events Calendar plugin dependency. By copying all your event information (including dates, venues, organizers, and categories) into standard WordPress custom fields, it ensures you maintain complete access to your event data even after deactivating the original plugin. Perfect for sites with large event collections looking to reduce plugin dependencies, migrate to a different events solution, or simply gain more control over their data structure. The plugin handles large datasets efficiently through batch processing and provides a seamless transition by registering compatible custom post types, giving you the freedom to move forward while preserving your valuable event history.

## Key Features

- **Post Type Registration**: Automatically registers identical post types and taxonomies when the original plugin is deactivated, maintaining your event data structure
- **Batch Processing**: Handles large event datasets without server timeouts by processing in configurable batches
- **Progress Tracking**: Includes an admin interface with progress bar to monitor backup status
- **Data Persistence**: Preserves all critical event data including:
  - Event date/time information
  - Venue details (address, coordinates, contact info)
  - Organizer information
  - Cost data
  - Event categories and tags
  - Series relationships (Pro feature)
- **Resume Capability**: Can pause and resume backup processes if interrupted
- **WP-CLI Support**: Includes command-line interface for efficient backups on large sites
- **Helper Functions**: Simple API to access backed-up data in your theme or plugins
- **Plugin Deactivation Hook**: Automatically triggers data backup when The Events Calendar is deactivated

## Technical Implementation

The solution uses WordPress scheduled actions to process events in small batches, preventing PHP timeouts and server overload while maintaining a complete record of all event data in custom meta fields that remain accessible even after plugin deactivation. The post type registration ensures your event structure remains intact in the database.

## Usage

After installation add code to functions.php or turn to a plugin, navigate to Events → Backup Event Data to start the backup process. 
For large datasets, the process runs incrementally with visual feedback on progress.
