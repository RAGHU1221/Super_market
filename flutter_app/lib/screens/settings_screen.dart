import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../services/api_service.dart';
import '../services/sync_service.dart';
import 'login_screen.dart';

class SettingsScreen extends StatefulWidget {
  final ApiService api;
  const SettingsScreen({super.key, required this.api});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  String serverUrl = '';
  String userName = '';
  String userRole = '';
  String lastSync = '';
  bool syncing = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      serverUrl = prefs.getString('server_url') ?? '';
      userName = prefs.getString('user_name') ?? '';
      userRole = prefs.getString('user_role') ?? '';
    });
  }

  Future<void> _syncNow() async {
    setState(() => syncing = true);
    final result = await SyncService(widget.api).fullSync();
    setState(() { syncing = false; lastSync = result.message; });
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result.message)));
    }
  }

  Future<void> _logout() async {
    await widget.api.logout();
    if (mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => LoginScreen(api: widget.api)),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: const Color(0xFF5C4433), title: const Text('Settings')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ListTile(title: const Text('Server'), subtitle: Text(serverUrl)),
          ListTile(title: const Text('Logged in as'), subtitle: Text('$userName ($userRole)')),
          const Divider(),
          ListTile(
            leading: syncing ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.sync),
            title: const Text('Sync now'),
            subtitle: Text(lastSync),
            onTap: syncing ? null : _syncNow,
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.logout, color: Colors.red),
            title: const Text('Logout', style: TextStyle(color: Colors.red)),
            onTap: _logout,
          ),
          const SizedBox(height: 20),
          const Text(
            'Tip: this app keeps working offline — bills are saved locally and synced automatically once internet is back. Products/prices/stock are managed from the web admin and pulled down here on each sync.',
            style: TextStyle(color: Colors.black54, fontSize: 12),
          ),
        ],
      ),
    );
  }
}
