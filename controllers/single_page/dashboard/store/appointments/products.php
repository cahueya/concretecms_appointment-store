<?php
namespace Concrete\Package\AppointmentStore\Controller\SinglePage\Dashboard\Store\Appointments;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\AppointmentStore\Entity\AppointmentBooking;
use Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig;
use Concrete\Package\AppointmentStore\Entity\AppointmentReservation;
use Concrete\Package\AppointmentStore\Entity\CalendarSource;
use Concrete\Package\AppointmentStore\Service\ProductOptionService;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductList;
use Doctrine\ORM\EntityManagerInterface;

class Products extends DashboardPageController
{
    public function view()
    {
        $this->loadView();
    }

    public function edit($id)
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $config = $em->find(AppointmentProductConfig::class, (int) $id);
        if (!$config) {
            $this->error->add(t('Product appointment configuration not found.'));
        }
        $this->loadView($config);
    }

    public function save()
    {
        if (!$this->token->validate('appointment_store_product')) {
            $this->error->add(t('Invalid security token.'));
            return $this->loadView();
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $productID = (int) $this->post('productID');
        $product = Product::getByID($productID);
        $source = $em->find(CalendarSource::class, (int) $this->post('sourceID'));
        if (!$product || !$source) {
            $this->error->add(t('A valid Community Store product and calendar source are required.'));
            return $this->loadView();
        }

        $display = (string) $this->post('displayMode');
        if (!in_array($display, [AppointmentProductConfig::DISPLAY_AUTO, AppointmentProductConfig::DISPLAY_SELECT, AppointmentProductConfig::DISPLAY_CALENDAR], true)) {
            $display = AppointmentProductConfig::DISPLAY_AUTO;
        }
        $trigger = (string) $this->post('bookingTrigger');
        if (!in_array($trigger, [AppointmentProductConfig::TRIGGER_ORDER_PLACED, AppointmentProductConfig::TRIGGER_PAYMENT_COMPLETE], true)) {
            $trigger = AppointmentProductConfig::TRIGGER_ORDER_PLACED;
        }

        $config = $em->getRepository(AppointmentProductConfig::class)->findOneBy(['productID' => $productID]);
        if (!$config) {
            $config = new AppointmentProductConfig();
        }

        // Populate the configuration before touching Community Store. If option creation fails,
        // the form can be rendered again without dereferencing an uninitialized calendar source.
        $config->setProductID($productID)
            ->setSource($source)
            ->setDisplayMode($display)
            ->setReservationMinutes((int) ($this->post('reservationMinutes') ?: 30))
            ->setMinimumBookingNoticeHours((int) ($this->post('minimumBookingNoticeHours') ?? 24))
            ->setBookingTrigger($trigger)
            ->setReleaseOnCancel((bool) $this->post('releaseOnCancel'))
            ->setEnabled(true);

        if ($config->getOriginalAllowQuantity() === null) {
            $config->setOriginalAllowQuantity((bool) $product->allowQuantity());
        }

        try {
            $option = $this->app->make(ProductOptionService::class)->ensureForProduct($productID);
        } catch (\Throwable $e) {
            $this->error->add($e->getMessage());
            return $this->loadView($config);
        }

        // An appointment slot is a single bookable unit. Community Store then keeps its cart quantity at one.
        $product->setNoQty(true);
        $em->persist($product);

        $config->setProductOptionID((int) $option->getID());
        $em->persist($config);
        $em->flush();
        $this->flash('message', t('Product appointment configuration saved.'));
        return $this->buildRedirect($this->action('edit', $config->getId()));
    }

    public function delete($id)
    {
        if (!$this->token->validate('appointment_store_delete_product')) {
            $this->error->add(t('Invalid security token.'));
            return $this->loadView();
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $config = $em->find(AppointmentProductConfig::class, (int) $id);
        if (!$config) {
            return $this->buildRedirect($this->action('view'));
        }
        $this->app->make(ProductOptionService::class)->removeForProduct($config->getProductID());
        $product = Product::getByID($config->getProductID());
        if ($product && $config->getOriginalAllowQuantity() !== null) {
            $product->setNoQty(!$config->getOriginalAllowQuantity());
            $em->persist($product);
        }
        $hasBookings = $em->getRepository(AppointmentBooking::class)->count(['productConfig' => $config]) > 0;
        $hasReservations = $em->getRepository(AppointmentReservation::class)->count(['productConfig' => $config]) > 0;
        if ($hasBookings || $hasReservations) {
            $config->setEnabled(false);
        } else {
            $em->remove($config);
        }
        $em->flush();
        $this->flash('message', t('Appointment booking was disabled for the product.'));
        return $this->buildRedirect($this->action('view'));
    }

    private function loadView(?AppointmentProductConfig $selected = null)
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $list = new ProductList();
        $list->setActiveOnly(false);
        $list->setItemsPerPage(1000);
        $this->set('products', $list->getResults());
        $this->set('sources', $em->getRepository(CalendarSource::class)->findBy(['enabled' => true], ['name' => 'ASC']));
        $this->set('configs', $em->getRepository(AppointmentProductConfig::class)->findBy([], ['productID' => 'ASC']));
        $this->set('selected', $selected);
        $this->set('token', $this->token);
    }
}
