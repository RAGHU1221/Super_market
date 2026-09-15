class Product {
  final int id;
  final String sku;
  final String? barcode;
  final String name;
  final String? nameTa;
  final int? categoryId;
  final String unit;
  final String? hsnCode;
  final double taxPercent;
  final double costPrice;
  final double sellingPrice;
  final double? mrp;
  final double stockQty;
  final double reorderLevel;

  Product({
    required this.id,
    required this.sku,
    this.barcode,
    required this.name,
    this.nameTa,
    this.categoryId,
    required this.unit,
    this.hsnCode,
    required this.taxPercent,
    required this.costPrice,
    required this.sellingPrice,
    this.mrp,
    required this.stockQty,
    required this.reorderLevel,
  });

  factory Product.fromMap(Map<String, dynamic> m) {
    return Product(
      id: _asInt(m['id']),
      sku: (m['sku'] ?? '').toString(),
      barcode: m['barcode']?.toString(),
      name: (m['name'] ?? '').toString(),
      nameTa: m['name_ta']?.toString(),
      categoryId: m['category_id'] == null ? null : _asInt(m['category_id']),
      unit: (m['unit'] ?? 'pcs').toString(),
      hsnCode: m['hsn_code']?.toString(),
      taxPercent: _asDouble(m['tax_percent']),
      costPrice: _asDouble(m['cost_price']),
      sellingPrice: _asDouble(m['selling_price']),
      mrp: m['mrp'] == null ? null : _asDouble(m['mrp']),
      stockQty: _asDouble(m['stock_qty']),
      reorderLevel: _asDouble(m['reorder_level']),
    );
  }

  Map<String, dynamic> toDbMap() {
    return {
      'id': id,
      'sku': sku,
      'barcode': barcode,
      'name': name,
      'name_ta': nameTa,
      'category_id': categoryId,
      'unit': unit,
      'hsn_code': hsnCode,
      'tax_percent': taxPercent,
      'cost_price': costPrice,
      'selling_price': sellingPrice,
      'mrp': mrp,
      'stock_qty': stockQty,
      'reorder_level': reorderLevel,
    };
  }

  bool get isLowStock => stockQty <= reorderLevel;
}

int _asInt(dynamic v) {
  if (v is int) return v;
  if (v is String) return int.tryParse(v) ?? 0;
  if (v is double) return v.toInt();
  return 0;
}

double _asDouble(dynamic v) {
  if (v is double) return v;
  if (v is int) return v.toDouble();
  if (v is String) return double.tryParse(v) ?? 0.0;
  return 0.0;
}
