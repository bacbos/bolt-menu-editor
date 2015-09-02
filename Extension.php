<?php

namespace Bolt\Extension\bacboslab\menueditor;

use Bolt\Translation\Translator as Trans;
use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\Translation\Loader as TranslationLoader;
use Symfony\Component\Yaml\Dumper as YamlDumper,
    Symfony\Component\Yaml\Parser as YamlParser,
    Symfony\Component\Yaml\Exception\ParseException;

class MenuEditorException extends \Exception {};

/**
 * Class Extension
 * @package MenuEditor
 * @author  Steven Wüthrich / bacbos lab (steven.wuethrich@me.com)
 */
class Extension extends \Bolt\BaseExtension
{
    private $dev = false;

    private $authorized = false;
    private $authorizedForPaths = false;
    private $allowCreateNew = false;
    private $backupDir;
    private $translationDir;
    private $configDirectory;

    public  $config;

    /**
     * @return string
     */
    public function getName()
    {
        return 'MenuEditor';
    }

    /**
     * Initialize extension
     */
    public function initialize()
    {
        $this->configDirectory = $this->app['resources']->getPath('config');
        $this->config = $this->getConfig();
        
        if (!isset($this->config['backupsFolder'])) {
            $this->backupDir = __DIR__ . '/backups';
        } else {
            $this->backupDir = $this->app['resources']->getPath('web') . $this->config['backupsFolder'];
        }

        /**
         * ensure proper config
         */
        if (!isset($this->config['permissions']) || !is_array($this->config['permissions'])) {
            $this->config['permissions'] = array('root', 'admin', 'developer');
        } else {
            $this->config['permissions'][] = 'root';
        }
        if (!isset($this->config['allowCreateNew']) || !is_array($this->config['allowCreateNew'])) {
            $this->config['allowCreateNew'] = array('root', 'admin', 'developer');
        } else {
            $this->config['allowCreateNew'][] = 'root';
        }
        if (!isset($this->config['pathsEditable'])) {
            $this->config['pathsEditable'] = false;
        }
        if (is_array($this->config['pathsEditable']) && !in_array('root', $this->config['pathsEditable'])) {
            $this->config['pathsEditable'][] = 'root';
        }
        if (!isset($this->config['enableBackups']) || !is_bool($this->config['enableBackups'])) {
            $this->config['enableBackups'] = false;
        }
        if (!isset($this->config['keepBackups']) || !is_int($this->config['keepBackups'])) {
            $this->config['keepBackups'] = 10;
        }

        // Add our route
        $this->path = $this->app['config']->get('general/branding/path') . '/extensions/menu-editor';
        $this->app->match($this->path, array($this, 'MenuEditor'));

        $this->app->before(array($this, 'before'));
    }

    public function before()
    {
        // check if user has allowed role(s)
        $currentUser    = $this->app['users']->getCurrentUser();
        $currentUserId  = $currentUser['id'];

        foreach ($this->config['permissions'] as $role) {
            if ($this->app['users']->hasRole($currentUserId, $role)) {
                $this->authorized = true;
                break;
            }
        }
        
        foreach ($this->config['allowCreateNew'] as $role) {
            if ($this->app['users']->hasRole($currentUserId, $role)) {
                $this->allowCreateNew = true;
                break;
            }
        }

        // check if user can edit paths of menu items
        if ($this->config['pathsEditable']) {
            if (is_array($this->config['pathsEditable'])) {
                foreach ($this->config['pathsEditable'] as $role) {
                    if ($this->app['users']->hasRole($currentUserId, $role)) {
                        $this->authorizedForPaths = true;
                        break;
                    }
                }
            } else {
                $this->authorizedForPaths = true;
            }
        }

        // Add the menu item if someone has enough permission.
        if ($this->authorized)
        {
            $this->addMenuOption(Trans::__('Menu editor'), $this->app['resources']->getUrl('bolt') . 'extensions/menu-editor', 'fa:rocket');

            $this->translationDir = __DIR__.'/locales/' . substr($this->app['locale'], 0, 2);

            if (is_dir($this->translationDir))
            {
                $iterator = new \DirectoryIterator($this->translationDir);
                foreach ($iterator as $fileInfo)
                {
                    if ($fileInfo->isFile())
                    {
                        $this->app['translator']->addLoader('yml', new TranslationLoader\YamlFileLoader());
                        $this->app['translator']->addResource('yml', $fileInfo->getRealPath(), $this->app['locale']);
                    }
                }
            }
        }
    }

    /**
     * Add some awesomeness to Bolt
     *
     * @return Response|\Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function MenuEditor()
    {
        if (!$this->authorized) {
            return new Response(Trans::__('Permission denied'), Response::HTTP_FORBIDDEN);
        }

        /**
         * Make sure that no other extensions are interfering with the menueditor JS.
         */
        $this->clearAssets();

        /**
         * check if menu.yml is writable
         */
        $file = $this->configDirectory . '/menu.yml';
        if (@!is_readable($file) || !@is_writable($file)) {
            throw new \Exception(
                Trans::__("The file '%s' is not writable. You will have to use your own editor to make modifications to this file.",
                    array('%s' => $file)));
        }
        if (!$writeLock = @filemtime($file)) {
            $writeLock = 0;
        }

        // add MenuEditor template namespace to twig
        $this->app['twig.loader.filesystem']->addPath(__DIR__.'/views/', 'MenuEditor');

        /**
         * process xhr-post
         */
        if (true === $this->app['request']->isXmlHttpRequest() && 'POST' == $this->app['request']->getMethod())
        {
            /**
             * render new item
             */
            try {
                if ($attributes = $this->app['request']->get('newitem')) {
                    if ($attributes['path'] != '') {
                        unset($attributes['link']);
                    }

                    $html = $this->app['twig']->render('@MenuEditor/_menuitem.twig', array(
                        'item' => $attributes
                    ));

                    return $this->app->json(array('status' => 0, 'html' => $html));
                }

            } catch (MenuEditorException $e) {
                return $this->app->json(array('status' => 1, 'error' => $e->getMessage()));
            }

            /**
             * restore backup
             */
            try {
                if ($filetime = $this->app['request']->get('filetime')) {

                    if ($this->restoreBackup($filetime)) {
                        $this->app['session']->getFlashBag()->set('success', Trans::__('Backup successfully restored'));
                        return $this->app->json(array('status' => 0));
                    }

                    throw new MenuEditorException(Trans::__("Backup file could not be found"));
                }

            } catch (MenuEditorException $e) {
                return $this->app->json(array('status' => 1, 'error' => $e->getMessage()));
            }

            /**
             * save menu(s)
             */
            try {
                if (
                    $menus = $this->app['request']->get('menus')
                    &&  $writeLockToken = $this->app['request']->get('writeLock')
                ){

                    // don't proceed if the file was edited in the meantime
                    if ($writeLock != $writeLockToken) {
                        throw new MenuEditorException($writeLock, 1);
                    } else {
                        $dumper = new YamlDumper();
                        $dumper->setIndentation(2);
                        $yaml = $dumper->dump($this->app['request']->get('menus'), 9999);

                        // clean up dump a little
                        $yaml = preg_replace("~(-)(\n\s+)~mi", "$1 ", $yaml);

                        try {
                            $parser = new YamlParser();
                            $parser->parse($yaml);
                        } catch (ParseException $e) {
                            throw new MenuEditorException($writeLock, 2);
                        }

                        // create backup
                        if (true === $this->config['enableBackups']) {
                            $this->backup($writeLock);
                        }

                        // save
                        if (!@file_put_contents($file, $yaml)) {
                            throw new MenuEditorException($writeLock, 3);
                        }

                        clearstatcache(true, $file);
                        $writeLock = filemtime($file);

                        if (count($this->app['request']->get('menus')) > 1) {
                            $message = Trans::__("Menus successfully saved");
                        } else {
                            $message = Trans::__("Menu successfully saved");
                        }
                        $this->app['session']->getFlashBag()->set('success', $message);

                        return $this->app->json(array('writeLock' => $writeLock, 'status' => 0));

                    }

                    // broken request
                    throw new MenuEditorException($writeLock, 4);

                }

            } catch (MenuEditorException $e) {
                return $this->app->json(array('writeLock' => $e->getMessage(), 'status' => $e->getCode()));
            }

            /**
             * search contenttype(s)
             */
            try {
                if ($this->app['request']->get('action') == 'search-contenttypes') {
                    $ct = $this->app['request']->get('ct');
                    $q = $this->app['request']->get('meq');
                    $retVal = array();

                    if (empty($ct)) {
                        $contenttypes = $this->app['config']->get('contenttypes');
                        foreach ($contenttypes as $ck => $contenttype) {
                            if (isset($contenttype['fields']['title'])) {
                                $retVal[] = $this->app['storage']->getContent($contenttype['name'], array('title'=> "%$q%", 'limit'=>100, 'order'=>'title'));
                            }
                            if (isset($contenttype['fields']['slug'])) {
                                $retVal[] = $this->app['storage']->getContent($contenttype['name'], array('slug'=> "%$q%", 'limit'=>100, 'order'=>'slug'));
                            }
                        }
                    } else {
                        $retVal[] = $this->app['storage']->getContent($ct, array('title'=> "%$q%", 'limit'=>100, 'order'=>'title'));
                        $retVal[] = $this->app['storage']->getContent($ct, array('slug'=> "%$q%", 'limit'=>100, 'order'=>'slug'));
                    }

                    $results = array();
                    foreach ($retVal as $val) {
                        if (!empty($val)) {
                            $results[] = $val;
                        }
                    }

                    return $this->app->json(array('records' => $results));
                }
            } catch (\Exception $e) {

            }

        }

        /**
         * load stuff
         */
        $menus          = $this->app['config']->get('menu');
        $contenttypes   = $this->app['config']->get('contenttypes');
        $taxonomys      = $this->app['config']->get('taxonomy');

        foreach ($contenttypes as $cK => $contenttype) {
            $contenttypes[$cK]['records'] = $this->app['storage']->getContent($contenttype['name'], array());
        }

        foreach ($taxonomys as $tK => $taxonomy)
        {

            $taxonomys[$tK]['me_options'] = array();

            // fetch slugs
            if (isset($taxonomy['behaves_like']) && 'tags' == $taxonomy['behaves_like']) {
                $prefix = $this->app['config']->get('general/database/prefix', "bolt_");

                $taxonomytype = $tK;
                $query = "select distinct %staxonomy.slug from %staxonomy where taxonomytype = ? order by slug asc;";
                $query = sprintf($query, $prefix, $prefix);
                $query = $this->app['db']->executeQuery($query, array($taxonomytype));

                if ($results = $query->fetchAll()) {
                    foreach ($results as $result) {
                        $taxonomys[$tK]['me_options'][$taxonomy['singular_slug'] .'/'. $result['slug']] = $result['slug'];
                    }
                }
            }

            if (isset($taxonomy['behaves_like']) && 'grouping' == $taxonomy['behaves_like']) {
                foreach ($taxonomy['options'] as $oK => $option) {
                    $taxonomys[$tK]['me_options'][$taxonomy['singular_slug'] .'/'. $oK] = $option;
                }
            }

            if (isset($taxonomy['behaves_like']) && 'categories' == $taxonomy['behaves_like']) {
                foreach ($taxonomy['options'] as $option) {
                    $taxonomys[$tK]['me_options'][$taxonomy['singular_slug'] .'/'. $option] = $option;
                }
            }

        }

        // fetch backups
        $backups = array();
        if (true === $this->config['enableBackups'])
        {
            try {
                $backups = $this->backup(0, true);

            } catch (MenuEditorException $e) {
                $this->app['session']->getFlashBag()->set('warning', $e->getMessage());
            }
        }

        $body = $this->app['render']->render('@MenuEditor/base.twig', array(
            'contenttypes'   => $contenttypes,
            'taxonomys'      => $taxonomys,
            'menus'          => $menus,
            'pathsEditable'  => $this->authorizedForPaths,
            'writeLock'      => $writeLock,
            'backups'        => $backups,
            'allowCreateNew' => $this->allowCreateNew,
            'readme'         => $this->getLocalizedReadme()
        ));

        return new Response($this->injectAssets($body));

    }

    /**
     * @param $html
     * @return mixed
     */
    private function injectAssets($html)
    {

        if ($this->dev) {
            $urlbase = $this->app['resources']->getUrl('extensions') . 'local/bacboslab/menueditor/';
            $assets = '<script data-main="{urlbase}assets/app" src="{urlbase}assets/bower_components/requirejs/require.js"></script>';
        } else {
            $urlbase = $this->app['resources']->getUrl('extensions') . 'vendor/bacboslab/menueditor/';
            //$assets = '<script src="{urlbase}assets/app.min.js"></script>';

            // current workaround for firefox issues [v 2.0.2]
            $assets = '<script data-main="{urlbase}assets/app" src="{urlbase}assets/bower_components/requirejs/require.js"></script>';
        }

        $assets .= '<link rel="stylesheet" href="{urlbase}assets/bolt-menu-editor/menueditor.css">';
        $assets = preg_replace('~\{urlbase\}~', $urlbase, $assets);

        // Insert just before </head>
        preg_match("~^([ \t]*)</head~mi", $html, $matches);
        $replacement = sprintf("%s\t%s\n%s", $matches[1], $assets, $matches[0]);
        return $this::str_replace_first($matches[0], $replacement, $html);

    }

    /**
     * Saves a backup of the current menu.yml
     *
     * @param $writeLock
     * @param bool $justFetchList
     * @return array
     * @throws MenuEditorException
     */
    private function backup($writeLock, $justFetchList = false)
    {

        if (!@is_dir($this->backupDir) && !@mkdir($this->backupDir)) {
            // dir doesn't exist and I can't create it
            throw new MenuEditorException($justFetchList ? Trans::__("Please make sure that there is a menueditor/backups folder or disable the backup-feature in config.yml") : $writeLock, 5);
        }

        // try to save a backup
        if (false === $justFetchList && !@copy($this->configDirectory . '/menu.yml', $this->backupDir . '/menu.'. time() . '.yml'))
        {
            throw new MenuEditorException($writeLock, 5);
        }

        // clean up
        $backupFiles = array();
        foreach (new \DirectoryIterator($this->backupDir) as $fileinfo) {
            if ($fileinfo->isFile() && preg_match("~^menu\.[0-9]{10}\.yml$~i", $fileinfo->getFilename())) {
                $backupFiles[$fileinfo->getMTime()] = $fileinfo->getFilename();
            }
        }

        if ($justFetchList)
        {
            // make sure there's at least one backup file (first use...)
            if (count($backupFiles) == 0)
            {
                if (!@copy($this->configDirectory . '/menu.yml', $this->backupDir . '/menu.'. time() . '.yml')) {
                    throw new MenuEditorException(Trans::__("Please make sure that the menueditor/backups folder is writeable by your webserver or disable the backup-feature in config.yml"));
                }
                return $this->backup(0, true);
            }

            krsort($backupFiles);
            return $backupFiles;
        }

        ksort($backupFiles);
        foreach ($backupFiles as $timestamp=>$backupFile)
        {
            if (count($backupFiles) <= (int) $this->config['keepBackups']) {
                break;
            }

            @unlink($this->backupDir . '/' . $backupFile);
            unset($backupFiles[$timestamp]);
        }

    }

    /**
     * Restores a previously saved backup, identified by its timestamp
     *
     * @param $filetime
     * @return bool
     * @throws MenuEditorException
     */
    private function restoreBackup($filetime)
    {

        $backupFiles = $this->backup(0, true);

        foreach ($backupFiles as $backupFiletime=>$backupFile)
        {
            if ($backupFiletime == $filetime)
            {
                // try to overwrite menu.yml
                if (@copy($this->backupDir . '/' . $backupFile, $this->configDirectory . '/menu.yml')) {
                    return true;
                }

                throw new MenuEditorException(Trans::__("Unable to overwrite menu.yml"));
            }
        }

        // requested backup-file was not found
        return false;
    }

    /**
     * symlinks the localized readme file, if existant
     */
    public function getLocalizedReadme()
    {
        $filename = __DIR__ . "/locales/readme_". substr($this->app['locale'], 0, 2) .".md";
        $fallback = __DIR__ . "/locales/readme_en.md";

        if (!$readme = @file_get_contents($filename)) {
            $readme = file_get_contents($fallback);
        }

        // Parse the field as Markdown, return HTML
        return preg_replace("~h1~", "h3", \ParsedownExtra::instance()->text($readme));
    }

    /**
     * @param $search
     * @param $replace
     * @param $subject
     * @return mixed
     *
     */
    private function str_replace_first($search, $replace, $subject)
    {
        $pos = strpos($subject, $search);
        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject;
    }

}
