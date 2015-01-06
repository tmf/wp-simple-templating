<?php
/**
 * @autor     Tom Forrer <tom.forrer@gmail.com>
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

    /**
     * get the page templates in a similar manner as WordPress, but with more flexibility to their location
     * @param string $fromDirectory
     * @return array
     */
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
     * Get the core templates depending on the query template type
     * @param string $queryTemplateType 'frontpage', 'home', 'archive', 'taxonomy', 'category', 'tag', 'single', 'page', 'author'
     * @return array an array of possible template file locations to try
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

    /**
     * Helper function for returning the rendered template part like get_template_part.
     *
     * @param       $templatePart
     * @param null  $specialization
     * @param array $templateParameters a key value array which will be extracted in the template part
     * @return string the rendered template
     */
    public function renderTemplatePart($templatePart, $specialization = null, $templateParameters = []){
        foreach($templateParameters as $key => $value) {
            set_query_var($key, $value);
        }
        ob_start();
        get_template_part($templatePart, $specialization);
        foreach($templateParameters as $key => $value) {
            set_query_var($key, null);
        }
        return ob_get_clean();
    }

    /**
     * Helper function to determine if the current or a specific page is a page template
     *
     * @param string $slug   the slug under which the page template was registered
     * @param int    $pageId page id
     * @return bool true if a page template (identfied by slug)
     */
    public function isPageTemplate($slug, $pageId)
    {
        return get_post_type($pageId) == 'page' && get_post_meta($pageId, '_wp_page_template', true) === $slug;
    }
}