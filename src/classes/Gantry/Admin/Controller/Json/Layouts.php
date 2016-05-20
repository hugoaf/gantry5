<?php
/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2016 RocketTheme, LLC
 * @license   Dual License: MIT or GNU/GPLv2 and later
 *
 * http://opensource.org/licenses/MIT
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Gantry Framework code that extends GPL code is considered GNU/GPLv2 and later
 */

namespace Gantry\Admin\Controller\Json;

use Gantry\Component\Config\BlueprintsForm;
use Gantry\Component\Controller\JsonController;
use Gantry\Component\File\CompiledYamlFile;
use Gantry\Component\Filesystem\Folder;
use Gantry\Component\Layout\Layout;
use Gantry\Component\Response\JsonResponse;
use Gantry\Framework\Base\Gantry;
use RocketTheme\Toolbox\File\JsonFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class Layouts
 * @package Gantry\Admin\Controller\Json
 * @deprecated
 */
class Layouts extends JsonController
{
    protected $httpVerbs = [
        'GET' => [
            '/' => 'index',
            '/*' => 'index'
        ],
        'POST' => [
            '/' => 'index',
            '/*' => 'index'
        ]
    ];
    
    public function index()
    {
        $path = implode('/', func_get_args());

        $post = $this->request->request;

        $outline = $post['outline'];
        $type = $post['type'];
        $subtype = $post['subtype'];
        $inherit = $post['inherit'];
        $id = $post['id'];

        $this->container['configuration'] = $outline;

        $layout = Layout::instance($outline);
        $item = $layout->find($id);
        $type = isset($item->type) ? $item->type : $type;
        $subtype = isset($item->subtype) ? $item->subtype : $subtype;
        $item->attributes = isset($item->attributes) ? (array) $item->attributes : [];
        $block = $layout->block($id);
        $block = isset($block->attributes) ? (array) $block->attributes : [];

        $params = [
            'gantry'        => $this->container,
            'parent'        => 'settings',
            'route'         => "configurations.{$outline}.settings",
            'inherit'       => $inherit ? $outline : null,
        ];

        if (in_array($type, ['wrapper', 'section', 'container', 'grid', 'offcanvas'])) {
            $name = $type;
            $particle = false;
            $defaults = [];
            $file = CompiledYamlFile::instance("gantry-admin://blueprints/layout/{$name}.yaml");
            $blueprints = new BlueprintsForm($file->content());
            $file->free();
        } else {
            $name = $subtype;
            $particle = true;
            $defaults = $this->container['config']->get("particles.{$name}");
            $item->attributes = $item->attributes + $defaults;
            $blueprints = new BlueprintsForm($this->container['particles']->get($name));
            $blueprints->set('form.fields._inherit', ['type' => 'gantry.inherit']);
        }

        $paramsParticle = [
            'title'         => isset($item->title) ? $item->title : '',
            'blueprints'    => $blueprints->get('form'),
            'item'          => $item,
            'data'          => ['particles' => [$name => $item->attributes]],
            'defaults'      => ['particles' => [$name => $defaults]],
            'prefix'        => "particles.{$name}.",
            'editable'      => $particle,
            'overrideable'  => $particle,
            'skip'          => ['enabled']
        ] + $params;

        $html['g-settings-particle'] = $this->container['admin.theme']->render('@gantry-admin/pages/configurations/layouts/particle-card.html.twig',  $paramsParticle);
        $html['g-settings-block-attributes'] = $this->renderBlockFields($block, $params);
        if ($path == 'list') {
            $html['g-inherit-particle'] = $this->renderParticlesInput($inherit ? $outline : null, $subtype, $post['selected']);
        }

        return new JsonResponse(['json' => $item, 'html' => $html]);
    }

    /**
     * Render block settings.
     *
     * @param array $block
     * @param array $params
     * @return string
     */
     protected function renderBlockFields(array $block, array $params)
     {
         $file = CompiledYamlFile::instance("gantry-admin://blueprints/layout/block.yaml");
         $blockBlueprints = new BlueprintsForm($file->content());
         $file->free();

         $paramsBlock = [
                 'title' => $this->container['translator']->translate('GANTRY5_PLATFORM_BLOCK'),
                 'blueprints' => ['fields' => $blockBlueprints->get('form.fields.block_container.fields')],
                 'data' => ['block' => $block],
                 'prefix' => 'block.',
             ] + $params;

         return $this->container['admin.theme']->render('@gantry-admin/forms/fields.html.twig',  $paramsBlock);
     }

    /**
     * Render input field for particle picker.
     *
     * @param string $outline
     * @param string $particle
     * @param string $selected
     * @return string
     */
    protected function renderParticlesInput($outline, $particle, $selected)
    {
        $list = $outline ? $this->container['configurations']->getParticleInstances($outline, $particle, false) : null;
        $selected = isset($list[$selected]) ? $selected : key($list);

        $params = [
            'layout' => 'input',
            'scope' => 'inherit.',
            'field' => [
                'name' => 'particle',
                'type' => 'gantry.particles',
                'id' => 'g-inherit-particle',
                'outline' => $outline,
                'particles' => $list,
                'particle' => $particle
            ],
            'value' => $selected
        ];

        return $this->container['admin.theme']->render('@gantry-admin/forms/fields/gantry/particles.html.twig', $params);
    }
}
