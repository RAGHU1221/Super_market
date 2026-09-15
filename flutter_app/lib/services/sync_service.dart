import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';

import '../db/database_helper.dart';
import '../models/product.dart';
import 'api_service.dart';

/// Orchestrates two-way sync between the local SQLite store and the server:
///  - pull: products + categories + live stock
///  - push: any offline bills queued in local_sales that aren't synced yet
/// Designed to be safe to call repeatedly (e.g. every few minutes, or on
/// connectivity regained) — pushed sales carry a client-generated sync_uid
/// so retried pushes never double-count a bill on the server.
class SyncService {
  final ApiService api;
  final DatabaseHelper db = DatabaseHelper.instance;

  SyncService(this.api);

  Future<bool> hasInternet() async {
    final result = await Connectivity().checkConnectivity();
    return !result.contains(ConnectivityResult.none);
  }

  Future<SyncResult> fullSync({String deviceId = 'desktop-app'}) async {
    final errors = <String>[];
    int pushed = 0, pulled = 0;

    if (!await hasInternet()) {
      return SyncResult(ok: false, message: 'No internet connection. Working offline.', pushedCount: 0, pulledCount: 0);
    }

    // 1) Push queued offline sales first, so server stock reflects them
    //    before we pull the latest stock numbers back down.
    try {
      final unsynced = await db.getUnsyncedSales();
      if (unsynced.isNotEmpty) {
        final payload = unsynced.map((s) {
          final items = (jsonDecode(s.itemsJson) as List).cast<Map<String, dynamic>>();
          return {
            'sync_uid': s.syncUid,
            'items': items,
            'discount_amount': s.discountAmount,
            'payment_mode': s.paymentMode,
            'paid_amount': s.paidAmount,
            'customer_name': s.customerName,
            'customer_phone': s.customerPhone,
            'sale_date': s.saleDate.toIso8601String(),
            'source': 'desktop',
          };
        }).toList();

        final res = await api.pushSales(payload, deviceId);
        if (res['ok'] == true) {
          for (final r in (res['results'] as List)) {
            final status = r['status'];
            if (status == 'inserted' || status == 'duplicate') {
              await db.markSynced(r['sync_uid'], r['invoice_no'] ?? '');
              pushed++;
            } else {
              errors.add('Sale ${r['sync_uid']}: ${r['error']}');
            }
          }
        } else {
          errors.add(res['error']?.toString() ?? 'Push failed');
        }
      }
    } catch (e) {
      errors.add('Push error: $e');
    }

    // 2) Pull latest catalogue + stock
    try {
      final res = await api.fetchProducts(since: '');
      if (res['ok'] == true) {
        final products = (res['products'] as List)
            .map((p) => Product.fromMap(p as Map<String, dynamic>))
            .toList();
        final categories = (res['categories'] as List).cast<Map<String, dynamic>>();
        await db.replaceProducts(products);
        await db.replaceCategories(categories);
        await db.setMeta('last_product_sync', res['server_time']?.toString() ?? '');
        pulled = products.length;
      } else {
        errors.add(res['error']?.toString() ?? 'Pull failed');
      }
    } catch (e) {
      errors.add('Pull error: $e');
    }

    return SyncResult(
      ok: errors.isEmpty,
      message: errors.isEmpty ? 'Synced successfully.' : errors.join(' | '),
      pushedCount: pushed,
      pulledCount: pulled,
    );
  }
}

class SyncResult {
  final bool ok;
  final String message;
  final int pushedCount;
  final int pulledCount;

  SyncResult({required this.ok, required this.message, required this.pushedCount, required this.pulledCount});
}
