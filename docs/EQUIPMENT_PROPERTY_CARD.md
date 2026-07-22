# Equipment Property Card

## Purpose

The Equipment Property Card provides a compact, print-safe label/card for a single equipment record. It is intended for property files, physical stickers, and quick equipment identification from the Equipment View page.

## Field Mapping

- Property Number: `equipment.property_number`
- Serial Number: `equipment.serial_number`
- Unit Cost: `equipment.acquisition_cost`, formatted as Philippine currency
- Date Acquired: `equipment.acquisition_date`
- Office Issued: current location name
- Equipment Name / Article: `equipment.equipment_name`
- Account Code: equipment category account code
- Category: equipment category name
- QR Code: existing `qr_code_url` accessor, when available

Missing serial numbers and other absent values render as `Not recorded`.

## Equipment Image Behavior

The upper-right image box uses the Equipment model's public photo accessor. Images are centered and rendered with `object-fit: contain` so the photo remains visible without cropping or distortion.

## Placeholder Behavior

If no valid equipment photo exists, the image box renders the same bordered area with the placeholder text `Equipment Image Here`. The image and placeholder use the same dimensions to avoid layout shifts.

## Text Auto-Shrink Behavior

Long names, account codes, categories, and field values use wrapping styles so they stay inside the property card border. The printable layout avoids clipping by allowing long words to wrap inside their cells.

## Print Instructions

Open the property card from Equipment View and use `Print Property Card`, or use the browser print button in the preview. The print stylesheet hides toolbar controls and uses A4 landscape orientation.

## PDF Behavior

The PDF action opens a browser print/PDF-ready card view. Use the browser print dialog and choose `Save as PDF`.

## QR Behavior

The card displays an existing QR code when `qr_code_url` is available. It does not regenerate QR codes.

## Authorization

Property card routes require authentication and the same equipment view permission used by the Equipment resource.

## Troubleshooting

- If the equipment image is missing, verify the file exists on the public disk and `php artisan storage:link` has been run.
- If the printed card is too small or large, adjust browser print scaling before printing on sticker paper.
- If logos do not appear, verify `public/snsu-logo.png` and `public/bagong-pilipinas-logo.png` exist.
