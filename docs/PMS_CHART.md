# Equipment PMS Chart

## Purpose

The Equipment Preventive Maintenance Service (PMS) Chart is a read-only, printable history designed to be attached to an equipment item. It updates from existing maintenance records and does not require manual chart entry.

## Field Mapping

| Chart field | System field |
| --- | --- |
| Model | Equipment Name / Article (`equipment.equipment_name`) |
| Serial Number | `equipment.serial_number`; displays `Not Recorded` when empty |
| Office | Current Location (`equipment.currentLocation.name`) |
| Date | Schedule/work-order completion date |
| Inspection / Maintenance Detail | Completion remarks, checklist instructions, maintenance type, action performed, or work-order description in order of relevance |
| Performed By | Schedule completer/assignee or work-order accepted/assigned technician |

## Data Source

The chart includes:

- Completed Maintenance Schedule records with a completion date.
- Recommendation-linked preventive work orders only when the work order status is `Completed` and both completion and verification timestamps are present.
- Preventive, due-soon, initial-maintenance, and warranty-inspection recommendation actions.

It excludes pending, rejected, cancelled, reopened, unverified, beyond-repair, and corrective work. Recommendations are never shown as chart rows by themselves.

## Workflow

Maintenance Schedule completion creates a PMS row immediately. Where preventive work proceeds through a recommendation, maintenance request, and work order, the row appears after the work order is completed and verified.

## Auto-update Behavior

The chart is generated from current database records every time it is viewed, printed, or downloaded. No separate PMS chart table or manual synchronization is used.

## Print Instructions

From an Equipment View page, open **PMS Chart** and choose **View PMS Chart**, **Print PMS Chart**, **Download PDF**, or **Open in New Tab**. Print uses A4 portrait orientation, hides application controls, repeats the table header on subsequent pages, and does not truncate history.
