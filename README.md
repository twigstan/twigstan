<p align="center">
    <img src="https://avatars.githubusercontent.com/u/179125187?s=200&v=4" alt="Logo" width=100><br>
    <strong>TwigStan</strong> is a static analyzer for <a href="https://twig.symfony.com">Twig</a> templates powered by <a href="https://phpstan.org">PHPStan</a>.
</p>
<p align="center">
    <img src="https://raw.githubusercontent.com/twigstan/twigstan/main/screenshot.png" alt="Screenshot">
</p>
<p align="center">
    <a href="https://packagist.org/packages/twigstan/twigstan"><img src="https://poser.pugx.org/twigstan/twigstan/v?style=for-the-badge" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/twigstan/twigstan"><img src="https://poser.pugx.org/twigstan/twigstan/require/php?style=for-the-badge" alt="PHP Version Require"></a>
    <a href="https://packagist.org/packages/twigstan/twigstan"><img src="https://poser.pugx.org/twigstan/twigstan/downloads?style=for-the-badge" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/twigstan/twigstan"><img src="https://poser.pugx.org/twigstan/twigstan/license?style=for-the-badge" alt="License"></a>
</p>


------

> [!CAUTION]
> This is very experimental

> [!NOTE]
> This project requires a significant amount of my time and effort to develop. If you find it valuable, please consider [sponsoring me](https://github.com/sponsors/twigstan) to support its continued growth and give it a star! ⭐️ Your support is truly appreciated! 🙏

# Introduction

TwigStan uses Twig to compile templates to PHP code. It then optimizes the compiled PHP code slightly, allowing PHPStan to analyze it better. It then reports any errors back to the original template and line number.

The process consists of the following steps:

## Template Context Collecting

We use PHPStan to search the PHP codebase for places where a Twig template is rendered. We collect the context that is passed to the template.

- [ContextFromReturnedArrayWithTemplateAttributeCollector](src/PHPStan/Collector/ContextFromReturnedArrayWithTemplateAttributeCollector.php) and [ContextFromControllerRenderMethodCallCollector](src/PHPStan/Collector/ContextFromControllerRenderMethodCallCollector.php) search for controllers that render a Twig template.
- [ContextFromTwigRenderMethodCallCollector](src/PHPStan/Collector/ContextFromTwigRenderMethodCallCollector.php) search for `Twig\Environment::render` calls.

## Compilation

The [TwigCompiler](src/Processing/Compilation/TwigCompiler.php) loads the template and converts it into a Twig AST (Abstract Syntax Tree).
The AST is optimized by running [several Twig NodeVisitors](src/Processing/Compilation/TwigVisitor). The AST is then compiled into PHP
using Twig's default compiler. The compiled PHP code is loaded and converted into a PHP AST.
We inject the previously collected template context as PHPDocs into the template.
On the PHP AST, we run [various PHP NodeVisitors](src/Processing/Compilation/PhpVisitor).
The goal is no longer to render the template but to analyze it.
This means we can remove elements that are not relevant to us.
The PHP AST is then dumped back into PHP code and saved to disk as a compilation result.

In the next steps, we will use these PHP files.

## Flattening

The next step is to flatten the Twig templates. Templates can [extend](https://twig.symfony.com/doc/3.x/tags/extends.html) other templates.
The child template can choose to override blocks or not, and the parent template can also extend another template.
Variables set in a parent template should be available in the child template.

The [TwigFlattener](src/Processing/Flattening/TwigFlattener.php) processes all the compilation results. It reads the Twig metadata
to identify the parent(s) and defined blocks. It takes the logic in the parent template (set variables, etc.) from the `doDisplay`
method and copies it into the child template's `doDisplay` block.

The same is done for the block hierarchy. It understands which blocks are overridden. The child template will eventually have all blocks defined.

While flattening, the original filename and line numbers are preserved. This is important because later on,
we want to trace errors back to their original location.

After the flattening process is finished, the PHP AST is again dumped to disk as a flattening result.

## Block Context Collecting

Now that we have a flat template, we can analyze the context for every block.

We use PHPStan to run the [BlockContextCollector](src/PHPStan/Collector/BlockContextCollector.php).
This collector gathers the context before rendering every block or parent block call.

## Scope Injection

Now that we know the context before every block call in the template, we can inject this knowledge as PHPDocs into the
flattened template.

## Analysis

Every template is now flattened and has defined context types.

We ask PHPStan to run the analysis on these files.

While this analysis is running, we also collect `{% include %}` usage in the templates.
Since we know the context at this point, we collect it.

If we found included templates, we will repeat the full analysis process for these templates.
This means that we go back to the Compilation step.

The [AnalysisResultFromJsonReader](src/PHPStan/Analysis/AnalysisResultFromJsonReader.php) processes the results from PHPStan.

For every error in the flattened PHP code, it tries to find the original Twig file and line number. It filters out a
few errors that are false positives. It also collapses errors that are already reported higher in the hierarchy.
When an error is reported in a parent template, it should only be reported once, instead of every time it's
flattened in a child template.

### Installation

```command
composer require --dev twigstan/twigstan:dev-main
```

Then run TwigStan and it will explain what to do next:
```command
vendor/bin/twigstan
```

## Usage

Make sure that you configure your `phpPaths` to point to the PHP codebase that renders the Twig templates.

This will make sure that TwigStan can collect the template context from the PHP codebase.

Use the `dump_type` tag below to debug if types are properly resolved.

### Debugging

You can dump the type of a variable by using:
```twig
{% dump_type variableName %}
```

If you want to dump the types for the whole context (everything that's available), you can do:
```twig
{% dump_type %}
```

### Adding type hints

TwigStan supports [the new `{% types %}` tag](https://twig.symfony.com/doc/3.x/tags/types.html) that was introduced in Twig 3.13.

If your types are not automatially resolved from where they are rendered, you manually type each and every
variable like this:
```twig
{% types { variableName: 'type' } %}
```

> [!NOTE]
> Keep in mind that TwigStan tries to resolve all these types automatically by collecting the context from the PHP codebase.
> Don't blindly start adding `{% types %}` tags. First investigate if TwigStan can resolve the types automatically.


The type can be a valid PHPDoc expression. For example:
```twig
{% types { name: 'string|null' } %}
```

Next to using multiple `{% types %}` tags, you can also define multiple types in a single line:
```twig
{% types {
    name: 'string',
    users: 'array<int, App\\User>',
} %}
```

If you want to indicate that a variable is optional, you can do it as follows:
```twig
{% types {
    isEnabled?: 'bool',
} %}
```

> [!NOTE]
> Starting from Twig version 4 you [no longer have to escape backslashes](https://github.com/twigphp/Twig/pull/4199) in fully qualified class names.

## They use TwigStan

* MicroSymfony ([PR](https://github.com/strangebuzz/MicroSymfony/pull/95), [GitHub action output](https://github.com/strangebuzz/MicroSymfony/pull/95/checks))

## Credits & Inspiration

* [Ondřej Mirtes](https://github.com/ondrejmirtes) for creating PHPStan and providing guidance to create TwigStan.
* [Tomas Votruba](https://github.com/tomasvotruba) for creating and blogging about [Twig PHPStan Compiler](https://github.com/deprecated-packages/twig-phpstan-compiler); and for creating [Bladestan](https://github.com/TomasVotruba/bladestan).
* [Jan Matošík](https://github.com/HonzaMatosik) for creating a [phpstan-twig proof of concept](https://github.com/driveto/phpstan-twig).
* [Jeroen Versteeg](https://github.com/drjayvee) for creating [TwigQI](https://github.com/alisqi/TwigStan) and discussing ideas.
* [Markus Staab](https://github.com/staabm) for improving PHPStan for specific use cases in TwigStan.
