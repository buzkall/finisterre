<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\CodingStyle\Rector\Use_\SeparateMultiUseImportsRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Rector\Class_\FillablePropertyToFillableAttributeRector;
use RectorLaravel\Rector\Class_\TablePropertyToTableAttributeRector;
use RectorLaravel\Rector\Class_\TouchesPropertyToTouchesAttributeRector;
use RectorLaravel\Rector\ClassMethod\ScopeNamedClassMethodToScopeAttributedClassMethodRector;

return RectorConfig::configure()
    // The migrations are .php.stub files, published into host applications whose own
    // Rector runs over them, so they are checked here too.
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/database',
        __DIR__ . '/config',
        __DIR__ . '/resources',
    ])
    ->withFileExtensions(['php', 'stub'])
    // The lowest PHP version composer.json allows, so no newer syntax slips in.
    ->withPhpSets(php83: true)
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSkip([
        AddClosureVoidReturnTypeWhereNoReturnRector::class,
        NewlineAfterStatementRector::class,
        NewlineBetweenClassLikeStmtsRector::class,
        SafeDeclareStrictTypesRector::class,
        AddArrowFunctionReturnTypeRector::class,
        ArrayToFirstClassCallableRector::class,
        SeparateMultiUseImportsRector::class,
        LocallyCalledStaticMethodToNonStaticRector::class,

        // #[Table], #[Fillable] and #[Touches] only exist in Laravel 13, and #[Scope]
        // from 12.40. The package still supports Laravel 12.
        TablePropertyToTableAttributeRector::class,
        FillablePropertyToFillableAttributeRector::class,
        TouchesPropertyToTouchesAttributeRector::class,
        ScopeNamedClassMethodToScopeAttributedClassMethodRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        carbon: true,
    )
    ->withComposerBased(laravel: true)
    ->withImportNames();
