<?php
/**
 * @autor Tom Forrer <tom.forrer@gmail.com>
 * @copyright Copyright (c) 2014 Tom Forrer (http://github.com/tmf)
 */

namespace Tmf\Wordpress\Service;

use Tmf\Wordpress\Container\HookableServiceProvider;

/**
 * Class SimpleTemplatingServiceProvider
 *
 * @package Tmf\Wordpress\Service
 */
class SimpleTemplatingServiceProvider extends HookableServiceProvider
{
  public function __construct($serviceKey = 'templating')
  {
    parent::__construct(
        $serviceKey,
        'Tmf\Wordpress\Service\SimpleTemplating',
        [[
             'hook'     => 'template_redirect',
             'method'   => 'setupTemplates',
         ], [
             'hook'   => 'admin_init',
             'method' => 'overridePageTemplates'
         ]]
    );
  }
} 