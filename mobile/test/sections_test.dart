import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:ttw_newsletter/core/api.dart';
import 'package:ttw_newsletter/core/auth.dart';
import 'package:ttw_newsletter/features/create_newsletter/sections.dart';

void main() {
  testWidgets(
    'More News can arrive before Header and Email Title can be added later',
    (tester) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var applied = false;
      var title = '';
      Map<String, dynamic>? jobInput;
      Map<String, dynamic> newsletter() => {
        'id': 55,
        'newsletter_date': '2026-11-09',
        'edition': 'M',
        'subject_title': title,
        'hidden_text': '',
        'header_title': '',
        'header_url': '',
        'header_description': '',
        'promotion_enabled': 0,
        'promotion_image': null,
        'promotion_url': '',
        'promotion_text': '',
        'pick_news': [],
        'more_news': applied
            ? [
                {
                  'title': 'Original More title',
                  'url':
                      'https://www.travelandtourworld.com/news/article/more/',
                },
              ]
            : [],
        'categories': [],
        'approved_at': null,
      };
      final client = MockClient((r) async {
        final route = r.url.queryParameters['route'];
        if (route == '/categories') {
          return http.Response(
            '{"categories":[{"id":1,"active":1,"category_name":"TOURISM NEWS"}]}',
            200,
          );
        }
        if (route == '/newsletters/draft') {
          final data = jsonDecode(r.body);
          title = data['subject_title'];
          return http.Response(
            jsonEncode({'id': 55, 'newsletter': newsletter()}),
            200,
          );
        }
        if (route == '/automation/parse') {
          return http.Response(
            '{"articles":[],"errors":[],"warnings":[]}',
            200,
          );
        }
        if (route == '/automation/jobs') {
          jobInput = jsonDecode(r.body);
          return http.Response('{"id":17}', 202);
        }
        if (route == '/automation/jobs/17') {
          return http.Response(
            '{"id":17,"total":1,"completed":1,"status":"completed","newsletter_id":null,"payload":{"input_mode":"section","section":"more","target_id":55},"articles":[]}',
            200,
          );
        }
        if (route == '/automation/jobs/17/generate') {
          applied = true;
          return http.Response('{"newsletter_id":55}', 200);
        }
        if (route == '/newsletters/55') {
          return http.Response(jsonEncode({'newsletter': newsletter()}), 200);
        }
        return http.Response('{"error":"Unexpected endpoint"}', 404);
      });
      final auth = Auth(Api(client: client))
        ..user = {
          'name': 'Editor',
          'permissions': ['view', 'create', 'edit'],
        };
      await tester.pumpWidget(
        MaterialApp(home: SectionDraftScreen(auth: auth)),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('More News'));
      await tester.tap(find.text('More News'));
      await tester.pumpAndSettle();
      final urls = find.byWidgetPredicate(
        (w) =>
            w is TextField &&
            w.decoration?.labelText == 'Paste MTN / More News links',
      );
      await tester.enterText(
        urls,
        'https://www.travelandtourworld.com/news/article/more/',
      );
      await tester.ensureVisible(find.text('Import / Refresh MTN / More News'));
      await tester.tap(find.text('Import / Refresh MTN / More News'));
      await tester.pumpAndSettle();
      expect(jobInput?['input_mode'], 'section');
      expect(jobInput?['section'], 'more');
      expect(jobInput?['target_id'], 55);
      expect(title, '');
      await tester.ensureVisible(find.text('Save imported section'));
      await tester.tap(find.text('Save imported section'));
      await tester.pumpAndSettle();
      expect(find.text('Original More title'), findsOneWidget);
      await tester.ensureVisible(find.text('Basic'));
      await tester.tap(find.text('Basic'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byWidgetPredicate(
          (w) =>
              w is TextField &&
              w.decoration?.labelText == 'Email Title (optional — add later)',
        ),
        'Manual Email Title later',
      );
      await tester.ensureVisible(find.text('Save Basic Details'));
      await tester.tap(find.text('Save Basic Details'));
      await tester.pumpAndSettle();
      expect(title, 'Manual Email Title later');
      expect(applied, isTrue);
      expect(tester.takeException(), isNull);
    },
  );
}
