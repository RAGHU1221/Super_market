import 'package:flutter/material.dart';

import 'services/api_service.dart';
import 'screens/login_screen.dart';
import 'screens/billing_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const SupermarketPosApp());
}

class SupermarketPosApp extends StatefulWidget {
  const SupermarketPosApp({super.key});

  @override
  State<SupermarketPosApp> createState() => _SupermarketPosAppState();
}

class _SupermarketPosAppState extends State<SupermarketPosApp> {
  final ApiService api = ApiService();
  bool _checkedSession = false;

  @override
  void initState() {
    super.initState();
    _checkSession();
  }

  Future<void> _checkSession() async {
    await api.loadSession();
    setState(() => _checkedSession = true);
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Supermarket POS',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorSchemeSeed: const Color(0xFF2D6A4F),
        scaffoldBackgroundColor: const Color(0xFFF4ECDD),
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
      ),
      home: !_checkedSession
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : (api.isLoggedIn ? BillingScreen(api: api) : LoginScreen(api: api)),
    );
  }
}
