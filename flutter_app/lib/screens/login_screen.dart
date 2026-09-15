import 'dart:io';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../services/api_service.dart';
import 'billing_screen.dart';

class LoginScreen extends StatefulWidget {
  final ApiService api;
  const LoginScreen({super.key, required this.api});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _serverController = TextEditingController();
  final _userController = TextEditingController();
  final _passController = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _prefillServerUrl();
  }

  Future<void> _prefillServerUrl() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('server_url');
    if (saved != null) _serverController.text = saved;
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _loading = true; _error = null; });

    try {
      final deviceId = 'windows-${Platform.localHostname}';
      final result = await widget.api.login(
        serverUrl: _serverController.text.trim(),
        username: _userController.text.trim(),
        password: _passController.text,
        deviceId: deviceId,
      );
      if (result['ok'] == true) {
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => BillingScreen(api: widget.api)),
        );
      } else {
        setState(() => _error = result['error']?.toString() ?? 'Login failed');
      }
    } catch (e) {
      setState(() => _error = 'Could not reach server. Check the URL and your internet connection.\n($e)');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF1B4332),
      body: Center(
        child: SingleChildScrollView(
          child: Container(
            width: 420,
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              color: const Color(0xFFFBF7EF),
              borderRadius: BorderRadius.circular(16),
              boxShadow: const [BoxShadow(color: Colors.black38, blurRadius: 24, offset: Offset(0, 10))],
            ),
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('🛒', style: TextStyle(fontSize: 42)),
                  const SizedBox(height: 4),
                  const Text('Supermarket POS', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Color(0xFF5C4433))),
                  const Text('சூப்பர்மார்க்கெட் பில்லிங்', style: TextStyle(color: Color(0xFF6B6A5E))),
                  const SizedBox(height: 20),
                  if (_error != null)
                    Container(
                      padding: const EdgeInsets.all(10),
                      margin: const EdgeInsets.only(bottom: 12),
                      decoration: BoxDecoration(color: const Color(0xFFFDE2DD), borderRadius: BorderRadius.circular(8)),
                      child: Text(_error!, style: const TextStyle(color: Color(0xFFA3301F))),
                    ),
                  TextFormField(
                    controller: _serverController,
                    decoration: const InputDecoration(labelText: 'Server URL', hintText: 'https://yourstore.site.je'),
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter your web admin URL' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _userController,
                    decoration: const InputDecoration(labelText: 'Username'),
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter username' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _passController,
                    decoration: const InputDecoration(labelText: 'Password'),
                    obscureText: true,
                    validator: (v) => (v == null || v.isEmpty) ? 'Enter password' : null,
                    onFieldSubmitted: (_) => _submit(),
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _loading ? null : _submit,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF2D6A4F),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      child: _loading
                          ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Text('Login'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'This app works offline once logged in. Bills sync automatically when internet is available.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 11, color: Color(0xFF6B6A5E)),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
