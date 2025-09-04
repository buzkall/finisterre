#!/bin/bash

# Script to create Filament resources and move them to package src directory
RESOURCE_NAME=$1
MODEL_NAME=$2

if [ -z "$RESOURCE_NAME" ]; then
    echo "Usage: $0 <ResourceName> [ModelName]"
    echo "Example: $0 TaskResource FinisterreTask"
    echo "Example: $0 TaskResource (will use default model)"
    exit 1
fi

# Default model name if not provided
if [ -z "$MODEL_NAME" ]; then
    MODEL_NAME="${RESOURCE_NAME%Resource}"
fi

echo "Creating Filament resource: $RESOURCE_NAME"
echo "Using model: $MODEL_NAME"

# Create the resource using testbench
vendor/bin/testbench make:filament-resource $RESOURCE_NAME

# Create the directories if they don't exist
mkdir -p src/Filament/Resources
mkdir -p src/Filament/Pages
mkdir -p src/Filament/Widgets

# Copy resources from vendor to package src
if [ -d "vendor/orchestra/testbench-core/laravel/app/Filament/Resources" ]; then
    cp -r vendor/orchestra/testbench-core/laravel/app/Filament/Resources/* src/Filament/Resources/ 2>/dev/null || true
fi

if [ -d "vendor/orchestra/testbench-core/laravel/workbench/app/Filament/Resources" ]; then
    cp -r vendor/orchestra/testbench-core/laravel/workbench/app/Filament/Resources/* src/Filament/Resources/ 2>/dev/null || true
fi

# Clean up vendor files
rm -rf vendor/orchestra/testbench-core/laravel/app/Filament/Resources/$RESOURCE_NAME
rm -rf vendor/orchestra/testbench-core/laravel/workbench/app/Filament/Resources/$RESOURCE_NAME

echo "✅ Resource $RESOURCE_NAME created in src/Filament/Resources/"
echo "You can now edit it in your package's src directory."

# Update namespace in the resource file
if [ -f "src/Filament/Resources/$RESOURCE_NAME.php" ]; then
    echo "📝 Updating namespace to package namespace..."
    
    # Create a temporary file for the main resource
    temp_file=$(mktemp)
    
    # Update the main resource file with proper namespace
    cat "src/Filament/Resources/$RESOURCE_NAME.php" | \
        sed "s/namespace Workbench\\\\App\\\\Filament\\\\Resources;/namespace Buzkall\\\\Finisterre\\\\Filament\\\\Resources;/" | \
        sed "s/use Workbench\\\\App\\\\Filament\\\\Resources\\\\${RESOURCE_NAME}\\\\Pages;/use Buzkall\\\\Finisterre\\\\Filament\\\\Resources\\\\${RESOURCE_NAME}\\\\Pages;/" | \
        sed "s/use Workbench\\\\App\\\\Filament\\\\Resources\\\\${RESOURCE_NAME}\\\\RelationManagers;/use Buzkall\\\\Finisterre\\\\Filament\\\\Resources\\\\${RESOURCE_NAME}\\\\RelationManagers;/" | \
        sed "s/use App\\\\Models\\\\[^;]*;/use Buzkall\\\\Finisterre\\\\Models\\\\${MODEL_NAME};/" | \
        sed "s/protected static \?string \$model = [^:]*::class;/protected static ?string \$model = ${MODEL_NAME}::class;/" > "$temp_file"
    
    mv "$temp_file" "src/Filament/Resources/$RESOURCE_NAME.php"
    echo "✅ Main resource namespace updated"
fi

# Update namespace in pages
if [ -d "src/Filament/Resources/$RESOURCE_NAME/Pages" ]; then
    echo "📝 Updating namespace in pages..."
    
    # Update all PHP files in the Pages directory
    find "src/Filament/Resources/$RESOURCE_NAME/Pages" -name "*.php" -print0 | while IFS= read -r -d '' file; do
        temp_file=$(mktemp)
        cat "$file" | \
            sed "s/namespace Workbench\\\\App\\\\Filament\\\\Resources\\\\${RESOURCE_NAME}\\\\Pages;/namespace Buzkall\\\\Finisterre\\\\Filament\\\\Resources\\\\${RESOURCE_NAME}\\\\Pages;/" | \
            sed "s/use Workbench\\\\App\\\\Filament\\\\Resources\\\\${RESOURCE_NAME};/use Buzkall\\\\Finisterre\\\\Filament\\\\Resources\\\\${RESOURCE_NAME};/g" > "$temp_file"
        mv "$temp_file" "$file"
    done
    
    echo "✅ Page namespaces updated"
fi

echo "🎉 Resource $RESOURCE_NAME is ready in src/Filament/Resources/ with correct namespace!"
echo "📝 Model reference updated to use Buzkall\\Finisterre\\Models\\$MODEL_NAME"
