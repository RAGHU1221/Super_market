import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:uuid/uuid.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../db/database_helper.dart';
import '../models/product.dart';
import '../models/cart_item.dart';
import '../models/sale.dart';
import '../services/api_service.dart';
import '../services/sync_service.dart';
import '../utils/receipt_printer.dart';
import 'sales_history_screen.dart';
import 'settings_screen.dart';

class BillingScreen extends StatefulWidget {
  final ApiService api;
  const BillingScreen({super.key, required this.api});

  @override
  State<BillingScreen> createState() => _BillingScreenState();
}

class _BillingScreenState extends State<BillingScreen> {
  final db = DatabaseHelper.instance;
  final searchController = TextEditingController();
  final customerNameController = TextEditingController();
  final customerPhoneController = TextEditingController();
  final discountController = TextEditingController(text: '0');

  List<Product> allProducts = [];
  List<Product> filtered = [];
  List<CartItem> cart = [];
  String paymentMode = 'cash';
  bool syncing = false;
  String syncStatus = '';

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    await _loadLocalProducts();
    await _sync(silent: true);
  }

  Future<void> _loadLocalProducts() async {
    final products = await db.getAllProducts();
    setState(() {
      allProducts = products;
      filtered = products;
    });
  }

  Future<void> _sync({bool silent = false}) async {
    setState(() { syncing = true; syncStatus = 'Syncing...'; });
    final sync = SyncService(widget.api);
    final result = await sync.fullSync();
    await _loadLocalProducts();
    setState(() {
      syncing = false;
      syncStatus = result.message;
    });
    if (!silent && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result.message)));
    }
  }

  void _filter(String query) {
    setState(() {
      if (query.isEmpty) {
        filtered = allProducts;
      } else {
        final q = query.toLowerCase();
        filtered = allProducts.where((p) =>
            p.name.toLowerCase().contains(q) ||
            (p.nameTa ?? '').contains(query) ||
            (p.barcode ?? '').contains(query) ||
            p.sku.toLowerCase().contains(q)).toList();
      }
    });
  }

  Future<void> _onScanSubmit(String value) async {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return;
    final exact = allProducts.where((p) => p.barcode == trimmed).toList();
    if (exact.isNotEmpty) {
      _addToCart(exact.first);
      searchController.clear();
      _filter('');
    }
  }

  void _addToCart(Product p) {
    setState(() {
      final existing = cart.where((c) => c.product.id == p.id).toList();
      if (existing.isNotEmpty) {
        existing.first.qty += 1;
      } else {
        cart.add(CartItem(product: p));
      }
    });
  }

  void _changeQty(CartItem item, double delta) {
    setState(() {
      item.qty += delta;
      if (item.qty <= 0) cart.remove(item);
    });
  }

  double get _subtotal => cart.fold(0, (sum, c) => sum + c.lineSubtotal);
  double get _tax => cart.fold(0, (sum, c) => sum + c.lineTax);
  double get _discount => double.tryParse(discountController.text) ?? 0;
  double get _grandTotal => (_subtotal + _tax - _discount).clamp(0, double.infinity);

  Future<void> _checkout() async {
    if (cart.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Cart is empty.')));
      return;
    }

    final syncUid = const Uuid().v4();
    final invoiceNoLocal = 'LOCAL-${DateTime.now().millisecondsSinceEpoch}';
    final itemsForSync = cart.map((c) => c.toSyncMap()).toList();
    final itemsForReceipt = cart.map((c) => {
          'name': c.product.name,
          'qty': c.qty,
          'unit': c.product.unit,
          'unit_price': c.product.sellingPrice,
          'line_total': c.lineTotal,
        }).toList();

    final sale = LocalSale(
      syncUid: syncUid,
      invoiceNoLocal: invoiceNoLocal,
      saleDate: DateTime.now(),
      subtotal: _subtotal,
      discountAmount: _discount,
      taxAmount: _tax,
      totalAmount: _grandTotal,
      paidAmount: _grandTotal,
      paymentMode: paymentMode,
      customerName: customerNameController.text,
      customerPhone: customerPhoneController.text,
      itemsJson: jsonEncode(itemsForSync),
    );

    await db.insertLocalSale(sale);
    for (final c in cart) {
      await db.decrementStock(c.product.id, c.qty);
    }

    final prefs = await SharedPreferences.getInstance();
    final cashierName = prefs.getString('user_name') ?? '';

    final pdfBytes = await ReceiptPrinter.buildReceiptPdf(
      storeName: prefs.getString('store_name') ?? 'My Supermarket',
      storeNameTa: prefs.getString('store_name_ta'),
      address: prefs.getString('store_address') ?? '',
      phone: prefs.getString('store_phone') ?? '',
      gstin: prefs.getString('store_gstin'),
      invoiceNo: invoiceNoLocal,
      saleDate: sale.saleDate,
      cashierName: cashierName,
      customerName: sale.customerName,
      customerPhone: sale.customerPhone,
      items: itemsForReceipt,
      subtotal: _subtotal,
      discount: _discount,
      tax: _tax,
      total: _grandTotal,
      paid: _grandTotal,
      paymentMode: paymentMode,
      isA4: (prefs.getString('receipt_paper_size') ?? 'thermal80') == 'a4',
    );

    setState(() {
      cart = [];
      customerNameController.clear();
      customerPhoneController.clear();
      discountController.text = '0';
    });

    await _loadLocalProducts();

    if (mounted) {
      showDialog(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Bill saved ✅'),
          content: Text('Invoice: $invoiceNoLocal\nTotal: ₹${_grandTotalDisplay(sale.totalAmount)}\n\nIt will sync to the server automatically.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Close')),
            ElevatedButton(
              onPressed: () async {
                Navigator.pop(context);
                await ReceiptPrinter.printReceipt(pdfBytes);
              },
              child: const Text('🖨️ Print'),
            ),
          ],
        ),
      );
    }

    // best-effort background sync
    _sync(silent: true);
  }

  String _grandTotalDisplay(double v) => v.toStringAsFixed(2);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4ECDD),
      appBar: AppBar(
        backgroundColor: const Color(0xFF5C4433),
        title: const Text('🛒 Supermarket POS'),
        actions: [
          IconButton(
            icon: syncing ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.sync),
            tooltip: syncStatus,
            onPressed: syncing ? null : () => _sync(),
          ),
          IconButton(
            icon: const Icon(Icons.receipt_long),
            tooltip: 'Sales History',
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SalesHistoryScreen())),
          ),
          IconButton(
            icon: const Icon(Icons.settings),
            tooltip: 'Settings',
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SettingsScreen(api: widget.api))),
          ),
        ],
      ),
      body: Row(
        children: [
          Expanded(
            flex: 2,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  TextField(
                    controller: searchController,
                    autofocus: true,
                    decoration: const InputDecoration(
                      prefixIcon: Icon(Icons.search),
                      hintText: 'Scan barcode or search product... / பொருள் தேடு',
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(),
                    ),
                    onChanged: _filter,
                    onSubmitted: _onScanSubmit,
                  ),
                  const SizedBox(height: 8),
                  Expanded(
                    child: GridView.builder(
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 4,
                        childAspectRatio: 1.1,
                        crossAxisSpacing: 8,
                        mainAxisSpacing: 8,
                      ),
                      itemCount: filtered.length,
                      itemBuilder: (context, i) {
                        final p = filtered[i];
                        return InkWell(
                          onTap: () => _addToCart(p),
                          child: Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(10),
                              boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 6, offset: Offset(0, 3))],
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(p.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                                const Spacer(),
                                Text('₹${p.sellingPrice.toStringAsFixed(2)}', style: const TextStyle(color: Color(0xFF2D6A4F), fontWeight: FontWeight.bold)),
                                if (p.isLowStock) Text('Low: ${p.stockQty.toStringAsFixed(0)}', style: const TextStyle(color: Colors.red, fontSize: 10)),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            ),
          ),
          Container(
            width: 360,
            color: Colors.white,
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text('Cart', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                TextField(controller: customerNameController, decoration: const InputDecoration(labelText: 'Customer name (optional)')),
                TextField(controller: customerPhoneController, decoration: const InputDecoration(labelText: 'Customer phone (optional)')),
                const Divider(),
                Expanded(
                  child: ListView.builder(
                    itemCount: cart.length,
                    itemBuilder: (context, i) {
                      final c = cart[i];
                      return ListTile(
                        dense: true,
                        title: Text(c.product.name, style: const TextStyle(fontSize: 13)),
                        subtitle: Text('₹${c.product.sellingPrice.toStringAsFixed(2)} x ${c.qty}'),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(icon: const Icon(Icons.remove_circle_outline, size: 18), onPressed: () => _changeQty(c, -1)),
                            Text('${c.qty}'),
                            IconButton(icon: const Icon(Icons.add_circle_outline, size: 18), onPressed: () => _changeQty(c, 1)),
                          ],
                        ),
                      );
                    },
                  ),
                ),
                TextField(
                  controller: discountController,
                  decoration: const InputDecoration(labelText: 'Discount ₹'),
                  keyboardType: TextInputType.number,
                  onChanged: (_) => setState(() {}),
                ),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [const Text('Subtotal'), Text('₹${_subtotal.toStringAsFixed(2)}')]),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [const Text('Tax (GST)'), Text('₹${_tax.toStringAsFixed(2)}')]),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  const Text('Grand Total', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  Text('₹${_grandTotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                ]),
                DropdownButtonFormField<String>(
                  value: paymentMode,
                  decoration: const InputDecoration(labelText: 'Payment Mode'),
                  items: const [
                    DropdownMenuItem(value: 'cash', child: Text('Cash')),
                    DropdownMenuItem(value: 'card', child: Text('Card')),
                    DropdownMenuItem(value: 'upi', child: Text('UPI')),
                    DropdownMenuItem(value: 'credit', child: Text('Credit')),
                  ],
                  onChanged: (v) => setState(() => paymentMode = v ?? 'cash'),
                ),
                const SizedBox(height: 8),
                ElevatedButton(
                  onPressed: _checkout,
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF2D6A4F), foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 16)),
                  child: const Text('✅ Pay / Save Bill', style: TextStyle(fontSize: 16)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
