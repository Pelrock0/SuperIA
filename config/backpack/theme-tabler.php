<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Theme Configuration Values
    |--------------------------------------------------------------------------
    |
    | The file provides extra configs on top of config/backpack/ui.php
    |
    | Any value set here will override the ones defined in
    | config/backpack/ui.php when this theme is in use.
    |
    */

    /**
     * 1st layer of customization
     *
     * Simple pick a layout and let Backpack decide the best look for it.
     * No extra step is required.
     *
     * Possible values: horizontal, horizontal_dark, horizontal_overlap, vertical,
     * vertical_dark, vertical_transparent (legacy theme), right_vertical, right_vertical_dark, right_vertical_transparent
     */
   'layout' => 'vertical_dark',
	'project_logo_img' => '/favicon.svg',
	'project_logo' => '<div style="display:flex;align-items:center;gap:10px;padding:8px 0"><svg width="32" height="32" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 10C20 10 21 6 25 4C25 4 24 8 20 10Z" fill="#10B981"/><path d="M20 10C17 10 16 8 16 8C16 8 18.5 6 20 6" stroke="#10B981" stroke-linecap="round" stroke-width="1.5"/><path d="M28 16C28 12.6863 25.3137 10 22 10H18C14.6863 10 12 12.6863 12 16C12 19.3137 14.6863 22 18 22H22C25.3137 22 28 24.6863 28 28C28 31.3137 25.3137 34 22 34H18C14.6863 34 12 31.3137 12 28" stroke="#ffffff" stroke-linecap="round" stroke-width="4"/></svg><div><span style="font-size:18px;font-weight:800;color:#ffffff;letter-spacing:-0.02em">Superia</span><br><span style="font-size:9px;color:#6ffbbe;text-transform:uppercase;letter-spacing:0.1em;font-weight:700">Admin Console</span></div></div>',
	/**
	 * Pick a login page layout.
	 * Possible values: default, illustration, cover
	 */
	'auth_layout' => 'cover', // default, illustration, cover
    /**
     * Here you can easily load your own extra css styles.
     * Note: if you want to customize the style to create your own custom skin colors:
     *   - make a copy of the file "vendor/backpack/theme-tabler/resources/assets/css/colors.css" into your project
     *   - adjust colors variables as you wish
     *   - replace "base_path('vendor/backpack/theme-tabler/resources/assets/css/colors.css')," with the path to the file created above
     *   - boom!
     */
    'styles' => [
        base_path('vendor/backpack/theme-tabler/resources/assets/css/color-adjustments.css'),
        base_path('vendor/backpack/theme-tabler/resources/assets/css/colors.css'),
    ],

    /**
     * 2nd Layer of customization
     *
     * If you need to further customize the way your panel looks,
     * these options will help you achieve that.
     */
    'options' => [
        /**
         * The available color modes.
         */
        'colorModes' => [
            'system' => 'la-desktop',
            'light' => 'la-sun',
            'dark' => 'la-moon',
        ],

        /**
         * The color mode used by default.
         */
        'defaultColorMode' => 'light', // system, light, dark

        /**
         * When true, a switch is displayed to let admins choose their favorite theme mode.
         * When false, the theme will only use the "defaultColorMode" set above.
         * In case "defaultColorMode" is null, system is the default.
         */
        'showColorModeSwitcher' => false,

        /**
         * Fix the top-header component (present in "vertical_transparent") and the menu when the layout type is set as "horizontal".
         * This value is skipped when the layout type is horizontal-overlap, using false as default.
         */
        'useStickyHeader' => false,

        /**
         * When true, the content area will take the whole screen width.
         */
        'useFluidContainers' => false,

        /**
         * When true, the sidebar content for vertical layouts will not scroll with the rest of the content.
         */
        'sidebarFixed' => false,

        /**
         * When true, horizontal layouts will display the classic top bar on top to free some space when multiple nav items are used.
         */
        'doubleTopBarInHorizontalLayouts' => false,

        /**
         * When true, the password input will have a toggle button to show/hide the password.
         */
        'showPasswordVisibilityToggler' => false,
    ],

    /**
     * 3rd Layer of customization
     *
     * In case the first two steps were not enough, here you have full control over
     * the classes that make up the look of your panel.
     */
    'classes' => [
        /**
         * Use this to pass classes to the <body> HTML element, on all pages.
         */
        'body' => null,

        /**
         * For background colors use:
         * bg-dark, bg-primary, bg-secondary, bg-danger, bg-warning, bg-success, bg-info, bg-blue, bg-light-blue,
         * bg-indigo, bg-purple, bg-pink, bg-red, bg-orange, bg-yellow, bg-green, bg-teal, bg-cyan, bg-white.
         *
         * For links to be visible on different background colors use: "navbar-dark", "navbar-light".
         *
         */
        'topHeader' => null,

        /**
         * Applies only for Vertical Menu Layout
         * For standard sidebar look (transparent):
         *      - Remove "navbar-dark/light"
         *      - Remove "navbar-light/dark" from 'classes.topHeader' and instead use "bg-light"
         * You can also add a background class like bg-dark, bg-primary, bg-secondary, bg-danger, bg-warning, bg-success,
         * bg-info, bg-blue, bg-light-blue, bg-indigo, bg-purple, bg-pink, bg-red, bg-orange, bg-yellow, bg-green, bg-teal, bg-cyan
         */
        'sidebar' => null,

        /**
         * Used in the top container menu when the layout is of horizontal type.
         */
        'menuHorizontalContainer' => null,

        /**
         * Used in the top menu content when the layout is of horizontal type.
         */
        'menuHorizontalContent' => null,

        /**
         * Make transparent with footer-transparent.
         * Hide it with d-none.
         *
         * Change background color with bg-dark, bg-primary, bg-secondary, bg-danger, bg-warning, bg-success, bg-info,
         * bg-blue, bg-light-blue, bg-indigo, bg-purple, bg-pink, bg-red, bg-orange, bg-yellow, bg-green, bg-teal, bg-cyan, bg-white.
         */
        'footer' => null,

        /**
         * Use this to pass classes to the table displayed in List Operation
         * It defaults to: "table table-striped table-hover nowrap rounded card-table table-vcenter card-table shadow-xs border-xs"
         */
        'table' => null,

        /**
         * Use this to pass classes to the table wrapper component displayed in List Operation
         */
        'tableWrapper' => null,
    ],

    /**
     * 4th Layer of customization
     *
     * Alright, if nothing so far met your need, then you still have an easy way to build
     * a custom layout using the already existing components of this theme.
     *
     * 1. Create a new blade file in resources/views/layouts/your-custom-layout.blade.php
     * 2. Replace the value of layout on this file with "your-custom-layout"
     * 3. Customize the blade and place components such as sidebar, header, top-bar, where you need them!
     */
];
