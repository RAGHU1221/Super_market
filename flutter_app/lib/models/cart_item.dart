import 'product.dart';

class CartItem {
  final Product product;
  double qty;

  CartItem({required this.product, this.qty = 1});

  double get lineSubtotal => product.sellingPrice * qty;
  double get lineTax => lineSubtotal * (product.taxPercent / 100);
  double get lineTotal => lineSubtotal + lineTax;

  Map<String, dynamic> toSyncMap() {
    return {
      'product_id': product.id,
      'qty': qty,
      'unit_price': product.sellingPrice,
      'tax_percent': product.taxPercent,
    };
  }
}
