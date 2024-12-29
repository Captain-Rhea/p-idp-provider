# API Documentation for Storage Provider

## Configuring Environment Variables

The application uses a `.env` file to manage environment-specific configurations. Follow these steps to set up the `.env` file:

### Step 1: Copy the Example File

Copy the `.env.example` file to `.env`:

```bash
cp .env.example .env
```

### Step 2: Update the Configuration

Edit the `.env` file with your specific configurations. Below is an example:

```env
APP_ENV=development

APP_NAME=StorageProvider
APP_VERSION=1.0.0

DB_HOST=127.0.0.1
DB_NAME=storage_db
DB_USER=root
DB_PASS=

API_GUARD=true

FILE_BASE_DOMAIN=http://localhost:8000
```

---

## Setting Up and Using Phinx

The application uses **Phinx** for database migrations. Follow these steps to set it up:

### Step 1: Configure Phinx

Ensure the `phinx.yml` file is properly configured. Below is an example configuration:

```yaml
environments:
  default_migration_table: phinxlog
  default_environment: development

  development:
    adapter: mysql
    host: 127.0.0.1
    name: your_database_name
    user: your_database_user
    pass: your_database_password
    port: 3306
    charset: utf8mb4

  production:
    adapter: mysql
    host: production_host
    name: production_database_name
    user: production_user
    pass: production_password
    port: 3306
    charset: utf8mb4
```

### Step 2: Set Up Phinx Scripts

Add the following `scripts` section to your `composer.json` file to simplify Phinx commands:

```json
"scripts": {
  "start": "php -S localhost:8000 -t public",
  "db-create": "vendor/bin/phinx create",
  "db-migrate": "vendor/bin/phinx migrate",
  "db-rollback": "vendor/bin/phinx rollback",
  "db-status": "vendor/bin/phinx status",
  "db-seed": "vendor/bin/phinx seed:run"
}
```

### Step 3: Create a Migration

Generate a new migration file:

```bash
composer db-create MigrationName
```

This will create a file in the `db/migrations` directory.

### Step 4: Write the Migration

Edit the generated migration file to define the schema changes. Example:

```php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateImagesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('images', ['id' => 'image_id']);
        $table->addColumn('name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('path', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('base_url', 'text', ['null' => true])
            ->addColumn('lazy_url', 'text', ['null' => true])
            ->addColumn('base_size', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('lazy_size', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('uploaded_by', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('uploaded_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => true,
            ])
            ->addColumn('updated_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'null' => true,
            ])
            ->addIndex(['uploaded_by'], ['name' => 'idx_uploaded_by'])
            ->create();
    }
}
```

### Step 5: Run Migrations

Run all pending migrations:

```bash
composer db-migrate
```

### Step 6: Rollback Migrations

If needed, rollback the last migration:

```bash
composer db-rollback
```

### Step 7: Check Migration Status

To view the migration status:

```bash
composer db-status
```

### Step 8: Run Database Seeds

Run seeds to populate the database:

```bash
composer db-seed
```

---

By following these steps, you can easily manage your database schema using Phinx with Composer scripts for convenience.
