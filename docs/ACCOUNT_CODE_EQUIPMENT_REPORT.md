# Account Code Equipment Report

## Report Purpose

The Equipment by Account Code report lists actual stored equipment records grouped by government account code and category.

## Grouping Rules

Equipment is grouped by category account code and category name. Empty account-code sections are not shown.

## Column Mapping

- Article: Equipment Name / Article
- Description: equipment description
- Date Acquired: acquisition date
- Property Number: property number
- Unit Value: acquisition cost
- Total Value: acquisition cost for one equipment record
- Remarks: current location

The sample quantity columns are intentionally excluded.

## Location-as-Remarks Rule

The Remarks column displays the equipment current location or office where available.

## Subtotal Calculation

Each account-code section sums the Total Value for all equipment in that group.

## Grand Total Calculation

The grand total sums all equipment Total Value amounts across every account-code section included by the active filters.

## Filters

The report supports account code, category, location, acquisition date range, condition, operational status, and archived status filters through the existing Report Center.

## Sorting

The default order is account code ascending, category name ascending, property number ascending, then equipment name ascending.

## Output Formats

The report uses the existing Report Center output routes for on-screen preview, print, PDF-ready view, Excel, and CSV.

## Sample Report Structure

```text
Account Code 10604010 - Building

Article | Description | Date Acquired | Property Number | Unit Value | Total Value | Remarks
...
TOTAL VALUE | PHP 36,574,001.94

GRAND TOTAL | PHP 36,574,001.94
```
