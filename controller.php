<?php
namespace Concrete\Package\AppointmentStore;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Asset\AssetList;
use Concrete\Core\Command\Task\Manager as TaskManager;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Single as SinglePage;
use Concrete\Core\Page\Page;
use Concrete\Core\Routing\Router;
use Concrete\Package\AppointmentStore\Command\Task\Controller\SyncAppointmentsController;
use Concrete\Package\AppointmentStore\CommunityStore\EventSubscriber;
use Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig;
use Concrete\Package\AppointmentStore\Service\ProductOptionService;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Doctrine\ORM\EntityManagerInterface;
use Concrete\Package\AppointmentStore\RouteList;
use Concrete\Package\AppointmentStore\Security\CredentialCipher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Controller extends Package implements ProviderAggregateInterface
{
    protected $pkgHandle = 'appointment_store';
    protected $pkgVersion = '0.1.19';
    protected $appVersionRequired = '9.0.0';
    protected $phpVersionRequired = '7.4.0';

    protected $packageDependencies = [
        'community_store' => '2.6.0',
    ];

    protected $pkgAutoloaderRegistries = [
        'src' => 'Concrete\\Package\\AppointmentStore',
    ];

    public function getPackageName()
    {
        return t('Appointment Store');
    }

    public function getPackageDescription()
    {
        return t('Adds CalDAV-backed appointment booking to Community Store products.');
    }

    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'Concrete\\Package\\AppointmentStore\\Entity',
        ]);
    }

    public function on_start()
    {
        // Flatpickr is bundled with the package. There are no frontend CDN
        // requests and the version cannot change independently of the package.
        $assets = AssetList::getInstance();
        $assets->register(
            'css',
            'appointment-store/flatpickr-css',
            'assets/vendor/flatpickr/flatpickr.css',
            ['version' => '4.6.13'],
            $this
        );
        $assets->register(
            'javascript',
            'appointment-store/flatpickr-js',
            'assets/vendor/flatpickr/flatpickr.js',
            ['version' => '4.6.13'],
            $this
        );
        $assets->registerGroup('appointment-store/flatpickr', [
            ['css', 'appointment-store/flatpickr-css'],
            ['javascript', 'appointment-store/flatpickr-js'],
        ]);

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        (new RouteList())->loadRoutes($router);

        /** @var TaskManager $taskManager */
        $taskManager = $this->app->make(TaskManager::class);
        $taskManager->extend('appointment_store_sync', function () {
            return $this->app->make(SyncAppointmentsController::class);
        });

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->app->make(EventDispatcherInterface::class);
        /** @var EventSubscriber $subscriber */
        $subscriber = $this->app->make(EventSubscriber::class);

        // Community Store 2.6+ event names.
        $dispatcher->addListener('on_community_store_cart_pre_add', [$subscriber, 'onCartPreAdd']);
        $dispatcher->addListener('on_community_store_cart_post_add', [$subscriber, 'onCartPostAdd']);
        $dispatcher->addListener('on_community_store_cart_get', [$subscriber, 'onCartGet']);
        $dispatcher->addListener('on_community_store_cart_pre_remove', [$subscriber, 'onCartPreRemove']);
        $dispatcher->addListener('on_community_store_cart_pre_clear', [$subscriber, 'onCartPreClear']);
        $dispatcher->addListener('on_community_store_order_created', [$subscriber, 'onOrderCreated']);
        $dispatcher->addListener('on_community_store_order', [$subscriber, 'onOrderPlaced']);
        $dispatcher->addListener('on_community_store_payment_complete', [$subscriber, 'onPaymentComplete']);
        $dispatcher->addListener('on_community_store_order_cancelled', [$subscriber, 'onOrderCancelled']);
    }

    public function install()
    {
        $pkg = parent::install();

        $this->app->make(CredentialCipher::class)->ensureKey();
        $this->installDashboardPages($pkg);
        $this->installContentFile('tasks.xml');

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->app->make(CredentialCipher::class)->ensureKey();
        $this->installDashboardPages($this);
        $this->removeLegacyDashboardPages();
        $this->installContentFile('tasks.xml');
    }

    public function uninstall()
    {
        /** @var EntityManagerInterface $em */
        $em = $this->app->make(EntityManagerInterface::class);
        /** @var ProductOptionService $options */
        $options = $this->app->make(ProductOptionService::class);

        $configs = $em->getRepository(AppointmentProductConfig::class)->findAll();
        foreach ($configs as $config) {
            $options->removeForProduct($config->getProductID());
            $product = Product::getByID($config->getProductID());
            if ($product && $config->getOriginalAllowQuantity() !== null) {
                $product->setNoQty(!$config->getOriginalAllowQuantity());
                $em->persist($product);
            }
        }
        $em->flush();

        parent::uninstall();
    }

    private function removeLegacyDashboardPages(): void
    {
        // Appointment Store 0.1.0–0.1.3 used a top-level dashboard section.
        // Remove only those package-owned legacy pages after the new Store section exists.
        foreach ([
            '/dashboard/appointment_store/products',
            '/dashboard/appointment_store/calendars',
            '/dashboard/appointment_store/accounts',
            '/dashboard/appointment_store',
        ] as $path) {
            $page = Page::getByPath($path);
            if ($page && !$page->isError() && (int) $page->getCollectionID() > 0) {
                $page->delete();
            }
        }
    }

    private function installDashboardPages($pkg): void
    {
        $pages = [
            '/dashboard/store/appointments' => t('Appointments'),
            '/dashboard/store/appointments/accounts' => t('Accounts'),
            '/dashboard/store/appointments/calendars' => t('Calendars'),
            '/dashboard/store/appointments/products' => t('Products'),
        ];

        foreach ($pages as $path => $name) {
            $page = SinglePage::add($path, $pkg);
            if ($page) {
                $page->updateCollectionName($name);
            }
        }
    }
}
