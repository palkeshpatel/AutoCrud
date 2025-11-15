# Relationship Guide for Auto CRUD Package

## Overview

The package now supports automatic relationship generation between tables. This allows you to create foreign key relationships and Eloquent model relationships automatically.

## How to Use Relationships

### Example: Employee and Employee Salary

**Step 1: Create the parent table (Employee)**

```bash
php artisan make:auto-crud employee
```

-   Select build type: 2 (Web)
-   Does this table have a relationship? **No** (first table, no relationship yet)
-   Add fields: name, detail, image, etc.

**Step 2: Create the child table (Employee Salary)**

```bash
php artisan make:auto-crud employee_sal
```

-   Select build type: 2 (Web)
-   Does this table have a relationship? **Yes**
-   Select relationship type: **belongsTo** (Employee Salary belongs to Employee)
-   Select related model: **Employee**
-   Foreign key field name: `employee_id` (or press Enter for default)

## Relationship Types

### 1. BelongsTo (Most Common)

**Use when:** This table has a foreign key pointing to another table.

**Example:** `employee_sal` belongs to `employee`

-   `employee_sal` table has `employee_id` foreign key
-   `employee_sal` model gets: `employee()` method
-   `employee` model gets: `employeeSals()` method (hasMany)

**Generated:**

-   Foreign key in migration: `$table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');`
-   Model relationship: `belongsTo(Employee::class)`
-   Inverse relationship in Employee: `hasMany(EmployeeSal::class)`
-   Dropdown in create/edit forms to select Employee

### 2. HasMany

**Use when:** Another table has a foreign key pointing to this table.

**Example:** `employee` has many `employee_sal`

-   `employee_sal` table has `employee_id` foreign key
-   `employee` model gets: `employeeSals()` method
-   `employee_sal` model gets: `employee()` method (belongsTo)

### 3. HasOne

**Use when:** Another table has a foreign key pointing to this table, but only one record.

**Example:** `employee` has one `employee_profile`

-   `employee_profile` table has `employee_id` foreign key
-   `employee` model gets: `employeeProfile()` method
-   `employee_profile` model gets: `employee()` method (belongsTo)

## What Gets Generated

### Migration

-   Foreign key column with constraint
-   Cascade delete (when parent is deleted, children are deleted)

### Model

-   Relationship method (e.g., `employee()`)
-   Foreign key added to `$fillable` array
-   Inverse relationship added to related model automatically

### Controller

-   Foreign key validation: `'employee_id' => 'required|exists:employees,id'`

### Views (Web only)

-   **Create/Edit forms:** Dropdown to select related record
-   **Show view:** Link to related record
-   **Index view:** Can display related data

## Example Workflow

```bash
# 1. Create Employee table
php artisan make:auto-crud employee
# Answer: No relationship

# 2. Create Employee Salary table
php artisan make:auto-crud employee_sal
# Answer: Yes, belongsTo, Employee, employee_id

# Result:
# - employee_sal table has employee_id foreign key
# - EmployeeSal model has employee() method
# - Employee model has employeeSals() method
# - Forms have Employee dropdown
```

## Usage in Code

After generation, you can use relationships:

```php
// Get employee's salaries
$employee = Employee::find(1);
$salaries = $employee->employeeSals;

// Get salary's employee
$salary = EmployeeSal::find(1);
$employee = $salary->employee;
```

## Notes

-   Relationships are automatically detected and added
-   Foreign keys are properly constrained in migrations
-   Validation ensures foreign keys exist
-   Forms include dropdowns for belongsTo relationships
-   Both models get relationship methods automatically
