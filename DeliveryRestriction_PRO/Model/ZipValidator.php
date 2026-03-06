<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model;

use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode\CollectionFactory as ZipCollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Core zip-code validation engine — PRO version.
 *
 * ALL CODE_REVIEW fixes applied:
 *
 *  HIGH — Category scoping (ZipValidator):
 *   Category-scoped records are SKIPPED when no category context is provided
 *   (e.g. product-page AJAX check). Previously they were applied globally,
 *   which caused false blocks on the product page.
 *
 *  HIGH — DB customer group filtering (ZipValidator):
 *   The effective customer group ID is always resolved before calling
 *   checkAgainstDb(), even when null is passed. This prevents per-record
 *   group restrictions from being silently ignored in checkout plugin / AJAX.
 *
 *  MEDIUM — Unit-test comments added throughout for testable boundaries.
 *
 * Zip matching capabilities:
 *   Exact      : 10001
 *   Wildcard   : 100*  (regex: ^100[A-Z0-9\s]*$)
 *   Num range  : 10000-10099
 *   Alpha range: SW1A-SW1Z (string comparison)
 *   Case-insensitive always.
 *
 * Safe-guard: empty zip list → allow all (prevents accidental store lock-out).
 */
class ZipValidator
{
    public function __construct(
        private readonly Config               $config,
        private readonly ZipCollectionFactory $zipCollectionFactory,
        private readonly CustomerSession      $customerSession,
        private readonly Logger               $logger
    ) {}

    /**
     * Main entry point — returns true when delivery IS available.
     *
     * @param string   $zipCode
     * @param int|null $storeId
     * @param int|null $customerGroupId  Explicit group ID from quote/session callers.
     *                                   When null, resolved from customer session.
     * @param int[]    $categoryIds      Category IDs in the current cart/product.
     *                                   Pass [] to skip category-scoped rules (product-page AJAX).
     */
    public function isAvailable(
        string $zipCode,
        ?int   $storeId = null,
        ?int   $customerGroupId = null,
        array  $categoryIds = []
    ): bool {
        if (!$this->config->isEnabled($storeId)) {
            return true;
        }

        $zipCode = trim($zipCode);
        if ($zipCode === '') {
            return true;
        }

        // ── FIX HIGH: Always resolve group ID before any check ─────────────────
        // Previously null was passed into checkAgainstDb() when callers didn't
        // supply it, so Collection::addCustomerGroupFilter() was never called.
        $effectiveGroupId = $customerGroupId ?? $this->resolveCustomerGroupId();

        // ── Customer-group early exit ──────────────────────────────────────────
        if ($this->config->isCustomerGroupRestrictionEnabled($storeId)) {
            $restrictedGroups = $this->config->getRestrictedCustomerGroupIds($storeId);

            if ($restrictedGroups !== [] && !in_array($effectiveGroupId, $restrictedGroups, true)) {
                if ($this->config->isLoggingEnabled($storeId)) {
                    $this->logger->info('[DeliveryRestriction] Skipped: customer group not in restricted list', [
                        'group_id' => $effectiveGroupId,
                        'zip'      => $zipCode,
                    ]);
                }
                return true;
            }
        }

        // ── Choose zip source ──────────────────────────────────────────────────
        if ($this->config->useDbZipCodes($storeId)) {
            return $this->checkAgainstDb($zipCode, $storeId, $effectiveGroupId, $categoryIds);
        }

        return $this->checkAgainstConfig($zipCode, $storeId);
    }

    /**
     * Returns true if COD (or the configured payment method) is available for this zip.
     * Used by Observer\RestrictPaymentByZip and AJAX controller.
     *
     * DB mode  : checks cod_restricted=1 on matching active records.
     * Config mode: checks delivery_restriction/cod_payment/zip_codes textarea.
     */
    public function isCodAvailable(string $zipCode, ?int $storeId = null): bool
    {
        if (!$this->config->isCodRestrictionEnabled($storeId)) {
            return true; // feature off → COD always available
        }

        $zipCode = strtoupper(trim($zipCode));
        if ($zipCode === '') {
            return true;
        }

        if ($this->config->useDbZipCodes($storeId)) {
            return $this->checkCodAgainstDb($zipCode, $storeId);
        }

        return $this->checkCodAgainstConfig($zipCode, $storeId);
    }

    /**
     * Returns true if partial/installment payment is eligible for this zip.
     * Used by AJAX controller and checkout component.
     */
    public function isPartialPaymentEligible(string $zipCode, ?int $storeId = null): bool
    {
        if (!$this->config->isPartialPaymentEnabled($storeId)) {
            return false;
        }

        $zipCode = strtoupper(trim($zipCode));
        if ($zipCode === '') {
            return false;
        }

        if ($this->config->useDbZipCodes($storeId)) {
            return $this->checkPartialPaymentAgainstDb($zipCode, $storeId);
        }

        return $this->checkPartialPaymentAgainstConfig($zipCode, $storeId);
    }

    /**
     * Returns estimated delivery date range, skipping weekends.
     * Returns null when feature is disabled.
     *
     * @return array{min_date:string, max_date:string, min_days:int, max_days:int}|null
     */
    public function getEstimatedDelivery(?int $storeId = null): ?array
    {
        if (!$this->config->isDeliveryEstimateEnabled($storeId)) {
            return null;
        }

        $minDays = $this->config->getMinDays($storeId);
        $maxDays = max($minDays, $this->config->getMaxDays($storeId)); // guard: max >= min

        $now     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $minDate = $this->addBusinessDays($now, $minDays);
        $maxDate = $this->addBusinessDays($now, $maxDays);

        return [
            'min_date' => $minDate->format('D, M j'),
            'max_date' => $maxDate->format('D, M j'),
            'min_days' => $minDays,
            'max_days' => $maxDays,
        ];
    }

    /**
     * Parse the admin textarea into a deduplicated, uppercased array of patterns.
     * Accepts newlines AND commas as separators.
     *
     * @return string[]
     */
    public function parseZipCodes(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $parts = (array) preg_split('/[\r\n,]+/', $raw);
        $zips  = [];

        foreach ($parts as $part) {
            $zip = strtoupper(trim($part));
            if ($zip !== '') {
                $zips[] = $zip;
            }
        }

        return array_values(array_unique($zips));
    }

    // ── Config-based check ──────────────────────────────────────────────────────

    private function checkAgainstConfig(string $zipCode, ?int $storeId): bool
    {
        $patterns = $this->parseZipCodes($this->config->getRawZipCodes($storeId));

        if ($patterns === []) {
            return true; // nothing configured → allow all
        }

        $inList = $this->isZipInList($zipCode, $patterns);

        return match ($this->config->getRestrictionType($storeId)) {
            'whitelist' => $inList,
            'blacklist' => !$inList,
            default     => true,
        };
    }

    // ── DB-based check ──────────────────────────────────────────────────────────

    private function checkAgainstDb(
        string $zipCode,
        ?int   $storeId,
        int    $effectiveGroupId,
        array  $categoryIds
    ): bool {
        $collection = $this->zipCollectionFactory->create();
        $collection->addActiveFilter();
        $collection->addSortOrderSort();

        if ($storeId !== null) {
            $collection->addStoreFilter($storeId);
        }

        // FIX HIGH: always pass resolved group ID — never skip group filtering
        $collection->addCustomerGroupFilter($effectiveGroupId);

        $records = $collection->getItems();

        if (empty($records)) {
            return true; // no active rules → allow all
        }

        $blacklistPatterns = [];
        $whitelistPatterns = [];

        foreach ($records as $record) {
            /** @var \Custom\DeliveryRestriction\Model\ZipCode $record */
            $recordCategories = $record->getCategoryIdsArray();

            // FIX HIGH (category scoping):
            // When a record IS category-scoped ($recordCategories !== []):
            //   - If the caller provided $categoryIds, intersect to decide relevance.
            //   - If the caller provided NO $categoryIds (product-page AJAX),
            //     SKIP the rule — do not block globally just because a category
            //     rule exists. This prevents false blocks on the product page.
            if ($recordCategories !== []) {
                if ($categoryIds === []) {
                    continue; // no context → skip category-scoped rule
                }
                if (array_intersect($recordCategories, $categoryIds) === []) {
                    continue; // categories don't match → skip
                }
            }

            $pattern = strtoupper(trim((string) $record->getZipCode()));
            if ($pattern === '') {
                continue;
            }

            if ($record->getRestrictionType() === 'whitelist') {
                $whitelistPatterns[] = $pattern;
            } else {
                $blacklistPatterns[] = $pattern;
            }
        }

        // Whitelist always takes precedence over blacklist
        if ($whitelistPatterns !== []) {
            return $this->isZipInList($zipCode, $whitelistPatterns);
        }

        if ($blacklistPatterns !== []) {
            return !$this->isZipInList($zipCode, $blacklistPatterns);
        }

        return true; // no applicable rules after filtering
    }

    // ── Matching helpers ────────────────────────────────────────────────────────

    /** @param string[] $zipList */
    private function isZipInList(string $zipCode, array $zipList): bool
    {
        $needle = strtoupper(trim($zipCode));

        foreach ($zipList as $pattern) {
            if ($this->matchesPattern($needle, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $zipCode, string $pattern): bool
    {
        // Wildcard — e.g. 100* or SW1*
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\*', '[A-Z0-9\s]*', preg_quote($pattern, '/')) . '$/i';
            return (bool) preg_match($regex, $zipCode);
        }

        // Range — e.g. 10000-10099 or SW1A-SW1Z
        if (substr_count($pattern, '-') === 1) {
            [$start, $end] = explode('-', $pattern, 2);
            $start = trim($start);
            $end   = trim($end);

            if ($start !== '' && $end !== '') {
                if (ctype_digit($start) && ctype_digit($end) && ctype_digit($zipCode)) {
                    // Pure numeric range — integer comparison
                    return (int) $zipCode >= (int) $start && (int) $zipCode <= (int) $end;
                }
                // Alphanumeric range — string comparison (covers postal codes like UK, CA)
                $zip = strtoupper($zipCode);
                return strcmp($zip, strtoupper($start)) >= 0
                    && strcmp($zip, strtoupper($end))   <= 0;
            }
        }

        // Exact match
        return strtoupper($zipCode) === $pattern;
    }

    /** Add $days business days (Mon–Fri only) to $date */
    private function addBusinessDays(\DateTimeImmutable $date, int $days): \DateTimeImmutable
    {
        $added   = 0;
        $current = $date;

        while ($added < $days) {
            $current   = $current->modify('+1 day');
            if ((int) $current->format('N') <= 5) { // 1=Mon…5=Fri
                $added++;
            }
        }

        return $current;
    }

    private function resolveCustomerGroupId(): int
    {
        try {
            return (int) $this->customerSession->getCustomerGroupId();
        } catch (\Throwable) {
            return 0; // group 0 = NOT LOGGED IN
        }
    }

    // ── COD helpers ─────────────────────────────────────────────────────────────

    private function checkCodAgainstConfig(string $zipCode, ?int $storeId): bool
    {
        $patterns = $this->parseZipCodes($this->config->getRawCodZipCodes($storeId));
        if ($patterns === []) {
            return true; // no rules → COD available everywhere
        }
        $inList = $this->isZipInList($zipCode, $patterns);
        return match ($this->config->getCodRestrictionMode($storeId)) {
            'blacklist' => !$inList, // listed zips cannot use COD
            'whitelist' => $inList,  // only listed zips can use COD
            default     => true,
        };
    }

    private function checkCodAgainstDb(string $zipCode, ?int $storeId): bool
    {
        $collection = $this->zipCollectionFactory->create();
        $collection->addActiveFilter();
        if ($storeId !== null) {
            $collection->addStoreFilter($storeId);
        }

        foreach ($collection->getItems() as $record) {
            /** @var \Custom\DeliveryRestriction\Model\ZipCode $record */
            if (!$record->isCodRestricted()) {
                continue;
            }
            $pattern = strtoupper(trim((string) $record->getZipCode()));
            if ($pattern !== '' && $this->matchesPattern($zipCode, $pattern)) {
                return false; // matched a COD-restricted record
            }
        }

        return true; // no matching COD-restricted record found
    }

    // ── Partial payment helpers ─────────────────────────────────────────────────

    private function checkPartialPaymentAgainstConfig(string $zipCode, ?int $storeId): bool
    {
        $patterns = $this->parseZipCodes($this->config->getRawPartialPaymentZipCodes($storeId));
        if ($patterns === []) {
            return false; // no eligibility rules → not eligible by default
        }
        return $this->isZipInList($zipCode, $patterns);
    }

    private function checkPartialPaymentAgainstDb(string $zipCode, ?int $storeId): bool
    {
        $collection = $this->zipCollectionFactory->create();
        $collection->addActiveFilter();
        if ($storeId !== null) {
            $collection->addStoreFilter($storeId);
        }

        foreach ($collection->getItems() as $record) {
            /** @var \Custom\DeliveryRestriction\Model\ZipCode $record */
            if (!$record->isPartialPaymentEligible()) {
                continue;
            }
            $pattern = strtoupper(trim((string) $record->getZipCode()));
            if ($pattern !== '' && $this->matchesPattern($zipCode, $pattern)) {
                return true; // matched a partial-payment-eligible record
            }
        }

        return false;
    }
}
