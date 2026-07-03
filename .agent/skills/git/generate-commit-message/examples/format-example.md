dev100 - feat(orders): implement order sheet validation and summary exclusion logic
This commit enhances the order management flow by adding robust frontend validation and backend support for accurate contract summaries. Key updates include a new summary exclusion API to prevent self-counting during order updates, cascade deletion for orders, and improved sorting for contract sheets.

frontend:
[NEW]
- InfoDialog.tsx: add reusable info/error dialog component
- ContractForm.tsx: add dependencies for validation consistency
- ContractModal.tsx: add modal handling

[MODIFY]
- ContractSheetConfirmation.tsx: update sheet confirmation view
- ContractSheetTable.tsx: refactor table to support validation feedback
- OrderForm.tsx: integrate validation props and handlers

backend:
[NEW]
- Contract.php:  add default ordering by sheet_seqno to contractSheets relation
- Order.php: implement cascade delete for ordersheets in boot event
- ContractController.php: refine contract sheet data filtering on save

[MODIFY]
- ContractSheetController.php: add endpoint for contract summary with order exclusion
- OrderController.php: update delete method to use repository
- ContractOrderSummaryRepository.php: implement summary logic with order exclusion
