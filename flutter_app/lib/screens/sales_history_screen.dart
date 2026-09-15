import 'package:flutter/material.dart';
import '../db/database_helper.dart';
import '../models/sale.dart';

class SalesHistoryScreen extends StatefulWidget {
  const SalesHistoryScreen({super.key});

  @override
  State<SalesHistoryScreen> createState() => _SalesHistoryScreenState();
}

class _SalesHistoryScreenState extends State<SalesHistoryScreen> {
  List<LocalSale> sales = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final rows = await DatabaseHelper.instance.getRecentSales();
    setState(() { sales = rows; loading = false; });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: const Color(0xFF5C4433), title: const Text('Sales History (this device)')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              itemCount: sales.length,
              itemBuilder: (context, i) {
                final s = sales[i];
                return ListTile(
                  leading: Icon(s.synced ? Icons.cloud_done : Icons.cloud_off, color: s.synced ? Colors.green : Colors.orange),
                  title: Text(s.serverInvoiceNo ?? s.invoiceNoLocal),
                  subtitle: Text('${s.saleDate.toString().substring(0, 16)} — ${s.paymentMode.toUpperCase()}${s.customerName.isNotEmpty ? ' — ${s.customerName}' : ''}'),
                  trailing: Text('₹${s.totalAmount.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                );
              },
            ),
    );
  }
}
