<?php
/**
 * @autor Tom Forrer <tom.forrer@gmail.com>
 * @copyright Copyright (c) 2014 Tom Forrer (http://github.com/tmf)
 */

namespace Tmf\Wordpress\Service;

use Tmf\Wordpress\Helper\PageTemplateFilterIterator;
use Tmf\Wordpress\Container\HookableService;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class SimpleTemplating
 *
 * @package Tmf\Wordpress\Service
 */
class SimpleTemplating extends HookableService
{
  public function setupTemplates()
  {
    $component = $this;

    $defaultQueryTemplates = array(
      '404', 'search', 'frontpage', 'home', 'archive', 'taxonomy', 'single', 'page', 'category', 'tag', 'author', 'date', 'index'
    );

    foreach ($defaultQueryTemplates as $queryTemplateType) {
      add_filter(sprintf('%s_template', $queryTemplateType), function ($file) use ($component, $queryTemplateType) {

        // always filter the index query template, because the theme root index template is not valid
        if (in_array($queryTemplateType, array('index', 'home')) || !$file) {

          // if nothing was found already, locate the template in the template_prefix directory
          return locate_template($component->getTemplatesFor($queryTemplateType));
        }

        return $file;
      });
    }

    // load_template() will extract any query_var for the included file: this could serve as a way to "inject" the service container
    // * WARNING * the service container present in query vars during WP_Query->get_posts will not work (as WP_Query will try to serialize the query vars):
    //             do not reuse the main query without unsetting the service container in the query vars (get_posts() is fine: new WP_Query)
    set_query_var('services', $this->getContainer());
  }

  /**
   * override the wordpress mechanism of discovering page templates
   */
  public function overridePageTemplates()
  {
    $themeDirectory = get_stylesheet_directory();
    $pageTemplates = $this->getPageTemplates(trailingslashit($themeDirectory) . $this->getContainer()['templating.directory']);

    // WP_Theme->get_page_templates will first look for a cached value of the page templates, before discovering
    wp_cache_set(sprintf('page_templates-%s', md5($themeDirectory)), $pageTemplates, 'themes');
  }

  protected function getPageTemplates($fromDirectory)
  {
    $directoryIterator = new RecursiveDirectoryIterator($fromDirectory);
    $pageTemplates = array();
    foreach (new RecursiveIteratorIterator(new PageTemplateFilterIterator($directoryIterator)) as $file) {
      $slug = str_replace(trailingslashit(get_stylesheet_directory()), '', $file->getPathname());
      preg_match('/Template Name:(.*)$/mi', file_get_contents($file->getPathname()), $header);
      $pageTemplates[$slug] = trim($header[1]);
    }

    return $pageTemplates;
  }

  /**
   * @param $queryTemplateType
   * @return array
   */
  public function getTemplatesFor($queryTemplateType)
  {
    $queriedObject = get_queried_object();
    $templateDirectory = $this->getContainer()['templating.directory'];

    if (empty($templateDirectory)) {
      $templateDirectory = '.';
    }
    $templates = array(sprintf('%s/%s.php', $templateDirectory, $queryTemplateType));

    switch ($queryTemplateType) {
      case 'frontpage':
        $templates = array(sprintf('%s/front-page.php', $templateDirectory));
        break;
      case 'home':
        $templates[] = sprintf('%s/index.php', $templateDirectory);
        break;
      case 'archive':
        $postTypes = array_filter((array)get_query_var('post_type'));
        if (count($postTypes) == 1) {
          $templates[] = sprintf('%s/archive-%s.php', $templateDirectory, reset($postTypes));
        }
        break;
      case 'taxonomy':
      case 'category':
      case 'tag':
        if (isset($queriedObject->taxonomy) && isset($queriedObject->slug)) {
          $templates[] = sprintf('%s/%s-%s-%s.php', $templateDirectory, $queryTemplateType, $queriedObject->taxonomy, $queriedObject->slug);
          $templates[] = sprintf('%s/%s-%s.php', $templateDirectory, $queryTemplateType, $queriedObject->taxonomy);
        }
        break;
      case 'single':
        if (isset($queriedObject->post_type)) {
          $templates[] = sprintf('%s/%s-%s.php', $templateDirectory, $queryTemplateType, $queriedObject->post_type);
        }
        break;
      case 'page':
        if (isset($queriedObject->post_name)) {
          $templates[] = sprintf('%s/%s-%s.php', $templateDirectory, $queryTemplateType, $queriedObject->post_name);
        }

        break;
      case 'author':
        if (is_a($queriedObject, 'WP_User')) {
          $templates[] = sprintf('%s/%s-%s.php', $templateDirectory, $queryTemplateType, $queriedObject->user_nicename);
        }
        break;
    }

    return array_reverse($templates);
  }
}