import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Talks to the Supermarket Suite web app's /api/mobile/ REST endpoints.
/// Server URL + auth token are persisted in SharedPreferences so the app
/// stays logged in between launches.
class ApiService {
  static const _kServerUrlKey = 'server_url';
  static const _kTokenKey = 'api_token';
  static const _kUserNameKey = 'user_name';
  static const _kUserRoleKey = 'user_role';

  String? _serverUrl;
  String? _token;

  Future<void> loadSession() async {
    final prefs = await SharedPreferences.getInstance();
    _serverUrl = prefs.getString(_kServerUrlKey);
    _token = prefs.getString(_kTokenKey);
  }

  Future<String?> get serverUrl async {
    if (_serverUrl != null) return _serverUrl;
    final prefs = await SharedPreferences.getInstance();
    _serverUrl = prefs.getString(_kServerUrlKey);
    return _serverUrl;
  }

  Future<String?> get token async {
    if (_token != null) return _token;
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_kTokenKey);
    return _token;
  }

  bool get isLoggedIn => _token != null && _token!.isNotEmpty;

  Uri _buildUri(String base, String path) {
    final normalizedBase = base.endsWith('/') ? base.substring(0, base.length - 1) : base;
    return Uri.parse('$normalizedBase/api/mobile/$path');
  }

  Future<Map<String, dynamic>> login({
    required String serverUrl,
    required String username,
    required String password,
    required String deviceId,
  }) async {
    final uri = _buildUri(serverUrl, 'login.php');
    final resp = await http
        .post(uri,
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'username': username, 'password': password, 'device_id': deviceId}))
        .timeout(const Duration(seconds: 20));

    final data = jsonDecode(resp.body) as Map<String, dynamic>;
    if (resp.statusCode == 200 && data['ok'] == true) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_kServerUrlKey, serverUrl);
      await prefs.setString(_kTokenKey, data['token']);
      await prefs.setString(_kUserNameKey, data['user']['name'] ?? '');
      await prefs.setString(_kUserRoleKey, data['user']['role'] ?? '');
      _serverUrl = serverUrl;
      _token = data['token'];
    }
    return data;
  }

  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kTokenKey);
    _token = null;
  }

  Future<Map<String, String>> _authHeaders() async {
    final t = await token;
    return {
      'Content-Type': 'application/json',
      if (t != null) 'Authorization': 'Bearer $t',
    };
  }

  Future<Map<String, dynamic>> fetchProducts({String since = ''}) async {
    final base = await serverUrl;
    if (base == null) throw Exception('Not logged in / server URL missing');
    final uri = _buildUri(base, 'products.php').replace(
      queryParameters: since.isNotEmpty ? {'since': since} : null,
    );
    final resp = await http.get(uri, headers: await _authHeaders()).timeout(const Duration(seconds: 30));
    return jsonDecode(resp.body) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> fetchStock() async {
    final base = await serverUrl;
    if (base == null) throw Exception('Not logged in / server URL missing');
    final uri = _buildUri(base, 'stock.php');
    final resp = await http.get(uri, headers: await _authHeaders()).timeout(const Duration(seconds: 20));
    return jsonDecode(resp.body) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> pushSales(List<Map<String, dynamic>> sales, String deviceId) async {
    final base = await serverUrl;
    if (base == null) throw Exception('Not logged in / server URL missing');
    final uri = _buildUri(base, 'sync_sales.php');
    final resp = await http
        .post(uri,
            headers: await _authHeaders(),
            body: jsonEncode({'device_id': deviceId, 'sales': sales}))
        .timeout(const Duration(seconds: 30));
    return jsonDecode(resp.body) as Map<String, dynamic>;
  }

  Future<bool> ping(String serverUrl) async {
    try {
      final uri = _buildUri(serverUrl, 'ping.php');
      final resp = await http.get(uri).timeout(const Duration(seconds: 8));
      return resp.statusCode == 200;
    } catch (_) {
      return false;
    }
  }
}
