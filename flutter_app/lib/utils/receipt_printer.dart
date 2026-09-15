import 'dart:typed_data';

import 'package:flutter/services.dart' show rootBundle;
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

/// Builds a receipt PDF (thermal 80mm roll or A4 sheet) and sends it to the
/// system print dialog via the `printing` package — works with any Windows
/// printer driver (thermal or regular), matching the paper-size setting
/// configured in the web admin's Settings page.
class ReceiptPrinter {
  static pw.Font? _regularFont;
  static pw.Font? _boldFont;
  static bool _fontsLoaded = false;

  static Future<void> _loadFonts() async {
    if (_fontsLoaded) return;
    try {
      final regData = await rootBundle.load('assets/fonts/NotoSansTamil-Regular.ttf');
      final boldData = await rootBundle.load('assets/fonts/NotoSansTamil-Bold.ttf');
      _regularFont = pw.Font.ttf(regData);
      _boldFont = pw.Font.ttf(boldData);
    } catch (_) {
      // Font not bundled yet (see assets/fonts/README.txt) — fall back to
      // the default PDF font. Tamil text may not render until it's added.
      _regularFont = null;
      _boldFont = null;
    }
    _fontsLoaded = true;
  }

  static Future<Uint8List> buildReceiptPdf({
    required String storeName,
    String? storeNameTa,
    required String address,
    required String phone,
    String? gstin,
    required String invoiceNo,
    required DateTime saleDate,
    required String cashierName,
    String customerName = '',
    String customerPhone = '',
    required List<Map<String, dynamic>> items, // {name, qty, unit, unit_price, line_total}
    required double subtotal,
    required double discount,
    required double tax,
    required double total,
    required double paid,
    required String paymentMode,
    bool isA4 = false,
  }) async {
    await _loadFonts();
    final doc = pw.Document();

    final baseStyle = pw.TextStyle(font: _regularFont, fontSize: isA4 ? 11 : 9);
    final boldStyle = pw.TextStyle(font: _boldFont ?? _regularFont, fontSize: isA4 ? 13 : 10, fontWeight: pw.FontWeight.bold);

    final pageFormat = isA4
        ? PdfPageFormat.a4
        : PdfPageFormat(80 * PdfPageFormat.mm, double.infinity, marginAll: 4 * PdfPageFormat.mm);

    doc.addPage(
      pw.Page(
        pageFormat: pageFormat,
        build: (context) {
          return pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.stretch,
            children: [
              pw.Center(child: pw.Text(storeName, style: boldStyle)),
              if (storeNameTa != null && storeNameTa.isNotEmpty)
                pw.Center(child: pw.Text(storeNameTa, style: baseStyle)),
              pw.Center(child: pw.Text(address, style: baseStyle, textAlign: pw.TextAlign.center)),
              if (phone.isNotEmpty) pw.Center(child: pw.Text('Ph: $phone', style: baseStyle)),
              if (gstin != null && gstin.isNotEmpty) pw.Center(child: pw.Text('GSTIN: $gstin', style: baseStyle)),
              pw.Divider(),
              pw.Text('Invoice: $invoiceNo', style: baseStyle),
              pw.Text('Date: ${saleDate.toString().substring(0, 16)}', style: baseStyle),
              pw.Text('Cashier: $cashierName', style: baseStyle),
              if (customerName.isNotEmpty) pw.Text('Customer: $customerName $customerPhone', style: baseStyle),
              pw.Divider(),
              pw.Table(
                columnWidths: {
                  0: const pw.FlexColumnWidth(3),
                  1: const pw.FlexColumnWidth(1),
                  2: const pw.FlexColumnWidth(1),
                  3: const pw.FlexColumnWidth(1),
                },
                children: [
                  pw.TableRow(children: [
                    pw.Text('Item', style: boldStyle),
                    pw.Text('Qty', style: boldStyle, textAlign: pw.TextAlign.right),
                    pw.Text('Rate', style: boldStyle, textAlign: pw.TextAlign.right),
                    pw.Text('Amt', style: boldStyle, textAlign: pw.TextAlign.right),
                  ]),
                  ...items.map((it) => pw.TableRow(children: [
                        pw.Text('${it['name']}', style: baseStyle),
                        pw.Text('${it['qty']} ${it['unit'] ?? ''}', style: baseStyle, textAlign: pw.TextAlign.right),
                        pw.Text((it['unit_price'] as num).toStringAsFixed(2), style: baseStyle, textAlign: pw.TextAlign.right),
                        pw.Text((it['line_total'] as num).toStringAsFixed(2), style: baseStyle, textAlign: pw.TextAlign.right),
                      ])),
                ],
              ),
              pw.Divider(),
              _totalRow('Subtotal', subtotal, baseStyle),
              _totalRow('Discount', -discount, baseStyle),
              _totalRow('GST', tax, baseStyle),
              _totalRow('TOTAL', total, boldStyle),
              _totalRow('Paid ($paymentMode)', paid, baseStyle),
              if ((total - paid).abs() > 0.005) _totalRow('Balance', total - paid, baseStyle),
              pw.Divider(),
              pw.Center(child: pw.Text('Thank you! Visit again', style: baseStyle)),
            ],
          );
        },
      ),
    );

    return doc.save();
  }

  static pw.Widget _totalRow(String label, double value, pw.TextStyle style) {
    return pw.Row(
      mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
      children: [
        pw.Text(label, style: style),
        pw.Text(value.toStringAsFixed(2), style: style),
      ],
    );
  }

  static Future<void> printReceipt(Uint8List pdfBytes) async {
    await Printing.layoutPdf(onLayout: (format) async => pdfBytes);
  }

  static Future<void> shareReceipt(Uint8List pdfBytes, String invoiceNo) async {
    await Printing.sharePdf(bytes: pdfBytes, filename: '$invoiceNo.pdf');
  }
}
