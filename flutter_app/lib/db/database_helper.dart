import 'dart:io';
import 'package:path/path.dart';
import 'package:path_provider/path_provider.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

import '../models/product.dart';
import '../models/sale.dart';

/// Offline-first local SQLite store. Mirrors the fields the server API needs
/// for products/categories, and queues sales made while offline for sync.
class DatabaseHelper {
  DatabaseHelper._internal();
  static final DatabaseHelper instance = DatabaseHelper._internal();

  Database? _db;

  Future<Database> get database async {
    if (_db != null) return _db!;
    _db = await _initDb();
    return _db!;
  }

  Future<Database> _initDb() async {
    // Desktop platforms need the FFI sqflite factory.
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;

    final Directory appDir = await getApplicationSupportDirectory();
    final String dbPath = join(appDir.path, 'supermarket_pos.db');

    return databaseFactory.openDatabase(
      dbPath,
      options: OpenDatabaseOptions(
        version: 1,
        onCreate: _onCreate,
      ),
    );
  }

  Future<void> _onCreate(Database db, int version) async {
    await db.execute('''
      CREATE TABLE categories (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        name_ta TEXT,
        parent_id INTEGER,
        is_active INTEGER DEFAULT 1
      )
    ''');

    await db.execute('''
      CREATE TABLE products (
        id INTEGER PRIMARY KEY,
        sku TEXT,
        barcode TEXT,
        name TEXT NOT NULL,
        name_ta TEXT,
        category_id INTEGER,
        unit TEXT DEFAULT 'pcs',
        hsn_code TEXT,
        tax_percent REAL DEFAULT 0,
        cost_price REAL DEFAULT 0,
        selling_price REAL DEFAULT 0,
        mrp REAL,
        stock_qty REAL DEFAULT 0,
        reorder_level REAL DEFAULT 5
      )
    ''');
    await db.execute('CREATE INDEX idx_products_barcode ON products(barcode)');
    await db.execute('CREATE INDEX idx_products_name ON products(name)');

    await db.execute('''
      CREATE TABLE local_sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sync_uid TEXT UNIQUE NOT NULL,
        invoice_no_local TEXT NOT NULL,
        sale_date TEXT NOT NULL,
        subtotal REAL NOT NULL,
        discount_amount REAL NOT NULL,
        tax_amount REAL NOT NULL,
        total_amount REAL NOT NULL,
        paid_amount REAL NOT NULL,
        payment_mode TEXT NOT NULL,
        customer_name TEXT,
        customer_phone TEXT,
        items_json TEXT NOT NULL,
        synced INTEGER DEFAULT 0,
        server_invoice_no TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE app_meta (
        key TEXT PRIMARY KEY,
        value TEXT
      )
    ''');
  }

  // ---------------- Products / Categories ----------------

  Future<void> replaceProducts(List<Product> products) async {
    final db = await database;
    final batch = db.batch();
    for (final p in products) {
      batch.insert('products', p.toDbMap(),
          conflictAlgorithm: ConflictAlgorithm.replace);
    }
    await batch.commit(noResult: true);
  }

  Future<void> replaceCategories(List<Map<String, dynamic>> categories) async {
    final db = await database;
    final batch = db.batch();
    for (final c in categories) {
      batch.insert('categories', c, conflictAlgorithm: ConflictAlgorithm.replace);
    }
    await batch.commit(noResult: true);
  }

  Future<List<Product>> getAllProducts() async {
    final db = await database;
    final rows = await db.query('products', orderBy: 'name');
    return rows.map((r) => Product.fromMap(r)).toList();
  }

  Future<List<Map<String, dynamic>>> getAllCategories() async {
    final db = await database;
    return db.query('categories', orderBy: 'name');
  }

  Future<Product?> findByBarcode(String barcode) async {
    final db = await database;
    final rows = await db.query('products', where: 'barcode = ?', whereArgs: [barcode], limit: 1);
    if (rows.isEmpty) return null;
    return Product.fromMap(rows.first);
  }

  Future<void> decrementStock(int productId, double qty) async {
    final db = await database;
    await db.rawUpdate(
      'UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?',
      [qty, productId],
    );
  }

  Future<void> applyServerStock(List<Map<String, dynamic>> stockRows) async {
    final db = await database;
    final batch = db.batch();
    for (final row in stockRows) {
      batch.rawUpdate('UPDATE products SET stock_qty = ? WHERE id = ?', [row['stock_qty'], row['id']]);
    }
    await batch.commit(noResult: true);
  }

  // ---------------- Sales (offline queue) ----------------

  Future<int> insertLocalSale(LocalSale sale) async {
    final db = await database;
    final map = sale.toDbMap()..remove('id');
    return db.insert('local_sales', map);
  }

  Future<List<LocalSale>> getUnsyncedSales() async {
    final db = await database;
    final rows = await db.query('local_sales', where: 'synced = 0', orderBy: 'id');
    return rows.map((r) => LocalSale.fromDbMap(r)).toList();
  }

  Future<List<LocalSale>> getRecentSales({int limit = 100}) async {
    final db = await database;
    final rows = await db.query('local_sales', orderBy: 'id DESC', limit: limit);
    return rows.map((r) => LocalSale.fromDbMap(r)).toList();
  }

  Future<void> markSynced(String syncUid, String serverInvoiceNo) async {
    final db = await database;
    await db.update(
      'local_sales',
      {'synced': 1, 'server_invoice_no': serverInvoiceNo},
      where: 'sync_uid = ?',
      whereArgs: [syncUid],
    );
  }

  // ---------------- App meta (server URL, last sync time) ----------------

  Future<void> setMeta(String key, String value) async {
    final db = await database;
    await db.insert('app_meta', {'key': key, 'value': value},
        conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<String?> getMeta(String key) async {
    final db = await database;
    final rows = await db.query('app_meta', where: 'key = ?', whereArgs: [key], limit: 1);
    if (rows.isEmpty) return null;
    return rows.first['value'] as String?;
  }
}
