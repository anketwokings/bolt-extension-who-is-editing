<?php

namespace Bolt\Extension\TwoKings\WhoIsEditing;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Asset\Target;
use Bolt\Asset\Widget\Widget;
use Bolt\Controller\Zone;
use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Extension\DatabaseSchemaTrait;
use Bolt\Extension\SimpleExtension;
use Bolt\Extension\TwoKings\WhoIsEditing\Controller\WhoIsEditingController;
use Bolt\Extension\TwoKings\WhoIsEditing\Service\WhoIsEditingService;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WhoIsEditing extension class.
 *
 * @author Néstor de Dios Fernández <nestor@twokings.nl>
 */
class WhoIsEditingExtension extends SimpleExtension
{
    use DatabaseSchemaTrait;

    /**
     * @todo Add a new widget to be displayed on edit config file pages
     *
     * {@inheritdoc}
     */
    protected function registerAssets()
    {
        $javascript = JavaScript::create()
            ->setFileName('who-is-editing.js')
            ->setLate(true)
            ->setZone(Zone::BACKEND)
        ;

        $css = Stylesheet::create()
            ->setFileName('who-is-editing.css')
            ->setLate(true)
            ->setZone(Zone::BACKEND)
        ;

        $widget1 = Widget::create()
            ->setZone(Zone::BACKEND)
            ->setLocation(Target::WIDGET_BACK_EDITCONTENT_ASIDE_TOP)
            ->setCallback([$this, 'outputActionsWidget'])
            ->setClass('who-is-editing-widget')
            ->setDefer(false)
        ;

        return [
            $widget1,
            $javascript,
            $css
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerBackendControllers()
    {
        return [
            '/' => new WhoIsEditingController(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [
            'templates' => [
                'position'  => 'prepend',
                'namespace' => 'whoisediting',
            ]
        ];
    }

    /**
     * The callback function to render the widget template.
     *
     * @return string HTML that displays the widget
     */
    public function outputActionsWidget()
    {
        $app = $this->getContainer();
        $request = $app['request'];
        $user = $app['users']->getCurrentUser();
        $hourstoSubstract = $this->getConfig()['lastActions'];

        $actions = $app['whoisediting.service']->fetchActions(
            $request,
            $request->get('contenttypeslug'),
            $request->get('id'),
            $user['id'],
            $hourstoSubstract
        );

        // If we don't have actions to show, show nothing and set ajax request data
        if(!$actions) {
            $contenttype = $request->attributes->get('contenttypeslug');
            $id = $request->attributes->get('id');
            return $app['twig']->render('@whoisediting/no_actions.twig', [
                'contenttype'        => $contenttype,
                'id'                 => $id,
                'whoiseditingconfig' => $app['whoisediting.config'],
            ]);
        }

        return $this->renderTemplate('actions_widget.twig', [
            'actions'            => $actions,
            'actionsmetadata'    => $app['whoisediting.service']->getActionsMetaData(),
            'whoiseditingconfig' => $app['whoisediting.config'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
        $this->extendDatabaseSchemaServices();

        $app['whoisediting.service'] = $app->share(
            function ($app) {
                return new WhoIsEditingService($app['storage']->getConnection());
            }
        );

        $app['whoisediting.config'] = $app->share(function ($app) {
            return parent::getConfig();
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'timeInterval' => 3000,
            'lastActions'  => 3,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerExtensionTables()
    {
        return [
            'extension_who_is_editing' => Storage\Schema\Table\ActionsTable::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $parentEvents = parent::getSubscribedEvents();
        $localEvents = [
            StorageEvents::POST_SAVE  => [
                ['onSave', 0],
            ],
            StorageEvents::POST_DELETE  => [
                ['onDelete', 0],
            ],
        ];

        return $parentEvents + $localEvents;
    }

    /**
     * StorageEvents::POST_SAVE event callback.
     *
     * @param StorageEvent $event
     */
    public function onSave(StorageEvent $event)
    {
        $app = $this->getContainer();
        $user = $app['users']->getCurrentUser();

        $app['whoisediting.service']->update(
            $event->getContentType(),
            $event->getId(),
            $user['id'],
            'update'
        );
    }

    /**
     * StorageEvents::POST_DELETE event callback.
     *
     * @param StorageEvent $event
     */
    public function onDelete(StorageEvent $event)
    {
        $app = $this->getContainer();
        $user = $app['users']->getCurrentUser();

        $app['whoisediting.service']->update(
            $event->getContentType(),
            $event->getId(),
            $user['id'],
            'delete'
        );
    }

}
