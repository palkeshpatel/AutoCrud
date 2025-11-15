<?php

namespace Palkesh\AutoCrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:auto-crud {table : The name of the table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate complete CRUD (Migration, Model, Controller, Blade Views) with validation';

    /**
     * Store fields for use in controller generation
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Store build type
     *
     * @var string
     */
    protected $buildType = 'api';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tableName = $this->argument('table');
        $modelName = Str::studly(Str::singular($tableName));
        $controllerName = $modelName . 'Controller';
        $resourceName = Str::kebab(Str::plural($tableName));

        // Ask for build type
        $buildTypeChoice = $this->choice(
            'Select build type:',
            ['1' => 'ONLY API CRUD with validation', '2' => 'WEB with CRUD validation/blade side basic validation'],
            '1'
        );

        // Determine build type - choice() returns the VALUE, not the key
        if ($buildTypeChoice === '1' || $buildTypeChoice === 'ONLY API CRUD with validation') {
            $buildType = 'api';
        } else {
            $buildType = 'web';
        }

        // Store build type for later use
        $this->buildType = $buildType;

        if ($buildType === 'api') {
            $this->info('📡 API Mode: Will generate API endpoints only (no views, no HTML input questions)');
        } else {
            $this->info('🌐 Web Mode: Will generate web views and forms');
        }

        // Ask about relationships
        $relationship = null;
        $hasRelationship = $this->confirm('Does this table have a relationship with another table?', false);

        if ($hasRelationship) {
            $relationshipType = $this->choice(
                'Select relationship type:',
                [
                    'belongsTo' => 'Belongs To (This table has foreign key)',
                    'hasMany' => 'Has Many (Other table has foreign key)',
                    'hasOne' => 'Has One (Other table has foreign key)',
                ],
                'belongsTo'
            );

            // Get list of existing models
            $existingModels = $this->getExistingModels();

            if (!empty($existingModels)) {
                $relatedModel = $this->choice(
                    'Select related model:',
                    $existingModels,
                    0
                );

                $relationship = [
                    'type' => $relationshipType,
                    'related_model' => $relatedModel,
                    'related_table' => $this->getTableNameFromModel($relatedModel),
                ];

                // If belongsTo, we'll add foreign key field automatically
                if ($relationshipType === 'belongsTo') {
                    $foreignKeyName = $this->ask('Foreign key field name (e.g., employee_id)', Str::snake($relatedModel) . '_id');
                    $relationship['foreign_key'] = $foreignKeyName;
                } else {
                    // For hasMany/hasOne, ask for foreign key name in related table
                    $foreignKeyName = $this->ask('Foreign key name in related table (e.g., employee_id)', Str::snake($modelName) . '_id');
                    $relationship['foreign_key'] = $foreignKeyName;
                }
            } else {
                $this->warn('No existing models found. Relationship will be skipped.');
            }
        }

        // Ask for fields with interactive menus
        $this->info('Add fields to your table. Press Enter on field name to finish.');
        $fields = [];
        while (true) {
            $fieldName = $this->ask('Field name (or press Enter to finish)');
            if (empty($fieldName)) {
                break;
            }

            // Select data type
            $dataTypeChoices = [
                'Integer',
                'Varchar/String',
                'Text',
                'LongText',
                'DateTime',
                'Date',
                'Timestamp',
                'Boolean',
                'Float',
                'Decimal',
                'BigInteger',
                'JSON',
            ];

            $dataType = $this->choice(
                'Select data type for "' . $fieldName . '":',
                $dataTypeChoices,
                1
            );

            $dataTypeMap = [
                'Integer' => 'integer',
                'Varchar/String' => 'string',
                'Text' => 'text',
                'LongText' => 'longText',
                'DateTime' => 'datetime',
                'Date' => 'date',
                'Timestamp' => 'timestamp',
                'Boolean' => 'boolean',
                'Float' => 'float',
                'Decimal' => 'decimal',
                'BigInteger' => 'bigInteger',
                'JSON' => 'json',
            ];
            $selectedDataType = $dataTypeMap[$dataType];

            // Ask if nullable
            $nullable = $this->confirm('Is this field nullable?', false);

            // Select HTML input type (ONLY for web builds, completely skip for API)
            $htmlInputType = 'text';
            $dropdownOptions = null;

            // CRITICAL: Only ask HTML input type questions for WEB builds
            // For API, we completely skip this - no HTML needed for APIs!
            if ($this->buildType === 'web') {
                $htmlInputChoices = [
                    'Text Box',
                    'Textarea',
                    'File Upload',
                    'Dropdown/Select',
                    'Date Picker',
                    'DateTime Picker',
                    'Checkbox',
                    'Number',
                    'Email',
                ];

                $defaultIndex = $this->getDefaultHtmlInputTypeIndex($selectedDataType);
                $htmlInputTypeChoice = $this->choice(
                    'Select HTML input type for "' . $fieldName . '":',
                    $htmlInputChoices,
                    $defaultIndex
                );

                $htmlInputTypeMap = [
                    'Text Box' => 'text',
                    'Textarea' => 'textarea',
                    'File Upload' => 'file',
                    'Dropdown/Select' => 'select',
                    'Date Picker' => 'date',
                    'DateTime Picker' => 'datetime',
                    'Checkbox' => 'checkbox',
                    'Number' => 'number',
                    'Email' => 'email',
                ];
                $htmlInputType = $htmlInputTypeMap[$htmlInputTypeChoice];

                // If dropdown, ask for options
                if ($htmlInputType === 'select') {
                    $this->info('Enter dropdown options (comma-separated, e.g., Active,Inactive,Pending):');
                    $optionsInput = $this->ask('Options');
                    if (!empty($optionsInput)) {
                        $dropdownOptions = array_map('trim', explode(',', $optionsInput));
                    }
                }
            }
            // For API: Skip HTML input type completely - not needed for API

            $fields[] = [
                'name' => $fieldName,
                'data_type' => $selectedDataType,
                'nullable' => $nullable,
                'html_input_type' => $htmlInputType,
                'dropdown_options' => $dropdownOptions,
            ];
        }

        if (empty($fields)) {
            $this->error('At least one field is required!');
            return 1;
        }

        // Generate Migration
        $this->generateMigration($tableName, $fields, $relationship);

        // Generate Model
        $this->generateModel($modelName, $tableName, $fields, $relationship);

        // Store fields for controller generation
        $this->fields = $fields;

        // Generate Controller
        $this->generateController($controllerName, $modelName, $tableName, $fields, $buildType, $relationship);

        // Generate Blade Views (ONLY for web, NEVER for API)
        if ($this->buildType === 'web') {
            $this->generateBladeViews($modelName, $tableName, $fields, $resourceName, $relationship);
        } else {
            $this->info('⏭️  Skipping view generation (API mode - no views needed)');
        }

        // Register Routes
        $this->registerRoutes($controllerName, $resourceName, $buildType);

        $this->info("✅ CRUD generated successfully for table: {$tableName}");
        $this->info("📝 Model: {$modelName}");
        $this->info("🎮 Controller: {$controllerName}");

        if ($buildType === 'web') {
            $this->info("🌐 Views: resources/views/{$resourceName}");
        }

        $this->info("🛣️  Routes registered in routes/" . ($buildType === 'api' ? 'api.php' : 'web.php'));

        return 0;
    }

    /**
     * Get existing models
     */
    protected function getExistingModels()
    {
        $modelsPath = app_path('Models');
        $models = [];

        if (File::exists($modelsPath)) {
            $files = File::files($modelsPath);
            foreach ($files as $file) {
                $modelName = str_replace('.php', '', $file->getFilename());
                if ($modelName !== 'User') { // Exclude User model
                    $models[] = $modelName;
                }
            }
        }

        return $models;
    }

    /**
     * Get table name from model name
     */
    protected function getTableNameFromModel($modelName)
    {
        // Try to get the actual table name from the model
        $modelPath = app_path('Models/' . $modelName . '.php');
        if (File::exists($modelPath)) {
            $content = File::get($modelPath);
            // Look for protected $table = 'table_name';
            if (preg_match("/protected\s+\\\$table\s*=\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
                return $matches[1];
            }
        }
        // Fallback to plural snake case
        return Str::snake(Str::plural($modelName));
    }

    /**
     * Generate migration file
     */
    protected function generateMigration($tableName, $fields, $relationship = null)
    {
        $migrationName = 'create_' . $tableName . '_table';

        // Delete existing migrations for this table
        $migrationsPath = database_path('migrations');
        if (File::exists($migrationsPath)) {
            $files = File::files($migrationsPath);
            foreach ($files as $file) {
                $fileName = $file->getFilename();
                // Check if this migration is for the same table
                if (strpos($fileName, $migrationName) !== false) {
                    File::delete($file->getPathname());
                    $this->info("🗑️  Deleted existing migration: {$fileName}");
                }
            }
        }

        $timestamp = date('Y_m_d_His');
        $fileName = $timestamp . '_' . $migrationName . '.php';
        $filePath = database_path('migrations/' . $fileName);

        $columns = $this->parseFields($fields);

        $migrationContent = "<?php\n\n";
        $migrationContent .= "use Illuminate\Database\Migrations\Migration;\n";
        $migrationContent .= "use Illuminate\Database\Schema\Blueprint;\n";
        $migrationContent .= "use Illuminate\Support\Facades\Schema;\n\n";
        $migrationContent .= "return new class extends Migration\n";
        $migrationContent .= "{\n";
        $migrationContent .= "    public function up(): void\n";
        $migrationContent .= "    {\n";
        $migrationContent .= "        Schema::create('{$tableName}', function (Blueprint \$table) {\n";
        $migrationContent .= "            \$table->id();\n";

        // Add foreign key if belongsTo relationship
        if ($relationship && $relationship['type'] === 'belongsTo') {
            $foreignKey = $relationship['foreign_key'];
            $relatedTable = $relationship['related_table'];
            $migrationContent .= "            \$table->foreignId('{$foreignKey}')->constrained('{$relatedTable}')->onDelete('cascade');\n";
        }

        foreach ($columns as $column) {
            $migrationContent .= "            \$table->{$column['type']}('{$column['name']}'";
            if (isset($column['nullable']) && $column['nullable']) {
                $migrationContent .= ")->nullable()";
            } else {
                $migrationContent .= ")";
            }
            if (isset($column['default'])) {
                $migrationContent .= "->default({$column['default']})";
            }
            $migrationContent .= ";\n";
        }

        $migrationContent .= "            \$table->timestamps();\n";
        $migrationContent .= "        });\n";
        $migrationContent .= "    }\n\n";
        $migrationContent .= "    public function down(): void\n";
        $migrationContent .= "    {\n";
        $migrationContent .= "        Schema::dropIfExists('{$tableName}');\n";
        $migrationContent .= "    }\n";
        $migrationContent .= "};\n";

        File::put($filePath, $migrationContent);
        $this->info("✅ Migration created: {$fileName}");
    }

    /**
     * Generate model file
     */
    protected function generateModel($modelName, $tableName, $fields, $relationship = null)
    {
        $filePath = app_path('Models/' . $modelName . '.php');

        $columns = $this->parseFields($fields);
        $fillable = array_map(fn($col) => "'{$col['name']}'", $columns);

        // Add foreign key to fillable if belongsTo
        if ($relationship && $relationship['type'] === 'belongsTo') {
            $fillable[] = "'{$relationship['foreign_key']}'";
        }

        $modelContent = "<?php\n\n";
        $modelContent .= "namespace App\Models;\n\n";
        $modelContent .= "use Illuminate\Database\Eloquent\Model;\n";
        $modelContent .= "use Illuminate\Database\Eloquent\Factories\HasFactory;\n\n";
        $modelContent .= "class {$modelName} extends Model\n";
        $modelContent .= "{\n";
        $modelContent .= "    use HasFactory;\n\n";
        $modelContent .= "    protected \$table = '{$tableName}';\n\n";
        $modelContent .= "    protected \$fillable = [\n";
        foreach ($fillable as $field) {
            $modelContent .= "        {$field},\n";
        }
        $modelContent .= "    ];\n";

        // Add relationship methods
        if ($relationship) {
            $relatedModel = $relationship['related_model'];

            if ($relationship['type'] === 'belongsTo') {
                $methodName = Str::camel($relatedModel);
                $modelContent .= "\n";
                $modelContent .= "    /**\n";
                $modelContent .= "     * Get the {$relatedModel} that owns this {$modelName}.\n";
                $modelContent .= "     */\n";
                $modelContent .= "    public function {$methodName}()\n";
                $modelContent .= "    {\n";
                $modelContent .= "        return \$this->belongsTo({$relatedModel}::class, '{$relationship['foreign_key']}');\n";
                $modelContent .= "    }\n";
            } elseif ($relationship['type'] === 'hasMany') {
                $methodName = Str::camel(Str::plural($modelName));
                $modelContent .= "\n";
                $modelContent .= "    /**\n";
                $modelContent .= "     * Get all {$modelName} records for this {$relatedModel}.\n";
                $modelContent .= "     */\n";
                $modelContent .= "    public function {$methodName}()\n";
                $modelContent .= "    {\n";
                $modelContent .= "        return \$this->hasMany({$modelName}::class, '{$relationship['foreign_key']}');\n";
                $modelContent .= "    }\n";
            } elseif ($relationship['type'] === 'hasOne') {
                $methodName = Str::camel($modelName);
                $modelContent .= "\n";
                $modelContent .= "    /**\n";
                $modelContent .= "     * Get the {$modelName} record for this {$relatedModel}.\n";
                $modelContent .= "     */\n";
                $modelContent .= "    public function {$methodName}()\n";
                $modelContent .= "    {\n";
                $modelContent .= "        return \$this->hasOne({$modelName}::class, '{$relationship['foreign_key']}');\n";
                $modelContent .= "    }\n";
            }
        }

        $modelContent .= "}\n";

        File::put($filePath, $modelContent);
        $this->info("✅ Model created: {$modelName}");

        // Update related model with inverse relationship
        if ($relationship) {
            $this->updateRelatedModel($relationship, $modelName, $tableName);
        }
    }

    /**
     * Update related model with inverse relationship
     */
    protected function updateRelatedModel($relationship, $currentModelName, $currentTableName)
    {
        $relatedModelName = $relationship['related_model'];
        $relatedModelPath = app_path('Models/' . $relatedModelName . '.php');

        if (!File::exists($relatedModelPath)) {
            return;
        }

        $content = File::get($relatedModelPath);

        // Check if relationship already exists
        $methodName = Str::camel($currentTableName);
        if (strpos($content, "function {$methodName}()") !== false) {
            return; // Relationship already exists
        }

        // Add inverse relationship before the closing brace
        $inverseRelationship = "\n";

        if ($relationship['type'] === 'belongsTo') {
            // Current model belongsTo related, so related hasMany current
            $methodName = Str::camel(Str::plural($currentTableName));
            $inverseRelationship .= "    /**\n";
            $inverseRelationship .= "     * Get all {$currentModelName} records for this {$relatedModelName}.\n";
            $inverseRelationship .= "     */\n";
            $inverseRelationship .= "    public function {$methodName}()\n";
            $inverseRelationship .= "    {\n";
            $inverseRelationship .= "        return \$this->hasMany({$currentModelName}::class, '{$relationship['foreign_key']}');\n";
            $inverseRelationship .= "    }\n";
        } elseif ($relationship['type'] === 'hasMany') {
            // Current model hasMany related, so related belongsTo current
            $methodName = Str::camel($currentModelName);
            $inverseRelationship .= "    /**\n";
            $inverseRelationship .= "     * Get the {$currentModelName} that owns this {$relatedModelName}.\n";
            $inverseRelationship .= "     */\n";
            $inverseRelationship .= "    public function {$methodName}()\n";
            $inverseRelationship .= "    {\n";
            $inverseRelationship .= "        return \$this->belongsTo({$currentModelName}::class, '{$relationship['foreign_key']}');\n";
            $inverseRelationship .= "    }\n";
        } elseif ($relationship['type'] === 'hasOne') {
            // Current model hasOne related, so related belongsTo current
            $methodName = Str::camel($currentModelName);
            $inverseRelationship .= "    /**\n";
            $inverseRelationship .= "     * Get the {$currentModelName} that owns this {$relatedModelName}.\n";
            $inverseRelationship .= "     */\n";
            $inverseRelationship .= "    public function {$methodName}()\n";
            $inverseRelationship .= "    {\n";
            $inverseRelationship .= "        return \$this->belongsTo({$currentModelName}::class, '{$relationship['foreign_key']}');\n";
            $inverseRelationship .= "    }\n";
        }

        // Insert before the closing brace
        $content = str_replace('}', $inverseRelationship . '}', $content);

        File::put($relatedModelPath, $content);
        $this->info("✅ Updated {$relatedModelName} model with inverse relationship");
    }

    /**
     * Generate controller file
     */
    protected function generateController($controllerName, $modelName, $tableName, $fields, $buildType, $relationship = null)
    {
        $filePath = app_path('Http/Controllers/' . $controllerName . '.php');

        $columns = $this->parseFields($fields);
        $validationRules = $this->generateValidationRules($columns, $relationship);

        $controllerContent = "<?php\n\n";
        $controllerContent .= "namespace App\Http\Controllers;\n\n";
        $controllerContent .= "use App\Models\\{$modelName};\n";
        $controllerContent .= "use Illuminate\Http\Request;\n";
        if ($buildType === 'api') {
            $controllerContent .= "use Illuminate\Http\JsonResponse;\n";
        }
        $controllerContent .= "\n";
        $controllerContent .= "class {$controllerName} extends Controller\n";
        $controllerContent .= "{\n";

        if ($buildType === 'api') {
            // API Methods
            $controllerContent .= $this->generateApiMethods($modelName, $validationRules);
        } else {
            // Web Methods
            $controllerContent .= $this->generateWebMethods($modelName, $tableName, $validationRules);
        }

        $controllerContent .= "}\n";

        File::put($filePath, $controllerContent);
        $this->info("✅ Controller created: {$controllerName}");
    }

    /**
     * Generate API methods
     */
    protected function generateApiMethods($modelName, $validationRules)
    {
        $resourceName = Str::kebab(Str::plural(class_basename($modelName)));

        $methods = "    /**\n";
        $methods .= "     * Display a listing of the resource.\n";
        $methods .= "     */\n";
        $methods .= "    public function index(): JsonResponse\n";
        $methods .= "    {\n";
        $methods .= "        \$items = {$modelName}::all();\n";
        $methods .= "        return response()->json(['data' => \$items], 200);\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Store a newly created resource in storage.\n";
        $methods .= "     */\n";
        $methods .= "    public function store(Request \$request): JsonResponse\n";
        $methods .= "    {\n";
        $methods .= "        \$validated = \$request->validate([\n";
        foreach ($validationRules as $field => $rules) {
            $methods .= "            '{$field}' => '{$rules}',\n";
        }
        $methods .= "        ]);\n\n";
        $methods .= "        \$item = {$modelName}::create(\$validated);\n";
        $methods .= "        return response()->json(['data' => \$item, 'message' => 'Created successfully'], 201);\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Display the specified resource.\n";
        $methods .= "     */\n";
        $methods .= "    public function show({$modelName} \${$resourceName}): JsonResponse\n";
        $methods .= "    {\n";
        $methods .= "        return response()->json(['data' => \${$resourceName}], 200);\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Update the specified resource in storage.\n";
        $methods .= "     */\n";
        $methods .= "    public function update(Request \$request, {$modelName} \${$resourceName}): JsonResponse\n";
        $methods .= "    {\n";
        $methods .= "        \$validated = \$request->validate([\n";
        foreach ($validationRules as $field => $rules) {
            $methods .= "            '{$field}' => '{$rules}',\n";
        }
        $methods .= "        ]);\n\n";
        $methods .= "        \${$resourceName}->update(\$validated);\n";
        $methods .= "        return response()->json(['data' => \${$resourceName}, 'message' => 'Updated successfully'], 200);\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Remove the specified resource from storage.\n";
        $methods .= "     */\n";
        $methods .= "    public function destroy({$modelName} \${$resourceName}): JsonResponse\n";
        $methods .= "    {\n";
        $methods .= "        \${$resourceName}->delete();\n";
        $methods .= "        return response()->json(['message' => 'Deleted successfully'], 200);\n";
        $methods .= "    }\n";

        return $methods;
    }

    /**
     * Generate Web methods
     */
    protected function generateWebMethods($modelName, $tableName, $validationRules)
    {
        $resourceName = Str::kebab(Str::plural($tableName));
        $variableName = Str::singular($resourceName);

        $methods = "    /**\n";
        $methods .= "     * Display a listing of the resource.\n";
        $methods .= "     */\n";
        $methods .= "    public function index()\n";
        $methods .= "    {\n";
        $methods .= "        \${$resourceName} = {$modelName}::latest()->paginate(10);\n";
        $methods .= "        return view('{$resourceName}.index', compact('{$resourceName}'));\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Show the form for creating a new resource.\n";
        $methods .= "     */\n";
        $methods .= "    public function create()\n";
        $methods .= "    {\n";
        $methods .= "        return view('{$resourceName}.create');\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Store a newly created resource in storage.\n";
        $methods .= "     */\n";
        $methods .= "    public function store(Request \$request)\n";
        $methods .= "    {\n";
        $methods .= "        \$validated = \$request->validate([\n";
        foreach ($validationRules as $field => $rules) {
            $methods .= "            '{$field}' => '{$rules}',\n";
        }
        $methods .= "        ]);\n\n";

        // Handle file uploads
        $columns = $this->parseFields($this->fields ?? []);
        foreach ($columns as $column) {
            if (($column['html_input_type'] ?? 'text') === 'file') {
                $fieldName = $column['name'];
                $methods .= "        // Handle file upload for {$fieldName}\n";
                $methods .= "        if (\$request->hasFile('{$fieldName}')) {\n";
                $methods .= "            \$validated['{$fieldName}'] = \$request->file('{$fieldName}')->store('{$resourceName}', 'public');\n";
                $methods .= "        }\n\n";
            }
        }

        $methods .= "        {$modelName}::create(\$validated);\n";
        $methods .= "        return redirect()->route('{$resourceName}.index')->with('success', 'Created successfully');\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Display the specified resource.\n";
        $methods .= "     */\n";
        $methods .= "    public function show({$modelName} \${$variableName})\n";
        $methods .= "    {\n";
        $methods .= "        return view('{$resourceName}.show', compact('{$variableName}'));\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Show the form for editing the specified resource.\n";
        $methods .= "     */\n";
        $methods .= "    public function edit({$modelName} \${$variableName})\n";
        $methods .= "    {\n";
        $methods .= "        return view('{$resourceName}.edit', compact('{$variableName}'));\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Update the specified resource in storage.\n";
        $methods .= "     */\n";
        $methods .= "    public function update(Request \$request, {$modelName} \${$variableName})\n";
        $methods .= "    {\n";
        $methods .= "        \$validated = \$request->validate([\n";

        // For update, make file fields optional (sometimes)
        $columns = $this->parseFields($this->fields ?? []);
        foreach ($validationRules as $field => $rules) {
            // Check if this is a file field
            $isFileField = false;
            foreach ($columns as $column) {
                if ($column['name'] === $field && ($column['html_input_type'] ?? 'text') === 'file') {
                    $isFileField = true;
                    break;
                }
            }

            // For file fields in update, replace 'required' with 'sometimes'
            if ($isFileField) {
                $rules = str_replace('required|', 'sometimes|', $rules);
            }
            $methods .= "            '{$field}' => '{$rules}',\n";
        }
        $methods .= "        ]);\n\n";

        // Handle file uploads for update
        $columns = $this->parseFields($this->fields ?? []);
        foreach ($columns as $column) {
            if (($column['html_input_type'] ?? 'text') === 'file') {
                $fieldName = $column['name'];
                $methods .= "        // Handle file upload for {$fieldName}\n";
                $methods .= "        if (\$request->hasFile('{$fieldName}')) {\n";
                $methods .= "            // Delete old file if exists\n";
                $methods .= "            if (\${$variableName}->{$fieldName}) {\n";
                $methods .= "                \\Storage::disk('public')->delete(\${$variableName}->{$fieldName});\n";
                $methods .= "            }\n";
                $methods .= "            \$validated['{$fieldName}'] = \$request->file('{$fieldName}')->store('{$resourceName}', 'public');\n";
                $methods .= "        } else {\n";
                $methods .= "            // Keep existing file if no new file uploaded\n";
                $methods .= "            unset(\$validated['{$fieldName}']);\n";
                $methods .= "        }\n\n";
            }
        }

        $methods .= "        \${$variableName}->update(\$validated);\n";
        $methods .= "        return redirect()->route('{$resourceName}.index')->with('success', 'Updated successfully');\n";
        $methods .= "    }\n\n";

        $methods .= "    /**\n";
        $methods .= "     * Remove the specified resource from storage.\n";
        $methods .= "     */\n";
        $methods .= "    public function destroy({$modelName} \${$variableName})\n";
        $methods .= "    {\n";
        $methods .= "        \${$variableName}->delete();\n";
        $methods .= "        return redirect()->route('{$resourceName}.index')->with('success', 'Deleted successfully');\n";
        $methods .= "    }\n";

        return $methods;
    }

    /**
     * Generate Blade views
     */
    protected function generateBladeViews($modelName, $tableName, $fields, $resourceName, $relationship = null)
    {
        $viewPath = resource_path('views/' . $resourceName);
        if (!File::exists($viewPath)) {
            File::makeDirectory($viewPath, 0755, true);
        }

        $columns = $this->parseFields($fields);
        $variableName = Str::singular($resourceName);

        // Index view
        $this->generateIndexView($viewPath, $resourceName, $columns, $variableName, $relationship);

        // Create view
        $this->generateCreateView($viewPath, $resourceName, $columns, $relationship);

        // Edit view
        $this->generateEditView($viewPath, $resourceName, $columns, $variableName, $relationship);

        // Show view
        $this->generateShowView($viewPath, $resourceName, $columns, $variableName, $relationship);

        $this->info("✅ Blade views created in: resources/views/{$resourceName}");
    }

    /**
     * Generate index view
     */
    protected function generateIndexView($viewPath, $resourceName, $columns, $variableName, $relationship = null)
    {
        $content = "@extends('layouts.app')\n\n";
        $content .= "@section('content')\n";
        $content .= "<div class=\"container\">\n";
        $content .= "    <div class=\"row\">\n";
        $content .= "        <div class=\"col-12\">\n";
        $content .= "            <h1>" . Str::title($resourceName) . "</h1>\n";
        $content .= "            @if(session('success'))\n";
        $content .= "                <div class=\"alert alert-success\">{{ session('success') }}</div>\n";
        $content .= "            @endif\n";
        $content .= "            <a href=\"{{ route('{$resourceName}.create') }}\" class=\"btn btn-primary mb-3\">Create New</a>\n";
        $content .= "            <table class=\"table table-striped\">\n";
        $content .= "                <thead>\n";
        $content .= "                    <tr>\n";
        $content .= "                        <th>ID</th>\n";
        foreach ($columns as $column) {
            $content .= "                        <th>" . Str::title(str_replace('_', ' ', $column['name'])) . "</th>\n";
        }
        $content .= "                        <th>Actions</th>\n";
        $content .= "                    </tr>\n";
        $content .= "                </thead>\n";
        $content .= "                <tbody>\n";
        $content .= "                    @forelse(\${$resourceName} as \${$variableName})\n";
        $content .= "                        <tr>\n";
        $content .= "                            <td>{{ \${$variableName}->id }}</td>\n";
        foreach ($columns as $column) {
            $fieldName = $column['name'];
            $htmlInputType = $column['html_input_type'] ?? 'text';

            // Check if this is a file/image field
            if ($htmlInputType === 'file') {
                // Check if it's likely an image (by field name)
                if (
                    stripos($fieldName, 'image') !== false ||
                    stripos($fieldName, 'photo') !== false ||
                    stripos($fieldName, 'picture') !== false ||
                    stripos($fieldName, 'avatar') !== false
                ) {
                    // Display as image thumbnail
                    $content .= "                            <td>\n";
                    $content .= "                                @if(\${$variableName}->{$fieldName})\n";
                    $content .= "                                    <img src=\"{{ asset('storage/' . \${$variableName}->{$fieldName}) }}\" alt=\"{$fieldName}\" class=\"img-thumbnail\" style=\"max-width: 50px; max-height: 50px; object-fit: cover;\">\n";
                    $content .= "                                @else\n";
                    $content .= "                                    <span class=\"text-muted\">No image</span>\n";
                    $content .= "                                @endif\n";
                    $content .= "                            </td>\n";
                } else {
                    // Display as file link
                    $content .= "                            <td>\n";
                    $content .= "                                @if(\${$variableName}->{$fieldName})\n";
                    $content .= "                                    <a href=\"{{ asset('storage/' . \${$variableName}->{$fieldName}) }}\" target=\"_blank\" class=\"btn btn-sm btn-outline-primary\">View File</a>\n";
                    $content .= "                                @else\n";
                    $content .= "                                    <span class=\"text-muted\">No file</span>\n";
                    $content .= "                                @endif\n";
                    $content .= "                            </td>\n";
                }
            } else {
                // Regular field - display as text
                $content .= "                            <td>{{ \${$variableName}->{$fieldName} }}</td>\n";
            }
        }
        $content .= "                            <td>\n";
        $content .= "                                <a href=\"{{ route('{$resourceName}.show', \${$variableName}) }}\" class=\"btn btn-sm btn-info\">View</a>\n";
        $content .= "                                <a href=\"{{ route('{$resourceName}.edit', \${$variableName}) }}\" class=\"btn btn-sm btn-warning\">Edit</a>\n";
        $content .= "                                <form action=\"{{ route('{$resourceName}.destroy', \${$variableName}) }}\" method=\"POST\" class=\"d-inline\">\n";
        $content .= "                                    @csrf\n";
        $content .= "                                    @method('DELETE')\n";
        $content .= "                                    <button type=\"submit\" class=\"btn btn-sm btn-danger\" onclick=\"return confirm('Are you sure?')\">Delete</button>\n";
        $content .= "                                </form>\n";
        $content .= "                            </td>\n";
        $content .= "                        </tr>\n";
        $content .= "                    @empty\n";
        $content .= "                        <tr><td colspan=\"" . (count($columns) + 2) . "\" class=\"text-center\">No records found</td></tr>\n";
        $content .= "                    @endforelse\n";
        $content .= "                </tbody>\n";
        $content .= "            </table>\n";
        $content .= "            {{ \${$resourceName}->links() }}\n";
        $content .= "        </div>\n";
        $content .= "    </div>\n";
        $content .= "</div>\n";
        $content .= "@endsection\n";

        File::put($viewPath . '/index.blade.php', $content);
    }

    /**
     * Generate create view
     */
    protected function generateCreateView($viewPath, $resourceName, $columns, $relationship = null)
    {
        $needsDatepicker = false;
        $content = "@extends('layouts.app')\n\n";
        $content .= "@section('content')\n";
        $content .= "<div class=\"container\">\n";
        $content .= "    <div class=\"row\">\n";
        $content .= "        <div class=\"col-12\">\n";
        $content .= "            <h1>Create " . Str::title(Str::singular($resourceName)) . "</h1>\n";
        $content .= "            <form action=\"{{ route('{$resourceName}.store') }}\" method=\"POST\" enctype=\"multipart/form-data\">\n";
        $content .= "                @csrf\n";

        // Add relationship dropdown if belongsTo
        if ($relationship && $relationship['type'] === 'belongsTo') {
            $relatedModel = $relationship['related_model'];
            $relatedTable = $relationship['related_table'];
            $foreignKey = $relationship['foreign_key'];
            $relatedVariable = Str::camel(Str::plural($relatedModel));
            $relatedLabel = Str::title(str_replace('_', ' ', $relatedModel));

            $content .= "                <div class=\"mb-3\">\n";
            $content .= "                    <label for=\"{$foreignKey}\" class=\"form-label\">{$relatedLabel}</label>\n";
            $content .= "                    <select class=\"form-control @error('{$foreignKey}') is-invalid @enderror\" id=\"{$foreignKey}\" name=\"{$foreignKey}\" required>\n";
            $content .= "                        <option value=\"\">Select {$relatedLabel}</option>\n";
            $content .= "                        @foreach(\\App\\Models\\{$relatedModel}::all() as \$item)\n";
            $content .= "                            <option value=\"{{ \$item->id }}\" {{ old('{$foreignKey}') == \$item->id ? 'selected' : '' }}>{{ \$item->name ?? \$item->id }}</option>\n";
            $content .= "                        @endforeach\n";
            $content .= "                    </select>\n";
            $content .= "                    @error('{$foreignKey}')\n";
            $content .= "                        <div class=\"invalid-feedback\">{{ \$message }}</div>\n";
            $content .= "                    @enderror\n";
            $content .= "                </div>\n";
        }

        foreach ($columns as $column) {
            $fieldName = $column['name'];
            $label = Str::title(str_replace('_', ' ', $fieldName));
            $required = !isset($column['nullable']) || !$column['nullable'] ? 'required' : '';
            $htmlInputType = $column['html_input_type'] ?? 'text';

            $content .= "                <div class=\"mb-3\">\n";
            $content .= "                    <label for=\"{$fieldName}\" class=\"form-label\">{$label}</label>\n";

            // Generate input based on HTML input type
            if ($htmlInputType === 'textarea') {
                $content .= "                    <textarea class=\"form-control @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" rows=\"3\" {$required}>{{ old('{$fieldName}') }}</textarea>\n";
            } elseif ($htmlInputType === 'select') {
                $content .= "                    <select class=\"form-control @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" {$required}>\n";
                $content .= "                        <option value=\"\">Select {$label}</option>\n";
                if (!empty($column['dropdown_options'])) {
                    foreach ($column['dropdown_options'] as $option) {
                        $optionValue = strtolower(str_replace(' ', '_', $option));
                        $content .= "                        <option value=\"{$optionValue}\" {{ old('{$fieldName}') == '{$optionValue}' ? 'selected' : '' }}>{$option}</option>\n";
                    }
                }
                $content .= "                    </select>\n";
            } elseif ($htmlInputType === 'checkbox') {
                $content .= "                    <div class=\"form-check\">\n";
                $content .= "                        <input type=\"checkbox\" class=\"form-check-input @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"1\" {{ old('{$fieldName}') ? 'checked' : '' }}>\n";
                $content .= "                        <label class=\"form-check-label\" for=\"{$fieldName}\">{$label}</label>\n";
                $content .= "                    </div>\n";
            } elseif ($htmlInputType === 'file') {
                $content .= "                    <input type=\"file\" class=\"form-control @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" {$required}>\n";
            } elseif ($htmlInputType === 'date') {
                $needsDatepicker = true;
                $content .= "                    <input type=\"text\" class=\"form-control datepicker @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{{ old('{$fieldName}') }}\" {$required}>\n";
            } elseif ($htmlInputType === 'datetime') {
                $needsDatepicker = true;
                $content .= "                    <input type=\"text\" class=\"form-control datetimepicker @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{{ old('{$fieldName}') }}\" {$required}>\n";
            } else {
                $inputType = $htmlInputType;
                if ($htmlInputType === 'number') {
                    $inputType = 'number';
                } elseif ($htmlInputType === 'email') {
                    $inputType = 'email';
                }
                $content .= "                    <input type=\"{$inputType}\" class=\"form-control @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{{ old('{$fieldName}') }}\" {$required}>\n";
            }

            $content .= "                    @error('{$fieldName}')\n";
            $content .= "                        <div class=\"invalid-feedback\">{{ \$message }}</div>\n";
            $content .= "                    @enderror\n";
            $content .= "                </div>\n";
        }

        $content .= "                <button type=\"submit\" class=\"btn btn-primary\">Submit</button>\n";
        $content .= "                <a href=\"{{ route('{$resourceName}.index') }}\" class=\"btn btn-secondary\">Cancel</a>\n";
        $content .= "            </form>\n";
        $content .= "        </div>\n";
        $content .= "    </div>\n";
        $content .= "</div>\n";

        if ($needsDatepicker) {
            $content .= "@push('styles')\n";
            $content .= "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css\">\n";
            $content .= "@endpush\n";
            $content .= "@push('scripts')\n";
            $content .= "<script src=\"https://cdn.jsdelivr.net/npm/flatpickr\"></script>\n";
            $content .= "<script>\n";
            $content .= "    document.addEventListener('DOMContentLoaded', function() {\n";
            $content .= "        // Initialize date pickers\n";
            $content .= "        flatpickr('.datepicker', {\n";
            $content .= "            dateFormat: 'Y-m-d',\n";
            $content .= "            allowInput: true\n";
            $content .= "        });\n";
            $content .= "        // Initialize datetime pickers\n";
            $content .= "        flatpickr('.datetimepicker', {\n";
            $content .= "            dateFormat: 'Y-m-d H:i:S',\n";
            $content .= "            enableTime: true,\n";
            $content .= "            allowInput: true\n";
            $content .= "        });\n";
            $content .= "    });\n";
            $content .= "</script>\n";
            $content .= "@endpush\n";
        }

        $content .= "@endsection\n";

        File::put($viewPath . '/create.blade.php', $content);
    }

    /**
     * Generate edit view
     */
    protected function generateEditView($viewPath, $resourceName, $columns, $variableName, $relationship = null)
    {
        $needsDatepicker = false;
        $content = "@extends('layouts.app')\n\n";
        $content .= "@section('content')\n";
        $content .= "<div class=\"container\">\n";
        $content .= "    <div class=\"row\">\n";
        $content .= "        <div class=\"col-12\">\n";
        $content .= "            <h1>Edit " . Str::title(Str::singular($resourceName)) . "</h1>\n";
        $content .= "            <form action=\"{{ route('{$resourceName}.update', \${$variableName}) }}\" method=\"POST\" enctype=\"multipart/form-data\">\n";
        $content .= "                @csrf\n";
        $content .= "                @method('PUT')\n";

        // Add relationship dropdown if belongsTo
        if ($relationship && $relationship['type'] === 'belongsTo') {
            $relatedModel = $relationship['related_model'];
            $relatedTable = $relationship['related_table'];
            $foreignKey = $relationship['foreign_key'];
            $relatedVariable = Str::camel(Str::plural($relatedModel));
            $relatedLabel = Str::title(str_replace('_', ' ', $relatedModel));

            $content .= "                <div class=\"mb-3\">\n";
            $content .= "                    <label for=\"{$foreignKey}\" class=\"form-label\">{$relatedLabel}</label>\n";
            $content .= "                    <select class=\"form-control @error('{$foreignKey}') is-invalid @enderror\" id=\"{$foreignKey}\" name=\"{$foreignKey}\" required>\n";
            $content .= "                        <option value=\"\">Select {$relatedLabel}</option>\n";
            $content .= "                        @foreach(\\App\\Models\\{$relatedModel}::all() as \$item)\n";
            $content .= "                            <option value=\"{{ \$item->id }}\" {{ (old('{$foreignKey}', \${$variableName}->{$foreignKey}) == \$item->id) ? 'selected' : '' }}>{{ \$item->name ?? \$item->id }}</option>\n";
            $content .= "                        @endforeach\n";
            $content .= "                    </select>\n";
            $content .= "                    @error('{$foreignKey}')\n";
            $content .= "                        <div class=\"invalid-feedback\">{{ \$message }}</div>\n";
            $content .= "                    @enderror\n";
            $content .= "                </div>\n";
        }

        foreach ($columns as $column) {
            $fieldName = $column['name'];
            $label = Str::title(str_replace('_', ' ', $fieldName));
            $required = !isset($column['nullable']) || !$column['nullable'] ? 'required' : '';
            $htmlInputType = $column['html_input_type'] ?? 'text';

            $content .= "                <div class=\"mb-3\">\n";
            $content .= "                    <label for=\"{$fieldName}\" class=\"form-label\">{$label}</label>\n";

            // Generate input based on HTML input type
            if ($htmlInputType === 'textarea') {
                $content .= "                    <textarea class=\"form-control @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" rows=\"3\" {$required}>{{ old('{$fieldName}', \${$variableName}->{$fieldName}) }}</textarea>\n";
            } elseif ($htmlInputType === 'select') {
                $content .= "                    <select class=\"form-control @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" {$required}>\n";
                $content .= "                        <option value=\"\">Select {$label}</option>\n";
                if (!empty($column['dropdown_options'])) {
                    foreach ($column['dropdown_options'] as $option) {
                        $optionValue = strtolower(str_replace(' ', '_', $option));
                        $content .= "                        <option value=\"{$optionValue}\" {{ (old('{$fieldName}', \${$variableName}->{$fieldName}) == '{$optionValue}') ? 'selected' : '' }}>{$option}</option>\n";
                    }
                }
                $content .= "                    </select>\n";
            } elseif ($htmlInputType === 'checkbox') {
                $content .= "                    <div class=\"form-check\">\n";
                $content .= "                        <input type=\"checkbox\" class=\"form-check-input @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"1\" {{ (old('{$fieldName}', \${$variableName}->{$fieldName}) ? 'checked' : '') }}>\n";
                $content .= "                        <label class=\"form-check-label\" for=\"{$fieldName}\">{$label}</label>\n";
                $content .= "                    </div>\n";
            } elseif ($htmlInputType === 'file') {
                $content .= "                    <input type=\"file\" class=\"form-control @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" {$required}>\n";
                $content .= "                    @if(\${$variableName}->{$fieldName})\n";
                $content .= "                        <small class=\"form-text text-muted\">Current: <a href=\"{{ asset('storage/' . \${$variableName}->{$fieldName}) }}\" target=\"_blank\">{{ \${$variableName}->{$fieldName} }}</a></small>\n";
                $content .= "                    @endif\n";
            } elseif ($htmlInputType === 'date') {
                $needsDatepicker = true;
                $content .= "                    <input type=\"text\" class=\"form-control datepicker @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{{ old('{$fieldName}', \${$variableName}->{$fieldName}) }}\" {$required}>\n";
            } elseif ($htmlInputType === 'datetime') {
                $needsDatepicker = true;
                $content .= "                    <input type=\"text\" class=\"form-control datetimepicker @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{{ old('{$fieldName}', \${$variableName}->{$fieldName}) }}\" {$required}>\n";
            } else {
                $inputType = $htmlInputType;
                if ($htmlInputType === 'number') {
                    $inputType = 'number';
                } elseif ($htmlInputType === 'email') {
                    $inputType = 'email';
                }
                $content .= "                    <input type=\"{$inputType}\" class=\"form-control @error('{$fieldName}') is-invalid @enderror\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{{ old('{$fieldName}', \${$variableName}->{$fieldName}) }}\" {$required}>\n";
            }

            $content .= "                    @error('{$fieldName}')\n";
            $content .= "                        <div class=\"invalid-feedback\">{{ \$message }}</div>\n";
            $content .= "                    @enderror\n";
            $content .= "                </div>\n";
        }

        $content .= "                <button type=\"submit\" class=\"btn btn-primary\">Update</button>\n";
        $content .= "                <a href=\"{{ route('{$resourceName}.index') }}\" class=\"btn btn-secondary\">Cancel</a>\n";
        $content .= "            </form>\n";
        $content .= "        </div>\n";
        $content .= "    </div>\n";
        $content .= "</div>\n";

        if ($needsDatepicker) {
            $content .= "@push('styles')\n";
            $content .= "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css\">\n";
            $content .= "@endpush\n";
            $content .= "@push('scripts')\n";
            $content .= "<script src=\"https://cdn.jsdelivr.net/npm/flatpickr\"></script>\n";
            $content .= "<script>\n";
            $content .= "    document.addEventListener('DOMContentLoaded', function() {\n";
            $content .= "        // Initialize date pickers\n";
            $content .= "        flatpickr('.datepicker', {\n";
            $content .= "            dateFormat: 'Y-m-d',\n";
            $content .= "            allowInput: true\n";
            $content .= "        });\n";
            $content .= "        // Initialize datetime pickers\n";
            $content .= "        flatpickr('.datetimepicker', {\n";
            $content .= "            dateFormat: 'Y-m-d H:i:S',\n";
            $content .= "            enableTime: true,\n";
            $content .= "            allowInput: true\n";
            $content .= "        });\n";
            $content .= "    });\n";
            $content .= "</script>\n";
            $content .= "@endpush\n";
        }

        $content .= "@endsection\n";

        File::put($viewPath . '/edit.blade.php', $content);
    }

    /**
     * Generate show view
     */
    protected function generateShowView($viewPath, $resourceName, $columns, $variableName, $relationship = null)
    {
        $content = "@extends('layouts.app')\n\n";
        $content .= "@section('content')\n";
        $content .= "<div class=\"container\">\n";
        $content .= "    <div class=\"row\">\n";
        $content .= "        <div class=\"col-12\">\n";
        $content .= "            <h1>" . Str::title(Str::singular($resourceName)) . " Details</h1>\n";
        $content .= "            <table class=\"table\">\n";
        $content .= "                <tr><th>ID</th><td>{{ \${$variableName}->id }}</td></tr>\n";

        // Show relationship if belongsTo
        if ($relationship && $relationship['type'] === 'belongsTo') {
            $relatedModel = $relationship['related_model'];
            $foreignKey = $relationship['foreign_key'];
            $relatedLabel = Str::title(str_replace('_', ' ', $relatedModel));
            $methodName = Str::camel($relatedModel);

            $content .= "                <tr><th>{$relatedLabel}</th><td>\n";
            $content .= "                    @if(\${$variableName}->{$methodName})\n";
            $content .= "                        <a href=\"{{ route('" . Str::kebab(Str::plural($relatedModel)) . ".show', \${$variableName}->{$methodName}) }}\">{{ \${$variableName}->{$methodName}->name ?? \${$variableName}->{$methodName}->id }}</a>\n";
            $content .= "                    @else\n";
            $content .= "                        <span class=\"text-muted\">No {$relatedLabel}</span>\n";
            $content .= "                    @endif\n";
            $content .= "                </td></tr>\n";
        }

        foreach ($columns as $column) {
            $fieldName = $column['name'];
            $label = Str::title(str_replace('_', ' ', $fieldName));
            $htmlInputType = $column['html_input_type'] ?? 'text';

            // Check if this is a file/image field
            if ($htmlInputType === 'file') {
                // Check if it's likely an image (by field name)
                if (
                    stripos($fieldName, 'image') !== false ||
                    stripos($fieldName, 'photo') !== false ||
                    stripos($fieldName, 'picture') !== false ||
                    stripos($fieldName, 'avatar') !== false
                ) {
                    // Display as larger image
                    $content .= "                <tr><th>{$label}</th><td>\n";
                    $content .= "                    @if(\${$variableName}->{$fieldName})\n";
                    $content .= "                        <img src=\"{{ asset('storage/' . \${$variableName}->{$fieldName}) }}\" alt=\"{$label}\" class=\"img-fluid\" style=\"max-width: 300px; max-height: 300px; object-fit: cover; border-radius: 8px;\">\n";
                    $content .= "                        <br><small class=\"text-muted\">{{ \${$variableName}->{$fieldName} }}</small>\n";
                    $content .= "                    @else\n";
                    $content .= "                        <span class=\"text-muted\">No image</span>\n";
                    $content .= "                    @endif\n";
                    $content .= "                </td></tr>\n";
                } else {
                    // Display as file link
                    $content .= "                <tr><th>{$label}</th><td>\n";
                    $content .= "                    @if(\${$variableName}->{$fieldName})\n";
                    $content .= "                        <a href=\"{{ asset('storage/' . \${$variableName}->{$fieldName}) }}\" target=\"_blank\" class=\"btn btn-primary\">Download File</a>\n";
                    $content .= "                        <br><small class=\"text-muted\">{{ \${$variableName}->{$fieldName} }}</small>\n";
                    $content .= "                    @else\n";
                    $content .= "                        <span class=\"text-muted\">No file</span>\n";
                    $content .= "                    @endif\n";
                    $content .= "                </td></tr>\n";
                }
            } else {
                // Regular field - display as text
                $content .= "                <tr><th>{$label}</th><td>{{ \${$variableName}->{$fieldName} }}</td></tr>\n";
            }
        }
        $content .= "                <tr><th>Created At</th><td>{{ \${$variableName}->created_at }}</td></tr>\n";
        $content .= "                <tr><th>Updated At</th><td>{{ \${$variableName}->updated_at }}</td></tr>\n";
        $content .= "            </table>\n";
        $content .= "            <a href=\"{{ route('{$resourceName}.edit', \${$variableName}) }}\" class=\"btn btn-warning\">Edit</a>\n";
        $content .= "            <form action=\"{{ route('{$resourceName}.destroy', \${$variableName}) }}\" method=\"POST\" class=\"d-inline\">\n";
        $content .= "                @csrf\n";
        $content .= "                @method('DELETE')\n";
        $content .= "                <button type=\"submit\" class=\"btn btn-danger\" onclick=\"return confirm('Are you sure?')\">Delete</button>\n";
        $content .= "            </form>\n";
        $content .= "            <a href=\"{{ route('{$resourceName}.index') }}\" class=\"btn btn-secondary\">Back</a>\n";
        $content .= "        </div>\n";
        $content .= "    </div>\n";
        $content .= "</div>\n";
        $content .= "@endsection\n";

        File::put($viewPath . '/show.blade.php', $content);
    }

    /**
     * Get default HTML input type index based on data type
     * Returns the index (0-based) for the choice array
     */
    protected function getDefaultHtmlInputTypeIndex($dataType)
    {
        $defaults = [
            'integer' => 7, // Number (index 7)
            'string' => 0, // Text Box (index 0)
            'text' => 1, // Textarea (index 1)
            'longText' => 1, // Textarea (index 1)
            'datetime' => 5, // DateTime Picker (index 5)
            'date' => 4, // Date Picker (index 4)
            'timestamp' => 5, // DateTime Picker (index 5)
            'boolean' => 6, // Checkbox (index 6)
            'float' => 7, // Number (index 7)
            'decimal' => 7, // Number (index 7)
            'bigInteger' => 7, // Number (index 7)
            'json' => 1, // Textarea (index 1)
        ];

        return $defaults[$dataType] ?? 0;
    }

    /**
     * Parse fields - now fields are already in array format
     */
    protected function parseFields($fields)
    {
        $parsed = [];
        foreach ($fields as $field) {
            // If it's already an array (new format), use it directly
            if (is_array($field)) {
                $parsed[] = [
                    'name' => $field['name'],
                    'type' => $this->mapFieldType($field['data_type']),
                    'nullable' => $field['nullable'] ?? false,
                    'html_input_type' => $field['html_input_type'] ?? 'text',
                    'dropdown_options' => $field['dropdown_options'] ?? null,
                ];
            } else {
                // Legacy format support
                $parts = explode(':', $field);
                $name = $parts[0];
                $type = $parts[1] ?? 'string';
                $nullable = in_array('nullable', $parts);

                $parsed[] = [
                    'name' => $name,
                    'type' => $this->mapFieldType($type),
                    'nullable' => $nullable,
                    'html_input_type' => 'text',
                    'dropdown_options' => null,
                ];
            }
        }
        return $parsed;
    }

    /**
     * Map field type to migration type
     */
    protected function mapFieldType($type)
    {
        $mapping = [
            'string' => 'string',
            'text' => 'text',
            'longText' => 'longText',
            'integer' => 'integer',
            'bigInteger' => 'bigInteger',
            'float' => 'float',
            'double' => 'double',
            'decimal' => 'decimal',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'timestamp' => 'timestamp',
            'json' => 'json',
        ];

        return $mapping[$type] ?? 'string';
    }

    /**
     * Generate validation rules
     */
    protected function generateValidationRules($columns, $relationship = null)
    {
        $rules = [];

        // Add foreign key validation if belongsTo relationship
        if ($relationship && $relationship['type'] === 'belongsTo') {
            $foreignKey = $relationship['foreign_key'];
            $relatedTable = $relationship['related_table'];
            $rules[$foreignKey] = 'required|exists:' . $relatedTable . ',id';
        }

        foreach ($columns as $column) {
            $rule = [];
            $htmlInputType = $column['html_input_type'] ?? 'text';

            // Handle file uploads differently
            if ($htmlInputType === 'file') {
                if (!isset($column['nullable']) || !$column['nullable']) {
                    $rule[] = 'required';
                }
                // Check if it's an image field (by name or data type)
                if (
                    stripos($column['name'], 'image') !== false ||
                    stripos($column['name'], 'photo') !== false ||
                    stripos($column['name'], 'picture') !== false
                ) {
                    $rule[] = 'image';
                } else {
                    $rule[] = 'file';
                }
                $rule[] = 'max:2048'; // 2MB max file size
            } else {
                // For non-file fields
                if (!isset($column['nullable']) || !$column['nullable']) {
                    $rule[] = 'required';
                }

                switch ($column['type']) {
                    case 'integer':
                    case 'bigInteger':
                        $rule[] = 'integer';
                        break;
                    case 'float':
                    case 'double':
                    case 'decimal':
                        $rule[] = 'numeric';
                        break;
                    case 'boolean':
                        $rule[] = 'boolean';
                        break;
                    case 'date':
                        $rule[] = 'date';
                        break;
                    case 'dateTime':
                    case 'timestamp':
                        $rule[] = 'date';
                        break;
                    case 'email':
                        $rule[] = 'email';
                        break;
                    default:
                        $rule[] = 'string';
                        if ($column['type'] !== 'text' && $column['type'] !== 'longText') {
                            $rule[] = 'max:255';
                        }
                }
            }

            $rules[$column['name']] = implode('|', $rule);
        }
        return $rules;
    }

    /**
     * Register routes
     */
    protected function registerRoutes($controllerName, $resourceName, $buildType)
    {
        $routeFile = $buildType === 'api' ? 'api.php' : 'web.php';
        $routePath = base_path('routes/' . $routeFile);

        if (!File::exists($routePath)) {
            if ($buildType === 'api') {
                File::put($routePath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n");
            } else {
                File::put($routePath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n");
            }
        }

        $routeContent = File::get($routePath);
        $controllerFullName = "App\\Http\\Controllers\\{$controllerName}";

        // Add use statement if not exists
        $useStatement = "use {$controllerFullName};\n";
        if (strpos($routeContent, $useStatement) === false) {
            // Check if any controller from App\Http\Controllers is already imported
            if (preg_match('/use App\\\\Http\\\\Controllers\\\\[^;]+;/', $routeContent)) {
                // Find the last use statement and add after it
                $lines = explode("\n", $routeContent);
                $lastUseIndex = 0;
                foreach ($lines as $index => $line) {
                    if (preg_match('/^use /', $line)) {
                        $lastUseIndex = $index;
                    }
                }
                array_splice($lines, $lastUseIndex + 1, 0, $useStatement);
                $routeContent = implode("\n", $lines);
            } else {
                // Add after the opening PHP tag and Route facade import
                if (preg_match('/^<\?php\n\nuse Illuminate\\\\Support\\\\Facades\\\\Route;\n\n/', $routeContent)) {
                    $routeContent = preg_replace('/^<\?php\n\nuse Illuminate\\\\Support\\\\Facades\\\\Route;\n\n/', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n" . $useStatement . "\n", $routeContent);
                } else {
                    // Fallback: add after first line
                    $routeContent = preg_replace('/^<\?php\n/', "<?php\n" . $useStatement, $routeContent);
                }
            }
        }

        if ($buildType === 'api') {
            $routeLine = "Route::apiResource('{$resourceName}', {$controllerName}::class);\n";
        } else {
            $routeLine = "Route::resource('{$resourceName}', {$controllerName}::class);\n";
        }

        // Check if route already exists
        if (strpos($routeContent, "Route::" . ($buildType === 'api' ? 'api' : '') . "Resource('{$resourceName}'") === false) {
            $routeContent .= "\n" . $routeLine;
            File::put($routePath, $routeContent);
            $this->info("✅ Routes registered in routes/{$routeFile}");
        } else {
            $this->warn("⚠️  Routes already exist in routes/{$routeFile}");
        }
    }
}