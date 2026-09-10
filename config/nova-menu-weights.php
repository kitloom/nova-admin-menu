<?php

use Kitloom\NovaMenuWeights\Repositories\DatabaseOverrideRepository;

return [
    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Implementation of Kitloom\NovaMenuWeights\Contracts\MenuOverrideRepository
    | holding what the admin has changed about the menu.
    |
    */
    'repository' => DatabaseOverrideRepository::class,

    'table' => 'nova_menu_overrides',

    'cache' => [
        'enabled' => true,
        'key' => 'nova_menu_overrides',
        'ttl' => 3600,
    ],

    /*
    | Weight applied to anything that has no stored value. Menu entries are
    | sorted by weight descending, so a heavier item floats to the top.
    */
    'default_weight' => 500,

    /*
    |--------------------------------------------------------------------------
    | Main menu
    |--------------------------------------------------------------------------
    |
    | Register the Nova::mainMenu callback that applies the sorting. Nova only
    | keeps ONE such callback, so turn this off if the application registers
    | its own - and call MenuWeights::sort($menu, $request) from inside it.
    |
    | `hide_paths` drops entries before sorting; '/dashboards/main' hides
    | Nova's default dashboard.
    |
    */
    'menu' => [
        'register' => true,
        'hide_paths' => [],

        /*
        | Let entries be dragged between sections, not just reordered within
        | one. Worth knowing before turning it on: an entry is only shown when
        | every ancestor's canSee passes, so moving one changes which rules
        | wrap it - out of a restricted section makes the link visible to more
        | people. The page behind it is still authorised by Nova either way.
        */
        'allow_reparenting' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Drag and drop screen
    |--------------------------------------------------------------------------
    |
    | The package ships a Nova tool showing the menu as a tree, which can be
    | reordered by dragging. Weights are recalculated from the position on
    | save, in steps of `step`.
    |
    */
    'tool' => [
        'register' => true,
        'path' => 'nova-menu-weights',
        'title' => 'Menu Order',
        'show_in_sidebar' => true,
        'step' => 100,
    ],
];
