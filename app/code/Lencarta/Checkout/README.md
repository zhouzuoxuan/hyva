# Lencarta_Checkout

## This package updates
- `/checkout` now resolves to the custom Lencarta checkout page through a custom router alias.
- The internal route remains `lencarta_checkout`, so the page handle stays `lencarta_checkout_index_index`.
- This keeps the clean `/checkout` URL while greatly reducing interference from modules targeting Magento's default checkout handles.
- Added coupon apply/remove AJAX endpoints.
- Hardened AJAX controllers with form key validation and centralized checkout state responses.
- Empty cart access to checkout now redirects back to `checkout/cart`.

## Install
1. Replace the existing module files.
2. Run:
   - `bin/magento setup:upgrade`
   - `bin/magento cache:flush`
3. If in production mode, also run:
   - `bin/magento setup:di:compile`
   - `bin/magento setup:static-content:deploy -f`

## Notes
- The PayPal module can continue to target the `lencarta_checkout_index_index` layout handle.
- Existing `lencarta_checkout/*` AJAX URLs remain valid.
