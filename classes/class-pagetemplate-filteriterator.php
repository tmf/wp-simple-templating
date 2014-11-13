<?php
/**
 * @autor Tom Forrer <tom.forrer@gmail.com>
 * @copyright Copyright (c) 2014 Tom Forrer (http://github.com/tmf)
 */

namespace Tmf\Wordpress\Helper;

use \RecursiveFilterIterator;

/**
 * Class PageTemplateFilterIterator
 *
 * @package Tmf\Wordpress\Helper
 */
class PageTemplateFilterIterator extends RecursiveFilterIterator
{
  public function accept()
  {
    $current = $this->current();

    return (
             $current->getFilename() != '.' && // exclude . directory
             $current->getFilename() != '..' &&  // exclude .. directory
             $current->isDir()  // explicitly include directories for recursive iteration
           ) || (
             $current->isFile() && // only accept files
             preg_match('/Template Name:(.*)$/mi', file_get_contents($current->getRealPath())) // that contain the thing
           );
  }
} 