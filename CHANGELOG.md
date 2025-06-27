# Changelog

## [[v1.3.0]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.3.0)

### Added

- Added plugin support for the markup API to accurately represent fees on WooCommerce orders.

### Fixed

- Resolved an issue with the cron debugging tool causing cancelled orders to not update.

### Changed

- Verified compatibility with WooCommerce 9.8.5 and WordPress 6.8.1.

## [[v1.2.2]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.2.2)

### Fixed

- Fixed an issue in the “Add Order Meta to Service” functionality, where entering an invalid Meta Key caused errors
  during checkout.
- Fixed issues causing the cron job to fail under certain conditions.

### Changed

- Updated the default placeholder examples on the “Add Order Meta to Service” setting.

## [[v1.2.1]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.2.1)

### Fixed

- Resolved an issue with the Service Type configuration that caused an “Unable to connect to payment gateway” error
  during checkout on some servers.

## [[v1.2.0]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.2.0)

### Changed

- Code quality improvements.
- Verified compatibility with WooCommerce 9.4.3 and WordPress 6.7.1.

## [[v1.1.6]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.1.6)

### Changed

- Updated to PHP 8.2 code quality standards.
- General bug fixes and improvements.

### Tested

- Verified compatibility with WooCommerce 9.1.4, PHP 8.2, and WordPress 6.6.1.

## [[v1.1.5]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.1.5)

### Tested

- Verified compatibility with WooCommerce 8.9.1, PHP 8.1, and WordPress 6.5.3.

## [[v1.1.4]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.1.4)

### Added

- Support for HPOS.
- DPO Pay order filter.

### Fixed

- Decline transactions if the reference and order ID don't match.

### Changed

- Amended Return and Cancel URLs for improved compatibility.
- Modified product invocation method for better compatibility.

### Tested

- Verified compatibility with WooCommerce 8.4.0 and WordPress 6.4.

## [[v1.1.3]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.1.3)

### Added

- Support for WooCommerce Blocks.

### Changed

- Bug fixes and general improvements.

### Tested

- Verified compatibility with WooCommerce 7.6.0 and WordPress 6.2.

## [[v1.1.2]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.1.2)

### Changed

- Updated for PHP 8.0.
- Bug fixes and general improvements.

### Tested

- Verified compatibility with WooCommerce 7.3.0 and WordPress 6.1.1.

## [[v1.1.1]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.1.1)

### Added

- Debug logging in the order side.
- Test for cURL extension.
- Card icon selection feature.

### Changed

- Moved plugin to WordPress.org.

### Tested

- Verified compatibility with WooCommerce 6.0 and WordPress 5.9.

## [[v1.1.0]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.1.0)

### Added

- Logging in cron job.
- DPO order reference to narrative.
- DPO payment icons.
- Push Payments handling.
- Cron checks for old and unpaid orders using WP cron scheduling.
- New `<TransactionSource>` tag.
- Support for MWK currency.

### Changed

- Improved code quality.
- Updated GitHub URL.

### Tested

- Verified compatibility with WooCommerce 5.4.1 and WordPress 5.7.2.

## [[v1.0.16]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.0.16)

### Changed

- Improved HTTP error display.

### Added

- Backwards compatibility with previous URL structures.

### Tested

- Verified compatibility with WooCommerce 4.2 and WordPress 5.4.2.

## [[v1.0.15]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.0.15)

### Added

- Test configuration.

### Changed

- Updated endpoints.
- Improved cancel handling.
- Improved error handling.

### Tested

- Compatibility updates for WooCommerce 4 and PHP 7.3.

## [[v1.0.14]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.0.14)

### Added

- "DPO Pay URL" feature.
- "Add Order Meta to Service" feature.
- "Add Order Meta to CompanyAccRef" feature.
- "Default DPO Service Type" feature.

### Fixed

- Warnings for `wp_enqueue_scripts` and `frontend_scripts_and_styles`.

## [[v1.0.13]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.0.13)

### Added

- Order status workflow to settings.

### Note

- If "Reduce Stock Automatically" was set to "no," update order status settings to "on-hold."

## [[v1.0.12]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/v1.0.12)

### Added

- Push XOF if CFA selected.

## [[v1.0.11]](https://github.com/DPO-Group/DPO_WooCommerce/releases/tag/1.0.11)

### Added

- Support for WooCommerce 3.
- Changed DPO Group branding.

### Fixed

- Broken "Pay Now" link in WooCommerce emails.

### Changed

- Moved to GitHub.

## [v1.0.10]

### Changed

- Improved multisite activation.

## [v1.0.9]

### Changed

- Updated DPO default checkout image.

## [v1.0.8]

### Added

- cURL SSL version 6 support.
- DPO URL version updated to 6.

## [v1.0.7]

### Changed

- Updated banner images and text.

## [v1.0.6]

### Fixed

- Order status bug.

## [v1.0.5]

### Fixed

- General bug.

## [v1.0.4]

### Added

- Checkout image and image URL settings fields to 3G Direct Pay settings.

## [v1.0.3]

### Fixed

- General bug.

## [v1.0.2]

### Removed

- Service description from product settings.

## [v1.0.1]

### Added

- 3G URL input.
- PTL Type select box.
- PTL input.

## [v1.0.0]

### Added

- First public release.
