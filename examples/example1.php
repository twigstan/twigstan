<?php

/**
 * @param Symfony\Bridge\Twig\AppVariable $app
 */
function twigstan_template(mixed $app) : string
{
    $output = '';
    // line 1
    $output .= 'Hello ';

    $output .= $name;

    $output .= '

';
    // line 3
    $context2 = [
        'app' => $app,
    ];
    $_parent = $context2;
    $_iterated = false;
    $_seq = $users;
    foreach ($_seq as $id => $name) {
        // line 4
        $output .= '    <p>';

        $output .= $id;

        $output .= ' ';

        $output .= twigstan_get_property_or_call_method(
            $name,
            'firstName'
        );

        $output .= '</p>
';
        $_iterated = true;
    }
    unset($_parent, $_iterated, $_seq, $id, $name);
    unset($context2);
    return $output;
}
