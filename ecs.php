<?php

declare(strict_types=1);

// ecs.php
use ParametersWithAttributes;
use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::PATHS, [__DIR__ . '/src', __FILE__]);
    $parameters->set(Option::PARALLEL, true);
    $parameters->set(Option::LINE_ENDING, "\n");
    $parameters->set(Option::INDENTATION, 'spaces');

    // A. full sets
    $containerConfigurator->import(SetList::PSR_12);
    $containerConfigurator->import(SetList::STRICT);
    $containerConfigurator->import(SetList::SPACES);

    // B. standalone rule
    $services = $containerConfigurator->services();
    $services->set(ArraySyntaxFixer::class)
        ->call('configure', [[    'syntax' => 'short' ]]);
};
