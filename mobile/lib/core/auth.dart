import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api.dart';

class Auth extends ChangeNotifier {
  final Api api;
  final FlutterSecureStorage storage;
  Map<String, dynamic>? user;
  bool ready = false;
  String? error;
  Auth(this.api, {this.storage = const FlutterSecureStorage()}) {
    api.onExpired = expire;
  }
  bool can(String permission) =>
      (user?['permissions'] as List? ?? []).contains(permission);
  Future<void> restore() async {
    try {
      api.token = await storage.read(key: 'ttw_token');
      if (api.token != null) user = await api.request('/auth/me');
    } catch (e) {
      error = e.toString();
    } finally {
      ready = true;
      notifyListeners();
    }
  }

  Future<void> login(String username, String password) async {
    final result = await api.request(
      '/auth/login',
      data: {'username': username.trim(), 'password': password},
    );
    final token = result['token'] as String;
    await storage.write(key: 'ttw_token', value: token);
    api.token = token;
    user = result['user'] as Map<String, dynamic>;
    error = null;
    notifyListeners();
  }

  void expire() {
    api.token = null;
    user = null;
    error = 'Session expired. Please sign in again.';
    storage.delete(key: 'ttw_token');
    notifyListeners();
  }

  Future<void> logout() async {
    // Revocation must reach the server before declaring logout successful.
    await api.request('/auth/logout', data: {});
    await storage.delete(key: 'ttw_token');
    api.token = null;
    user = null;
    error = null;
    notifyListeners();
  }
}
