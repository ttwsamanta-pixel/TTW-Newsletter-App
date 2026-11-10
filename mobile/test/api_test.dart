import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:ttw_newsletter/core/api.dart';
import 'package:ttw_newsletter/models/newsletter.dart';

void main() {
  test(
    'Promotion upload includes the selected newsletter issue date',
    () async {
      final directory = await Directory.systemTemp.createTemp(
        'ttw-promotion-test-',
      );
      final file = File('${directory.path}/banner.jpg');
      await file.writeAsString('fixture image');
      try {
        final api = Api(
          client: MockClient((r) async {
            expect(r.url.queryParameters['route'], '/uploads/promotion');
            expect(r.body, contains('name="newsletter_date"'));
            expect(r.body, contains('2099-03-12'));
            return http.Response(
              '{"path":"uploads/temporary/2099/Mar/auto_fixture.jpg"}',
              200,
            );
          }),
        );
        final result = await api.upload(
          file.path,
          newsletterDate: '2099-03-12',
        );
        expect(result['path'], contains('/2099/Mar/'));
      } finally {
        await file.delete();
        await directory.delete();
      }
    },
  );
  test('Default mobile API targets the deployed New folder', () {
    final api = Api();
    expect(api.uri('/auth/login').path, '/New/api/index.php');
    expect(api.uri('/auth/login').queryParameters['route'], '/auth/login');
  });
  test('Stored promotion images use the server fallback resolver', () {
    final api = Api();
    for (final path in [
      'uploads/2026/Nov/banner.jpg',
      'https://newsletter.travelandtourworld.com/uploads/2026/Nov/banner.jpg',
    ]) {
      final uri = Uri.parse(api.imageUrl(path));
      expect(uri.path, '/New/image.php');
      expect(uri.queryParameters['path'], path);
      expect(uri.queryParameters['kind'], 'promotion');
    }
    expect(
      api.imageUrl('https://external.example/image.jpg'),
      'https://external.example/image.jpg',
    );
    expect(
      api.imageUrl(
        'https://newsletter.travelandtourworld.com/New/image.php?path=uploads%2Fbanner.jpg',
      ),
      'https://newsletter.travelandtourworld.com/New/image.php?path=uploads%2Fbanner.jpg',
    );
  });
  test('API sends bearer and JSON to server, never article hosts', () async {
    final api = Api(
      client: MockClient((r) async {
        expect(r.url.host, 'newsletter.travelandtourworld.com');
        expect(r.url.queryParameters['route'], '/automation/parse');
        expect(r.headers['Authorization'], 'Bearer secure-token');
        expect(jsonDecode(r.body)['text'], 'header:');
        return http.Response('{"articles":[],"errors":[]}', 200);
      }),
    )..token = 'secure-token';
    final r = await api.request('/automation/parse', data: {'text': 'header:'});
    expect(r['errors'], isEmpty);
  });
  test('401 expires auth, but transport errors do not', () async {
    var expired = false;
    final api =
        Api(
            client: MockClient(
              (r) async => http.Response('{"error":"Expired"}', 401),
            ),
          )
          ..token = 'token'
          ..onExpired = () => expired = true;
    await expectLater(
      api.request('/newsletters'),
      throwsA(isA<ApiException>()),
    );
    expect(expired, isTrue);
  });
  test('Duplicate conflict retains existing newsletter ID', () async {
    final api = Api(
      client: MockClient(
        (r) async => http.Response('{"error":"Exists","existing_id":42}', 409),
      ),
    );
    await expectLater(
      api.request('/automation/jobs/1/generate', data: {}),
      throwsA(
        isA<ApiException>().having((e) => e.existingId, 'existing id', 42),
      ),
    );
  });
  test('Unexpected HTML becomes a useful server error', () async {
    final api = Api(
      client: MockClient((r) async => http.Response('<html>Error</html>', 500)),
    );
    await expectLater(
      api.request('/newsletters'),
      throwsA(
        isA<ApiException>().having(
          (e) => e.message,
          'message',
          contains('unexpected response'),
        ),
      ),
    );
  });
  test('Cleartext API configuration is rejected', () {
    expect(() => Api(base: 'http://example.com/api'), throwsArgumentError);
  });
  test('Job status handles database numeric strings', () {
    final j = AutomationJob.fromJson({
      'id': '1',
      'total': '4',
      'completed': '2',
      'status': 'processing',
      'newsletter_id': null,
    });
    expect(j.progress, 0.5);
    expect(j.finished, isFalse);
  });
}
