# DXPR Theme Helper

A helper module that provides features and tools for DXPR Theme, enhancing the theme's functionality with additional blocks, fields, and administrative features.

## Overview

The DXPR Theme Helper module extends the DXPR Theme with essential functionality including search capabilities, user registration forms, page layout controls, and administrative tools. It serves as a bridge between the theme and Drupal core functionality.

## Features

### 🔍 Full Screen Search Block
- **Configurable Search Provider**: Choose between Core Search and Search API
- **Dynamic URL Configuration**: Customize search URL path and parameter names

**Configuration Options:**
- Search Provider: Core Search or Search API
- Search URL Path: Customizable path (e.g., `/search`)
- Search Parameter: Configurable parameter name (e.g., `search_api_fulltext`, `keys`)

### 👤 User Registration Block
- Provides a dedicated block for user registration forms
- Access control based on user registration settings

### 🎨 Page Layout Fields
The module adds several fields to node entities for enhanced page customization:

#### Body Background Field (`field_dth_body_background`)
- Media reference field for setting custom background images
- Allows content creators to customize page backgrounds

#### Hide Regions Field (`field_dth_hide_regions`)
- Multi-select field to hide specific theme regions
- Available options:
  - Navigation
  - Secondary Header
  - Page Title
  - Hero Region
  - Header
  - Content Top/Bottom
  - Sidebar First/Second
  - Footer

#### Main Content Width Field (`field_dth_main_content_width`)
- Controls content column width and layout
- Options:
  - Full width content
  - Squish content column to 1/3
  - Squish content column to 1/2
  - Squish content column to 2/3
  - Squish content column to 5/6

#### Page Layout Field (`field_dth_page_layout`)
- Choose between full width and boxed layouts
- Options:
  - Full Width
  - Boxed Layout

#### Page Title Background Field (`field_dth_page_title_backgrou`)
- Media reference field for custom page title backgrounds
- Enhances visual appeal of page headers

### 🛠️ Administrative Features

#### DXPR Studio Integration
- Custom admin menu structure under "DXPR Studio"
- Theme settings integration
- Custom theme negotiator for proper theme context

#### Theme Settings
- Enhanced theme configuration pages
- Proper theme context handling during configuration

Drush commands for creating DXPR theme subthemes.

### ⚡ Commands

#### dxpr:create-subtheme

Creates a new DXPR theme subtheme from starterkit.

**Usage:**
```bash
# Interactive mode
drush dxpr:create-subtheme

# Non-interactive mode
drush dxpr:create-subtheme my_theme_name

# With custom name
drush dxpr:create-subtheme my_theme_name --theme-name="My Custom Theme"

# Alias
drush dxpr-cs
```

**What it does:**
- Copies starterkit from `themes/contrib/dxpr_theme/dxpr_theme_STARTERKIT`
- Creates new subtheme in `themes/custom/`
- Renames files and updates content automatically

**Requirements:**
- Drupal 11+
- DXPR Theme module installed
- Starterkit must exist in `themes/contrib/dxpr_theme/dxpr_theme_STARTERKIT`

## Installation

This module is available as a contributed module on Drupal.org. Install it using Composer:

```bash
composer require 'drupal/dxpr_theme_helper:^2.0'
```

## Dependencies

### Required Core Modules
- `node` - For node entity support
- `text` - For text field support
- `media` - For media reference fields
- `media_library` - For media library integration
- `media_library_form_element` - For enhanced media form elements

### Optional Modules
- `search_api` - For advanced search functionality
- `search_api_block` - For Search API block integration

**Note:** For Search API setup and configuration, refer to the [Search API documentation](https://www.drupal.org/docs/contributed-modules/search-api) or watch the [Search API tutorial video](https://youtu.be/6EppiQw21ow?t=1702).

## Configuration

### Full Screen Search Block

1. Navigate to **Structure > Block Layout**
2. Add the "DXPR Theme Full Screen Search" block
3. Configure the block settings:
   - **Search Provider**: Choose between Core Search and Search API
   - **Search URL Path**: Enter the path for your search results page
   - **Search Parameter**: Specify the parameter name used by your search implementation

### Page Layout Fields

The module automatically creates fields for all content types. To use them:

1. Navigate to **Structure > Content types**
2. Edit your desired content type
3. The DXPR Theme Helper fields will be available in the "Manage fields" section
4. Configure field visibility and settings as needed

## Usage Examples

### Search API Integration

If you're using Search API, first set up Search API following the [official documentation](https://www.drupal.org/docs/contributed-modules/search-api) or [tutorial video](https://youtu.be/6EppiQw21ow?t=1702), then configure the block with:
- **Search Provider**: Search API
- **Search URL Path**: `search` (or your custom path)
- **Search Parameter**: `search_api_fulltext` (or your custom parameter)

### Core Search Integration

For standard Drupal search:
- **Search Provider**: Core Search
- Other fields will be hidden automatically

## Technical Details

### Theme Negotiator
The module includes a custom theme negotiator (`DxprThemeSettingsThemeNegotiator`) that ensures theme settings forms use the correct theme context.

### Field Storage
All custom fields are stored as optional configuration, ensuring they're only created when the module is installed.

### JavaScript Integration
The module provides CSS for admin toolbar icons and integrates with the existing DXPR Theme JavaScript functionality.

*This module is designed to work seamlessly with DXPR Theme and provides essential functionality for building modern, responsive Drupal websites.*

## Development

### Linting

```bash
# Run linting checks
docker compose up drupal-lint

# Run linting with auto-fix
docker compose up drupal-lint-auto-fix

# Run Drupal compatibility checks
docker compose up drupal-check
```
