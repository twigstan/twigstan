<?php

/**
 * @param Symfony\Bridge\Twig\AppVariable $app
 * @param null|string $name
 * @param array<int, App\Entity\User> $users
 */
function twigstan_template(mixed $app, mixed $name, mixed $users) : string
{
    $output = '';
    // line 5
    $output .= '
Hello ';
    // line 6
    $output .= $name;

    $output .= '

';
    // line 8
    $context2 = [
        'app' => $app,
        'name' => $name,
        'users' => $users,
    ];
    $_parent = $context2;
    $_iterated = false;
    $_seq = $users;
    foreach ($_seq as $id => $a) {
        // line 9
        $output .= '    <p>';

        $output .= $id;

        $output .= ' ';

        $output .= twigstan_get_property_or_call_method(
            $a,
            'firstName'
        );

        $output .= '</p>
';
        $_iterated = true;
    }
    unset($_parent, $_iterated, $_seq, $id, $a);
    unset($context2);
    return $output;
}
