import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:ttw_newsletter/core/api.dart';
import 'package:ttw_newsletter/core/auth.dart';
import 'package:ttw_newsletter/features/auth/login.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  testWidgets('Login validates empty credentials and renders branding', (
    tester,
  ) async {
    final auth = Auth(Api());
    await tester.pumpWidget(MaterialApp(home: LoginScreen(auth: auth)));
    expect(find.text('Newsletter Manager'), findsOneWidget);
    await tester.ensureVisible(find.text('Login  →'));
    await tester.tap(find.text('Login  →'));
    await tester.pump();
    expect(find.text('Enter your username'), findsOneWidget);
    expect(find.text('Enter your password'), findsOneWidget);
  });
  test(
    'Login persists token; restore uses API and logout revokes token',
    () async {
      FlutterSecureStorage.setMockInitialValues({});
      final routes = <String>[];
      final client = MockClient((r) async {
        final route = r.url.queryParameters['route']!;
        routes.add(route);
        if (route == '/auth/login') {
          return http.Response(
            '{"token":"test-token","user":{"id":1,"name":"Editor","permissions":["view","create"]}}',
            200,
          );
        }
        if (route == '/auth/me') {
          return http.Response(
            '{"id":1,"name":"Editor","permissions":["view","create"]}',
            200,
          );
        }
        return http.Response('{"ok":true}', 200);
      });
      final auth = Auth(Api(client: client));
      await auth.login('editor', 'password');
      expect(await auth.storage.read(key: 'ttw_token'), 'test-token');
      expect(auth.can('create'), isTrue);
      final restored = Auth(Api(client: client));
      await restored.restore();
      expect(restored.user?['name'], 'Editor');
      await restored.logout();
      expect(restored.user, isNull);
      expect(await restored.storage.read(key: 'ttw_token'), isNull);
      expect(routes, ['/auth/login', '/auth/me', '/auth/logout']);
    },
  );
}
