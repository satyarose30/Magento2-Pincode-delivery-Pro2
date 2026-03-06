# Code Review Recommendations

## Scope Reviewed
- `DeliveryRestriction_PRO/Plugin/ShippingInformationPlugin.php`
- `DeliveryRestriction_PRO/Plugin/PaymentMethodPlugin.php`
- `DeliveryRestriction_PRO/Observer/RestrictPaymentByZip.php`
- `DeliveryRestriction_PRO/Model/ZipValidator.php`
- `DeliveryRestriction_PRO/Model/ResourceModel/ZipCode/Collection.php`
- `DeliveryRestriction_PRO/Model/Config.php`

## High Priority

1. **Use quote/cart store ID instead of current store manager in checkout plugins**
   - `ShippingInformationPlugin` and `PaymentMethodPlugin` currently resolve the store via `StoreManagerInterface::getStore()`.
   - In multi-store and headless scenarios, the current store may differ from the quote store.
   - Recommendation: derive `$storeId` from quote/cart (`$quote->getStoreId()` or quote loaded by cart ID) and pass it through all validator/config calls.

2. **Reduce full-rule scans in DB mode for large rule sets**
   - `ZipValidator::checkAgainstDb()` loads all active rules into PHP via `$collection->getItems()` and evaluates every pattern in-memory.
   - This can become expensive when rules grow into thousands.
   - Recommendation: add pre-filtering strategy (e.g., normalize rule type, exact-prefix column, or split exact/wildcard/range into separate indexed columns) and short-circuit early where possible.

## Medium Priority

3. **Avoid FIND_IN_SET on comma-separated fields for hot paths**
   - Store and customer-group filters use `FIND_IN_SET` on CSV columns.
   - This prevents efficient index usage and grows poorly.
   - Recommendation: move to relation tables (`zipcode_store`, `zipcode_customer_group`, optional `zipcode_category`) and join-based filters.

4. **Harmonize store-scoping behavior across payment restrictions**
   - `RestrictPaymentByZip` calls config/validator methods without an explicit store ID.
   - Other components pass explicit store IDs.
   - Recommendation: resolve store ID from quote when available and use it consistently to avoid config drift between storefront contexts.

## Low Priority

5. **Trim production comments that reference historical review fixes**
   - Several classes include long "ALL CODE_REVIEW fixes applied" blocks.
   - Useful during review, but noisy in long-term maintenance.
   - Recommendation: replace with concise docblocks and rely on changelog/PR history for fix provenance.

6. **Consider stronger typing around restriction mode values**
   - Modes like `blacklist`/`whitelist` are plain strings across config + validator.
   - Recommendation: centralize accepted constants (or enum if PHP version allows) and validate unknown config values in one place.

## Suggested Validation After Changes
- Add integration coverage for:
  - multi-store quote with mismatched current store context,
  - large DB ruleset performance baseline,
  - payment method filtering parity between observer and method-list plugin.
