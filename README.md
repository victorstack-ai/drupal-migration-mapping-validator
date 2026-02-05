# Migration Mapping Validator

Provides a Drush utility that validates migrate source mappings before import. It checks that fields referenced in `process` mappings exist on the migration source plugin.

## Usage

Validate all migrations:

```bash
drush migrate:mapping-validate
```

Validate specific migrations:

```bash
drush migrate:mapping-validate my_migration another_migration
```

## What It Checks

- Extracts referenced source field names from `process` mappings.
- Compares them against fields exposed by the source plugin (`fields()` + IDs).
- Reports missing fields as errors and exits with a non-zero status.

## Notes

- Some source plugins do not implement `fields()`. Those migrations are reported with a warning and skipped.
- Complex or custom process plugins that reference source properties indirectly may require manual review.
- Pipeline references (values prefixed with `@`) and `constants/*` mappings are ignored during validation.
