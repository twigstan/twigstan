<p align="center">
    <strong>TwigStan</strong> is a static analyzer for <a href="https://twig.symfony.com">Twig</a> templates powered by <a href="https://phpstan.org">PHPStan</a>.
</p>
<p align="center">
    <img src="https://raw.githubusercontent.com/twigstan/twigstan/main/screenshot.png" alt="Screenshot" height="300">
</p>

------

> [!CAUTION]
> This is very experimental and some parts are implemented very naive.

# Introduction

TwigStan works by transforming your Twig templates to naive PHP equivalents that can be analyzed by PHPStan:

1. **For each Twig template:**
   - Tokenize Twig template
   - Parse Twig tokens into AST (Abstract Syntax Tree)
   - Transform Twig AST to PHP AST
   - Dump PHP AST to a file
   - Keep track of line numbers between Twig template and PHP file

2. **Run PHPStan:**
   - Analyze all generated PHP files
   - Run TwigStan's [PHPStan rules](src/PHPStan/Rules)

3. **Error Handling:**
   - Get the reported errors from PHPStan
   - Map errors back to the original Twig file and line numbers
   - Display the errors

## Transforming

Every Twig node needs to be transformed to a PHP node. This can be done by creating
a [TwigNodeTransformer](src/Twig/Transforming/NodeTransformer/TwigNodeTransformer.php).

The goal of transforming is to produce PHP code that PHPStan can analyze.
The transformed PHP code cannot ever be used to render the Twig template.
Twig can perfectly compile templates
to PHP code that can be rendered. But the issue is, that this code is very hard to analyze for PHPStan.

## Understanding Twig's `.` operator

Twig has a very powerful `.` operator that allows you to write expressions like `{{ user.account.name }}`.

Let's say `user` is an instance of `App\User`. When resolving `account`, Twig tries the following things:
* `account` property;
* `account()` method;
* `getaccount()` method;
* `isaccount()` method;
* `hasaccount()` method.

This is hard to convert to PHP code because PHP does not have such concepts.

TwigStan solves this by transforming `{{ user.account.name }}` to a function call:
```php
echo twigstan_get_property_or_call_method(twigstan_get_property_or_call_method($user, 'account'), 'name')
```

The [PropertyOrMethodCallReturnType](src/PHPStan/DynamicFunctionReturnType/PropertyOrMethodCallReturnType.php) will then instruct
PHPStan on the returned type of these function calls.

### Installation

```command
$ composer require --dev twigstan/twigstan:dev-main
```

Then run TwigStan and it will explain what to do next:
```command
$ vendor/bin/twigstan
```

## Usage

### Defining requirements

One of the problems while analyzing Twig templates is that there is no clear
definition of available variables in the context.

The context is provided by the process that renders the template. If you have multiple locations that render
a template, the template needs to be validated per context provided.

Some other projects have tried to solve static analysis like that.

TwigStan aims to do this differently. Every template can define it's requirements at the top of the file:
```twig
{% requirements name 'string|null', users 'array<int, App\\User>' %}
```

Let's say the rest of the template looks like this:
```twig
<h1>Hello {{ name }}</h1>

{% for id, user in users %}
    <p>{{ user.firstName }}</p>
{% endfor %}
```

When analyzing this file, TwigStan will be able to know that `name` exists.
When looping over the `users`, TwigStan will know that `id` is of type `int` and that `user` is of type `App\User`.

It becomes even better when you include other templates. Let's say you have a `footer.twig`:
```twig
{% requirements year 'int' %}
Copyright &copy; {{ year }}
```

We can include this file in the template above:
```twig
{{ include('footer.twig') }}
```

TwigStan will complain:
> Requirements for template "footer.twig" are not valid: 'year' is required but not given.

We can solve it in multiple ways:
```twig
{{ include('footer.twig', { year: 2024 }) }}
```

Or by setting the variable in the template:
```twig
{% set year = 2024 %}
{{ include('footer.twig') }}
```

When rendering the template from PHP, TwigStan will be able to tell of the provided context matches the requirements of the template.

If a template does not require any variables, you can signal make it clear like this:
```twig
{% requirements none %}
```

> [!TIP]
> You can check more examples by looking at the tests for the [ExtendsRequirementsRule](tests/Rules/ExtendsRequirements),
[IncludeRequirementsRule](tests/Rules/IncludeRequirements) and [RenderRequirementsRule](tests/Rules/RenderRequirements).

### Defining types

If you don't want to define requirements (yet) but do want to introduce type safety, you manually type each and every
variable like this: :
```twig
{% type variableName 'type' %}
```

The type can be a valid PHPDoc expression. For example:
```twig
{% type name 'string|null' %}
```

Next to using multiple `{% type %}` tags, you can also define multiple types in a single line:
```twig
{% type name 'string', users 'array<int, App\\User>' %}
```
> [!NOTE]
> Starting from Twig version 4 you [no longer have to escape backslashes](https://github.com/twigphp/Twig/pull/4199) in fully qualified class names.

> [!IMPORTANT]
> There is [currently a discussion happening](https://github.com/twigphp/Twig/issues/4165) to add a `var` or `type` tag into Twig core.
> For now, TwigStan uses the `{% type %}` syntax. But depending on what the outcome will be, this could change.

### Debugging

You can dump the type of a variable by using:
```twig
{% dump_type variableName %}
```

When running TwigStan it will then output the type of the variable _at that point_.

For example:
```twig
{% type authenticated 'bool' %}

This will print `bool`:
{% dump_type authenticated %}

{% if authenticated %}
    This will print `true`:
    {% dump_type authenticated %}
{% else %}
    This will print `false`:
    {% dump_type authenticated %}
{% endif %}
```

## Known issues

* Macros are not supported
* Some transformers are done very naive and should be verified

## Credits & Inspiration

* [Ondřej Mirtes](https://github.com/ondrejmirtes) for creating PHPStan and providing guidance to create TwigStan.
* [Tomas Votruba](https://github.com/tomasvotruba) for creating and blogging about [Twig PHPStan Compiler](https://github.com/deprecated-packages/twig-phpstan-compiler); and for creating [Bladestan](https://github.com/TomasVotruba/bladestan).
* [Jan Matošík](https://github.com/HonzaMatosik) for creating a [phpstan-twig proof of concept](https://github.com/driveto/phpstan-twig).
* [Jeroen Versteeg](https://github.com/drjayvee) for creating a [TwigStan proof of concept](https://github.com/alisqi/TwigStan) and discussing ideas.
