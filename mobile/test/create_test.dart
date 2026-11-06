import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:ttw_newsletter/core/api.dart';
import 'package:ttw_newsletter/core/auth.dart';
import 'package:ttw_newsletter/features/create_newsletter/create.dart';

void main() {
  testWidgets(
    'Mobile input, parse, review, manual details and async job flow',
    (tester) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var parsed = 0;
      Map<String, dynamic>? submission;
      final api = Api(
        client: MockClient((r) async {
          final route = r.url.queryParameters['route'];
          if (route == '/automation/parse') {
            parsed++;
            return http.Response(
              jsonEncode({
                'articles': [
                  {
                    'section': 'header',
                    'url': 'https://www.travelandtourworld.com/news/article/header/',
                  },
                  {
                    'section': 'pick',
                    'url':
                        'https://www.travelandtourworld.com/news/article/pick/',
                  },
                ],
                'errors': [],
                'warnings': [],
              }),
              200,
            );
          }
          if (route == '/automation/jobs') {
            submission = jsonDecode(r.body) as Map<String, dynamic>;
            return http.Response('{"id":17}', 202);
          }
          if (route == '/automation/jobs/17') {
            return http.Response(
              '{"id":17,"status":"completed","completed":2,"total":2,"newsletter_id":null,"articles":[]}',
              200,
            );
          }
          return http.Response('{"error":"Unexpected route"}', 404);
        }),
      );
      final auth = Auth(api)
        ..user = {
          'name': 'Editor',
          'permissions': ['view', 'create', 'edit'],
        };
      await tester.pumpWidget(MaterialApp(home: CreateScreen(auth: auth)));
      await tester.enterText(
        find.byType(TextField).first,
        'header:\nhttps://www.travelandtourworld.com/news/article/header/',
      );
      await tester.tap(find.text('Parse Links  →'));
      await tester.pumpAndSettle();
      expect(find.textContaining('2 Articles Found'), findsOneWidget);
      expect(find.text('Header Story'), findsWidgets);
      await tester.tap(find.text('Continue  →'));
      await tester.pumpAndSettle();
      expect(parsed, 2);
      await tester.enterText(
        find.byType(TextField).at(2),
        'Manually entered subject',
      );
      await tester.enterText(
        find.byType(TextField).at(3),
        'Manually entered description',
      );
      await tester.tap(find.text('Generate Newsletter  →'));
      await tester.pumpAndSettle();
      expect(submission?['subject_title'], 'Manually entered subject');
      expect(submission?['hidden_text'], 'Manually entered description');
      expect(submission?['text'], contains('Pick News:'));
      expect(find.text('Automation #17'), findsOneWidget);
      expect(find.text('Review Newsletter Data'), findsOneWidget);
      expect(find.text('Generate Draft  →'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
