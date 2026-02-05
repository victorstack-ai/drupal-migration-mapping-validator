<?php

namespace Drupal\migration_mapping_validator\Validator;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;

class MappingValidator {
  private MigrationPluginManagerInterface $migrationManager;

  public function __construct(MigrationPluginManagerInterface $migrationManager) {
    $this->migrationManager = $migrationManager;
  }

  /**
   * Validates source mappings for one or more migrations.
   *
   * @param array|null $migrationIds
   *   Optional list of migration IDs. When NULL, validates all migrations.
   *
   * @return array
   *   Validation results keyed by migration ID.
   */
  public function validate(?array $migrationIds = NULL): array {
    $migrations = $this->migrationManager->createInstances($migrationIds);
    $results = [];

    foreach ($migrations as $migrationId => $migration) {
      $results[$migrationId] = $this->validateMigration($migration);
    }

    return $results;
  }

  private function validateMigration(MigrationInterface $migration): array {
    $source = $migration->getSourcePlugin();
    $availableFields = $this->getAvailableFields($source);

    if (empty($availableFields)) {
      return [
        'status' => 'warning',
        'message' => 'Source plugin does not expose fields; skipping validation.',
        'missing' => [],
        'checked' => 0,
        'available' => 0,
      ];
    }

    $referencedFields = $this->collectSourceFields($migration->getProcess());
    $missing = array_values(array_diff($referencedFields, $availableFields));

    if (!empty($missing)) {
      sort($missing);
      return [
        'status' => 'error',
        'message' => 'Missing source fields referenced in process mapping.',
        'missing' => $missing,
        'checked' => count($referencedFields),
        'available' => count($availableFields),
      ];
    }

    return [
      'status' => 'ok',
      'message' => 'All referenced source fields are present.',
      'missing' => [],
      'checked' => count($referencedFields),
      'available' => count($availableFields),
    ];
  }

  private function getAvailableFields($source): array {
    $fields = [];

    if (method_exists($source, 'fields')) {
      try {
        $fields = array_keys($source->fields());
      }
      catch (\Throwable $exception) {
        $fields = [];
      }
    }

    if (method_exists($source, 'getIds')) {
      try {
        $ids = array_keys($source->getIds());
        $fields = array_merge($fields, $ids);
      }
      catch (\Throwable $exception) {
        // Ignore ID discovery failures.
      }
    }

    $fields = array_values(array_unique(array_filter($fields)));
    sort($fields);

    return $fields;
  }

  private function collectSourceFields(array $process): array {
    $fields = [];

    foreach ($process as $definition) {
      if (is_string($definition)) {
        $fields[] = $definition;
        continue;
      }

      if (is_array($definition)) {
        $fields = array_merge($fields, $this->collectSourcesFromDefinition($definition));
      }
    }

    $fields = array_values(array_unique(array_filter($fields)));
    sort($fields);

    return $fields;
  }

  private function collectSourcesFromDefinition(array $definition): array {
    $fields = [];

    if (array_key_exists('source', $definition)) {
      $fields = array_merge($fields, $this->extractSources($definition['source']));
    }

    if (isset($definition['process']) && is_array($definition['process'])) {
      $fields = array_merge($fields, $this->collectSourceFields($definition['process']));
    }

    foreach ($definition as $key => $value) {
      if ($key === 'source' || $key === 'process') {
        continue;
      }
      if (is_array($value)) {
        $fields = array_merge($fields, $this->collectSourcesFromDefinition($value));
      }
    }

    return $fields;
  }

  private function extractSources($source): array {
    if (is_string($source)) {
      return [$source];
    }

    if (is_array($source)) {
      $values = [];
      foreach ($source as $value) {
        if (is_string($value)) {
          $values[] = $value;
        }
      }
      return $values;
    }

    return [];
  }

}
