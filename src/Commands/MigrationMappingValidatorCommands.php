<?php

namespace Drupal\migration_mapping_validator\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\migration_mapping_validator\Validator\MappingValidator;

class MigrationMappingValidatorCommands extends DrushCommands {
  private MappingValidator $validator;

  public function __construct(MappingValidator $validator) {
    parent::__construct();
    $this->validator = $validator;
  }

  #[CLI\Command(name: 'migrate:mapping-validate', aliases: ['mmv:validate'])]
  #[CLI\Argument(name: 'migration_ids', description: 'Optional migration IDs to validate (space-separated).')]
  #[CLI\Usage(name: 'drush migrate:mapping-validate', description: 'Validate mappings for all migrations.')]
  #[CLI\Usage(name: 'drush migrate:mapping-validate article node', description: 'Validate mappings for specific migrations.')]
  public function validate(array $migration_ids = []): int {
    $ids = empty($migration_ids) ? NULL : $migration_ids;
    $results = $this->validator->validate($ids);

    if (empty($results)) {
      $this->logger()->warning('No migrations found to validate.');
      return self::EXIT_SUCCESS;
    }

    $hasErrors = FALSE;

    foreach ($results as $migrationId => $result) {
      $status = strtoupper($result['status']);
      $message = $result['message'];
      $checked = $result['checked'];
      $available = $result['available'];

      if ($result['status'] === 'error') {
        $hasErrors = TRUE;
        $missing = implode(', ', $result['missing']);
        $this->logger()->error("[$migrationId] $message Missing: $missing (checked $checked of $available fields). ");
      }
      elseif ($result['status'] === 'warning') {
        $this->logger()->warning("[$migrationId] $message");
      }
      else {
        $this->logger()->success("[$migrationId] $message (checked $checked of $available fields). ");
      }
    }

    return $hasErrors ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

}
