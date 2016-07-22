<?php

namespace Bolt\Extension\Bacboslab\Menueditor;

use Silex\Application;
use Bolt\Menu\MenuEntry;
use Bolt\Controller\Zone;
use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Extension\SimpleExtension;
use Bolt\Routing\ControllerCollection;
use Bolt\Translation\Translator as Trans;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Symfony\Component\HttpFoundation\RedirectResponse;

class MenuEditorException extends \Exception {};

/**
 * Menueditor extension class.
 *
 * @package MenuEditor
 * @author Svante Richter <svante.richter@gmail.com>
 */
class MenueditorExtension extends SimpleExtension
{
    /**
     * {@inheritdoc}
     */
    protected function registerBackendRoutes(ControllerCollection $collection)
    {
        $collection->match('/extend/menueditor', [$this, 'menuEditor']);
        $collection->match('/extend/menueditor/search', [$this, 'menuEditorSearch']);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerMenuEntries()
    {
        $menu = new MenuEntry('extend/menueditor', 'menueditor');
        $menu->setLabel('Menu Editor')
            ->setIcon('fa:bars')
            ->setPermission('files:config');

        return [
            $menu,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [
            'templates' => ['position' => 'prepend', 'namespace' => 'bolt']
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'fields' => [],
            'backups' => [
                'enabled' => false
            ]
        ];
    }

    /**
     * Menueditor controller
     *
     * @param  Application $app
     * @param  Request $request
     * @return Response
     */
    public function menuEditor(Application $app, Request $request)
    {
        $assets = [
            new JavaScript('menueditor.js'),
            new Stylesheet('menueditor.css'),
            new JavaScript('jquery.mjs.nestedSortable.js')
        ];

        foreach ($assets as $asset) {
            $asset->setZone(Zone::BACKEND);
            $file = $this->getWebDirectory()->getFile($asset->getPath());
            $asset->setPackageName('extensions')->setPath($file->getPath());
            $app['asset.queue.file']->add($asset);
        }
        
        $config = $this->getConfig();
        
        // Block unauthorized access...
        if (!$app['users']->isAllowed('files:config')) {
            throw new AccessDeniedException('Logged in user does not have the correct rights to use this route.');
        }

        // Handle posted menus
        if ($request->get('menus')) {
            try {
                $menus = json_decode($request->get('menus'), true);
                // Throw JSON error if we couldn't decode it
                if(json_last_error() !== 0){
                    throw new \Exception('JSON Error');
                }
                $dumper = new Dumper();
                $dumper->setIndentation(4);
                $yaml = $dumper->dump($menus, 9999);
                $parser = new Parser();
                $parser->parse($yaml);
            } catch (\Exception $e) {
                // Don't save menufile if we got a json on yaml error
                $app['logger.flash']->error('Menu couldn\'t be saved, we have restored it to it\'s last known good state.');
                return new RedirectResponse($app['resources']->getUrl('currenturl'), 301);
            }
            // Handle backups
            if($config['backups']['enable']){
                $app['filesystem']->createDir($config['backups']['folder']);
                // Create new backup
                $backup = $dumper->dump($app['config']->get('menu'), 9999);
                $app['filesystem']->put($config['backups']['folder'].'/menu.' . time() . '.yml', $backup);
                // Delete oldest backup if we have too many
                $backups = $app['filesystem']->listContents($config['backups']['folder']);
                if(count($backups) > $config['backups']['keep']){
                    reset($backups)->delete();
                }
            }
            // Save menu file
            $app['filesystem']->getFile('config://menu.yml')->put($yaml);
            // We use the success flash to test the extension translation feature, will replace as soon as it's gtg
            $app['logger.flash']->success(Trans::__('test.extension.trans'));
            return new RedirectResponse($app['resources']->getUrl('currenturl'), 301);
        }
        // Handle restoring backups
        if ($request->get('backup')) {
            $backup = $app['filesystem']->get($config['backups']['folder'])->get($request->get('backup'));
            $app['filesystem']->put('config://menu.yml', $backup->read());
            $app['logger.flash']->success('Menu backup from ' . $backup->getCarbon()->diffForHumans() . ' restored');
            return new RedirectResponse($app['resources']->getUrl('currenturl'), 301);
        }

        // Get data and render backend view
        $data = [
            'menus' => $app['config']->get('menu'),
            'config' => $config
        ];

        if($config['backups']['enable']){
            $data['backups'] = $app['filesystem']->listContents($config['backups']['folder']);
        }

        $html = $this->renderTemplate("menueditor.twig", $data);
        return new Response($html);
    }

    /**
     * Menueditor search controller
     *
     * @param  Application $app
     * @param  Request $request
     * @return Response
     */
    public function menuEditorSearch(Application $app, Request $request)
    {
        //Block unauthorized access...
        if (!$app['users']->isAllowed('files:config')) {
            throw new AccessDeniedException('Logged in user does not have the correct rights to use this route.');
        }

        $query = $app['request']->get('q');
        // Do a normal search
        $search = $app['storage']->searchContent($query);
        $items = [];
        foreach ($search['results'] as $record) {
            $items[] = [
                'title' => $record->getTitle(),
                'image' => $record->getImage() ?: '',
                'body' => (String)$record->getExcerpt(100),
                'link' => $record->link(),
                'contenttype' => $record->contenttype['singular_slug'],
                'type' => $record->contenttype['singular_name'],
                'icon' => str_replace(':', '-', $record->contenttype['icon_one']),
                'id' => $record->id
            ];
        }

        // Check contenttype listings
        foreach ($app['config']->get('contenttypes') as $ct) {
            if((!isset($ct['viewless']) || $ct['viewless'] === false) && (stripos($ct['slug'], $query) !== false || stripos($ct['name'], $query))){
                $items[] = [
                    'link' => $ct['slug'],
                    'id' => $ct['slug'],
                    'title' => $ct['name'],
                    'type' => 'Overview',
                    'icon' => str_replace(':', '-', $ct['icon_many'])
                ];
            }
        }

        // Check taxonomy listings
        foreach ($app['config']->get('taxonomy') as $tax) {
            if(isset($tax['options'])){
                foreach ($tax['options'] as $key => $taxOpt) {
                    if(stripos($taxOpt, $query) || stripos($key, $query)){
                        $items[] = [
                            'link' => $tax['slug'] . '/' . $key,
                            'id' => $tax['slug'] . '/' . $key,
                            'title' => $taxOpt,
                            'type' => $tax['name'] . ' (Taxonomy)',
                            'icon' => str_replace(':', '-', $tax['icon_one'])
                        ];
                    }
                }
            }else{
                $prefix = $app['config']->get('general/database/prefix', 'bolt_');
                $tablename = $prefix . "taxonomy";
                $slug = $tax['slug'];
                $taxquery = '%'.$query.'%';
                $taxonomyQuery = "SELECT COUNT(name) as count, slug, name 
                                  FROM $tablename
                                  WHERE taxonomytype IN ('$slug')
                                  AND (slug LIKE ? OR name LIKE ?)
                                  GROUP BY name, slug, sortorder 
                                  ORDER BY count";
                $stmt = $app['db']->prepare($taxonomyQuery);
                $stmt->bindValue(1, $taxquery);
                $stmt->bindValue(2, $taxquery);
                $stmt->execute();
                foreach ($stmt->fetchAll() as $result) {
                    $items[] = [
                        'link' => $tax['slug'] . '/' . $result['slug'],
                        'id' => $tax['slug'] . '/' . $result['slug'],
                        'title' => $result['name'],
                        'type' => $tax['name'] . ' (Taxonomy)',
                        'icon' => str_replace(':', '-', $tax['icon_one'])
                    ];
                }
            }
        }
        return new JsonResponse($items);
    }
}
