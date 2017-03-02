<?php

namespace Bolt\Extension\Bacboslab\Menueditor;

use Silex\Application;
use Bolt\Version as Version;
use Bolt\Menu\MenuEntry;
use Bolt\Controller\Zone;
use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Extension\SimpleExtension;
use Silex\ControllerCollection;
use Bolt\Translation\Translator as Trans;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
        //Since version 3.3 ther is a new mounting point for the extensions
        if (Version::compare('3.3', '>')) {
            $collection->match('/extend/menueditor', [$this, 'menuEditor']);
            $collection->match('/extend/menueditor/search', [$this, 'menuEditorSearch']);
        } else {
            $collection->match('/extensions/menueditor', [$this, 'menuEditor']);
            $collection->match('/extensions/menueditor/search', [$this, 'menuEditorSearch']);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function registerMenuEntries()
    {
        $config = $this->getConfig();
        $menu = new MenuEntry('menueditor', 'menueditor');
        $menu->setLabel(Trans::__(
            'menueditor.menuitem',
            ['DEFAULT' => 'Menu editor']
        ))
            ->setIcon('fa:bars')
            ->setPermission($config['permission']);

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
            'templates' => [
                'position' => 'prepend',
                'namespace' => 'bolt'
            ]
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
            ],
            'permission' => 'files:config'
        ];
    }

    /**
     * Menueditor route
     *
     * @param  Application $app
     * @param  Request $request
     * @return Response|RedirectResponse
     */
    public function menuEditor(Application $app, Request $request)
    {
        $config = $this->getConfig();

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

        // Block unauthorized access...
        if (!$app['users']->isAllowed($config['permission'])) {
            throw new AccessDeniedException(Trans::__(
                'menueditor.notallowed',
                ['DEFAULT' => 'Logged in user does not have the correct rights to use this route.']
            ));
        }

        // Handle posted menus
        if ($request->get('menus')) {
            try {
                $menus = json_decode($request->get('menus'), true);
                // Throw JSON error if we couldn't decode it
                if (json_last_error() !== 0) {
                    throw new \Exception('JSON Error');
                }
                $dumper = new Dumper();
                $dumper->setIndentation(4);
                $yaml = $dumper->dump($menus, 9999);
                $parser = new Parser();
                $parser->parse($yaml);
            } catch (\Exception $e) {
                // Don't save menufile if we got a json on yaml error
                $app['logger.flash']->error(Trans::__(
                    'menueditor.flash.error',
                    ['DEFAULT' => 'Menu couldn\'t be saved, we have restored it to it\'s last known good state.']
                ));
                return new RedirectResponse($app['resources']->getUrl('currenturl'), 301);
            }
            // Handle backups
            if ($config['backups']['enable']) {
                $app['filesystem']->createDir($config['backups']['folder']);
                // Create new backup
                $backup = $dumper->dump($app['config']->get('menu'), 9999);
                $app['filesystem']->put($config['backups']['folder'] . '/menu.' . time() . '.yml', $backup);
                // Delete oldest backup if we have too many
                $backups = $app['filesystem']->listContents($config['backups']['folder']);
                if (count($backups) > $config['backups']['keep']) {
                    reset($backups)->delete();
                }
            }
            // Save menu file
            $app['filesystem']->getFile('config://menu.yml')->put($yaml);
            $app['logger.flash']->success(Trans::__(
                'menueditor.flash.saved',
                ['DEFAULT' => 'The menus have been saved']
            ));
            return new RedirectResponse($app['resources']->getUrl('currenturl'), 301);
        }
        // Handle restoring backups
        if ($request->get('backup')) {
            $backup = $app['filesystem']->get($config['backups']['folder'])->get($request->get('backup'));
            $app['filesystem']->put('config://menu.yml', $backup->read());
            $app['logger.flash']->success(Trans::__('menueditor.flash.backup', ['%time%' => $backup->getCarbon()->diffForHumans()]));
            return new RedirectResponse($app['resources']->getUrl('currenturl'), 301);
        }

        // Get data and render backend view
        $data = [
            'menus' => $app['config']->get('menu'),
            'menu_config' => $config,
            'JsTranslations' => json_encode([
                'menueditor.js.loading' => Trans::__(
                    'menueditor.js.loading',
                    ['DEFAULT' => 'Loading suggestions']
                ),
                'menueditor.js.newlink' => Trans::__(
                    'menueditor.js.newlink',
                    ['DEFAULT' => 'New link to']
                ),
                'menueditor.actions.showhidechildren' => Trans::__(
                    'menueditor.actions.showhidechildren',
                    ['DEFAULT' => 'Click to show/hide children']
                ),
                'menueditor.action.showhideeditor' => Trans::__(
                    'menueditor.action.showhideeditor',
                    ['DEFAULT' => 'Click to show/hide item editor']
                ),
                'menueditor.action.delete' => Trans::__(
                    'menueditor.action.delete',
                    ['DEFAULT' => 'Click to delete item']
                ),
                'menueditor.fields.label' => Trans::__(
                    'menueditor.fields.label',
                    ['DEFAULT' => 'Label']
                ),
                'menueditor.fields.link' => Trans::__(
                    'menueditor.fields.link',
                    ['DEFAULT' => 'Link']
                ),
                'menueditor.fields.path' => Trans::__(
                    'menueditor.fields.path',
                    ['DEFAULT' => 'Path']
                ),
                'menueditor.flash.removefield' => Trans::__(
                    'menueditor.flash.removefield',
                    ['DEFAULT' => 'The field <strong>%1%</strong> has successfully been removed. Don\'t forget to safe your changes']
                ),
                'menueditor.flash.addedfield' => Trans::__(
                    'menueditor.flash.addedfield',
                    ['DEFAULT' => 'The field <strong>%1%</strong> has successfully been added. Don\'t forget to safe your changes.']
                ),
                'menueditor.confirm.removefield' => Trans::__(
                    'menueditor.confirm.removefield',
                    ['DEFAULT' => 'Are you sure you want to remove the field: %1%?']
                )
            ])
        ];

        if ($config['backups']['enable']) {
            $data['backups'] = $app['filesystem']->listContents($config['backups']['folder']);
        }

        $html = $app['twig']->render("@bolt/menueditor.twig", $data);
        return new Response($html);
    }

    /**
     * Menueditor search route
     *
     * @param  Application $app
     * @param  Request $request
     * @return JsonResponse
     */
    public function menuEditorSearch(Application $app, Request $request)
    {
        $config = $this->getConfig();

        //Block unauthorized access...
        if (!$app['users']->isAllowed($config['permission'])) {
            throw new AccessDeniedException(Trans::__(
                'menueditor.notallowed',
                ['DEFAULT' => 'Logged in user does not have the correct rights to use this route.']
            ));
        }

        $query = $request->get('q');
        // Do a normal search
        $search = $app['storage']->searchContent($query);
        $items = [];
        foreach ($search['results'] as $record) {
            $items[] = [
                'title'       => $record->getTitle(),
                'image'       => $record->getImage() ?: '',
                'body'        => (String)$record->getExcerpt(100),
                'link'        => $record->link(),
                'contenttype' => $record->contenttype['singular_slug'],
                'type'        => $record->contenttype['singular_name'],
                'icon'        => isset($record->contenttype['icon_one']) ? str_replace(':', '-', $record->contenttype['icon_one']) : '',
                'id'          => $record->id
            ];
        }

        // Check contenttype listings
        foreach ($app['config']->get('contenttypes') as $ct) {
            if ((!isset($ct['viewless']) || $ct['viewless'] === false) && (stripos($ct['slug'], $query) !== false || stripos($ct['name'], $query))) {
                $items[] = [
                    'link'    => $ct['slug'],
                    'ctslug'  => $ct['slug'],
                    'id'      => $ct['slug'],
                    'title'   => $ct['name'],
                    'type'    => Trans::__('menueditor.search.overview', ['DEFAULT' => 'Overview']),
                    'icon'    => isset($ct['icon_many']) ? str_replace(':', '-', $ct['icon_many']) : ''
                ];
            }
        }

        // Check taxonomy listings
        foreach ($app['config']->get('taxonomy') as $tax) {
            if (isset($tax['options'])) {
                foreach ($tax['options'] as $key => $taxOpt) {
                    if (stripos($taxOpt, $query) || stripos($key, $query)) {
                        $items[] = [
                            'link'  => $tax['slug'] . '/' . $key,
                            'id'    => $tax['slug'] . '/' . $key,
                            'title' => $taxOpt,
                            'type'  => $tax['name'] . ' (' . Trans::__('menueditor.search.taxonomy', ['DEFAULT' => 'Taxonomy']) . ')',
                            'icon'  => isset($tax['icon_one']) ? str_replace(':', '-', $tax['icon_one']) : ''
                        ];
                    }
                }
            } else {
                $prefix = $app['config']->get('general/database/prefix', 'bolt_');
                $tablename = $prefix . "taxonomy";
                $slug = $tax['slug'];
                $taxquery = '%' . $query . '%';
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
                        'link'  => $tax['slug'] . '/' . $result['slug'],
                        'id'    => $tax['slug'] . '/' . $result['slug'],
                        'title' => $result['name'],
                        'type'  => $tax['name'] . ' (' . Trans::__('menueditor.search.taxonomy', ['DEFAULT' => 'Taxonomy']) . ')',
                        'icon'  => isset($tax['icon_one']) ? str_replace(':', '-', $tax['icon_one']) : ''
                    ];
                }
            }
        }
        return new JsonResponse($items);
    }
}
