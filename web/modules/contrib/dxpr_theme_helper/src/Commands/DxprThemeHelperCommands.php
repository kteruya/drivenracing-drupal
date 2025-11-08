<?php

namespace Drupal\dxpr_theme_helper\Commands;

use Consolidation\AnnotatedCommand\CommandError;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * DXPR Theme Helper Drush commands.
 */
final class DxprThemeHelperCommands extends DrushCommands {

  /**
   * Create a DXPR Theme subtheme.
   *
   * @command dxpr:create-subtheme
   * @aliases dxpr-cs
   * @usage dxpr:create-subtheme
   *   Create a subtheme interactively
   * @usage dxpr:create-subtheme my_theme_name
   *   Create a subtheme with machine name 'my_theme_name' (non-interactive)
   * @usage dxpr:create-subtheme my_theme_name --theme-name="My Custom Theme"
   *   Create a subtheme with custom human-readable name (non-interactive)
   */
  #[CLI\Command(name: 'dxpr:create-subtheme', aliases: ['dxpr-cs'])]
  #[CLI\Argument(name: 'machine_name', description: 'The machine name for the subtheme (optional, will prompt if not provided).')]
  #[CLI\Option(name: 'theme-name', description: 'The human-readable name for the subtheme (optional, will prompt if not provided).')]
  public function createSubtheme(
    ?string $machine_name = NULL,
    array $options = [
      'theme-name' => NULL,
    ],
  ) {
    // Interactive mode if no arguments provided.
    if (empty($machine_name)) {
      return $this->interactiveMode();
    }

    // Non-interactive mode with provided arguments.
    return $this->createSubthemeWithArgs($machine_name, $options);
  }

  /**
   * Interactive mode for creating subtheme.
   */
  protected function interactiveMode() {
    $this->output()->writeln('');
    $this->output()->writeln('🎨 <info>DXPR Theme Subtheme Creator</info>');
    $this->output()->writeln('=====================================');
    $this->output()->writeln('');

    // Ask for human-readable name.
    $theme_name = $this->ask('What would you like to name your new theme? (e.g., "My Awesome DXPR Theme")');
    if (empty($theme_name)) {
      return new CommandError('Theme name cannot be empty.');
    }

    // Generate suggested machine name.
    $suggested_machine_name = $this->generateMachineName($theme_name);

    $this->output()->writeln('');
    $this->output()->writeln("Suggested machine name: <comment>{$suggested_machine_name}</comment>");

    // Ask for machine name with suggestion.
    $machine_name = $this->ask('Enter machine name for your theme [' . $suggested_machine_name . ']');
    // Use suggested name if user just pressed Enter.
    if (empty($machine_name)) {
      $machine_name = $suggested_machine_name;
    }

    // Validate machine name.
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $machine_name)) {
      return new CommandError('Machine name must contain only lowercase letters, numbers, and underscores, and must start with a letter.');
    }

    $this->output()->writeln('');
    $this->output()->writeln("Creating theme: <info>{$theme_name}</info> ({$machine_name})");

    // Confirm before creating.
    if (!$this->confirm('Proceed with theme creation?')) {
      $this->output()->writeln('Theme creation cancelled.');
      return 0;
    }

    return $this->createSubthemeInternal($machine_name, $theme_name);
  }

  /**
   * Non-interactive mode with provided arguments.
   */
  protected function createSubthemeWithArgs(string $machine_name, array $options) {
    // Validate machine name.
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $machine_name)) {
      return new CommandError('Machine name must contain only lowercase letters, numbers, and underscores, and must start with a letter.');
    }

    // Set theme name.
    $theme_name = $options['theme-name'] ?: ucwords(str_replace('_', ' ', $machine_name));

    $this->output()->writeln(sprintf('Creating DXPR Theme subtheme: %s (%s)', $theme_name, $machine_name));

    return $this->createSubthemeInternal($machine_name, $theme_name);
  }

  /**
   * Internal method to create subtheme.
   */
  protected function createSubthemeInternal(string $machine_name, string $theme_name) {
    try {
      // Get paths.
      $drupal_root = $this->getDrupalRoot();
      $starterkit_path = $drupal_root . '/themes/contrib/dxpr_theme/dxpr_theme_STARTERKIT';
      $custom_themes_path = $drupal_root . '/themes/custom';
      $new_theme_path = $custom_themes_path . '/' . $machine_name;

      // Check if starterkit exists.
      if (!is_dir($starterkit_path)) {
        return new CommandError('Starterkit not found at: ' . $starterkit_path);
      }

      // Create custom themes directory if it doesn't exist.
      if (!is_dir($custom_themes_path)) {
        mkdir($custom_themes_path, 0755, TRUE);
      }

      // Check if theme already exists.
      if (is_dir($new_theme_path)) {
        return new CommandError('Theme already exists at: ' . $new_theme_path);
      }

      // Copy starterkit to new theme directory.
      $this->output()->writeln('Copying starterkit files...');
      $this->copyRecursive($starterkit_path, $new_theme_path);

      // Rename files.
      $this->output()->writeln('Renaming files...');
      $this->renameFiles($new_theme_path, $machine_name);

      // Replace content in files.
      $this->output()->writeln('Updating file contents...');
      $this->replaceContent($new_theme_path, $machine_name, $theme_name);

      $this->output()->writeln('');
      $this->output()->writeln(sprintf('✅ <info>Subtheme created successfully at: %s</info>', $new_theme_path));
      $this->output()->writeln('');
      $this->output()->writeln('<comment>Next steps:</comment>');
      $this->output()->writeln('1. Visit Appearance administration page and enable your new theme');
      $this->output()->writeln('2. Set it as default theme');
      $this->output()->writeln('3. Clear cache');

      return 0;

    }
    catch (\Exception $e) {
      return new CommandError('Error creating subtheme: ' . $e->getMessage());
    }
  }

  /**
   * Generate machine name from human-readable name.
   */
  protected function generateMachineName(string $human_name): string {
    // Convert to lowercase.
    $machine_name = strtolower($human_name);

    // Replace spaces and special characters with underscores.
    $machine_name = preg_replace('/[^a-z0-9]+/', '_', $machine_name);

    // Remove leading/trailing underscores.
    $machine_name = trim($machine_name, '_');

    // Ensure it starts with a letter.
    if (!preg_match('/^[a-z]/', $machine_name)) {
      $machine_name = 'theme_' . $machine_name;
    }

    // Limit length.
    if (strlen($machine_name) > 50) {
      $machine_name = substr($machine_name, 0, 50);
      $machine_name = rtrim($machine_name, '_');
    }

    return $machine_name;
  }

  /**
   * Get Drupal root directory.
   */
  protected function getDrupalRoot(): string {
    // Try to get from Drush context first.
    $drupal_root = $this->getConfig()->get('drush.drupal.root');
    if ($drupal_root) {
      return $drupal_root;
    }

    // Fallback: assume we're in web directory.
    $current_dir = getcwd();
    if (strpos($current_dir, '/web') !== FALSE) {
      return $current_dir;
    }

    // Last resort: assume current directory is Drupal root.
    return $current_dir;
  }

  /**
   * Copy directory recursively.
   */
  protected function copyRecursive(string $source, string $destination): void {
    if (!is_dir($source)) {
      throw new \Exception("Source directory does not exist: $source");
    }

    if (!is_dir($destination)) {
      mkdir($destination, 0755, TRUE);
    }

    $dir = opendir($source);
    while (($file = readdir($dir)) !== FALSE) {
      if ($file === '.' || $file === '..') {
        continue;
      }

      $source_path = $source . '/' . $file;
      $dest_path = $destination . '/' . $file;

      if (is_dir($source_path)) {
        $this->copyRecursive($source_path, $dest_path);
      }
      else {
        copy($source_path, $dest_path);
      }
    }
    closedir($dir);
  }

  /**
   * Rename files from starterkit to new theme name.
   */
  protected function renameFiles(string $theme_path, string $machine_name): void {
    $files_to_rename = [
      'dxpr_theme_STARTERKIT.info.yml' => $machine_name . '.info.yml',
      'dxpr_theme_STARTERKIT.libraries.yml' => $machine_name . '.libraries.yml',
      'dxpr_theme_STARTERKIT.theme' => $machine_name . '.theme',
    ];

    foreach ($files_to_rename as $old_name => $new_name) {
      $old_path = $theme_path . '/' . $old_name;
      $new_path = $theme_path . '/' . $new_name;
      if (file_exists($old_path)) {
        rename($old_path, $new_path);
      }
    }

    // Rename config files.
    $config_install_path = $theme_path . '/config/install';
    if (is_dir($config_install_path)) {
      $old_settings = $config_install_path . '/dxpr_theme_STARTERKIT.settings.yml';
      $new_settings = $config_install_path . '/' . $machine_name . '.settings.yml';
      if (file_exists($old_settings)) {
        rename($old_settings, $new_settings);
      }
    }

    $config_schema_path = $theme_path . '/config/schema';
    if (is_dir($config_schema_path)) {
      $old_schema = $config_schema_path . '/dxpr_theme_STARTERKIT.schema.yml';
      $new_schema = $config_schema_path . '/' . $machine_name . '.schema.yml';
      if (file_exists($old_schema)) {
        rename($old_schema, $new_schema);
      }
    }
  }

  /**
   * Replace content in files.
   */
  protected function replaceContent(string $theme_path, string $machine_name, string $theme_name): void {
    $files_to_update = [
      $theme_path . '/' . $machine_name . '.info.yml',
      $theme_path . '/' . $machine_name . '.libraries.yml',
      $theme_path . '/' . $machine_name . '.theme',
      $theme_path . '/config/install/' . $machine_name . '.settings.yml',
      $theme_path . '/config/schema/' . $machine_name . '.schema.yml',
    ];

    foreach ($files_to_update as $file_path) {
      if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        if ($content !== FALSE) {
          // Replace dxpr_theme_STARTERKIT with machine_name.
          $content = str_replace('dxpr_theme_STARTERKIT', $machine_name, $content);
          // Replace THEMETITLE with theme_name.
          $content = str_replace('THEMETITLE', $theme_name, $content);
          file_put_contents($file_path, $content);
        }
      }
    }

    // Update CSS file reference in libraries.yml.
    $libraries_file = $theme_path . '/' . $machine_name . '.libraries.yml';
    if (file_exists($libraries_file)) {
      $content = file_get_contents($libraries_file);
      if ($content !== FALSE) {
        // Replace the CSS file path reference.
        $content = str_replace('css/dxpr_theme_subtheme.css', 'css/' . $machine_name . '_subtheme.css', $content);
        file_put_contents($libraries_file, $content);
      }
    }

    // Rename CSS file if it exists.
    $old_css = $theme_path . '/css/dxpr_theme_subtheme.css';
    $new_css = $theme_path . '/css/' . $machine_name . '_subtheme.css';
    if (file_exists($old_css)) {
      rename($old_css, $new_css);
    }
  }

}
