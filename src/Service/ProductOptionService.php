<?php
namespace Concrete\Package\AppointmentStore\Service;

use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductOption\ProductOption;

class ProductOptionService
{
    public const HANDLE = 'appointment_slot';
    public const MACHINE_FIELD = 'appointment_store_slot_id';
    public const RESERVATION_TOKEN_FIELD = 'appointment_store_reservation_token';
    public const MARKER = 'Managed by Appointment Store';

    public function ensureForProduct(int $productID): ProductOption
    {
        $product = Product::getByID($productID);
        if (!$product) {
            throw new \InvalidArgumentException(t('Community Store product not found.'));
        }

        $options = ProductOption::getOptionsForProduct($product);
        $sort = 10;
        foreach ($options as $option) {
            $sort = max($sort, ((int) $option->getSort()) + 10);
            if ($option->getHandle() !== self::HANDLE) {
                continue;
            }
            if ((string) $option->getDetails() !== self::MARKER) {
                throw new \RuntimeException(t(
                    'A Community Store product option with the handle "appointment_slot" already exists and is not managed by Appointment Store.'
                ));
            }
            $option->update(
                $product,
                $option->getName() ?: 'Appointment',
                $option->getSort(),
                'text',
                self::HANDLE,
                true,
                false,
                $option->getDisplayType(),
                self::MARKER
            );
            return $option;
        }

        return ProductOption::add(
            $product,
            'Appointment',
            $sort,
            'text',
            self::HANDLE,
            true,
            false,
            '',
            self::MARKER
        );
    }

    public function removeForProduct(int $productID): void
    {
        $product = Product::getByID($productID);
        if (!$product) {
            return;
        }
        foreach (ProductOption::getOptionsForProduct($product) as $option) {
            if ($option->getHandle() === self::HANDLE && (string) $option->getDetails() === self::MARKER) {
                $option->delete();
            }
        }
    }
}
