<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

return array(
    'router' => array(
        'routes' => array(
            'zf-apigility-documentation' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/documentation[/:api[-v:version][/:service]]',
                    'constraints' => array(
                        'api' => '[a-zA-Z][a-zA-Z0-9_]+',
                    ),
                    'defaults' => array(
                        'controller' => 'ZF\Apigility\Documentation\Controller',
                        'action'     => 'show',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'factories' => array(
            'ZF\Apigility\Documentation\Controller' => 'ZF\Apigility\Documentation\ControllerFactory',
        ),
    ),
    'zf-content-negotiation' => array(
        'controllers' => array(
            'ZF\Apigility\Documentation\Controller' => 'Documentation',
        ),
        'accept_whitelist' => array(
            'ZF\Apigility\Documentation\Controller' => array(
                0 => 'application/vnd.swagger+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ),
        ),
        'selectors' => array(
            'Documentation' => array(
                'ZF\ContentNegotiation\JsonModel' => array(
                    'application/json',
                    'application/*+json',
                ),
            ),
        ),
    ),
);