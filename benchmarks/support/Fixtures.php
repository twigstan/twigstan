<?php

declare(strict_types=1);

namespace TwigStan\Benchmarks\Support;

/**
 * Projects for the benchmarks: templates, the PHP code that renders them, and the classes they read. Each returns the
 * files of a TwigStanProject. Every project has warmup.twig, which the template analysis analyses before counting.
 */
final class Fixtures
{
    /**
     * The reproducer of https://github.com/twigstan/twigstan/issues/317, fixed in
     * https://github.com/twigstan/twigstan/pull/318: nested loops copy the whole context into the context at every level,
     * until PHPStan generalizes it and the variables lose their types.
     *
     * @return array<string, string>
     */
    public static function bug317(): array
    {
        return self::project(
            [
                'two_loops.twig' => <<<'TWIG'
                    {{ known.nonexistent }}

                    {% for level1 in 1..3 %}
                        {{ known.nonexistent }}
                        {{ loop.index }}

                        {% for level2 in 1..3 %}
                            {{ known.nonexistent }}
                            {{ loop.index }}
                        {% endfor %}
                    {% endfor %}
                    TWIG,
                'four_loops.twig' => <<<'TWIG'
                    {{ known.nonexistent }}
                    {% for l1 in 1..3 %}
                        {{ known.nonexistent }}
                        {{ loop.index }}
                        {% for l2 in 1..3 %}
                            {{ known.nonexistent }}
                            {{ loop.index }}
                            {% for l3 in 1..3 %}
                                {{ known.nonexistent }}
                                {{ loop.index }}
                                {% for l4 in 1..3 %}
                                    {{ known.nonexistent }}
                                    {{ loop.index }}
                                {% endfor %}
                            {% endfor %}
                        {% endfor %}
                    {% endfor %}
                    TWIG,
            ],
            <<<'PHP'
                public function twoLoops(Environment $environment, mixed $dynamic): string
                {
                    return $environment->render('two_loops.twig', [
                        'known' => 42,
                        'dynamic' => $dynamic,
                        'metadata' => array_fill(0, 5, null),
                    ]);
                }

                public function fourLoops(Environment $environment, mixed $dynamic): string
                {
                    return $environment->render('four_loops.twig', [
                        'known' => 42,
                        'dynamic' => $dynamic,
                        'metadata' => array_fill(0, 90, null),
                    ]);
                }
                PHP,
        );
    }

    /**
     * A product listing that nests a loop for each level, over the categories, their products, their variants and so on,
     * with variables of the page around them, as in #317.
     *
     * @return array<string, string>
     */
    public static function nestedLoops(int $depth, int $variables): array
    {
        $template = "<h1>{{ title }}</h1>\n";
        $close = '';
        for ($level = 1; $level <= $depth; $level++) {
            $indent = str_repeat('    ', $level - 1);
            $over = $level === 1 ? 'categories' : sprintf('item%d.children', $level - 1);
            $template .= sprintf("%s{%% for item%d in %s %%}\n", $indent, $level, $over);
            $template .= sprintf(
                "%s    <li class=\"{{ loop.first ? 'first' }}\">{{ loop.index }}. {{ item%d.name|upper }} ({{ item%d.children|length }}) {{ title }} {{ variable%d.name }}</li>\n",
                $indent,
                $level,
                $level,
                $level % max(1, $variables),
            );
            $close = sprintf("%s{%% endfor %%}\n", $indent) . $close;
        }

        $context = ["'title' => \$title", "'categories' => \$categories"];
        for ($index = 0; $index < $variables; $index++) {
            $context[] = sprintf("'variable%d' => ['id' => %d, 'name' => 'name%d', 'tags' => \$tags]", $index, $index, $index);
        }

        return self::project(
            ['nested_loops.twig' => $template . $close],
            sprintf(
                <<<'PHP'
                    /**
                     * @param list<Tree> $categories
                     * @param list<string> $tags
                     */
                    public function nestedLoops(Environment $environment, string $title, array $categories, array $tags): string
                    {
                        return $environment->render('nested_loops.twig', [%s]);
                    }
                    PHP,
                implode(', ', $context),
            ),
        );
    }

    /**
     * A long page: a layout with blocks, and a page that overrides them with sections that each use the usual tags,
     * filters and getters of the classes it renders.
     *
     * @return array<string, string>
     */
    public static function hugeTemplate(int $sections): array
    {
        $page = "{% extends 'layout.twig' %}\n{% import 'macros.twig' as ui %}\n{% block title %}{{ shop.name }} - {{ products|length }} products{% endblock %}\n{% block content %}\n";
        for ($section = 0; $section < $sections; $section++) {
            $page .= <<<TWIG
                <section id="section-{$section}">
                    {% set heading{$section} = shop.name ~ ' {$section}' %}
                    <h2>{{ heading{$section}|title }}</h2>
                    {% if user is not null and user.admin %}
                        <a href="/admin/{$section}">{{ user.name }}</a>
                    {% elseif user is not null %}
                        <span>{{ user.email|lower }}</span>
                    {% else %}
                        {{ ui.button('Sign in', '/login') }}
                    {% endif %}
                    {% for product in products|slice(0, 10) %}
                        <article class="{{ loop.index is odd ? 'odd' : 'even' }}">
                            <h3>{{ product.name|e }}</h3>
                            <p>{{ product.price|number_format(2, '.', ',') }} {{ shop.currency }}</p>
                            {% if product.tags is not empty %}{{ product.tags|join(', ') }}{% endif %}
                            {{ product.createdAt|date('Y-m-d') }}
                            {{ ui.button('Buy', '/buy/' ~ product.id) }}
                        </article>
                    {% else %}
                        <p>No products in section {$section}.</p>
                    {% endfor %}
                </section>

                TWIG;
        }

        $page .= "{% endblock %}\n";

        return self::project(
            [
                'layout.twig' => <<<'TWIG'
                    <!DOCTYPE html>
                    <html>
                        <head><title>{% block title %}{{ shop.name }}{% endblock %}</title></head>
                        <body>
                            <header>{% block header %}{{ shop.name }}{% if user is not null %} {{ user.name }}{% endif %}{% endblock %}</header>
                            <main>{% block content %}{% endblock %}</main>
                            <footer>{% block footer %}&copy; {{ 'now'|date('Y') }} {{ shop.name }}{% endblock %}</footer>
                        </body>
                    </html>
                    TWIG,
                'macros.twig' => <<<'TWIG'
                    {% macro button(label, url) %}<a class="button" href="{{ url }}">{{ label|upper }}</a>{% endmacro %}
                    TWIG,
                'page.twig' => $page,
            ],
            <<<'PHP'
                /**
                 * @param list<Product> $products
                 */
                public function page(Environment $environment, Shop $shop, ?User $user, array $products): string
                {
                    return $environment->render('page.twig', ['shop' => $shop, 'user' => $user, 'products' => $products]);
                }
                PHP,
        );
    }

    /**
     * A template rendered with many variables, of objects, lists and shapes, such as a dashboard.
     *
     * @return array<string, string>
     */
    public static function hugeContext(int $variables): array
    {
        $template = '';
        $context = [];
        for ($index = 0; $index < $variables; $index++) {
            [$value, $use] = match ($index % 4) {
                0 => ['$products', "{% for product in var{$index} %}{{ product.name }}{% endfor %}"],
                1 => ['$shop', "{{ var{$index}.name }} {{ var{$index}.currency }}"],
                2 => ["['id' => {$index}, 'label' => 'Label {$index}', 'enabled' => \$enabled]", "{% if var{$index}.enabled %}{{ var{$index}.label }}{% endif %}"],
                default => ['$user', "{{ var{$index} is not null ? var{$index}.name : 'anonymous' }}"],
            };
            $context[] = sprintf("'var%d' => %s", $index, $value);
            $template .= "<div>{$use}</div>\n";
        }

        return self::project(
            ['dashboard.twig' => $template],
            sprintf(
                <<<'PHP'
                    /**
                     * @param list<Product> $products
                     */
                    public function dashboard(Environment $environment, Shop $shop, ?User $user, array $products, bool $enabled): string
                    {
                        return $environment->render('dashboard.twig', [%s]);
                    }
                    PHP,
                implode(', ', $context),
            ),
        );
    }

    /**
     * One template and the partial it includes, rendered by many controllers that each pass other types, so that the
     * contexts of the render points are merged.
     *
     * @return array<string, string>
     */
    public static function manyRenderPoints(int $renderPoints): array
    {
        $methods = '';
        $types = ['Product', 'Shop', 'User', 'int', 'string', 'list<Product>', 'array{id: int, name: string}', 'null'];
        for ($index = 0; $index < $renderPoints; $index++) {
            $type = $types[$index % count($types)];
            $extra = $types[intdiv($index, count($types)) % count($types)];
            $methods .= <<<PHP
                /**
                 * @param {$type} \$item
                 * @param {$extra} \$extra
                 */
                public function render{$index}(Environment \$environment, mixed \$item, mixed \$extra, Shop \$shop): string
                {
                    return \$environment->render('item.twig', ['item' => \$item, 'extra{$index}' => \$extra, 'shop' => \$shop, 'title' => 'Page {$index}']);
                }


                PHP;
        }

        return self::project(
            [
                'item.twig' => "<h1>{{ title }}</h1>\n{{ shop.name }}\n{% include 'partial.twig' %}\n{{ item is defined ? 'item' }}\n",
                'partial.twig' => "<p>{{ shop.currency }} {{ title|upper }}</p>\n",
            ],
            $methods,
        );
    }

    /**
     * Pages that extend a layout and include partials, each rendered by its own controller.
     *
     * @return array<string, string>
     */
    public static function manyTemplates(int $templates): array
    {
        $files = [
            'layout.twig' => "<html><head><title>{% block title %}{{ shop.name }}{% endblock %}</title></head>\n<body>{% block header %}{% include 'header.twig' %}{% endblock %}\n{% block content %}{% endblock %}\n{% block footer %}{{ shop.currency }}{% endblock %}</body></html>\n",
            'header.twig' => "<header>{{ shop.name }}{% if user is not null %} {{ user.name }}{% endif %}</header>\n",
        ];
        $methods = '';
        for ($index = 0; $index < $templates; $index++) {
            $files[sprintf('pages/page%d.twig', $index)] = <<<TWIG
                {% extends 'layout.twig' %}
                {% block title %}Page {$index} - {{ parent() }}{% endblock %}
                {% block content %}
                    {% for product in products %}
                        <p>{{ product.name }} {{ product.price|number_format(2) }}</p>
                    {% endfor %}
                    {% include 'footer_note.twig' with {note: 'Page {$index}'} %}
                {% endblock %}
                TWIG;
            $methods .= <<<PHP
                /**
                 * @param list<Product> \$products
                 */
                public function page{$index}(Environment \$environment, Shop \$shop, ?User \$user, array \$products): string
                {
                    return \$environment->render('pages/page{$index}.twig', ['shop' => \$shop, 'user' => \$user, 'products' => \$products]);
                }


                PHP;
        }

        $files['footer_note.twig'] = "<small>{{ note }} {{ shop.name }}</small>\n";

        return self::project($files, $methods);
    }

    /**
     * Templates in which TwigStan reports many errors, such as in a project that starts using it.
     *
     * @return array<string, string>
     */
    public static function manyErrors(int $templates, int $errorsPerTemplate): array
    {
        $files = [];
        $methods = '';
        for ($index = 0; $index < $templates; $index++) {
            $template = '';
            for ($error = 0; $error < $errorsPerTemplate; $error++) {
                $template .= match ($error % 3) {
                    0 => "{{ shop.unknown{$error} }}\n",
                    1 => "{{ product.name.length }}\n",
                    default => "{{ missing{$error} }}\n",
                };
            }

            $files[sprintf('errors%d.twig', $index)] = $template;
            $methods .= <<<PHP
                public function errors{$index}(Environment \$environment, Shop \$shop, Product \$product): string
                {
                    return \$environment->render('errors{$index}.twig', ['shop' => \$shop, 'product' => \$product]);
                }


                PHP;
        }

        return self::project($files, $methods);
    }

    /**
     * A category tree that includes itself for the children. When it passes the same context on every run, as a flag
     * for the nested lists, merging that context again made TwigStan analyse the template again and again, fixed in
     * https://github.com/twigstan/twigstan/pull/294. When it passes the next level, each analysis passed another
     * constant to the next one.
     *
     * @return array<string, string>
     */
    public static function recursiveInclude(bool $nextLevel): array
    {
        $with = $nextLevel ? '{categories: category.children, level: level + 1, nested: true} only' : '{categories: category.children, level: level, nested: true} only';

        return self::project(
            [
                'category_navigation.twig' => <<<TWIG
                    <ul class="level-{{ level }}{{ nested ? ' nested' }}">
                    {% for category in categories %}
                        <li>
                            {{ category.name }}
                            {% if category.children is not empty %}
                                {% include 'category_navigation.twig' with {$with} %}
                            {% endif %}
                        </li>
                    {% endfor %}
                    </ul>
                    TWIG,
            ],
            <<<'PHP'
                /**
                 * @param list<Tree> $categories
                 */
                public function navigation(Environment $environment, array $categories): string
                {
                    return $environment->render('category_navigation.twig', ['categories' => $categories, 'level' => 1, 'nested' => false]);
                }
                PHP,
        );
    }

    /**
     * A menu whose blocks render each other, as knp_menu's list -> children -> item -> list, fixed in 35fca7a, where
     * TwigStan recursed until it ran out of memory.
     *
     * @return array<string, string>
     */
    public static function recursiveBlocks(): array
    {
        return self::project(
            [
                'menu.twig' => <<<'TWIG'
                    {% block list %}
                        {% if item.children is not empty %}
                            <ul class="menu-{{ depth }}">{{ block('children') }}</ul>
                        {% endif %}
                    {% endblock %}

                    {% block children %}
                        {% set depth = depth + 1 %}
                        {% for item in item.children %}
                            {{ block('item') }}
                        {% endfor %}
                    {% endblock %}

                    {% block item %}
                        <li>{{ item.name|title }}{{ block('list') }}</li>
                    {% endblock %}
                    TWIG,
            ],
            <<<'PHP'
                public function menu(Environment $environment, Tree $item): string
                {
                    return $environment->render('menu.twig', ['item' => $item, 'depth' => 0]);
                }
                PHP,
        );
    }

    /**
     * @param array<string, string> $templates
     *
     * @return array<string, string>
     */
    private static function project(array $templates, string $methods): array
    {
        $files = [
            'templates/warmup.twig' => "{{ shop.name }}\n",
            'src/Controller.php' => <<<PHP
                <?php

                declare(strict_types=1);

                namespace App;

                use Twig\\Environment;

                final class Controller
                {
                    public function warmup(Environment \$environment, Shop \$shop): string
                    {
                        return \$environment->render('warmup.twig', ['shop' => \$shop]);
                    }

                {$methods}
                }

                PHP,
            'src/Model.php' => <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace App;

                final class Shop
                {
                    public function __construct(public string $name, public string $currency) {}
                }

                final class User
                {
                    public function __construct(public string $name, public string $email, private bool $admin) {}

                    public function isAdmin(): bool
                    {
                        return $this->admin;
                    }
                }

                final class Product
                {
                    /**
                     * @param list<string> $tags
                     */
                    public function __construct(
                        private int $id,
                        private string $name,
                        private float $price,
                        private array $tags,
                        private \DateTimeImmutable $createdAt,
                    ) {}

                    public function getId(): int
                    {
                        return $this->id;
                    }

                    public function getName(): string
                    {
                        return $this->name;
                    }

                    public function getPrice(): float
                    {
                        return $this->price;
                    }

                    /**
                     * @return list<string>
                     */
                    public function getTags(): array
                    {
                        return $this->tags;
                    }

                    public function getCreatedAt(): \DateTimeImmutable
                    {
                        return $this->createdAt;
                    }
                }

                final class Tree
                {
                    /**
                     * @param list<Tree> $children
                     */
                    public function __construct(public string $name, public array $children) {}
                }

                PHP,
        ];

        foreach ($templates as $name => $contents) {
            $files['templates/' . $name] = $contents;
        }

        return $files;
    }
}
