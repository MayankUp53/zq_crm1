<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * It's loaded within the context of `Application::routes()` method which
 * receives a `RouteBuilder` instance `$routes` as method argument.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/*
 * This file is loaded in the context of the `Application` class.
 * So you can use `$this` to reference the application class instance
 * if required.
 */
return function (RouteBuilder $routes): void {
    /*
     * The default class to use for all routes
     *
     * The following route classes are supplied with CakePHP and are appropriate
     * to set as the default:
     *
     * - Route
     * - InflectedRoute
     * - DashedRoute
     *
     * If no call is made to `Router::defaultRouteClass()`, the class used is
     * `Route` (`Cake\Routing\Route\Route`)
     *
     * Note that `Route` does not do any inflections on URLs which will result in
     * inconsistently cased URLs when used with `{plugin}`, `{controller}` and
     * `{action}` markers.
     */
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/customers', ['controller' => 'Customers', 'action' => 'index']);
        $builder->connect('/', ['controller' => 'Customers', 'action' => 'index']);
        $builder->connect('/homes', ['controller' => 'Homes', 'action' => 'index']);
        $builder->connect('/core-settings', ['controller' => 'CoreSettings', 'action' => 'index']);
        $builder->connect('/mt-phonelists', ['controller' => 'MtPhonelists', 'action' => 'index']);
        $builder->connect('/mt-cids', ['controller' => 'MtCids', 'action' => 'index']);
        $builder->connect('/mt-queues', ['controller' => 'MtQueues', 'action' => 'index']);
        $builder->connect('/mt-statuses', ['controller' => 'MtStatuses', 'action' => 'index']);
        $builder->connect('/mt-products', ['controller' => 'MtProducts', 'action' => 'index']);
        $builder->connect('/callbacks', ['controller' => 'Callbacks', 'action' => 'index']);
        $builder->connect('/imports', ['controller' => 'Imports', 'action' => 'index']);
        $builder->connect('/settings', ['controller' => 'Settings', 'action' => 'index']);

        // Legacy CakePHP 2 URL compatibility.
        $builder->connect('/mt_queues/index', ['controller' => 'MtQueues', 'action' => 'index']);
        $builder->connect('/mt_statuses/index', ['controller' => 'MtStatuses', 'action' => 'index']);
        $builder->connect('/mt_phonelists/index', ['controller' => 'MtPhonelists', 'action' => 'index']);
        foreach (['mt_phonelists', 'mt-phonelists'] as $path) {
            $builder->connect("/$path/sortupdate/{ids}", ['controller' => 'MtPhonelists', 'action' => 'sortupdate'])
                ->setPatterns(['ids' => '\d+(?:-\d+)*-?'])
                ->setPass(['ids']);
        }
        $builder->connect('/mt_cids/index', ['controller' => 'MtCids', 'action' => 'index']);
        $builder->connect('/core_settings/index', ['controller' => 'CoreSettings', 'action' => 'index']);
        $builder->connect('/homes/phoneList', ['controller' => 'Homes', 'action' => 'phoneList']);
        $builder->connect('/homes/storeList', ['controller' => 'Homes', 'action' => 'storeList']);
        $builder->connect('/homes/manualDialAPI', ['controller' => 'Homes', 'action' => 'manualDialApi']);
        foreach (['Customers' => 'customerDial', 'Contacts' => 'contactDial'] as $source => $routeName) {
            $path = '/' . strtolower($source) . '/manualDialAPI';
            $target = ['controller' => 'Homes', 'action' => 'manualDialApi', 'dialFrom' => $source];
            $builder->post($path, $target);
            $builder->connect($path . '/{phone}', $target, ['_name' => $routeName, '_urldecode' => false]);
        }

        $builder->connect('/mt_queues/add', ['controller' => 'MtQueues', 'action' => 'add']);
        foreach (['view', 'edit', 'delete'] as $action) {
            $builder->connect("/mt_queues/$action/{id}", [
                'controller' => 'MtQueues',
                'action' => $action,
            ])
                ->setPatterns(['id' => '\d+'])
                ->setPass(['id']);
        }
        $builder->connect('/mt_queues/sortupdate/{id}', [
            'controller' => 'MtQueues',
            'action' => 'sortupdate',
        ])
            ->setPatterns(['id' => '[\d-]+'])
            ->setPass(['id']);

        foreach (['view', 'edit', 'delete', 'sortupdate'] as $action) {
            $builder->connect("/mt_statuses/$action/{id}", [
                'controller' => 'MtStatuses',
                'action' => $action,
            ])
                ->setPatterns(['id' => '[^/]+'])
                ->setPass(['id']);
        }

        $builder->post('/contacts/callback_edit', ['controller' => 'Callbacks', 'action' => 'edit', 'legacyCallback' => true]);
        foreach (['edit', 'delete'] as $action) {
            $builder->connect("/contacts/callback_$action/{id}", [
                'controller' => 'Callbacks', 'action' => $action, 'legacyCallback' => true,
            ], ['_name' => 'legacyCallback' . ucfirst($action)])
                ->setPatterns(['id' => '\d+'])
                ->setPass(['id']);
        }

        foreach (['Contacts' => 'contacts', 'Customers' => 'customers'] as $controller => $path) {
            foreach (['view', 'edit', 'delete'] as $action) {
                $builder->connect("/$path/$action/{id}", [
                    'controller' => $controller,
                    'action' => $action,
                ])
                    ->setPatterns(['id' => '\d+'])
                    ->setPass(['id']);
            }
        }

        /*
         * ...and connect the rest of 'Pages' controller's URLs.
         */
        $builder->connect('/pages/*', 'Pages::display');

        /*
         * Connect catchall routes for all controllers.
         *
         * The `fallbacks` method is a shortcut for
         *
         * ```
         * $builder->connect('/{controller}', ['action' => 'index']);
         * $builder->connect('/{controller}/{action}/*', []);
         * ```
         *
         * It is NOT recommended to use fallback routes after your initial prototyping phase!
         * See https://book.cakephp.org/5/en/development/routing.html#fallbacks-method for more information
         */
        $builder->fallbacks();
    });

    /*
     * If you need a different set of middleware or none at all,
     * open new scope and define routes there.
     *
     * ```
     * $routes->scope('/api', function (RouteBuilder $builder): void {
     *     // No $builder->applyMiddleware() here.
     *
     *     // Parse specified extensions from URLs
     *     // $builder->setExtensions(['json', 'xml']);
     *
     *     // Connect API actions here.
     * });
     * ```
     */
};
