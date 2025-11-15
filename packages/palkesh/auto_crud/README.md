# Auto CRUD Package

A Laravel package that automatically generates complete CRUD (Create, Read, Update, Delete) functionality with migrations, models, controllers, and blade views.

## Installation

```bash
composer require palkesh/auto_crud
```

Or if developing locally, add to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/palkesh/auto_crud"
        }
    ],
    "require": {
        "palkesh/auto_crud": "*"
    }
}
```

Then run:

```bash
composer update
```

## Usage

Run the artisan command to generate CRUD:

```bash
php artisan make:auto-crud {table_name}
```

### Example

```bash
php artisan make:auto-crud employee
```

The command will prompt you to:

1. **Select build type:**

    - `1` - ONLY API CRUD with validation
    - `2` - WEB with CRUD validation/blade side basic validation

2. **Enter table fields** (format: `name:type` or `name:type:nullable`):
    - Example: `name:string`
    - Example: `email:string:nullable`
    - Example: `age:integer`
    - Example: `bio:text`
    - Press Enter when done

### Supported Field Types

-   `string` - VARCHAR
-   `text` - TEXT
-   `longText` - LONGTEXT
-   `integer` - INTEGER
-   `bigInteger` - BIGINT
-   `float` - FLOAT
-   `double` - DOUBLE
-   `decimal` - DECIMAL
-   `boolean` - BOOLEAN
-   `date` - DATE
-   `datetime` - DATETIME
-   `timestamp` - TIMESTAMP
-   `json` - JSON

### What Gets Generated

#### For API Type (Option 1):

-   ✅ Migration file
-   ✅ Model with fillable attributes
-   ✅ API Controller with:
    -   `index()` - List all resources
    -   `store()` - Create new resource
    -   `show()` - Show single resource
    -   `update()` - Update resource
    -   `destroy()` - Delete resource
-   ✅ Validation rules
-   ✅ API routes in `routes/api.php`

#### For Web Type (Option 2):

-   ✅ Migration file
-   ✅ Model with fillable attributes
-   ✅ Web Controller with all CRUD methods
-   ✅ Blade Views:
    -   `index.blade.php` - List view with pagination
    -   `create.blade.php` - Create form
    -   `edit.blade.php` - Edit form
    -   `show.blade.php` - Detail view
-   ✅ Validation rules (server-side and client-side)
-   ✅ Web routes in `routes/web.php`

### Generated Routes

**API Routes:**

-   `GET /api/{resource}` - List all
-   `POST /api/{resource}` - Create
-   `GET /api/{resource}/{id}` - Show
-   `PUT/PATCH /api/{resource}/{id}` - Update
-   `DELETE /api/{resource}/{id}` - Delete

**Web Routes:**

-   `GET /{resource}` - List all
-   `GET /{resource}/create` - Show create form
-   `POST /{resource}` - Store
-   `GET /{resource}/{id}` - Show
-   `GET /{resource}/{id}/edit` - Show edit form
-   `PUT/PATCH /{resource}/{id}` - Update
-   `DELETE /{resource}/{id}` - Delete

## Features

-   ✅ Automatic migration generation
-   ✅ Model generation with fillable attributes
-   ✅ Controller generation (API or Web)
-   ✅ Blade view generation (for Web type)
-   ✅ Automatic validation rules based on field types
-   ✅ Automatic route registration
-   ✅ Support for nullable fields
-   ✅ Pagination support in list views
-   ✅ Bootstrap 5 styling for views

## Example Output

After running `php artisan make:auto-crud employee` and selecting fields:

-   `name:string`
-   `email:string`
-   `age:integer:nullable`
-   `position:string`

You'll get:

-   Migration: `database/migrations/YYYY_MM_DD_HHMMSS_create_employees_table.php`
-   Model: `app/Models/Employee.php`
-   Controller: `app/Http/Controllers/EmployeeController.php`
-   Views (if Web): `resources/views/employees/*.blade.php`
-   Routes: Added to `routes/api.php` or `routes/web.php`

## Requirements

-   PHP >= 8.2
-   Laravel >= 12.0

## License

MIT
