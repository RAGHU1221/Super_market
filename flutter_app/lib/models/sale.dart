class LocalSale {
  final int? localId;
  final String syncUid;
  final String invoiceNoLocal;
  final DateTime saleDate;
  final double subtotal;
  final double discountAmount;
  final double taxAmount;
  final double totalAmount;
  final double paidAmount;
  final String paymentMode;
  final String customerName;
  final String customerPhone;
  final String itemsJson; // serialized cart items for local receipt reprint
  final bool synced;
  final String? serverInvoiceNo;

  LocalSale({
    this.localId,
    required this.syncUid,
    required this.invoiceNoLocal,
    required this.saleDate,
    required this.subtotal,
    required this.discountAmount,
    required this.taxAmount,
    required this.totalAmount,
    required this.paidAmount,
    required this.paymentMode,
    this.customerName = '',
    this.customerPhone = '',
    required this.itemsJson,
    this.synced = false,
    this.serverInvoiceNo,
  });

  Map<String, dynamic> toDbMap() {
    return {
      'id': localId,
      'sync_uid': syncUid,
      'invoice_no_local': invoiceNoLocal,
      'sale_date': saleDate.toIso8601String(),
      'subtotal': subtotal,
      'discount_amount': discountAmount,
      'tax_amount': taxAmount,
      'total_amount': totalAmount,
      'paid_amount': paidAmount,
      'payment_mode': paymentMode,
      'customer_name': customerName,
      'customer_phone': customerPhone,
      'items_json': itemsJson,
      'synced': synced ? 1 : 0,
      'server_invoice_no': serverInvoiceNo,
    };
  }

  factory LocalSale.fromDbMap(Map<String, dynamic> m) {
    return LocalSale(
      localId: m['id'] as int?,
      syncUid: m['sync_uid'] as String,
      invoiceNoLocal: m['invoice_no_local'] as String,
      saleDate: DateTime.parse(m['sale_date'] as String),
      subtotal: (m['subtotal'] as num).toDouble(),
      discountAmount: (m['discount_amount'] as num).toDouble(),
      taxAmount: (m['tax_amount'] as num).toDouble(),
      totalAmount: (m['total_amount'] as num).toDouble(),
      paidAmount: (m['paid_amount'] as num).toDouble(),
      paymentMode: m['payment_mode'] as String,
      customerName: (m['customer_name'] ?? '') as String,
      customerPhone: (m['customer_phone'] ?? '') as String,
      itemsJson: m['items_json'] as String,
      synced: (m['synced'] as int) == 1,
      serverInvoiceNo: m['server_invoice_no'] as String?,
    );
  }
}
