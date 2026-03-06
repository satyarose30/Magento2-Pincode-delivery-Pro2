<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Helper;

use Magento\Quote\Model\Quote;

/**
 * Centralized category extraction from a Quote.
 *
 * FIX (CODE_REVIEW Medium): Eliminates the duplicate extractCategoryIds() method
 * that existed in both ShippingInformationPlugin and ValidateZipOnOrderPlace,
 * preventing divergence in future updates.
 *
 * FIX (CODE_REVIEW Low): Gracefully handles partially-loaded products — if
 * getCategoryIds() throws or returns a non-array, that item is safely skipped.
 */
class CategoryExtractor
{
    /**
     * Returns unique category IDs from all visible items in the quote.
     * Returns an empty array if products are not loaded or have no categories.
     *
     * @return int[]
     */
    public function extractFromQuote(Quote $quote): array
    {
        $categoryIds = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            try {
                $product = $item->getProduct();
                if ($product === null) {
                    continue;
                }

                $cats = $product->getCategoryIds();
                if (!is_array($cats)) {
                    continue;
                }

                foreach ($cats as $catId) {
                    $categoryIds[(int) $catId] = true;
                }
            } catch (\Throwable) {
                // Skip items with loading issues — do not propagate
                continue;
            }
        }

        return array_keys($categoryIds);
    }
}
