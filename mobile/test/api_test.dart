import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:ttw_newsletter/core/api.dart';
import 'package:ttw_newsletter/models/newsletter.dart';

void main() {
  test('Default mobile API targets the deployed New folder', () {
    final api = Api();
    expect(api.uri('/auth/login').path, '/New/api/index.php');
    expect(api.uri('/auth/login').queryParameters['route'], '/auth/login');
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
