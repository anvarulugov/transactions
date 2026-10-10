# Receipt import and market purchases

Transactions can be marked `is_market_purchase`. Receipt photos are stored on the local private disk and served through authenticated, scoped routes. Images are never sent to an external AI provider. The Dockerfile installs Tesseract with English and Russian OCR data. PHP-FPM deployments also need the system packages `tesseract-ocr`, `tesseract-ocr-eng`, and `tesseract-ocr-rus`, with `/usr/bin/tesseract` accessible to the PHP user.

The browser resizes/encodes camera or gallery photos as JPEG. POST `/api/receipts` reads the image and returns an editable draft; it writes no transactions. OCR is a suggestion: headers, faint printing, abbreviations, discounts and unfamiliar receipt layouts can need manual correction. Unknown quantities, units and categories require review. The user must check all rows and the receipt total before confirmation.

POST `/api/receipts/{id}/confirm` requires explicit confirmation, valid active categories and required dimensions, positive exact quantities and monetary amounts, and a matching total. It writes the whole receipt atomically under a row lock. Retries return the same entries. Identical image uploads by the same user reuse their receipt. New photos of the same paper are not automatically identified as duplicates. Images remain available from history/admin under the same access scope as transactions.

Run PHP, Composer, Artisan and tests through Makefile in the app container. Run `make app-lint app-test app-deploy` for the frontend. The test fixture is a synthetic receipt; OCR integration runs against the installed engine. Back up source and PostgreSQL before applying the additive migration. Deploy assets first and index.html last; keep prior hashed assets for open Telegram webviews.
